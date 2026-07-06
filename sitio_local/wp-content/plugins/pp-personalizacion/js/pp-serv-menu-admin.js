/**
 * Personalización Parche — Tipos de servicio en el metabox "Menu (Pricing)"
 * de wp-admin (editor del listado, CMB2).
 *
 * - Muestra el bloque "Depende de mascota" de cada servicio SOLO cuando el
 *   "Tipo de servicio" del menú lo permite (tipos con "Según mascota").
 * - Checkbox "Depende de mascota" → despliega/oculta las variables.
 * - Rangos de peso: añadir / eliminar filas mín–máx (los nombres se derivan
 *   del select de especie de la misma fila, así funcionan también en filas
 *   clonadas por CMB2).
 *
 * Datos: window.PP_SERV_ADMIN { mascota: {slug:1, …} }
 */
(function ($) {
	'use strict';

	$(function () {
		var D = window.PP_SERV_ADMIN || {};
		var permite = D.mascota || {};

		/* Mostrar u ocultar los bloques según el tipo elegido en su menú. */
		function refrescar() {
			$('.cmb-repeatable-grouping').each(function () {
				var $grupo = $(this);
				var tipo = $grupo.find('select[name*="[pp_tipo_servicio]"]').val() || '';
				$grupo.find('.pp-dm-admin').toggle(!!permite[tipo]);
			});
		}
		$(document).on('change', 'select[name*="[pp_tipo_servicio]"]', refrescar);

		/* Checkbox → variables. */
		$(document).on('change', '.pp-dm-admin .pp-dm-check', function () {
			$(this).closest('.pp-dm-admin').find('.pp-dm-campos').toggle(this.checked);
		});

		/* Rangos de peso. */
		$(document).on('click', '.pp-dm-admin .pp-dm-rango-anadir', function () {
			var $bloque = $(this).closest('.pp-dm-admin');
			// Base del nombre, derivada del select de especie (fiable incluso
			// en filas renumeradas/clonadas por CMB2).
			var nombreEspecie = $bloque.find('select[name$="[pp_dm_especie]"]').attr('name') || '';
			var base = nombreEspecie.replace(/\[pp_dm_especie\]$/, '');
			if (!base) { return; }
			var $lista = $bloque.find('.pp-dm-pesos-lista');
			var n = 0;
			$lista.find('.pp-dm-rango input').each(function () {
				var m = ($(this).attr('name') || '').match(/\[pp_dm_pesos\]\[(\d+)\]/);
				if (m) { n = Math.max(n, parseInt(m[1], 10) + 1); }
			});
			$lista.append(
				'<div class="pp-dm-rango" style="display:flex;align-items:center;gap:6px;margin-top:6px">' +
					'<input type="number" step="0.1" min="0" style="width:90px" name="' + base + '[pp_dm_pesos][' + n + '][min]" placeholder="Mín">' +
					'<span>–</span>' +
					'<input type="number" step="0.1" min="0" style="width:90px" name="' + base + '[pp_dm_pesos][' + n + '][max]" placeholder="Máx">' +
					'<button type="button" class="button-link pp-dm-rango-quitar" style="color:#c0392b" title="Eliminar rango">&times;</button>' +
				'</div>'
			);
		});
		$(document).on('click', '.pp-dm-admin .pp-dm-rango-quitar', function () {
			$(this).closest('.pp-dm-rango').remove();
		});

		/* Menús o servicios nuevos (CMB2 añade filas por JS). */
		$(document).on('cmb2_add_row cmb2_shift_rows_complete cmb2_remove_row', function () {
			setTimeout(refrescar, 0);
		});
		var $caja = $('#_menu_metabox');
		if ($caja.length && window.MutationObserver) {
			var timer = null;
			new MutationObserver(function () {
				if (timer) { clearTimeout(timer); }
				timer = setTimeout(refrescar, 60);
			}).observe($caja[0], { childList: true, subtree: true });
		}

		refrescar();
	});
})(jQuery);
