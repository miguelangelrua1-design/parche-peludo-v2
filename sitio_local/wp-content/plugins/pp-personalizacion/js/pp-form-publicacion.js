/**
 * Quick wins del formulario "Agregar Publicación".
 * TODO con guardas: si un selector no existe, ese ajuste simplemente no se
 * aplica y el formulario nativo queda intacto (incluye el asistente de pasos:
 * aquí solo se agregan textos/plegables, jamás se tocan sus botones ni su
 * validación).
 */
(function ($) {
	'use strict';

	$(function () {
		// Solo si el formulario está presente.
		if (!$('.add-listing-section').length) { return; }

		/* ---- QW1: aviso bajo el toggle "Precios y Servicios Reservables".
		 * La sección lleva la clase `menu` (builder de menús de Listeo). */
		var $seccMenu = $('.add-listing-section.menu.has-switcher').first();
		if (!$seccMenu.length) {
			$seccMenu = $('.add-listing-section').filter(function () {
				return /Precios y Servicios Reservables/i.test($(this).find('h3').first().text());
			}).first();
		}
		if ($seccMenu.length && !$seccMenu.find('.pp-fp-aviso').length) {
			$seccMenu.find('h3').first().after(
				'<p class="pp-fp-aviso">💡 <strong>Actívalo para recibir reservas.</strong> ' +
				'Sin servicios, tu publicación no mostrará el botón <em>Reservar</em>, ' +
				'ni la sección "Servicios y Precios", ni aparecerá en la búsqueda por disponibilidad.</p>'
			);
		}

		/* ---- QW2: ocultar la Categoría GLOBAL solo si el tipo tiene la suya.
		 * Global = .form-field-listing_category-container; la del tipo termina
		 * igual en _category-container pero con su slug (p. ej. directorio). */
		var $catGlobal = $('.form-field-listing_category-container').first();
		if ($catGlobal.length) {
			var hayPropia = $('[class*="_category-container"]').filter(function () {
				return !$(this).hasClass('form-field-listing_category-container');
			}).length > 0;
			if (hayPropia) {
				$catGlobal.addClass('pp-fp-oculto');
			}
		}

		/* ---- QW3: Place ID / Longitud / Latitud tras "Opciones avanzadas".
		 * Los inputs SIGUEN en el DOM (el mapa los llena solo); solo se
		 * esconden de la vista tras un plegable. */
		var $avanzados = $(
			'.form-field-_place_id-container, ' +
			'.form-field-_geolocation_long-container, ' +
			'.form-field-_geolocation_lat-container'
		);
		if ($avanzados.length === 3 && !$('.pp-fp-avanzado').length) {
			var $btn = $(
				'<div class="pp-fp-avanzado col-md-12">' +
					'<button type="button" class="pp-fp-avanzado__btn">' +
						'<i class="fa fa-cog" aria-hidden="true"></i> Opciones avanzadas de ubicación ' +
						'<i class="fa fa-angle-down" aria-hidden="true"></i>' +
					'</button>' +
				'</div>'
			);
			$avanzados.first().before($btn);
			$avanzados.addClass('pp-fp-oculto');
			$btn.on('click', 'button', function () {
				var abierto = $avanzados.first().hasClass('pp-fp-oculto');
				$avanzados.toggleClass('pp-fp-oculto', !abierto);
				$(this).find('.fa-angle-down, .fa-angle-up')
					.toggleClass('fa-angle-down', abierto === false)
					.toggleClass('fa-angle-up', abierto === true);
			});
		}

		/* ---- Guía breve en Galería ---- */
		var $gal = $('.add-listing-section').filter(function () {
			return /Galer/i.test($(this).find('h3').first().text());
		}).first();
		if ($gal.length && $gal.find('.dropzone').length && !$gal.find('.pp-fp-guia-gal').length) {
			$gal.find('h3').first().after(
				'<p class="pp-fp-guia-gal">📷 Sube <strong>mínimo 3 fotos</strong> horizontales y con buena luz. ' +
				'La primera será la portada de tu publicación — ¡las fotos son lo que más reservas genera!</p>'
			);
		}

		/* =====================================================================
		 * M1 — CHECKLIST DE CALIDAD (último paso, antes de "Vista Previa")
		 * Informativo: NUNCA bloquea el envío ni toca el asistente; solo lee
		 * campos y navega con los MISMOS clicks que haría el usuario.
		 * ================================================================== */

		var $btnPreview = $('button[name="submit_listing"]').first();
		var $pasosNav = $('.form-progress-step');

		function textoDescripcion() {
			// El editor visual (TinyMCE) no sincroniza el textarea hasta el
			// submit: leerlo directo si está activo.
			try {
				if (window.tinyMCE && tinyMCE.get('listing_description') && !tinyMCE.get('listing_description').isHidden()) {
					return tinyMCE.get('listing_description').getContent({ format: 'text' }) || '';
				}
			} catch (e) { /* sin editor visual */ }
			return String($('[name="listing_description"]').val() || '');
		}

		// Cada ítem: se evalúa SOLO si su campo existe en este tipo de listado.
		// paso = índice del paso del asistente donde vive el campo.
		function evaluarChecklist() {
			var items = [];

			var $tit = $('input[name="listing_title"]');
			if ($tit.length) {
				items.push({ ok: String($tit.val() || '').trim().length >= 3, nivel: 'req', paso: 0, txt: 'Título de la publicación' });
			}
			if ($('[name="listing_description"]').length) {
				items.push({ ok: textoDescripcion().trim().length >= 20, nivel: 'req', paso: 0, txt: 'Descripción (mínimo un par de frases)' });
			}
			var $dz = $('.dropzone._gallery').first();
			if ($dz.length) {
				var fotos = $dz.find('.dz-preview').length;
				items.push({ ok: fotos >= 3, casi: fotos > 0, nivel: 'rec', paso: 0, txt: fotos > 0 ? 'Fotos (' + fotos + ' — recomendamos 3+)' : 'Fotos (las fichas con fotos reciben más reservas)' });
			}
			// Categoría: cualquier campo *_category del formulario con valor.
			var $cats = $('[name$="_category"], [name$="_category[]"]').filter(function () {
				return !$(this).closest('.pp-fp-oculto').length;
			});
			if ($cats.length) {
				var hayCat = false;
				$cats.each(function () {
					var v = $(this).val();
					if (v && String(v).length) { hayCat = true; return false; }
				});
				items.push({ ok: hayCat, nivel: 'rec', paso: 0, txt: 'Categoría de tu negocio' });
			}
			var $dir = $('input[name="_address"]');
			if ($dir.length) {
				items.push({ ok: String($dir.val() || '').trim().length > 0, nivel: 'rec', paso: 0, txt: 'Dirección (para aparecer en el mapa)' });
			}
			// Servicios reservables: el corazón del negocio (solo si el tipo los tiene).
			var $secMenu = $('.add-listing-section.menu');
			if ($secMenu.length) {
				var activo = $('input[name="_menu_status"]').is(':checked');
				var hayServicio = false;
				$('[name*="[menu_elements]"][name$="[name]"]').each(function () {
					if (String($(this).val() || '').trim()) { hayServicio = true; return false; }
				});
				items.push({
					ok: activo && hayServicio,
					nivel: 'req',
					paso: 1,
					txt: activo ? (hayServicio ? 'Servicios reservables' : 'Servicios: activaste la sección pero aún no creas ninguno')
						: 'Servicios reservables (sin esto no hay botón Reservar)'
				});
			}
			// Horario de apertura (informativo).
			var $hor = $('input[name="_opening_hours_status"]');
			if ($hor.length) {
				items.push({ ok: $hor.is(':checked'), nivel: 'rec', paso: 1, txt: 'Horario de apertura' });
			}
			return items;
		}

		function montarChecklist() {
			if (!$btnPreview.length || $('#pp-fp-checklist').length) { return; }
			$btnPreview.before('<div id="pp-fp-checklist" style="display:none"></div>');
		}

		function pintarChecklist() {
			var $box = $('#pp-fp-checklist');
			if (!$box.length) { return; }
			// Visible SOLO en el último paso del asistente (o sin asistente).
			var ultimo = !$pasosNav.length || $pasosNav.last().hasClass('active');
			if (!ultimo) { $box.hide(); return; }

			var items = evaluarChecklist();
			if (!items.length) { $box.hide(); return; }
			var pendientes = items.filter(function (i) { return !i.ok; }).length;
			var html = '<h4>📋 Revisión final' + (pendientes ? '' : ' — ¡todo listo!') + '</h4><ul>';
			items.forEach(function (i, n) {
				var estado = i.ok ? 'ok' : (i.casi ? 'casi' : (i.nivel === 'req' ? 'falta' : 'casi'));
				html += '<li class="pp-fp-chk--' + estado + '">' +
					'<span class="pp-fp-chk__ico">' + (i.ok ? '✅' : (estado === 'falta' ? '❌' : '⚠️')) + '</span> ' +
					i.txt +
					(i.ok ? '' : ' <button type="button" class="pp-fp-chk__ir" data-paso="' + i.paso + '">Completar</button>') +
				'</li>';
			});
			html += '</ul>';
			if (pendientes) {
				html += '<p class="pp-fp-chk__nota">Puedes publicar igual — esto es solo una guía para que tu publicación rinda más.</p>';
			}
			$box.html(html).show();
		}

		// Navegar al paso del ítem con el MISMO click del asistente.
		$(document).on('click', '.pp-fp-chk__ir', function () {
			var paso = parseInt($(this).attr('data-paso'), 10) || 0;
			var $destino = $pasosNav.eq(paso);
			if ($destino.length) {
				$destino.trigger('click');
				setTimeout(function () {
					var $s = $('.add-listing-section:visible').first();
					if ($s.length) { $('html,body').animate({ scrollTop: $s.offset().top - 90 }, 250); }
				}, 250);
			}
		});

		// Re-evaluar al navegar (clicks del asistente o botones internos):
		// delegación amplia con debounce — barata y a prueba de variantes.
		var chkTimer = null;
		$(document).on('click', '.form-progress-step, .submit-page button, .submit-page a.button', function () {
			clearTimeout(chkTimer);
			chkTimer = setTimeout(pintarChecklist, 300);
		});
		montarChecklist();
		pintarChecklist();

		/* =====================================================================
		 * M2 — MINI-ASISTENTE "TU PRIMER SERVICIO"
		 * Aparece al activar "Precios y Servicios Reservables" cuando aún no
		 * hay menús con datos. Crea el menú y el servicio usando los CONTROLES
		 * NATIVOS del builder (mismos clicks del usuario): los índices, el
		 * guardado y las inyecciones (tipología de pp-serv-menu, campos de
		 * Booking Plus) siguen siendo 100% los de siempre.
		 * ================================================================== */

		var $secServicios = $('.add-listing-section.menu').first();
		var $toggleMenu = $('input[name="_menu_status"]').first();

		function hayDatosMenus() {
			var hay = false;
			$('[name$="[menu_title]"]').each(function () {
				if (String($(this).val() || '').trim()) { hay = true; return false; }
			});
			if (!hay) {
				$('[name*="[menu_elements]"][name$="[name]"]').each(function () {
					if (String($(this).val() || '').trim()) { hay = true; return false; }
				});
			}
			return hay;
		}

		function opcionesTipologia() {
			// 1º: clonar las del select real que inyecta pp-serv-menu.
			var $sel = $('select.pp-tipo-servicio').first();
			if ($sel.length && $sel.find('option').length > 1) {
				return $sel.html();
			}
			// 2º: construirlas de los datos localizados del builder.
			var D = window.PP_SERV_MENU || null;
			if (!D || !D.tipos) { return ''; }
			var tipoListado = String($('input[name="_listing_type"]').val() || D.tipoListado || '');
			var permitidos = (D.porListado && D.porListado[tipoListado]) ? D.porListado[tipoListado] : null;
			var html = '<option value="">— Elige el tipo —</option>';
			D.tipos.forEach(function (t) {
				if (permitidos && permitidos.indexOf(t.slug) === -1) { return; }
				html += '<option value="' + t.slug + '">' + t.nombre + '</option>';
			});
			return html;
		}

		function montarPrimerServicio() {
			if (!$secServicios.length || $('#pp-fp-primer').length) { return; }
			// Piezas nativas imprescindibles; si faltan, no montar (no-op).
			if (!$('#pricing-list-container').length || !$('.add-pricing-submenu').length) { return; }
			var ops = opcionesTipologia();
			if (!ops) { return; }
			var $caja = $(
				'<div id="pp-fp-primer" style="display:none">' +
					'<h4>🚀 Crea tu primer servicio en 30 segundos</h4>' +
					'<div class="pp-fp-primer__grid">' +
						'<label>Nombre del menú<br><input type="text" id="pp-fp-p-menu" placeholder="Ej: Peluquería" maxlength="60"></label>' +
						'<label>Tipo de servicio<br><select id="pp-fp-p-tipo">' + ops + '</select></label>' +
						'<label>Tu primer servicio<br><input type="text" id="pp-fp-p-serv" placeholder="Ej: Baño e hidratación" maxlength="80"></label>' +
						'<label>Precio (opcional)<br><input type="number" id="pp-fp-p-precio" min="0" step="any" placeholder="Ej: 40000"></label>' +
					'</div>' +
					'<button type="button" class="pp-fp-primer__crear">Crear mi primer servicio</button> ' +
					'<button type="button" class="pp-fp-primer__no">Prefiero hacerlo manualmente</button>' +
					'<p class="pp-fp-primer__err" style="display:none"></p>' +
				'</div>'
			);
			// Delante de la tabla del builder, dentro de la sección.
			$('#pricing-list-container').before($caja);
		}

		function refrescarPrimerServicio() {
			var $caja = $('#pp-fp-primer');
			if (!$caja.length) { return; }
			var mostrar = $toggleMenu.is(':checked') && !hayDatosMenus() && !$caja.data('ppRechazado');
			$caja.toggle(!!mostrar);
		}

		$(document).on('change', 'input[name="_menu_status"]', function () {
			setTimeout(refrescarPrimerServicio, 150);
		});
		$(document).on('click', '.pp-fp-primer__no', function () {
			$('#pp-fp-primer').data('ppRechazado', true).hide();
		});

		$(document).on('click', '.pp-fp-primer__crear', function () {
			var $caja = $('#pp-fp-primer');
			var menuNombre = String($('#pp-fp-p-menu').val() || '').trim();
			var tipologia = String($('#pp-fp-p-tipo').val() || '');
			var servNombre = String($('#pp-fp-p-serv').val() || '').trim();
			var precio = String($('#pp-fp-p-precio').val() || '').trim();
			var $err = $caja.find('.pp-fp-primer__err');
			if (!menuNombre || !tipologia || !servNombre) {
				$err.text('Completa el nombre del menú, el tipo de servicio y el nombre del servicio.').show();
				return;
			}
			$err.hide();

			// 1) MENÚ: reutilizar una categoría vacía o crearla con el botón nativo.
			var $tituloMenu = $('tr.pricing-submenu input[name$="[menu_title]"]').filter(function () {
				return !String($(this).val() || '').trim();
			}).first();
			if (!$tituloMenu.length) {
				$('.add-pricing-submenu').first().trigger('click');
				$tituloMenu = $('tr.pricing-submenu').last().find('input[name$="[menu_title]"]').first();
			}
			if (!$tituloMenu.length) { $err.text('No se pudo crear el menú; usa los botones de abajo.').show(); return; }
			$tituloMenu.val(menuNombre).trigger('change');
			var m = String($tituloMenu.attr('name')).match(/^_menu\[(\d+)\]/);
			var idx = m ? m[1] : null;
			if (null === idx) { $err.text('No se pudo identificar el menú; usa los botones de abajo.').show(); return; }

			// 2) SERVICIO: preferir una fila vacía DEL MISMO menú; si no, crearla
			//    con el botón nativo (cae bajo el último menú, que es este).
			function filaVaciaDe(i) {
				return $('input[name^="_menu[' + i + '][menu_elements]"][name$="[name]"]').filter(function () {
					return !String($(this).val() || '').trim();
				}).first();
			}
			var $nombreServ = filaVaciaDe(idx);
			if (!$nombreServ.length) {
				$('.add-pricing-list-item').first().trigger('click');
				$nombreServ = filaVaciaDe(idx);
			}
			if (!$nombreServ.length) { $err.text('No se pudo crear el servicio; usa los botones de abajo.').show(); return; }
			$nombreServ.val(servNombre).trigger('change');

			var $fila = $nombreServ.closest('tr.pricing-list-item');
			if (precio) {
				$fila.find('input[name$="[price]"]').first().val(precio).trigger('change');
			}
			var $bookable = $fila.find('input[name$="[bookable]"]').first();
			if ($bookable.length && !$bookable.prop('checked')) {
				$bookable.prop('checked', true).trigger('change');
			}

			// Estética: el título del menú SIEMPRE arriba de su servicio (los
			// índices del POST no dependen del orden visual).
			var $trMenu = $tituloMenu.closest('tr.pricing-submenu');
			if ($trMenu.length && $fila.length && $trMenu.index() > $fila.index()) {
				$trMenu.insertBefore($fila);
			}

			// 3) TIPOLOGÍA: pp-serv-menu inyecta el select del menú (observer,
			//    ≤50ms); setearla dispara sus refrescos (domicilio/antelación).
			setTimeout(function () {
				var $selTipo = $('select.pp-tipo-servicio[name="_menu[' + idx + '][pp_tipo_servicio]"]').first();
				if (!$selTipo.length) { $selTipo = $('select.pp-tipo-servicio').first(); }
				if ($selTipo.length) { $selTipo.val(tipologia).trigger('change'); }
			}, 200);

			// 4) Cierre: ocultar la guía, resaltar lo creado y dejar al usuario
			//    sobre el builder real (puede seguir agregando con los botones).
			$caja.hide();
			$fila.addClass('pp-fp-nuevo');
			$trMenu.addClass('pp-fp-nuevo');
			setTimeout(function () { $('.pp-fp-nuevo').removeClass('pp-fp-nuevo'); }, 2600);
			$('html,body').animate({ scrollTop: $('#pricing-list-container').offset().top - 110 }, 250);
		});

		montarPrimerServicio();
		refrescarPrimerServicio();
	});
})(jQuery);
