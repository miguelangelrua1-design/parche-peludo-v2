<?php
/**
 * Related Products — "Productos sugeridos" (override del hijo, Parche Peludo V2).
 *
 * Copia FIEL de la plantilla nativa del plugin (plugins/woocommerce/templates/
 * single-product/related.php, @version 10.3.0). Se sobrescribe en el HIJO
 * porque la del padre (listeo/woocommerce/single-product/related.php) tiene el
 * título "Related Products" en duro y NO pasa por el filtro nativo
 * woocommerce_product_related_products_heading, que usamos en functions.php
 * para llamarla "Productos sugeridos". Sin más cambios: las tarjetas salen de
 * content-product.php (rediseñado) y el grid/carrusel lo pone el CSS
 * ("PPV2 — PDP PRODUCTO" en style.css).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package listeo-child
 * @version 10.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( $related_products ) :
	/**
	 * Ensure all images of related products are lazy loaded by increasing the
	 * current media count to WordPress's lazy loading threshold if needed.
	 * Because wp_increase_content_media_count() is a private function, we
	 * check for its existence before use.
	 */
	if ( function_exists( 'wp_increase_content_media_count' ) ) {
		$content_media_count = wp_increase_content_media_count( 0 );
		if ( $content_media_count < wp_omit_loading_attr_threshold() ) {
			wp_increase_content_media_count( wp_omit_loading_attr_threshold() - $content_media_count );
		}
	}
	?>

	<section class="related products">

		<?php
		$heading = apply_filters( 'woocommerce_product_related_products_heading', __( 'Related products', 'woocommerce' ) );

		if ( $heading ) :
			?>
			<h2><?php echo esc_html( $heading ); ?></h2>
		<?php endif; ?>
		<?php woocommerce_product_loop_start(); ?>

			<?php foreach ( $related_products as $related_product ) : ?>

					<?php
					$post_object = get_post( $related_product->get_id() );

					setup_postdata( $GLOBALS['post'] = $post_object ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, Squiz.PHP.DisallowMultipleAssignments.Found

					wc_get_template_part( 'content', 'product' );
					?>

			<?php endforeach; ?>

		<?php woocommerce_product_loop_end(); ?>

	</section>
	<?php
endif;

wp_reset_postdata();
