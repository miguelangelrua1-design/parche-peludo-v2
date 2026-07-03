<?php
/**
 * Tarjeta de producto en los listados (PLP) — Parche Peludo V2 (override del hijo).
 *
 * Basado en listeo/woocommerce/content-product.php (v9.6.0). Se conserva TAL CUAL
 * la rama de "listing_package" (planes de reserva de Listeo) y se rediseña solo la
 * tarjeta de PRODUCTO NORMAL para alta fidelidad con el diseño (Diseño/Stitch/
 * tienda_plp*.html): imagen cuadrada, título, precio (con oferta tachada) y botón
 * "Agregar" a ancho completo. Mantiene los hooks/funciones de WooCommerce, así que
 * el add-to-cart por AJAX sigue funcionando.
 *
 * PENDIENTE (requiere lógica que aún no existe — no se implementa aquí):
 *  - Corazón de "favorito" por producto.
 *  - Puntos/carrusel de galería en la tarjeta.
 *
 * @package listeo-child
 */

defined( 'ABSPATH' ) || exit;

global $product, $post;

if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}

$classes = array();

$single_buy_products = get_option( 'listeo_buy_only_once' );
$user = wp_get_current_user();
if ( $single_buy_products ) {
	if ( is_user_logged_in() && in_array( $product->get_id(), $single_buy_products ) && wc_customer_bought_product( $user->user_email, $user->ID, $product->get_id() ) ) {
		return;
	}
}
if ( $product->is_featured() ) {
	$classes[] = 'featured';
}
if ( $product->get_type() == 'listing_package' ) {
	$classes[] = 'plan';
}

if ( $product->get_type() == 'listing_package' ) { ?>

	<li <?php post_class( $classes ); ?>>
		<?php
		if ( has_post_thumbnail() ) {
			$attachment_count = count( $product->get_gallery_image_ids() );
			$gallery          = $attachment_count > 0 ? '[product-gallery]' : '';
			$props            = wc_get_product_attachment_props( get_post_thumbnail_id(), $post );
			$image            = get_the_post_thumbnail( $post->ID, apply_filters( 'single_product_large_thumbnail_size', 'shop_single' ), array(
				'title' => $props['title'],
				'alt'   => $props['alt'],
			) );
			echo apply_filters( 'woocommerce_single_product_image_html', sprintf( '%s', $image ), $post->ID );
		}
		?>
		<?php if ( $product->is_featured() ) : ?>
			<div class="listing-badge">
				<span class="featured"><?php esc_html_e( 'Featured', 'listeo' ); ?></span>
			</div>
		<?php endif; ?>
		<div class="plan-price">
			<h3><?php the_title(); ?></h3>
			<span class="value"> <?php echo wc_price( $product->get_price() ); ?></span>
			<span class="period"><?php echo wp_kses_post( $product->get_short_description() ); ?></span>
		</div>

		<div class="plan-features">
			<ul class="plan-features-auto-wc">
				<?php
				$propertieslimit = $product->get_limit();
				if ( ! $propertieslimit ) {
					echo '<li>';
					esc_html_e( 'Unlimited number of listings', 'listeo' );
					echo '</li>';
				} else { ?>
					<li>
						<?php esc_html_e( 'This plan includes ', 'listeo' ); printf( _n( '%d listing', '%s listings', $propertieslimit, 'listeo' ) . ' ', $propertieslimit ); ?>
					</li>
				<?php } ?>
				<?php if ( $product->get_duration() ) { ?>
				<li>
					<?php esc_html_e( 'Listings are visible ', 'listeo' ); printf( _n( 'for %s day', 'for %s days', $product->get_duration(), 'listeo' ), $product->get_duration() ); ?>
				</li>
				<?php } ?>
			</ul>
			<?php
			echo wp_kses_post( $product->get_description() );
			$link  = $product->add_to_cart_url();
			$label = apply_filters( 'add_to_cart_text', esc_html__( 'Add to cart', 'listeo' ) );
			?>
			<a href="<?php echo esc_url( $link ); ?>" class="button"><i class="fa fa-shopping-cart"></i> <?php echo esc_html( $label ); ?></a>
		</div>

	</li>

	<?php
} elseif ( is_shop() || is_product_taxonomy() ) {
	// -------- TIENDA (PLP): tarjeta rediseñada (alta fidelidad al diseño) --------
	$classes[] = 'regular-product';
	$classes[] = 'pp-product-card';
	?>
	<li <?php post_class( $classes ); ?>>

		<div class="pp-card-media">
			<a class="pp-card-thumb" href="<?php the_permalink(); ?>">
				<?php echo $product->get_image( 'woocommerce_thumbnail' ); // phpcs:ignore ?>
			</a>
			<?php // Favoritos: sin lógica (wishlist) — no se renderiza ningún corazón. ?>
		</div>

		<?php // Puntos de galería: implican un carrusel en la tarjeta que no existe — no se renderizan. ?>

		<div class="pp-card-body">
			<h3 class="pp-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>

			<?php if ( $price_html = $product->get_price_html() ) : ?>
				<div class="pp-card-price"><?php echo $price_html; // phpcs:ignore ?></div>
			<?php endif; ?>

			<?php
			// Botón "Agregar" (WooCommerce, con AJAX). Texto e ícono se ajustan con
			// filtros en functions.php (ppv2_loop_add_to_cart_text / _icon).
			woocommerce_template_loop_add_to_cart();
			?>
		</div>

	</li>
	<?php
} else {
	// -------- OTROS LISTADOS (Home Tienda/V2, relacionados, shortcodes): markup ORIGINAL de Listeo --------
	$classes[] = 'regular-product';
	?>
	<li <?php post_class( $classes ); ?>>

		<?php do_action( 'woocommerce_before_shop_loop_item' ); ?>

		<div class="mediaholder">
			<?php do_action( 'woocommerce_before_shop_loop_item_title' ); ?>
		</div>

		<section>
			<span class="product-category">
			<?php
				$product_cats = wp_get_post_terms( get_the_ID(), 'product_cat' );
				if ( $product_cats && ! is_wp_error( $product_cats ) ) {
					$single_cat = array_shift( $product_cats );
					echo esc_html( $single_cat->name );
				} ?>
			</span>

			<h5><a href="<?php echo get_permalink( get_the_ID() ); ?>"><?php the_title(); ?></a></h5>
			<?php do_action( 'woocommerce_after_shop_loop_item_title' ); ?>
		</section>

		<?php do_action( 'woocommerce_after_shop_loop_item' ); ?>

	</li>
<?php }
