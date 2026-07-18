/**
 * Vitrina "Servicios y Precios" — comportamiento.
 *
 * - Tabs por menú (mostrar/ocultar grupos ya renderizados; cero AJAX).
 * - "Detalles" → panel deslizante desde abajo con título, precio, botón
 *   Reservar y descripción (datos ya presentes en el HTML de la fila).
 * - "Reservar" (fila y panel) → dispara el MISMO abridor del popup de
 *   reserva que ya existe en la página. Si el listado no es reservable
 *   (sin abridor), los botones Reservar se ocultan solos.
 */
(function ($) {
	'use strict';

	$(function () {
		var $vitrina = $('.pp-sp');
		if (!$vitrina.length) { return; }

		/* ---------- Abridor del popup de reserva ---------- */
		function abridor() {
			var $a = $('a.lbp-book-now-btn').first();
			if (!$a.length) { $a = $('a[href="#lbp-booking-modal"]').first(); }
			if (!$a.length) { $a = $('a.book-now-notloggedin').first(); }
			return $a;
		}
		// Listado sin reserva → sin botones Reservar (degradación).
		if (!abridor().length) {
			$vitrina.find('.pp-sp__reservar').hide();
		}

		/* ---------- Tabs ---------- */
		$(document).on('click', '.pp-sp__tab', function () {
			var i = $(this).attr('data-i');
			$('.pp-sp__tab').removeClass('activo');
			$(this).addClass('activo');
			$('.pp-sp__grupo').each(function () {
				this.hidden = $(this).attr('data-i') !== i;
			});
		});

		/* ---------- Panel deslizante ---------- */
		var $sheet = null;
		function montarSheet() {
			if ($sheet) { return $sheet; }
			$sheet = $(
				'<div class="pp-sp-sheet" hidden>' +
					'<div class="pp-sp-sheet__velo"></div>' +
					'<div class="pp-sp-sheet__panel" role="dialog" aria-modal="true">' +
						'<button type="button" class="pp-sp-sheet__x" aria-label="Cerrar">&times;</button>' +
						'<h3 class="pp-sp-sheet__titulo"></h3>' +
						'<div class="pp-sp-sheet__fila">' +
							'<span class="pp-sp-sheet__precio"></span>' +
							'<button type="button" class="pp-sp__reservar pp-sp-sheet__reservar">' +
								'<i class="fa fa-calendar-check-o" aria-hidden="true"></i> Reservar' +
							'</button>' +
						'</div>' +
						'<div class="pp-sp-sheet__cuerpo"></div>' +
					'</div>' +
				'</div>'
			);
			$('body').append($sheet);
			return $sheet;
		}

		function abrirSheet($fila) {
			var $s = montarSheet();
			var $precioFila = $fila.find('.pp-sp__precio').first();
			$s.find('.pp-sp-sheet__titulo').text($fila.find('.pp-sp__nombre').first().text());
			$s.find('.pp-sp-sheet__precio')
				.html($precioFila.html())
				.toggleClass('pp-sp__precio--gratis', $precioFila.hasClass('pp-sp__precio--gratis'));
			$s.find('.pp-sp-sheet__cuerpo').html($fila.find('.pp-sp__datos').first().html() || '');
			$s.find('.pp-sp-sheet__reservar').toggle(abridor().length > 0);
			$s.prop('hidden', false);
			// Animación: clase en el siguiente frame para que transicione.
			requestAnimationFrame(function () { $s.addClass('abierto'); });
			$('body').addClass('pp-sp-bloqueado');
		}
		function cerrarSheet() {
			if (!$sheet) { return; }
			$sheet.removeClass('abierto');
			$('body').removeClass('pp-sp-bloqueado');
			setTimeout(function () { $sheet.prop('hidden', true); }, 220);
		}

		$(document).on('click', '.pp-sp__detalles', function () {
			abrirSheet($(this).closest('.pp-sp__fila'));
		});
		$(document).on('click', '.pp-sp-sheet__x, .pp-sp-sheet__velo', cerrarSheet);
		$(document).on('keydown', function (ev) {
			if ('Escape' === ev.key) { cerrarSheet(); }
		});

		/* ---------- Reservar ---------- */
		$(document).on('click', '.pp-sp__reservar', function () {
			cerrarSheet();
			var $a = abridor();
			if ($a.length) { $a.trigger('click'); }
		});
	});
})(jQuery);
