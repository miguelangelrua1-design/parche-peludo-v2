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
 * @version 9.6.0
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
} elseif ( function_exists( 'ppv2_es_contexto_tarjeta' ) ? ppv2_es_contexto_tarjeta() : ( is_shop() || is_product_taxonomy() || is_product() ) ) {
	// -------- TIENDA (PLP) y PDP (sugeridos/upsells): tarjeta rediseñada --------
	// is_product() añadido 2026-07-11: los "Productos sugeridos" y upsells de la
	// ficha de producto usan la MISMA tarjeta que la tienda (el PDP también vive
	// dentro de .listeo-shop-grid, así que el CSS existente aplica sin cambios).
	//
	// 2026-07-25: la condición pasó a ppv2_es_contexto_tarjeta(), que además
	// cubre los loops del shortcode [products] (carruseles de Home y Home
	// Tienda). Antes caían al `else` y salía la tarjeta del tema PADRE, sin
	// pastillas de presentación y con el botón "Añadir al carrito" nativo.
	//
	// HOOKS DEL LOOP (reinsertados 2026-07-10): la tarjeta dispara los 5 hooks
	// estándar de WooCommerce para que el badge "¡Oferta!" (sale flash) y los
	// plugins de terceros (wishlist, quick-view, badges) funcionen. Los
	// handlers del CORE que duplicarían lo que la tarjeta ya pinta a su manera
	// (enlace, imagen, título, precio, rating, botón) se quitan SOLO durante
	// el disparo y se restauran después — así el diseño no cambia: lo único
	// nuevo visible es el badge de oferta.
	if ( ! function_exists( 'pp_card_do_action' ) ) {
		/**
		 * Dispara $hook sin los callbacks de core listados en $quitar.
		 * $quitar = array de nombres de función; se detecta su prioridad real
		 * (por si el tema los re-registró) y se restauran tras el do_action.
		 */
		function pp_card_do_action( $hook, $quitar = array() ) {
			$restaurar = array();
			foreach ( $quitar as $cb ) {
				$prio = has_action( $hook, $cb );
				if ( false !== $prio ) {
					remove_action( $hook, $cb, $prio );
					$restaurar[] = array( $cb, $prio );
				}
			}
			do_action( $hook );
			foreach ( $restaurar as $r ) {
				add_action( $hook, $r[0], $r[1] );
			}
		}
	}
	$classes[] = 'regular-product';
	$classes[] = 'pp-product-card';
	?>
	<li <?php post_class( $classes ); ?>>

		<?php pp_card_do_action( 'woocommerce_before_shop_loop_item', array( 'woocommerce_template_loop_product_link_open' ) ); ?>

		<div class="pp-card-media">
			<a class="pp-card-thumb" href="<?php the_permalink(); ?>">
				<?php echo $product->get_image( 'woocommerce_thumbnail' ); // phpcs:ignore ?>
			</a>
			<?php
			// Sale flash del core (badge "¡Oferta!") + badges de terceros.
			// Se quita el thumbnail del core (la tarjeta ya pinta el suyo) Y el
			// add-to-cart que LISTEO reubica aquí (inc/woocommerce.php:138 lo mueve
			// a woocommerce_before_shop_loop_item_title, prio 10): sin quitarlo salía
			// un 2º botón NEGRO sobre la imagen ("Seleccionar opciones"/"Agregar")
			// además del botón teal del cuerpo. Ahora solo queda el del cuerpo.
			pp_card_do_action( 'woocommerce_before_shop_loop_item_title', array( 'woocommerce_template_loop_product_thumbnail', 'woocommerce_template_loop_add_to_cart' ) );
			?>
			<?php // Favoritos: sin lógica (wishlist) — no se renderiza ningún corazón. ?>
		</div>

		<?php // Puntos de galería: implican un carrusel en la tarjeta que no existe — no se renderizan. ?>

		<div class="pp-card-body">
			<h3 class="pp-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
			<?php pp_card_do_action( 'woocommerce_shop_loop_item_title', array( 'woocommerce_template_loop_product_title' ) ); ?>

			<?php
			// Presentaciones como PASTILLAS (productos variables): el precio
			// mostrado es el de la presentación ACTIVA (por defecto la más
			// económica disponible; el orden lo da ppv2_card_presentaciones).
			// El clic lo maneja js/pp-plp.js: cambia precio y data-product_id
			// del botón "Agregar" — sin AJAX ni recarga.
			$pp_pres    = function_exists( 'ppv2_card_presentaciones' ) ? ppv2_card_presentaciones( $product ) : array();
			$price_html = $pp_pres ? $pp_pres[0]['price_html'] : $product->get_price_html();
			?>
			<?php if ( $price_html ) : ?>
				<div class="pp-card-price"><?php echo $price_html; // phpcs:ignore ?></div>
			<?php endif; ?>
			<?php if ( $pp_pres ) : ?>
				<div class="pp-card-pres" role="group" aria-label="<?php esc_attr_e( 'Presentaciones', 'listeo-child' ); ?>">
					<?php foreach ( $pp_pres as $pp_i => $pp_p ) : ?>
						<button type="button"
							class="pp-pres-pill<?php echo 0 === $pp_i ? ' is-active' : ''; ?>"
							data-vid="<?php echo esc_attr( $pp_p['id'] ); ?>"
							data-price="<?php echo esc_attr( $pp_p['price_html'] ); ?>"
							aria-pressed="<?php echo 0 === $pp_i ? 'true' : 'false'; ?>"><?php echo esc_html( $pp_p['label'] ); ?></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php
			// Rating y precio del core fuera (la tarjeta pinta su propio precio
			// y añadir estrellas cambiaría el diseño); terceros sí pasan.
			pp_card_do_action( 'woocommerce_after_shop_loop_item_title', array( 'woocommerce_template_loop_rating', 'woocommerce_template_loop_price' ) );
			?>

			<?php
			// Botón "Agregar" (WooCommerce, con AJAX). Texto e ícono se ajustan con
			// filtros en functions.php (ppv2_loop_add_to_cart_text / _icon).
			woocommerce_template_loop_add_to_cart();
			?>
		</div>

		<?php
		// link_close y add_to_cart del core fuera (la tarjeta no abre ese <a>
		// y el botón ya se pintó arriba); terceros sí pasan.
		pp_card_do_action( 'woocommerce_after_shop_loop_item', array( 'woocommerce_template_loop_product_link_close', 'woocommerce_template_loop_add_to_cart' ) );
		?>

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
