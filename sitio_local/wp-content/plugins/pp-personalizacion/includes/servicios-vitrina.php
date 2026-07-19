<?php
/**
 * Módulo: Vitrina "Servicios y Precios" (ficha del listado).
 *
 * Rediseña la sección de menús de precios de Listeo Core al estilo
 * Doctoralia (pedido de Miguel 2026-07-07):
 *   - Título "Servicios y Precios" (sección + pestaña del nav).
 *   - Cada servicio: título + precio + botón "Detalles" (tenue) + botón
 *     "Reservar" (protagonista, abre el flujo de reserva).
 *   - "Detalles" abre un panel deslizante desde abajo (título, precio,
 *     Reservar, descripción, X para cerrar). Sin AJAX: la descripción ya
 *     viaja en el HTML.
 *   - Varios menús → tabs con el NOMBRE DEL MENÚ que escribió el prestador
 *     (aquí es su carta de presentación; la regla "solo tipologías del
 *     catálogo" aplica únicamente dentro del popup de reserva).
 *
 * Método: override server-side de la plantilla
 * single-partials/single-listing-pricing.php vía el filtro
 * `listeo_core_template_paths` del loader Gamajo de Core (tema hijo = 1,
 * nosotros = 50, plugin = 100 → un override del tema seguiría mandando).
 * Server-side = el HTML llega pintado con el diseño nuevo: cero salto de
 * layout y cero consultas nuevas (los datos del _menu ya estaban en la
 * página). Assets solo en fichas de listado.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Registrar nuestra carpeta de plantillas en el loader de Listeo Core.
 *
 * OJO: este hook lo usan varios plugins y alguno RE-INDEXA el array
 * (array_merge destruye las claves numéricas que son la prioridad del
 * Gamajo: tema hijo=1, tema=10, plugin=100). Por eso: prioridad 999 (correr
 * de últimos, después de cualquier mangler) y clave NEGATIVA (-50): tras el
 * ksort ganamos a las rutas de Core/re-indexadas (0,1,2,...) pero seguimos
 * perdiendo ante el tema hijo (-100), que conserva la última palabra. */
add_filter( 'listeo_core_template_paths', 'pp_sv_template_path', 999 );
function pp_sv_template_path( $paths ) {
	$paths[-50] = PP_PERS_DIR . 'templates/listeo-core/';
	return $paths;
}

/* La pestaña del nav de la ficha imprime gettext 'Pricing' (que la
 * traducción del sitio renombró a "Planes y Servicios"): en fichas de
 * listado pasa a "Servicios y Precios", igual que el título de la sección. */
add_filter( 'gettext_listeo_core', 'pp_sv_renombrar_pricing', 20, 2 );
function pp_sv_renombrar_pricing( $traduccion, $texto ) {
	if ( 'Pricing' === $texto && ! is_admin() && is_singular( 'listing' ) ) {
		return 'Servicios y Precios';
	}
	return $traduccion;
}

/* Reubicación (pedido de Miguel): "Servicios y Precios" va ANTES de los
 * datos de contacto (Celular/Correo/redes), en desktop y móvil. El gancho
 * `listeo/single-listing/after-content` dispara justo entre la descripción
 * y el bloque de contacto (.listing-links-container, que el partial socials
 * pinta después) → renderizamos la sección ahí, server-side (sin saltos).
 * La invocación ORIGINAL de la plantilla (más abajo en la ficha) queda
 * anulada por la guarda de una-sola-vez dentro del override. */
add_action( 'listeo/single-listing/after-content', 'pp_sv_render_temprano', 5 );
function pp_sv_render_temprano() {
	if ( ! is_singular( 'listing' ) || ! class_exists( 'Listeo_Core_Template_Loader' ) ) {
		return;
	}
	$loader = new Listeo_Core_Template_Loader();
	$loader->get_template_part( 'single-partials/single-listing', 'pricing' );
}

/* Assets de la vitrina (tabs + panel deslizante), solo en fichas. */
add_action( 'wp_enqueue_scripts', 'pp_sv_assets', 120 );
function pp_sv_assets() {
	if ( is_admin() || ! is_singular( 'listing' ) ) {
		return;
	}
	// Sin menú activo no se pinta la sección → tampoco los assets.
	if ( ! get_post_meta( get_queried_object_id(), '_menu_status', true ) ) {
		return;
	}
	$css = PP_PERS_DIR . 'css/pp-servicios-precios.css';
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'pp-servicios-precios', PP_PERS_URL . 'css/pp-servicios-precios.css', array(), filemtime( $css ) );
	}
	$js = PP_PERS_DIR . 'js/pp-servicios-precios.js';
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'pp-servicios-precios', PP_PERS_URL . 'js/pp-servicios-precios.js', array( 'jquery' ), filemtime( $js ), true );
	}
}

/** Precio formateado como el original de Core ('' si no hay dato). */
function pp_sv_precio_html( $item ) {
	if ( ! isset( $item['price'] ) || '' === $item['price'] || 0 == $item['price'] ) {
		return esc_html__( 'Free', 'listeo_core' );
	}
	$currency_abbr    = get_option( 'listeo_currency' );
	$currency_postion = get_option( 'listeo_currency_postion' );
	$currency_symbol  = class_exists( 'Listeo_Core_Listing' ) ? Listeo_Core_Listing::get_currency_symbol( $currency_abbr ) : '$';

	$precio = $item['price'];
	if ( is_numeric( $precio ) ) {
		$decimals = get_option( 'listeo_number_decimals', 2 );
		$precio   = number_format_i18n( $precio, $decimals );
	} else {
		$precio = esc_html( $precio );
	}
	if ( 'after' === $currency_postion ) {
		return $precio . ' ' . esc_html( $currency_symbol );
	}
	return esc_html( $currency_symbol ) . ' ' . $precio;
}
