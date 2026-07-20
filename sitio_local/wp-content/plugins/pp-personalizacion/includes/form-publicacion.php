<?php
/**
 * Módulo: Quick wins del formulario "Agregar Publicación" (auditoría 2026-07).
 *
 * Mejoras de experiencia SIN tocar el flujo nativo de Listeo (todo por
 * capas: JS con guardas + gettext). Si algún selector no existe, no pasa
 * nada — el formulario sigue idéntico.
 *
 *  QW1  Aviso bajo el toggle "Precios y Servicios Reservables": sin
 *       activarlo no hay botón Reservar, ni vitrina, ni búsqueda por
 *       disponibilidad (el hueco de conversión nº 1 de la auditoría).
 *  QW2  El campo "Categoría" GLOBAL se oculta cuando el tipo ya tiene su
 *       propio campo de categorías (dos selectores de categoría confundían).
 *       Condicional: si el tipo NO tiene categoría propia, la global queda.
 *  QW3  Place ID / Longitud / Latitud tras un plegable "Opciones avanzadas"
 *       (el mapa los sigue llenando solo; solo se esconden de la vista).
 *  +    Guía breve en Galería (las fotos venden).
 *  QW5  Mensajes post-envío claros (gettext, frontend).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---- Assets: SOLO en la página del formulario (crear y editar) ---- */
add_action( 'wp_enqueue_scripts', 'pp_fp_assets', 125 );
function pp_fp_assets() {
	if ( is_admin() || ! is_user_logged_in() ) {
		return;
	}
	$submit_page = (int) get_option( 'listeo_submit_page' );
	if ( ! $submit_page || ! is_page( $submit_page ) ) {
		return;
	}
	$css = PP_PERS_DIR . 'css/pp-form-publicacion.css';
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'pp-form-publicacion', PP_PERS_URL . 'css/pp-form-publicacion.css', array(), filemtime( $css ) );
	}
	$js = PP_PERS_DIR . 'js/pp-form-publicacion.js';
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'pp-form-publicacion', PP_PERS_URL . 'js/pp-form-publicacion.js', array( 'jquery' ), filemtime( $js ), true );
	}
}

/* ---- M3: dirección obligatoria por tipo (server, fuente de verdad) ----
 * Un negocio físico sin dirección no aparece en el mapa ni le sirve al
 * cliente. Se exige SOLO en los tipos donde tiene sentido; el resto
 * (Adopción, Mascotas perdidas…) queda igual. El aviso amable del cliente
 * vive en el JS; esta validación es la garantía real. */
function pp_fp_tipos_con_direccion() {
	return apply_filters( 'pp_fp_tipos_con_direccion', array( 'directorio', 'guarderia' ) );
}

add_filter( 'submit_listing_form_validate_fields', 'pp_fp_validar_direccion', 25, 3 );
function pp_fp_validar_direccion( $valido, $fields, $values ) {
	if ( is_wp_error( $valido ) ) {
		return $valido;
	}
	$tipo = isset( $_POST['_listing_type'] ) ? sanitize_key( wp_unslash( $_POST['_listing_type'] ) ) : '';
	if ( ! $tipo || ! in_array( $tipo, pp_fp_tipos_con_direccion(), true ) ) {
		return $valido;
	}
	$direccion = isset( $_POST['_address'] ) ? trim( (string) wp_unslash( $_POST['_address'] ) ) : '';
	if ( '' === $direccion ) {
		return new WP_Error(
			'pp_fp_direccion',
			'Escribe la dirección de tu negocio (sección Ubicación): sin ella no apareces en el mapa ni te encuentran los clientes.'
		);
	}
	return $valido;
}

/* ---- QW5: mensajes post-envío claros (solo frontend) ---- */
add_filter( 'gettext_listeo_core', 'pp_fp_mensajes_envio', 20, 2 );
function pp_fp_mensajes_envio( $traduccion, $texto ) {
	if ( is_admin() ) {
		return $traduccion;
	}
	switch ( $texto ) {
		case 'Thanks for your submission!':
			return '¡Gracias! Recibimos tu publicación 🐾';
		case 'Your listing has been saved and is awaiting admin approval':
			return 'Nuestro equipo la está revisando para asegurar la calidad del directorio. Estará visible muy pronto.';
		case 'Your changes have been saved.':
			return 'Tus cambios fueron guardados y están en revisión. Muy pronto se verán reflejados.';
	}
	return $traduccion;
}
