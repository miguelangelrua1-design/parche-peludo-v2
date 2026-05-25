<?php
/**
 * Listeo Child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package listeo-child
 */

function listeo_child_enqueue_styles() {
    // Cargar estilo del tema padre
    wp_enqueue_style( 'listeo-parent-style', get_template_directory_uri() . '/style.css' );

    // Cargar estilo del tema hijo (hereda y sobrescribe con tokens de marca V2)
    wp_enqueue_style( 'listeo-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array( 'listeo-parent-style' ),
        wp_get_theme()->get('Version')
    );
}
add_action( 'wp_enqueue_scripts', 'listeo_child_enqueue_styles', 99 );

/**
 * Personalizaciones y ganchos adicionales de Parche Peludo V2
 * Agrega aqui funciones personalizadas para integraciones seguras.
 */

/**
 * Tienda V2 (Home): añade la etiqueta de categoría a cada producto y habilita
 * el filtrado por categoría en vivo (pills .ppv2-shop-filter) sobre el shortcode
 * nativo [products] dentro de .ppv2-shop-products. Scoped: solo actúa si existe
 * la sección en la página, no afecta el resto del sitio.
 */
function ppv2_shop_filter_script() {
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		var shop = document.querySelector('.ppv2-shop-products');
		if (!shop) return;

		// 1) CTA "Ver más" (enlaza a la ficha del producto) movido al final de la tarjeta.
		//    La etiqueta de categoría ya la imprime Listeo (span.product-category).
		shop.querySelectorAll('li.product').forEach(function (li) {
			var link = li.querySelector('a.woocommerce-LoopProduct-link');
			var btn = li.querySelector('a.button');
			if (btn) {
				btn.textContent = 'Ver más';
				if (link && link.href) { btn.href = link.href; }
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
				li.style.display = show ? '' : 'none';
			});
		}, true);
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'ppv2_shop_filter_script', 99 );
