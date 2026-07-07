/**
 * Personalización Parche — Tipos de servicio en el builder de menús del
 * formulario de publicar/editar listado (Listeo Core).
 *
 * - Inyecta un select "Tipo de servicio" (obligatorio) en cada fila de
 *   categoría del menú. Las opciones se LIMITAN a las tipologías permitidas
 *   para el TIPO DE LISTADO actual (matriz administrable en Personalización
 *   Parche → Servicios → Tipos de Servicio).
 * - Si el tipo de listado permite UNA sola tipología (p. ej. "Guardería y
 *   Cuidador" → solo Cuidador), NO se pregunta: se inyecta un input oculto
 *   con esa tipología por defecto en cada menú.
 * - El tipo de listado se elige EN LA MISMA página (selector que llena el
 *   hidden #listing_type): al cambiarlo, los selects se reconstruyen.
 * - Muestra el bloque "Depende de mascota" (que pinta PHP en cada fila de
 *   servicio) SOLO cuando la tipología del menú lo permite; lo replica en
 *   filas nuevas añadidas con "Add Item".
 * - Rangos de peso: añadir / eliminar filas mín–máx.
 * - Validación al enviar (el servidor la repite y además auto-asigna la
 *   tipología única si el JS no corrió).
 *
 * Datos: window.PP_SERV_MENU { tipos:[{slug,nombre,mascota}], guardados:{i:slug},
 *   porListado:{tipoListado:[slugs]}, tipoListado, msgFalta }
 */
(function ($) {
	'use strict';

	$(function () {
		var $cont = $('#pricing-list-container');
		if (!$cont.length || typeof PP_SERV_MENU === 'undefined') { return; }

		var D = PP_SERV_MENU;
		var tipos = D.tipos || [];
		if (!tipos.length) { return; }

		var nombrePorSlug = {};
		var permiteMascota = {};
		var tieneDomicilio = {};
		tipos.forEach(function (t) {
			nombrePorSlug[t.slug] = t.nombre;
			if (t.mascota) { permiteMascota[t.slug] = 1; }
			if (t.domicilio) { tieneDomicilio[t.slug] = 1; }
		});

		function esc(s) { return $('<span>').text(s || '').html(); }

		/* ============ Tipo de listado actual y tipologías permitidas ============ */

		function tipoListadoActual() {
			var input = document.querySelector('input[name="_listing_type"]');
			if (input && input.value) { return String(input.value); }
			return D.tipoListado || '';
		}

		function permitidosActuales() {
			var lt = tipoListadoActual();
			var mapa = D.porListado || {};
			if (lt && mapa[lt] && mapa[lt].length) {
				// Solo slugs que sigan existiendo en el catálogo.
				return mapa[lt].filter(function (s) { return !!nombrePorSlug[s]; });
			}
			return tipos.map(function (t) { return t.slug; }); // sin config → todas
		}

		function unicoActual() {
			var p = permitidosActuales();
			return (p.length === 1) ? p[0] : null;
		}

		function opciones(seleccionado) {
			var permitidos = permitidosActuales();
			var h = '<option value="">— Elige el tipo —</option>';
			permitidos.forEach(function (s) {
				h += '<option value="' + esc(s) + '"' + (s === seleccionado ? ' selected' : '') + '>' + esc(nombrePorSlug[s]) + '</option>';
			});
			// Valor guardado que ya NO está permitido: mostrarlo marcado para
			// que el prestador lo vea y lo cambie (el servidor lo rechaza).
			if (seleccionado && permitidos.indexOf(seleccionado) === -1 && nombrePorSlug[seleccionado]) {
				h += '<option value="' + esc(seleccionado) + '" selected>' + esc(nombrePorSlug[seleccionado]) + ' (no disponible)</option>';
			}
			return h;
		}

		/* Índice de grupo (i) a partir del input de título de la categoría. */
		function indiceDeCategoria($row) {
			var n = ($row.find('input[name$="[menu_title]"]').attr('name') || '');
			var m = n.match(/^_menu\[(\d+)\]/);
			return m ? m[1] : null;
		}

		/* Índices (i, z) de una fila de servicio, desde el name de sus campos. */
		function baseDeFila($tr) {
			var n = '';
			$tr.find('input, textarea, select').each(function () {
				var nm = $(this).attr('name') || '';
				if (/^_menu\[\d+\]\[menu_elements\]\[\d+\]/.test(nm)) { n = nm; return false; }
			});
			var m = n.match(/^_menu\[(\d+)\]\[menu_elements\]\[(\d+)\]/);
			return m ? '_menu[' + m[1] + '][menu_elements][' + m[2] + ']' : null;
		}

		/* ================= Select / auto-tipología por categoría ================= */

		function inyectarSelects() {
			var unico = unicoActual();

			// a) Filas de categoría normales.
			$cont.find('tr.pricing-submenu').not('.pp-tipo-suelto').each(function () {
				var $row = $(this);
				var i = indiceDeCategoria($row);
				if (i === null) { return; }
				var guardado = (D.guardados && D.guardados[i]) ? D.guardados[i] : '';

				if (unico) {
					// UNA sola tipología: no se pregunta — input oculto con el default.
					$row.find('.pp-tipo-wrap').remove();
					var $oc = $row.find('input.pp-tipo-oculto');
					if (!$oc.length) {
						$row.find('td').first().append(
							'<input type="hidden" class="pp-tipo-oculto" name="_menu[' + i + '][pp_tipo_servicio]" value="' + esc(unico) + '">'
						);
					} else {
						$oc.attr('name', '_menu[' + i + '][pp_tipo_servicio]').val(unico);
					}
					return;
				}

				// Varias tipologías: select visible.
				$row.find('input.pp-tipo-oculto').remove();
				if ($row.find('select.pp-tipo-servicio').length) { return; }
				var $wrap = $(
					'<div class="fm-input pp-tipo-wrap">' +
						'<select class="pp-tipo-servicio" name="_menu[' + i + '][pp_tipo_servicio]" ' +
							'title="Tipo de servicio (obligatorio)">' + opciones(guardado) + '</select>' +
					'</div>'
				);
				$row.find('.fm-input').first().after($wrap);
			});

			// b) Servicios SIN fila de categoría delante (menú sin título).
			var $primera = $cont.find('tr.pricing-list-item').first();
			var $suelto  = $cont.find('tr.pp-tipo-suelto');
			if ($primera.length && !$primera.hasClass('pricing-submenu')) {
				var base = baseDeFila($primera);
				if (base) {
					var i0 = (base.match(/^_menu\[(\d+)\]/) || [])[1];
					var guardado0 = (D.guardados && D.guardados[i0]) ? D.guardados[i0] : '';
					if (unico) {
						$suelto.remove();
						if (!$cont.find('input.pp-tipo-oculto-suelto').length) {
							$primera.find('td').first().append(
								'<input type="hidden" class="pp-tipo-oculto pp-tipo-oculto-suelto" name="_menu[' + i0 + '][pp_tipo_servicio]" value="' + esc(unico) + '">'
							);
						}
					} else {
						$cont.find('input.pp-tipo-oculto-suelto').remove();
						if (!$suelto.length) {
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
				}
			}

			refrescarDM();
		}

		/* Reconstruir todo cuando cambia el TIPO DE LISTADO (misma página). */
		function reaplicar() {
			$cont.find('tr.pp-tipo-suelto').remove();
			$cont.find('tr.pp-dom-suelto').remove();
			$cont.find('.pp-tipo-wrap').remove();
			$cont.find('.pp-dom-wrap').remove();
			$cont.find('input.pp-tipo-oculto').remove();
			inyectarSelects();
			refrescarDomicilio();
		}
		// El selector de tipo de listado llena el hidden #listing_type al hacer
		// clic en una tarjeta .listing-type (misma página, sin recarga).
		$(document).on('click', '.listing-type', function () {
			setTimeout(reaplicar, 80);
		});

		/* Tipología elegida del grupo al que pertenece una fila de servicio. */
		function tipoDeFila($tr) {
			var unico = unicoActual();
			if (unico) { return unico; }
			var $sub = $tr.prevAll('tr.pricing-submenu').first();
			return $sub.find('select.pp-tipo-servicio').val() || '';
		}

		/* Mostrar el bloque "Depende de mascota" solo si la tipología lo permite. */
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
			refrescarDomicilio();
		});

		/* Campo "Valor domicilio" POR MENÚ: visible solo cuando la tipología
		   del menú tiene la marca Domicilio (catálogo). Vacío = gratis. El
		   valor viaja en _menu[i][pp_dom_valor] (el guardado de Core lo
		   conserva, igual que pp_tipo_servicio). */
		function feeHtml(i, val) {
			return '<div class="fm-input pp-dom-wrap">' +
				'<label class="fm-input-label">Valor domicilio</label>' +
				'<input type="number" class="pp-dom-valor" name="_menu[' + i + '][pp_dom_valor]" min="0" step="any" ' +
					'value="' + esc(val) + '" placeholder="Vacío = gratis" ' +
					'title="Costo adicional cuando el cliente elige A domicilio; vacío = domicilio gratis">' +
			'</div>';
		}

		/* Campo "Antelación mín. (min)" POR MENÚ: todos los servicios del menú
		   la comparten; varía entre menús. Vacío = hereda (tipología →
		   listado → global). Viaja en _menu[i][pp_aviso]. */
		function avisoHtml(i, val) {
			return '<div class="fm-input pp-dom-wrap pp-aviso-wrap">' +
				'<label class="fm-input-label">Antelación mínima (min)</label>' +
				'<input type="number" class="pp-aviso-menu" name="_menu[' + i + '][pp_aviso]" min="0" step="1" ' +
					'value="' + esc(val) + '" placeholder="Opcional" ' +
					'title="Minutos mínimos de anticipación con que se puede reservar este menú. Déjalo vacío para usar la antelación general del servicio o del listado. Ejemplos: 1440 = 1 día, 4320 = 3 días.">' +
			'</div>';
		}

		function refrescarDomicilio() {
			var unico = unicoActual();

			// a) Menús con fila de categoría.
			$cont.find('tr.pricing-submenu').not('.pp-tipo-suelto').not('.pp-dom-suelto').each(function () {
				var $row = $(this);
				var i = indiceDeCategoria($row);
				if (i === null) { return; }
				var tipo = unico || ($row.find('select.pp-tipo-servicio').val() || '');
				// Antelación por menú: SIEMPRE disponible.
				if (!$row.find('.pp-aviso-wrap').length) {
					var valA = (D.avisoValores && D.avisoValores[i]) ? D.avisoValores[i] : '';
					$row.find('td').first().append(avisoHtml(i, valA));
				} else {
					$row.find('input.pp-aviso-menu').attr('name', '_menu[' + i + '][pp_aviso]');
				}
				// Valor domicilio: solo si la tipología del menú lo permite.
				var $dom = $row.find('.pp-dom-wrap').not('.pp-aviso-wrap');
				if (!tieneDomicilio[tipo]) { $dom.remove(); return; }
				if (!$dom.length) {
					var val = (D.domValores && D.domValores[i]) ? D.domValores[i] : '';
					$row.find('td').first().append(feeHtml(i, val));
				} else {
					$dom.find('input.pp-dom-valor').attr('name', '_menu[' + i + '][pp_dom_valor]');
				}
			});

			// b) Bloque inicial SIN categoría: fila propia que aloja los campos
			//    del menú (antelación SIEMPRE; domicilio según la tipología).
			var $primera = $cont.find('tr.pricing-list-item').not('.pp-dom-suelto').first();
			var $filaDom = $cont.find('tr.pp-dom-suelto');
			if ($primera.length && !$primera.hasClass('pricing-submenu')) {
				var base = baseDeFila($primera);
				var i0 = base ? (base.match(/^_menu\[(\d+)\]/) || [])[1] : null;
				var tipo0 = unico || $cont.find('tr.pp-tipo-suelto select.pp-tipo-servicio').val() || '';
				if (i0 === null || typeof i0 === 'undefined') { $filaDom.remove(); return; }
				if (!$filaDom.length) {
					$primera.before('<tr class="pricing-list-item pricing-submenu pp-dom-suelto"><td></td></tr>');
					$filaDom = $cont.find('tr.pp-dom-suelto');
				}
				var $td = $filaDom.find('td').first();
				if (!$td.find('.pp-aviso-wrap').length) {
					var valA0 = (D.avisoValores && D.avisoValores[i0]) ? D.avisoValores[i0] : '';
					$td.append(avisoHtml(i0, valA0));
				}
				var $dom0 = $td.find('.pp-dom-wrap').not('.pp-aviso-wrap');
				if (tieneDomicilio[tipo0]) {
					if (!$dom0.length) {
						var val0 = (D.domValores && D.domValores[i0]) ? D.domValores[i0] : '';
						$td.append(feeHtml(i0, val0));
					}
				} else {
					$dom0.remove();
				}
			} else {
				$filaDom.remove();
			}
		}

		/* ============ Bloque "Depende de mascota" (por servicio) ============ */

		$(document).on('change', '.pp-dm-check', function () {
			$(this).closest('.pp-dm-wrap').find('.pp-dm-campos').toggle(this.checked);
		});

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

		/* Plantilla del bloque para filas NUEVAS (Add Item las crea por JS). */
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

		/* Filas y categorías nuevas. */
		$(document).on('listeo:pricing-row-added', function () {
			setTimeout(function () { inyectarSelects(); inyectarDMFaltantes(); refrescarDomicilio(); }, 0);
		});
		var timer = null;
		new MutationObserver(function () {
			if (timer) { clearTimeout(timer); }
			timer = setTimeout(function () { inyectarSelects(); inyectarDMFaltantes(); refrescarDomicilio(); }, 50);
		}).observe($cont[0], { childList: true, subtree: true });

		/* ================= Envío del formulario ================= */

		/* Selects de tipología SIN valor en menús que sí tienen contenido. */
		function tiposFaltantes() {
			var $faltan = $();
			$cont.find('select.pp-tipo-servicio:visible').each(function () {
				var $sel = $(this);
				if ($sel.val()) { return; }
				var $fila = $sel.closest('tr');
				var conTitulo = ($fila.find('input[name$="[menu_title]"]').val() || '').trim() !== '';
				var conServicios = false;
				$fila.nextUntil('tr.pricing-submenu').each(function () {
					var $t = $(this).find('input[name$="[name]"]').first();
					if (($t.val() || '').trim() !== '') { conServicios = true; return false; }
				});
				if (conTitulo || conServicios) { $faltan = $faltan.add($sel); }
			});
			return $faltan;
		}

		function avisarFaltantes($faltan) {
			$faltan.addClass('pp-tipo-error');
			window.alert(D.msgFalta || 'Selecciona el "Tipo de servicio" en cada menú de servicios.');
			$('html, body').animate({ scrollTop: $faltan.first().offset().top - 120 }, 250);
		}

		/* Validación EN EL CLIC del botón guardar, en fase de CAPTURA: corre
		   ANTES que el asistente multipaso de Listeo (submit-listing.steps.js),
		   que en su clic muestra un velo de página (msf-loader-overlay) y deja
		   continuar el envío. Si nosotros frenáramos recién en el submit, el
		   velo quedaría girando para siempre (bug reproducido por Miguel).
		   Bloqueando aquí, el asistente ni corre y no hay velo. */
		document.addEventListener('click', function (ev) {
			var btn = ev.target && ev.target.closest ? ev.target.closest('button[name="submit_listing"]') : null;
			if (!btn) { return; }
			if (unicoActual()) { return; } // tipología única: nada que validar
			var $faltan = tiposFaltantes();
			if ($faltan.length) {
				ev.preventDefault();
				ev.stopPropagation(); // el asistente (listener del botón) no corre
				avisarFaltantes($faltan);
			}
		}, true);

		var $form = $cont.closest('form');
		$form.on('submit', function (e) {
			// 1) Re-sincronizar nombres (Core renumera índices al reordenar).
			$cont.find('tr.pricing-submenu').not('.pp-tipo-suelto').not('.pp-dom-suelto').each(function () {
				var $row = $(this);
				var i = indiceDeCategoria($row);
				if (i !== null) {
					$row.find('select.pp-tipo-servicio, input.pp-tipo-oculto').attr('name', '_menu[' + i + '][pp_tipo_servicio]');
					$row.find('input.pp-dom-valor').attr('name', '_menu[' + i + '][pp_dom_valor]');
					$row.find('input.pp-aviso-menu').attr('name', '_menu[' + i + '][pp_aviso]');
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

			// 2) Respaldo de validación en el submit (p. ej. envío con Enter,
			//    que no pasa por el clic del botón). Si por cualquier camino el
			//    velo del asistente ya quedó visible, se apaga para no dejar la
			//    página colgada "cargando".
			if (unicoActual()) { return; } // tipología única: nada que validar
			var $faltan = tiposFaltantes();
			if ($faltan.length) {
				e.preventDefault();
				var velo = document.querySelector('.msf-loader-overlay');
				if (velo) { velo.style.display = 'none'; }
				avisarFaltantes($faltan);
				return false;
			}
		});

		inyectarSelects();
		inyectarDMFaltantes();
		refrescarDomicilio();
	});
})(jQuery);
