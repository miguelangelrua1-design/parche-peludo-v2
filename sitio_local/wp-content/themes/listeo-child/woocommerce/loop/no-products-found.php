<?php
/**
 * OVERRIDE del tema hijo — "sin resultados" de la tienda.
 * Copiada de woocommerce/templates/loop/no-products-found.php (@version 7.8.0).
 *
 * En BÚSQUEDAS muestra el mensaje de marca con la sección ("Tienda"), con el
 * mismo formato que el "sin resultados" del directorio (override
 * listeo-core/archive/no-found.php). En vistas sin búsqueda (p. ej. una
 * categoría vacía) se conserva el aviso nativo de WooCommerce.
 * El puente Tienda→Directorio (ppv2_cross_search_banner_tienda) se imprime
 * después, vía el hook woocommerce_no_products_found (prioridad 20).
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="woocommerce-no-products-found">
	<?php if ( is_search() ) : ?>
		<div class="ppv2-no-results">
			<h2>Sin resultados</h2>
			<p>Lo sentimos, no encontramos resultados que coincidan con tu búsqueda en la sección &ldquo;Tienda&rdquo;</p>
		</div>
	<?php else : ?>
		<?php wc_print_notice( esc_html__( 'No products were found matching your selection.', 'woocommerce' ), 'notice' ); ?>
	<?php endif; ?>
</div>
