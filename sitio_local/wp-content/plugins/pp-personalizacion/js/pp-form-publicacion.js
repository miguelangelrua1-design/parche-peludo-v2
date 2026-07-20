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
	});
})(jQuery);
