/**
 * Pastillas de presentación en las tarjetas de producto (PLP y sugeridos).
 *
 * Las pastillas vienen pintadas del servidor (content-product.php) con la más
 * ECONÓMICA activa. Un clic:
 *   1. activa la pastilla,
 *   2. muestra el precio de esa presentación (data-price, ya formateado),
 *   3. apunta el botón "Agregar" a esa variación (data-product_id) — el alta
 *      real la hace el AJAX NATIVO de WooCommerce (wc-add-to-cart.js),
 *   4. si el botón estaba en estado "agregado" (check + enlace "Ver carrito"
 *      que añade Woo), lo regresa a "Agregar" limpio para la nueva selección.
 *
 * Sin AJAX propio ni recargas: puro DOM. Delegado para sobrevivir a grids
 * repintados (filtros, paginación AJAX).
 */
(function ($) {
	'use strict';

	$(document).on('click', '.pp-product-card .pp-pres-pill', function (e) {
		e.preventDefault();
		var $pill = $(this);
		if ($pill.hasClass('is-active')) { return; }

		var $card = $pill.closest('.pp-product-card');

		// 1) Activa la pastilla (y accesibilidad aria-pressed).
		$card.find('.pp-pres-pill').removeClass('is-active').attr('aria-pressed', 'false');
		$pill.addClass('is-active').attr('aria-pressed', 'true');

		// 2) Precio de la presentación seleccionada.
		$card.find('.pp-card-price').first().html($pill.attr('data-price'));

		// 3) El botón "Agregar" ahora agrega ESTA variación. Se actualiza tanto
		//    el atributo como el caché .data() de jQuery (wc-add-to-cart.js lee
		//    .data('product_id') y jQuery no relee el atributo si ya hay caché).
		var vid  = $pill.attr('data-vid');
		var $btn = $card.find('a.add_to_cart_button').first();
		$btn.attr('data-product_id', vid).data('product_id', vid);

		// 4) Estado "agregado" de una selección anterior → volver a "Agregar".
		$btn.removeClass('added loading');
		$card.find('a.added_to_cart').remove();
	});
})(jQuery);
