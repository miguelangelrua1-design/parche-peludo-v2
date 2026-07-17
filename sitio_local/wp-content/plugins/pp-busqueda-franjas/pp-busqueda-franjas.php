<?php
/**
 * Plugin Name: Filtros personalizados (v1.8.6)
 * Plugin URI:  https://parchepeludo.com
 * Description: Filtros propios de Parche Peludo sobre los resultados de listados (Listeo). Hoy: panel de disponibilidad por servicio ("Búsqueda por franjas") que reemplaza el carrusel de categorías del cabezote — el usuario elige servicio y franja (fecha+hora) o estadía (entrada-salida) y, SOLO al pulsar "Buscar disponibilidad", se filtran los listados con disponibilidad. Motor por consultas agrupadas (sin N+1) y caché con invalidación al crear/cancelar reservas.
 * Version:     1.8.6
 * Author:      Parche Peludo
 * Text Domain: pp-busqueda-franjas
 *
 * Historia: nació como "Búsqueda por franjas" (v1.0.0) y en v1.1.0 se
 * renombró a "Filtros personalizados" (decisión de Miguel 2026-07-11).
 * La carpeta y los prefijos internos (pp-busqueda-franjas / pp_franjas_*)
 * se CONSERVAN a propósito: renombrarlos desactivaría el plugin y
 * rompería opciones ya guardadas.
 *
 * Requiere el tema Listeo + plugin listeo-core. Convive con el módulo
 * Listados de pp-personalizacion (separar resultados por tipo): ambos
 * actúan sobre las mismas queries vía pre_get_posts sin pisarse.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PP_FRANJAS_VERSION', '1.8.6' );
define( 'PP_FRANJAS_DIR', plugin_dir_path( __FILE__ ) );
define( 'PP_FRANJAS_URL', plugin_dir_url( __FILE__ ) );

require_once PP_FRANJAS_DIR . 'includes/motor.php';
require_once PP_FRANJAS_DIR . 'includes/indice.php';
require_once PP_FRANJAS_DIR . 'includes/query.php';
require_once PP_FRANJAS_DIR . 'includes/frontend.php';
require_once PP_FRANJAS_DIR . 'includes/admin.php';

/* Valores por defecto al activar (solo si no existen). */
register_activation_hook( __FILE__, 'pp_franjas_activar' );
function pp_franjas_activar() {
	add_option( 'pp_franjas_activo', 1 );
	add_option( 'pp_franjas_hora_min', '08:00' );
	add_option( 'pp_franjas_paso', 30 );
	add_option( 'pp_franjas_incluir_date_range', 1 );
	add_option( 'pp_franjas_cache_ver', 1 );
	add_option( 'pp_franjas_reemplazar_carrusel', 1 );
}

/** ¿El panel reemplaza el carrusel de categorías del cabezote? */
function pp_franjas_reemplaza_carrusel() {
	return pp_franjas_esta_activo() && (bool) get_option( 'pp_franjas_reemplazar_carrusel', 1 );
}

/* -------------------------------------------------------------------------
 * Reemplazo del carrusel de categorías del cabezote (2026-07-11)
 *
 * El carrusel (#categorySlider) lo pinta template-split-map-sidebar.php SOLO
 * si la opción `pp_listings_split-categories-slider-options` está en un modo
 * "show_*". Forzándola a 'hide' el tema NO genera el markup NI encola su JS:
 * el reemplazo es limpio y 100 % reversible (desactivar el plugin o apagar
 * el toggle devuelve el carrusel). El botón "Mostrar Filtros" no se toca.
 * ---------------------------------------------------------------------- */
add_filter( 'pre_option_pp_listings_split-categories-slider-options', 'pp_franjas_ocultar_carrusel_opcion' );
function pp_franjas_ocultar_carrusel_opcion( $valor ) {
	// Solo donde el panel APLICA (Directorio/Guardería). En los contextos de
	// Adopción / Mascotas Perdidas el carrusel del tema sigue: ahí no hay
	// panel y el módulo Separar-resultados pinta sus categorías en él.
	if ( ! is_admin() && pp_franjas_reemplaza_carrusel() && pp_franjas_contexto_aplica() ) {
		return 'hide';
	}
	return $valor; // false = seguir con el valor real de la opción
}

/** ¿El filtro está activo en el admin? */
function pp_franjas_esta_activo() {
	return (bool) get_option( 'pp_franjas_activo', 0 );
}
