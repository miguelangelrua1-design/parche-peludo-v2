// Migrado de functions.php::ppv2_shop_filter_script() 2026-07-10 (wp_footer, prio 99).
// Condición original: if ( ! is_page( array( 1555, 1610 ) ) && ! is_front_page() ) return; — solo Home V2 (1555), Home Tienda (1610) y portada.
	document.addEventListener('DOMContentLoaded', function () {
		var shop = document.querySelector('.ppv2-shop-products');
		if (!shop) return;

		// 1) CTA "Ver más" (enlaza a la ficha del producto) movido al final de la tarjeta.
		//    La etiqueta de categoría ya la imprime Listeo (span.product-category).
		//
		//    2026-07-25: SOLO para la tarjeta antigua del tema padre. Desde que los
		//    carruseles usan la MISMA tarjeta del PLP (.pp-product-card), esta ya
		//    trae su propio botón "Agregar" funcional —con la variación de la
		//    pastilla seleccionada en data-product_id— y reescribirlo a "Ver más"
		//    le quitaba el add-to-cart. Si la tarjeta es la nueva, no se toca.
		shop.querySelectorAll('li.product').forEach(function (li) {
			if (li.classList.contains('pp-product-card')) { return; }
			var link = li.querySelector('a.woocommerce-LoopProduct-link');
			var btn = li.querySelector('a.button');
			if (btn) {
				btn.textContent = 'Ver más';
				if (link && link.href) {
					btn.href = link.href;
				} else {
					// Sin enlace a la ficha: neutralizar el href "?add-to-cart=ID"
					// para que "Ver más" no añada el producto al carrito.
					btn.removeAttribute('href');
				}
				btn.classList.remove('add_to_cart_button', 'ajax_add_to_cart');
				btn.removeAttribute('data-quantity');
				btn.removeAttribute('data-product_id');
				li.appendChild(btn); // mover el botón al pie de la tarjeta
			}
		});

		// 2) Filtrado en vivo con las pills de categoría.
		//    Delegación en fase de captura: se ejecuta antes de cualquier
		//    handler que detenga la propagación en el <a> del botón Elementor.
		document.addEventListener('click', function (e) {
			var pill = e.target.closest ? e.target.closest('.ppv2-shop-filter') : null;
			if (!pill) return;
			e.preventDefault();
			e.stopPropagation();
			var cls = pill.className.match(/ppv2-filter-([a-z0-9\-]+)/);
			var cat = cls ? cls[1] : 'all';
			document.querySelectorAll('.ppv2-shop-filter').forEach(function (x) { x.classList.remove('is-active'); });
			pill.classList.add('is-active');
			shop.querySelectorAll('li.product').forEach(function (li) {
				var show = (cat === 'all') || li.classList.contains('product_cat-' + cat);
				// Clase (no estilo en línea): el CSS oculta con !important y mayor
				// especificidad para ganar a la regla base display:flex !important del carrusel.
				li.classList.toggle('ppv2-hidden', !show);
			});
		}, true);
	});
