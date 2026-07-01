<?php
/**
 * Sobrescritura del minicart de Listeo (tema hijo Parche Peludo V2).
 *
 * WordPress carga esta versión en lugar de la del tema padre porque
 * get_template_part('inc/mini-cart') busca primero en el tema hijo.
 *
 * Mantiene la MISMA estructura externa que el padre (.listeo-cart-container,
 * botón, wrapper y .listeo-mini-cart) para no romper el header, pero el interior
 * lo dibuja pp_minicart_render_inner() (en functions.php), que añade los controles
 * de cantidad y eliminar por producto. El mismo render se usa en el fragmento AJAX.
 */
if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
	return;
}

if ( ! function_exists( 'WC' ) || ! WC() || ! property_exists( WC(), 'cart' ) || is_null( WC()->cart ) ) {
	return;
}

if ( is_woocommerce_activated() && get_option( 'listeo_cart_display' ) ) : ?>
<div class="listeo-cart-container">

	<div class="mini-cart-button"><i class="fa fa-shopping-cart"></i><span class="badge"><?php echo sprintf( _n( '%d', '%d', WC()->cart->cart_contents_count, 'listeo' ), WC()->cart->cart_contents_count ); ?></span></div>
	<div class="listeo-cart-wrapper">
		<div class="listeo-mini-cart">
			<?php
			if ( function_exists( 'pp_minicart_render_inner' ) ) {
				pp_minicart_render_inner();
			}
			?>
		</div>
	</div>
</div>
<?php endif; ?>
