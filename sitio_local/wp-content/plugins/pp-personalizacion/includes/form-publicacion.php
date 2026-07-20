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
