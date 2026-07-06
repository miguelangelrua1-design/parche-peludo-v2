/**
 * Personalización Parche — Tipos de servicio en el builder de menús del
 * formulario de publicar/editar listado (Listeo Core).
 *
 * - Inyecta un select "Tipo de servicio" (obligatorio) en cada fila de
 *   categoría del menú (Core no ofrece hook a nivel de grupo, así que se
 *   hace por DOM; el nombre `_menu[i][pp_tipo_servicio]` se deriva del
 *   input del título y se re-sincroniza antes de enviar).
 * - Muestra el bloque "Depende de mascota" (que pinta PHP en cada fila de
 *   servicio) SOLO cuando el tipo del menú lo permite (p. ej. Peluquería
 *   y baño); lo replica en filas nuevas añadidas con "Add Item".
 * - Rangos de peso: añadir / eliminar filas mín–máx.
 * - Validación al enviar: cada menú con contenido debe tener tipo (el
 *   servidor lo exige igual; esto da el aviso amable antes).
 *
 * Datos: window.PP_SERV_MENU { tipos:[{slug,nombre,mascota}], guardados:{i:slug}, msgFalta }
 */
(function ($) {
	'use strict';

	$(function () {
		var $cont = $('#pricing-list-container');
		if (!$cont.length || typeof PP_SERV_MENU === 'undefined') { return; }

		var D = PP_SERV_MENU;
		var tipos = D.tipos || [];
		if (!tipos.length) { return; }

		var permiteMascota = {};
		tipos.forEach(function (t) { if (t.mascota) { permiteMascota[t.slug] = 1; } });

		function esc(s) { return $('<span>').text(s || '').html(); }

		function opciones(seleccionado) {
			var h = '<option value="">— Elige el tipo —</option>';
			tipos.forEach(function (t) {
				h += '<option value="' + esc(t.slug) + '"' + (t.slug === seleccionado ? ' selected' : '') + '>' + esc(t.nombre) + '</option>';
			});
			return h;
		}

		/* Índice de grupo (i) a partir del input de título de la categoría. */
		function indiceDeCategoria($row) {
			var n = ($row.find('input[name$="[menu_title]"]').attr('name') || '');
			var m = n.match(/^_menu\[(\d+)\]/);
			return m ? m[1] : null;
		}

		/* Índices (i, z) de una fila de servicio, desde el name de su Título. */
		function baseDeFila($tr) {
			var n = '';
			$tr.find('input, textarea, select').each(function () {
				var nm = $(this).attr('name') || '';
				if (/^_menu\[\d+\]\[menu_elements\]\[\d+\]/.test(nm)) { n = nm; return false; }
			});
			var m = n.match(/^_menu\[(\d+)\]\[menu_elements\]\[(\d+)\]/);
			return m ? '_menu[' + m[1] + '][menu_elements][' + m[2] + ']' : null;
		}

		/* ================= Select de tipo por categoría ================= */
		function inyectarSelects() {
			// a) Filas de categoría normales.
			$cont.find('tr.pricing-submenu').each(function () {
				var $row = $(this);
				if ($row.find('select.pp-tipo-servicio').length) { return; }
				var i = indiceDeCategoria($row);
				if (i === null) { return; }
				var guardado = (D.guardados && D.guardados[i]) ? D.guardados[i] : '';
				var $wrap = $(
					'<div class="fm-input pp-tipo-wrap">' +
						'<select class="pp-tipo-servicio" name="_menu[' + i + '][pp_tipo_servicio]" ' +
							'title="Tipo de servicio (obligatorio)">' + opciones(guardado) + '</select>' +
					'</div>'
				);
				$row.find('.fm-input').first().after($wrap);
			});

			// b) Servicios SIN fila de categoría delante (menú sin título):
			//    inyectar una fila propia al inicio con el select del grupo.
			var $primera = $cont.find('tr.pricing-list-item').first();
			if ($primera.length && !$primera.hasClass('pricing-submenu') && !$cont.find('tr.pp-tipo-suelto').length) {
				var base = baseDeFila($primera);
				if (base) {
					var i0 = (base.match(/^_menu\[(\d+)\]/) || [])[1];
					var guardado0 = (D.guardados && D.guardados[i0]) ? D.guardados[i0] : '';
					$primera.before(
						'<tr class="pricing-list-item pricing-submenu pp-tipo-suelto"><td>' +
							'<div class="fm-input pp-tipo-wrap pp-tipo-wrap--suelto">' +
								'<label class="fm-input-label">Tipo de servicio de este menú</label>' +
								'<select class="pp-tipo-servicio" name="_menu[' + i0 + '][pp_tipo_servicio]">' + opciones(guardado0) + '</select>' +
							'</div>' +
						'</td></tr>'
					);
				}
			}

			refrescarDM();
		}

		/* Tipo elegido del grupo al que pertenece una fila de servicio. */
		function tipoDeFila($tr) {
			var $sub = $tr.prevAll('tr.pricing-submenu').first();
			return $sub.find('select.pp-tipo-servicio').val() || '';
		}

		/* Mostrar el bloque "Depende de mascota" solo si el tipo lo permite. */
		function refrescarDM() {
			$cont.find('tr.pricing-list-item').not('.pricing-submenu').each(function () {
				var $tr = $(this);
				var permite = !!permiteMascota[tipoDeFila($tr)];
				$tr.find('.pp-dm-wrap').toggle(permite);
			});
		}
		$(document).on('change', 'select.pp-tipo-servicio', function () {
			$(this).removeClass('pp-tipo-error');
			refrescarDM();
		});

		/* ============ Bloque "Depende de mascota" (por servicio) ============ */

		// Checkbox → despliega/oculta las variables.
		$(document).on('change', '.pp-dm-check', function () {
			$(this).closest('.pp-dm-wrap').find('.pp-dm-campos').toggle(this.checked);
		});

		// Rangos de peso: añadir / quitar.
		function filaRango(base, n) {
			return '<div class="pp-dm-rango">' +
				'<input type="number" step="0.1" min="0" name="' + base + '[pp_dm_pesos][' + n + '][min]" placeholder="Mín">' +
				'<span class="pp-dm-rango-sep">–</span>' +
				'<input type="number" step="0.1" min="0" name="' + base + '[pp_dm_pesos][' + n + '][max]" placeholder="Máx">' +
				'<button type="button" class="pp-dm-rango-quitar" title="Eliminar rango">&times;</button>' +
			'</div>';
		}
		$(document).on('click', '.pp-dm-rango-anadir', function () {
			var $wrap  = $(this).closest('.pp-dm-wrap');
			var base   = $wrap.attr('data-base');
			var $lista = $wrap.find('.pp-dm-pesos-lista');
			var n = 0;
			$lista.find('.pp-dm-rango input').each(function () {
				var m = ($(this).attr('name') || '').match(/\[pp_dm_pesos\]\[(\d+)\]/);
				if (m) { n = Math.max(n, parseInt(m[1], 10) + 1); }
			});
			$lista.append(filaRango(base, n));
		});
		$(document).on('click', '.pp-dm-rango-quitar', function () {
			$(this).closest('.pp-dm-rango').remove();
		});

		/* Plantilla del bloque para filas NUEVAS (Add Item las crea por JS
		   sin pasar por PHP). Replica el markup de pp_serv_campos_mascota_html. */
		function plantillaDM(base) {
			var id = base.replace(/[\[\]]+/g, '_') + 'pp_dm';
			return '<div class="fm-input pp-dm-wrap" data-base="' + base + '" style="display:none">' +
				'<div class="checkboxes in-row pp-dm-check-fila">' +
					'<input type="checkbox" class="input-checkbox pp-dm-check" id="' + id + '" name="' + base + '[pp_dm]" value="on">' +
					'<label for="' + id + '">Depende de mascota</label>' +
				'</div>' +
				'<div class="pp-dm-campos" style="display:none">' +
					'<div class="pp-dm-grid">' +
						'<div class="pp-dm-campo"><label class="fm-input-label">Especie</label>' +
							'<select name="' + base + '[pp_dm_especie]">' +
								'<option value="">Cualquiera</option><option value="perro">Perro</option><option value="gato">Gato</option>' +
							'</select></div>' +
						'<div class="pp-dm-campo"><label class="fm-input-label">Tipo de pelaje</label>' +
							'<select name="' + base + '[pp_dm_pelaje]">' +
								'<option value="">Cualquiera</option><option value="corto">Corto</option><option value="largo">Largo</option>' +
							'</select></div>' +
					'</div>' +
					'<div class="pp-dm-pesos">' +
						'<label class="fm-input-label">Rangos de peso (kg)</label>' +
						'<div class="pp-dm-pesos-lista"></div>' +
						'<button type="button" class="button pp-dm-rango-anadir">+ Añadir rango</button>' +
						'<p class="pricing-field-desc">Sin rangos = aplica a cualquier peso. La mascota aplica si su peso cae en alguno de los rangos.</p>' +
					'</div>' +
				'</div>' +
			'</div>';
		}

		function inyectarDMFaltantes() {
			$cont.find('tr.pricing-list-item').not('.pricing-submenu').each(function () {
				var $tr = $(this);
				if ($tr.find('.pp-dm-wrap').length) { return; }
				var base = baseDeFila($tr);
				if (!base) { return; }
				var $destino = $tr.find('.pricing-row-right').first();
				if (!$destino.length) { $destino = $tr.find('td').first(); }
				$destino.append(plantillaDM(base));
			});
			refrescarDM();
		}

		/* Filas y categorías nuevas: Core avisa las filas de servicio con el
		   evento `listeo:pricing-row-added`; el observer cubre categorías
		   nuevas, borrados y cualquier reconstrucción del builder. */
		$(document).on('listeo:pricing-row-added', function () {
			setTimeout(function () { inyectarSelects(); inyectarDMFaltantes(); }, 0);
		});
		var timer = null;
		new MutationObserver(function () {
			if (timer) { clearTimeout(timer); }
			timer = setTimeout(function () { inyectarSelects(); inyectarDMFaltantes(); }, 50);
		}).observe($cont[0], { childList: true, subtree: true });

		/* ================= Envío del formulario ================= */
		var $form = $cont.closest('form');
		$form.on('submit', function (e) {
			// 1) Re-sincronizar nombres (Core renumera índices al reordenar).
			$cont.find('tr.pricing-submenu').not('.pp-tipo-suelto').each(function () {
				var $row = $(this);
				var i = indiceDeCategoria($row);
				if (i !== null) {
					$row.find('select.pp-tipo-servicio').attr('name', '_menu[' + i + '][pp_tipo_servicio]');
				}
			});
			$cont.find('tr.pricing-list-item').not('.pricing-submenu').each(function () {
				var $tr = $(this);
				var base = baseDeFila($tr);
				var $dm = $tr.find('.pp-dm-wrap');
				if (!base || !$dm.length) { return; }
				if ($dm.attr('data-base') !== base) {
					var viejo = $dm.attr('data-base');
					$dm.attr('data-base', base);
					$dm.find('[name]').each(function () {
						var nm = $(this).attr('name') || '';
						if (viejo && nm.indexOf(viejo) === 0) {
							$(this).attr('name', base + nm.slice(viejo.length));
						}
					});
				}
			});

			// 2) Validación amable: cada menú con contenido necesita tipo.
			//    (El servidor la repite por si el JS no corre.)
			var $faltan = $();
			$cont.find('select.pp-tipo-servicio:visible').each(function () {
				var $sel = $(this);
				if ($sel.val()) { return; }
				var $fila = $sel.closest('tr');
				// ¿El grupo tiene contenido? (título o algún servicio con nombre)
				var conTitulo = ($fila.find('input[name$="[menu_title]"]').val() || '').trim() !== '';
				var conServicios = false;
				$fila.nextUntil('tr.pricing-submenu').each(function () {
					var $t = $(this).find('input[name$="[name]"]').first();
					if (($t.val() || '').trim() !== '') { conServicios = true; return false; }
				});
				if (conTitulo || conServicios) { $faltan = $faltan.add($sel); }
			});
			if ($faltan.length) {
				e.preventDefault();
				$faltan.addClass('pp-tipo-error');
				window.alert(D.msgFalta || 'Selecciona el "Tipo de servicio" en cada menú de servicios.');
				$('html, body').animate({ scrollTop: $faltan.first().offset().top - 120 }, 250);
				return false;
			}
		});

		inyectarSelects();
		inyectarDMFaltantes();
	});
})(jQuery);
