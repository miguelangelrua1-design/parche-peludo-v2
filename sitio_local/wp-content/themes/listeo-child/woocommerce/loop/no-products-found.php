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
		<div class="ppv2-no-results ppv2-nores">
			<span class="ppv2-nores-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/><path d="m8.8 8.8 4.4 4.4"/><path d="m13.2 8.8-4.4 4.4"/></svg></span>
			<h2>Sin resultados</h2>
			<?php $ppv2_q = get_search_query(); ?>
			<?php if ( $ppv2_q ) : ?>
				<p class="ppv2-nores-text">No encontramos resultados para <strong>&ldquo;<?php echo esc_html( $ppv2_q ); ?>&rdquo;</strong> en <strong>la Tienda</strong>.</p>
			<?php else : ?>
				<p class="ppv2-nores-text">No encontramos resultados que coincidan con tu búsqueda en <strong>la Tienda</strong>.</p>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<?php wc_print_notice( esc_html__( 'No products were found matching your selection.', 'woocommerce' ), 'notice' ); ?>
	<?php endif; ?>
</div>
