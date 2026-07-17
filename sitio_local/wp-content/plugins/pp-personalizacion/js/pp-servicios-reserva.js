/**
 * Personalización Parche — módulo Servicios (popup de reserva de Booking Plus).
 *
 * 1) Etiqueta dinámica: al seleccionar un servicio INDIVIDUAL, la cabecera
 *    del acordeón muestra el nombre del servicio elegido (cubre el vacío de
 *    Booking Plus, cuyo swap solo funciona en el widget clásico).
 *    Datos: window.lbpServiceConstraints (los localiza Booking Plus).
 *
 * 2) Tabs por tipo de servicio: si el listado tiene VARIOS menús con
 *    servicios reservables, se pintan pestañas con los títulos de los menús
 *    encima de la lista; hasta no elegir un tipo, los servicios quedan
 *    ocultos. Cada pestaña muestra un contador con sus seleccionados.
 *    Datos: window.PP_SERV.menus (los localiza includes/servicios.php).
 *
 * Regla vigente (decisión de producto): UN solo servicio principal
 * (individual) por reserva sin importar el tipo — eso lo aplica el propio
 * Booking Plus; aquí no se toca.
 */
(function ($) {
	'use strict';

	// Escapa tambien comillas: esc() se usa dentro de atributos HTML.
	function esc(s) {
		return String(s || '').replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	/* =====================================================================
	   1) Etiqueta dinámica ("Elige tu servicio" → nombre del elegido)
	   ================================================================== */
	function iniciarEtiqueta() {
		var data = window.lbpServiceConstraints || null;
		if (!data || !data.services) { return; }

		function slugDe($cb) {
			return $cb.attr('data-service-slug') || $cb.val();
		}

		function individualMarcada() {
			var slug = null;
			$('input.bookable-service-checkbox:checked').each(function () {
				var s = slugDe($(this));
				if (data.services[s] && data.services[s].individual) {
					slug = s;
					return false;
				}
			});
			return slug;
		}

		function actualizarCabecera() {
			var $toggle = $('.lbp-panel-services .lbp-panel-toggle').first();
			if (!$toggle.length) { return; }
			var $counter = $toggle.find('.lbp-services-counter');

			if (!$toggle.data('ppBase')) {
				var base = $toggle.contents().filter(function () {
					return this.nodeType === 3;
				}).text().trim();
				$toggle.data('ppBase', base || 'Elige tu servicio');
			}

			$toggle.contents().filter(function () {
				return this.nodeType === 3;
			}).remove();

			var slug = individualMarcada();
			if (slug) {
				$toggle.prepend(document.createTextNode(data.services[slug].name + ' '));
				$counter.hide();
			} else {
				$toggle.prepend(document.createTextNode($toggle.data('ppBase') + ' '));
				$counter.show();
			}
		}

		$(document).on('change', 'input.bookable-service-checkbox', function () {
			setTimeout(actualizarCabecera, 0); // tras la exclusión de Booking Plus
		});
		actualizarCabecera();

		// Al SELECCIONAR un servicio INDIVIDUAL (elección única y definitiva),
		// cerrar el desplegable: el cliente ve el total y sigue sin pasos de
		// más. Los extras NO cierran (son acumulables: puede querer varios).
		$(document).on('change', 'input.bookable-service-checkbox', function () {
			var $cb = $(this);
			if (!$cb.is(':checked')) { return; }
			var s = slugDe($cb);
			if (!data.services[s] || !data.services[s].individual) { return; }
			setTimeout(function () {
				var $panel = $('.lbp-panel-services').first();
				var $lista = $panel.find('.lbp-bookable-services').first();
				if ($lista.is(':visible')) {
					$panel.find('.lbp-panel-toggle').trigger('click'); // plegar
				}
			}, 350); // pequeña pausa: que se alcance a ver el check
		});
	}

	/* =====================================================================
	   2) Tabs por tipo de servicio (menús)
	   - Los tabs viven FUERA del desplegable, como botones.
	   - El desplegable arranca deshabilitado; se habilita (y se abre) al
	     elegir un tipo.
	   - Si el tipo elegido tiene UNA sola opción, se selecciona sola.
	   ================================================================== */
	function iniciarTabs() {
		var D = window.PP_SERV || null;
		if (!D || !D.menus || D.menus.length < 2) { return; }

		var $panel = $('.lbp-panel-services').first();
		var $lista = $panel.find('.lbp-bookable-services').first();
		if (!$panel.length || !$lista.length) { return; }

		// Mapa slug → índice de menú.
		var mapa = {};
		D.menus.forEach(function (m, i) {
			m.slugs.forEach(function (s) { mapa[s] = i; });
		});

		// Etiquetar cada fila de servicio con su menú.
		$lista.find('.lbp-single-service').each(function () {
			var slug = $(this).attr('data-service-slug');
			$(this).attr('data-pp-menu', mapa.hasOwnProperty(slug) ? mapa[slug] : -1);
		});

		// Barra de pestañas FUERA del desplegable (antes del panel).
		var html = '<div class="pp-serv-zona">' +
			'<div class="pp-serv-zona__titulo">Tipo de servicio</div>' +
			'<div class="pp-serv-tabs" role="tablist">';
		D.menus.forEach(function (m, i) {
			html += '<button type="button" class="pp-serv-tab" role="tab" data-menu="' + i + '">' +
				esc(m.titulo) +
				'<span class="pp-serv-tab__num" style="display:none">0</span>' +
			'</button>';
		});
		html += '</div>' +
			'<p class="pp-serv-hint">Elige el tipo de servicio para habilitar las opciones.</p>' +
		'</div>';
		$panel.before(html);

		// Estado inicial: sin tipo elegido → filas ocultas, desplegable
		// deshabilitado y plegado.
		$lista.find('.lbp-single-service').hide();
		if ($lista.is(':visible')) {
			$panel.find('.lbp-panel-toggle').trigger('click'); // plegar
		}
		$panel.addClass('pp-serv-bloqueado');

		function autoSeleccionUnica(i) {
			var $filas = $lista.find('.lbp-single-service[data-pp-menu="' + i + '"]');
			if (1 !== $filas.length) { return; }
			var $cb = $filas.find('input.bookable-service-checkbox');
			if ($cb.length && !$cb.prop('checked')) {
				$cb.prop('checked', true).trigger('change');
			}
		}

		function aplicarTab(i) {
			$('.pp-serv-tab').removeClass('activa')
				.filter('[data-menu="' + i + '"]').addClass('activa');
			$('.pp-serv-hint').hide();

			// Filtrar filas por tipo (sin mapa (-1): mostrar siempre).
			$lista.find('.lbp-single-service').each(function () {
				var m = parseInt($(this).attr('data-pp-menu'), 10);
				$(this).toggle(m === i || m === -1);
			});

			// Habilitar el desplegable.
			$panel.removeClass('pp-serv-bloqueado');

			var $filas = $lista.find('.lbp-single-service[data-pp-menu="' + i + '"]');
			if (1 === $filas.length) {
				// Única opción: se selecciona sola y NO se abre el desplegable
				// (no hay nada que elegir); si estaba abierto, se pliega.
				autoSeleccionUnica(i);
				setTimeout(function () {
					if ($lista.is(':visible')) {
						$panel.find('.lbp-panel-toggle').trigger('click');
					}
				}, 0);
			} else {
				// Varias opciones: abrir para mostrarlas. La apertura va en
				// setTimeout(0): Booking Plus cierra sus desplegables al
				// detectar clics FUERA del panel, y el clic en el tab (que
				// está fuera) lo cerraría en el mismo instante.
				setTimeout(function () {
					if (!$lista.is(':visible')) {
						$panel.find('.lbp-panel-toggle').trigger('click');
					}
				}, 0);
			}
		}

		$(document).on('click', '.pp-serv-tab', function () {
			aplicarTab(parseInt($(this).attr('data-menu'), 10));
		});

		// Contador de seleccionados por pestaña (visibiliza que hay elegidos
		// en otros tipos aunque sus filas estén ocultas).
		function refrescarContadores() {
			D.menus.forEach(function (m, i) {
				var n = $lista.find('.lbp-single-service[data-pp-menu="' + i + '"] input.bookable-service-checkbox:checked').length;
				var $num = $('.pp-serv-tab[data-menu="' + i + '"] .pp-serv-tab__num');
				$num.text(String(n)).toggle(n > 0);
			});
		}
		$(document).on('change', 'input.bookable-service-checkbox', function () {
			setTimeout(refrescarContadores, 0);
		});
		refrescarContadores();
	}

	/* =====================================================================
	   3) Filtrado de servicios según la MASCOTA elegida (Tipos de servicio)
	   - Datos: PP_SERV.restricciones = { índice: {especie, pelaje, pesos[]} }
	     (el índice es el value del checkbox del servicio en el popup).
	   - Escucha el evento `pp:mascota-elegida` que emite
	     pp-mascotas-reserva.js al confirmar la mascota en el paso 1.
	   - Oculta con la clase .pp-oculta-mascota (CSS con !important, para
	     que sobreviva a los .toggle() de los tabs) y desmarca el servicio
	     si estaba seleccionado.
	   ================================================================== */
	function iniciarFiltroMascota() {
		var D = window.PP_SERV || null;
		if (!D || !D.restricciones) { return; }
		var R = D.restricciones;
		if ($.isEmptyObject(R)) { return; }

		function aplica(r, m) {
			if (!m) { return true; }
			if (r.especie && m.especie && r.especie !== m.especie) { return false; }
			if (r.pelaje && m.pelaje && r.pelaje !== m.pelaje) { return false; }
			var peso = parseFloat(m.peso);
			if (r.pesos && r.pesos.length && peso > 0) {
				var dentro = false;
				r.pesos.forEach(function (p) {
					var minOk = (p.min === null || typeof p.min === 'undefined') || peso >= p.min;
					var maxOk = (p.max === null || typeof p.max === 'undefined') || peso <= p.max;
					if (minOk && maxOk) { dentro = true; }
				});
				if (!dentro) { return false; }
			}
			return true;
		}

		function filtrar(m) {
			$('.lbp-single-service').each(function () {
				var $fila = $(this);
				var $cb = $fila.find('input.bookable-service-checkbox');
				if (!$cb.length) { return; }
				var r = R[String($cb.val())];
				var ok = !r || aplica(r, m);
				$fila.toggleClass('pp-oculta-mascota', !ok);
				if (!ok && $cb.prop('checked')) {
					$cb.prop('checked', false).trigger('change');
				}
			});

			// Tabs sin servicios aplicables: se ocultan (y reaparecen si la
			// mascota cambia a una que sí aplica).
			$('.pp-serv-tab').each(function () {
				var i = $(this).attr('data-menu');
				var $filas = $('.lbp-single-service[data-pp-menu="' + i + '"]');
				var ocultas = $filas.filter('.pp-oculta-mascota').length;
				$(this).toggle($filas.length === 0 || ocultas < $filas.length);
			});
		}

		$(document).on('pp:mascota-elegida', function (e, m) {
			filtrar(m || null);
		});
	}

	$(function () {
		iniciarEtiqueta();
		// Con el flujo "Reserva por Servicios" activo, el paso Servicios del
		// popup reemplaza estos tabs (PP_RS los supersede); el filtrado por
		// mascota y la etiqueta dinámica siguen siendo responsabilidad nuestra.
		if (!window.PP_RS || !window.PP_RS.activo) {
			iniciarTabs();
		}
		iniciarFiltroMascota();
	});
})(jQuery);
