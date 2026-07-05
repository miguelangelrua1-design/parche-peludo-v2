<?php
/**
 * Módulo SERVICIOS del plugin Personalización Parche.
 *
 * Personalización 1 (2026-07-04) — "Elige tu servicio":
 * En listados con servicios INDIVIDUALES (Service Constraints de Booking
 * Plus), el acordeón de servicios del popup cambia su etiqueta base a
 * "Elige tu servicio" y, al seleccionar un individual, muestra el NOMBRE
 * del servicio elegido (Booking Plus solo lo hace en el widget clásico,
 * no en su popup).
 *
 * Personalización 2 (2026-07-04) — Tabs por tipo de servicio:
 * Cuando el listado tiene VARIOS menús con servicios reservables (p. ej.
 * "Adiestramiento" y "Fotografía y Video"), el popup muestra pestañas con
 * los títulos de los menús; al elegir una se habilita la lista de
 * servicios de ese tipo. Con un solo menú no aparecen pestañas.
 * Decisión de producto (Miguel 2026-07-04): se mantiene UN solo servicio
 * principal (individual) por reserva, sin importar el tipo; los extras
 * se acumulan libremente.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** ¿La página actual es un listado con servicios individuales configurados? */
function pp_serv_listado_con_individuales() {
	if ( is_admin() || ! is_singular( 'listing' ) ) {
		return false;
	}
	if ( ! class_exists( 'LBP_Service_Constraints' ) || ! method_exists( 'LBP_Service_Constraints', 'listing_has_individuals' ) ) {
		return false;
	}
	$id = get_queried_object_id();
	return $id && LBP_Service_Constraints::listing_has_individuals( $id );
}

/**
 * ¿La página actual es un listado con AL MENOS un servicio reservable
 * (haya o no servicios individuales)? En Parche Peludo el cliente viene a
 * contratar un servicio, así que el acordeón se rotula "Elige tu servicio"
 * siempre que haya servicios, no solo cuando hay individuales.
 */
function pp_serv_listado_con_servicios() {
	if ( is_admin() || ! is_singular( 'listing' ) ) {
		return false;
	}
	$id = get_queried_object_id();
	return $id && count( pp_serv_menus_reservables( $id ) ) > 0;
}

/**
 * Menús del listado que tienen servicios reservables, como
 * [ { titulo, slugs: [slug-de-servicio, …] }, … ].
 * El slug replica el de Booking Plus: sanitize_title(nombre del servicio).
 */
function pp_serv_menus_reservables( $listing_id ) {
	$menu   = get_post_meta( $listing_id, '_menu', true );
	$grupos = array();
	if ( ! is_array( $menu ) ) {
		return $grupos;
	}
	foreach ( $menu as $g ) {
		$slugs = array();
		foreach ( (array) ( $g['menu_elements'] ?? array() ) as $el ) {
			if ( ! empty( $el['bookable'] ) && 'on' === $el['bookable'] && ! empty( $el['name'] ) ) {
				$slugs[] = sanitize_title( (string) $el['name'] );
			}
		}
		if ( $slugs ) {
			$grupos[] = array(
				'titulo' => sanitize_text_field( $g['menu_title'] ?? '' ) ?: 'Servicios',
				'slugs'  => $slugs,
			);
		}
	}
	return $grupos;
}

/* ---- Personalización 1a: etiqueta base del acordeón ---- */
add_filter( 'gettext_listeo-booking-plus', 'pp_serv_etiqueta_servicios', 20, 2 );
function pp_serv_etiqueta_servicios( $traduccion, $texto ) {
	// "Elige tu servicio" siempre que el listado ofrezca servicios
	// reservables, tenga o no servicios individuales marcados.
	if ( 'Extra Services' === $texto && pp_serv_listado_con_servicios() ) {
		return 'Elige tu servicio';
	}
	return $traduccion;
}

/* ---- Assets del módulo (etiqueta dinámica + tabs) ---- */
add_action( 'wp_enqueue_scripts', 'pp_serv_assets', 120 );
function pp_serv_assets() {
	if ( is_admin() || ! is_singular( 'listing' ) ) {
		return;
	}

	$listing_id   = get_queried_object_id();
	$menus        = pp_serv_menus_reservables( $listing_id );
	$individuales = pp_serv_listado_con_individuales();

	// Solo cargar si hay algo que hacer: etiqueta dinámica o tabs.
	if ( ! $individuales && count( $menus ) < 2 ) {
		return;
	}

	$css = PP_PERS_DIR . 'css/pp-servicios-reserva.css';
	if ( count( $menus ) > 1 && file_exists( $css ) ) {
		wp_enqueue_style( 'pp-servicios-reserva', PP_PERS_URL . 'css/pp-servicios-reserva.css', array(), filemtime( $css ) );
	}

	$js = PP_PERS_DIR . 'js/pp-servicios-reserva.js';
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'pp-servicios-reserva', PP_PERS_URL . 'js/pp-servicios-reserva.js', array( 'jquery' ), filemtime( $js ), true );
		wp_localize_script( 'pp-servicios-reserva', 'PP_SERV', array(
			'menus' => $menus,
		) );
	}
}
