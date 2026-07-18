<?php
/**
 * Vitrina "Servicios y Precios" — override de Personalización Parche sobre
 * single-partials/single-listing-pricing.php de Listeo Core (v2.0.47).
 *
 * Conserva TODAS las guardas y datos del original (menu_status,
 * hide_pricing_if_bookable, show_title, hook listeo_pricing_menu_item_meta
 * para la duración de Booking Plus) y cambia la presentación: filas
 * título+precio con botones "Detalles" y "Reservar", panel deslizante con
 * la descripción, y tabs por NOMBRE de menú cuando hay varios.
 * Un override en el tema hijo (listeo-core/single-partials/...) nos pisa.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! empty( $data ) && isset( $data->show_title ) ) {
	$show_title = $data->show_title;
} else {
	$show_title = true;
}

$_menu_status = get_post_meta( get_the_ID(), '_menu_status', true );
if ( ! $_menu_status ) {
	return;
}
$_bookable_show_menu = get_post_meta( get_the_ID(), '_hide_pricing_if_bookable', true );
if ( ! empty( $_bookable_show_menu ) ) {
	return;
}
$_menu = get_post_meta( get_the_ID(), '_menu', 1 );
if ( ! is_array( $_menu ) ) {
	return;
}
if ( ! isset( $_menu[0]['menu_elements'][0]['name'] ) || empty( $_menu[0]['menu_elements'][0]['name'] ) ) {
	return;
}

// Grupos con elementos (los vacíos no pintan tab ni lista).
$grupos = array();
foreach ( $_menu as $menu ) {
	if ( isset( $menu['menu_elements'] ) && ! empty( $menu['menu_elements'] ) ) {
		$grupos[] = $menu;
	}
}
if ( ! $grupos ) {
	return;
}
$con_tabs = count( $grupos ) > 1;
?>

<!-- Servicios y Precios (vitrina Personalización Parche) -->
<div id="listing-pricing-list" class="listing-section pp-sp">
	<?php if ( $show_title ) : ?>
		<h2 class="listing-desc-headline margin-top-70 margin-bottom-30">Servicios y Precios</h2>
	<?php endif; ?>

	<?php if ( $con_tabs ) : ?>
		<div class="pp-sp__tabs" role="tablist">
			<?php foreach ( $grupos as $i => $menu ) :
				$titulo = isset( $menu['menu_title'] ) && '' !== trim( (string) $menu['menu_title'] )
					? $menu['menu_title']
					: 'Servicios'; ?>
				<button type="button" class="pp-sp__tab<?php echo 0 === $i ? ' activo' : ''; ?>" role="tab" data-i="<?php echo esc_attr( $i ); ?>">
					<?php echo esc_html( $titulo ); ?>
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="pricing-list-container pp-sp__contenedor<?php if ( defined( 'LBP_VERSION' ) ) echo ' lbp-active'; ?>">
		<?php foreach ( $grupos as $i => $menu ) : ?>
			<div class="pp-sp__grupo" data-i="<?php echo esc_attr( $i ); ?>"<?php echo ( $con_tabs && 0 !== $i ) ? ' hidden' : ''; ?>>
				<ul class="pp-sp__lista">
					<?php foreach ( $menu['menu_elements'] as $item ) :
						if ( ! isset( $item['name'] ) || '' === trim( (string) $item['name'] ) ) {
							continue;
						}
						$precio    = function_exists( 'pp_sv_precio_html' ) ? pp_sv_precio_html( $item ) : '';
						$es_gratis = ! isset( $item['price'] ) || '' === $item['price'] || 0 == $item['price'];
						$desc      = isset( $item['description'] ) && '' !== trim( (string) $item['description'] ) ? $item['description'] : '';
						$img_url   = '';
						if ( isset( $item['cover'] ) && ! empty( $item['cover'] ) ) {
							$img = wp_get_attachment_image_src( $item['cover'], 'listeo-gallery' );
							if ( $img ) {
								$img_url = $img[0];
							}
						}
						?>
						<li class="pp-sp__fila">
							<h5 class="pp-sp__nombre"><?php echo esc_html( $item['name'] ); ?></h5>
							<?php
							// Booking Plus pinta aquí la duración del servicio.
							do_action( 'listeo_pricing_menu_item_meta', $item );
							?>
							<div class="pp-sp__sub">
								<span class="pp-sp__precio<?php echo $es_gratis ? ' pp-sp__precio--gratis' : ''; ?>">
									<?php echo wp_kses_post( $precio ); ?>
								</span>
								<?php if ( $desc || $img_url ) : ?>
									<button type="button" class="pp-sp__detalles">Detalles</button>
								<?php endif; ?>
								<button type="button" class="pp-sp__reservar">
									<i class="fa fa-calendar-check-o" aria-hidden="true"></i> Reservar
								</button>
							</div>
							<?php if ( $desc || $img_url ) : ?>
								<div class="pp-sp__datos" hidden>
									<?php if ( $img_url ) : ?>
										<img class="pp-sp__datos-img" src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>">
									<?php endif; ?>
									<div class="pp-sp__datos-desc"><?php echo wp_kses_post( wpautop( $desc ) ); ?></div>
								</div>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endforeach; ?>
	</div>
</div>
