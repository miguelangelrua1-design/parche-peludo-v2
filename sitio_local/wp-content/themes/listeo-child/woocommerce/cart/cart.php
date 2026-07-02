<?php
/**
 * Cart Page — Parche Peludo V2 (override del tema hijo)
 *
 * Estructura propia con CSS grid (divs) en lugar de la tabla de WooCommerce, para
 * NO heredar el CSS del carrito del tema padre y lograr fidelidad exacta con la
 * maqueta. Conserva TODOS los hooks/campos de WooCommerce (input cart[key][qty],
 * nonce, cupón, actualizar carrito). Los +/−/papelera actualizan en vivo (pp-cart.js).
 *
 * @package listeo-child
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' ); ?>

<form class="woocommerce-cart-form pp-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
	<?php do_action( 'woocommerce_before_cart_table' ); ?>

	<div class="pp-cart-list">

		<div class="pp-cart-head" aria-hidden="true">
			<span class="pp-col-thumb"></span>
			<span class="pp-col-name"><?php esc_html_e( 'Producto', 'listeo-child' ); ?></span>
			<span class="pp-col-price"><?php esc_html_e( 'Precio', 'listeo-child' ); ?></span>
			<span class="pp-col-qty"><?php esc_html_e( 'Cantidad', 'listeo-child' ); ?></span>
			<span class="pp-col-total"><?php esc_html_e( 'Total', 'listeo-child' ); ?></span>
		</div>

		<?php do_action( 'woocommerce_before_cart_contents' ); ?>

		<?php
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

			if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
				$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
				$qty               = (int) $cart_item['quantity'];
				$thumbnail         = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key );
				$name              = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
				?>
				<div class="pp-cart-row pp-cart-item" data-cart_item_key="<?php echo esc_attr( $cart_item_key ); ?>">

					<div class="pp-col-thumb">
					<?php
					if ( ! $product_permalink ) {
						echo wp_kses_post( $thumbnail );
					} else {
						printf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $thumbnail );
					}
					?>
					</div>

					<div class="pp-col-name">
						<?php
						if ( ! $product_permalink ) {
							echo '<span class="pp-item-name">' . wp_kses_post( $name ) . '</span>';
						} else {
							printf( '<a class="pp-item-name" href="%s">%s</a>', esc_url( $product_permalink ), wp_kses_post( $name ) );
						}
						do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );
						echo wc_get_formatted_cart_item_data( $cart_item );
						if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
							echo wp_kses_post( '<p class="backorder_notification">' . esc_html__( 'Disponible en pedido pendiente', 'listeo-child' ) . '</p>' );
						}
						?>
					</div>

					<div class="pp-col-price">
						<?php echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); ?>
					</div>

					<div class="pp-col-qty">
						<?php if ( $_product->is_sold_individually() ) : ?>
							<span class="pp-qty-fixed">1</span>
							<input type="hidden" name="cart[<?php echo esc_attr( $cart_item_key ); ?>][qty]" value="1" />
						<?php else : $max = $_product->get_max_purchase_quantity(); ?>
							<div class="pp-qty pp-cart-qty" data-cart_item_key="<?php echo esc_attr( $cart_item_key ); ?>">
								<?php if ( $qty <= 1 ) : ?>
									<button type="button" class="pp-qty-btn pp-remove" data-cart_item_key="<?php echo esc_attr( $cart_item_key ); ?>" aria-label="<?php esc_attr_e( 'Eliminar producto', 'listeo-child' ); ?>"><?php echo function_exists( 'pp_minicart_trash_svg' ) ? pp_minicart_trash_svg() : '&times;'; ?></button>
								<?php else : ?>
									<button type="button" class="pp-qty-btn pp-qty-minus" aria-label="<?php esc_attr_e( 'Disminuir cantidad', 'listeo-child' ); ?>">&minus;</button>
								<?php endif; ?>
								<input type="number" class="pp-qty-input" name="cart[<?php echo esc_attr( $cart_item_key ); ?>][qty]" value="<?php echo esc_attr( $qty ); ?>" min="0" <?php echo ( $max > 0 ? 'max="' . esc_attr( $max ) . '"' : '' ); ?> step="1" inputmode="numeric" aria-label="<?php esc_attr_e( 'Cantidad', 'listeo-child' ); ?>" />
								<button type="button" class="pp-qty-btn pp-qty-plus" aria-label="<?php esc_attr_e( 'Aumentar cantidad', 'listeo-child' ); ?>">&plus;</button>
							</div>
						<?php endif; ?>
					</div>

					<div class="pp-col-total">
						<span class="pp-total-label"><?php esc_html_e( 'Total', 'listeo-child' ); ?></span>
						<span class="pp-total-value"><?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); ?></span>
					</div>

				</div>
				<?php
			}
		}

		do_action( 'woocommerce_cart_contents' );
		do_action( 'woocommerce_after_cart_contents' );
		?>
	</div>

	<div class="pp-cart-actions">

		<?php if ( wc_coupons_enabled() ) { ?>
			<div class="coupon pp-cart-coupon">
				<label class="pp-coupon-label" for="coupon_code"><?php esc_html_e( '¿Tienes un cupón?', 'listeo-child' ); ?></label>
				<div class="pp-coupon-row">
					<input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="<?php esc_attr_e( 'Código de cupón', 'listeo-child' ); ?>" />
					<button type="submit" class="button pp-btn-coupon" name="apply_coupon" value="<?php esc_attr_e( 'Aplicar cupón', 'listeo-child' ); ?>"><?php esc_html_e( 'Aplicar cupón', 'listeo-child' ); ?></button>
				</div>
				<?php do_action( 'woocommerce_cart_coupon' ); ?>
			</div>
		<?php } ?>

		<button type="submit" class="button pp-btn-update" name="update_cart" value="<?php esc_attr_e( 'Actualizar carrito', 'listeo-child' ); ?>"><?php esc_html_e( 'Actualizar carrito', 'listeo-child' ); ?></button>

		<?php do_action( 'woocommerce_cart_actions' ); ?>

		<?php wp_nonce_field( 'woocommerce-cart' ); ?>
	</div>

	<?php do_action( 'woocommerce_after_cart_table' ); ?>

</form>

<div class="cart-collaterals">
	<?php do_action( 'woocommerce_cart_collaterals' ); ?>
</div>

<?php do_action( 'woocommerce_after_cart' ); ?>
