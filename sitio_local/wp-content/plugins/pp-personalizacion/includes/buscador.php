<?php
/**
 * MÓDULO BUSCADOR — bootstrap
 * -----------------------------------------------------------------------------
 * Motor de búsqueda de Parche Peludo: registro de búsquedas, ranking de
 * relevancia, sinónimos administrables, "¿Quisiste decir…?", categorías en las
 * sugerencias y redirecciones. Plan completo en el workspace:
 * PLAN-EVOLUCION-BUSCADOR.md.
 *
 * REPARTO DE RESPONSABILIDADES (importante para no duplicar lógica):
 *  - Este módulo (plugin) decide QUÉ se encuentra y en qué ORDEN.
 *  - El tema hijo decide CÓMO SE VE (tabs, desplegable, plantillas).
 *  - La NORMALIZACIÓN (puntuación, tildes, plurales) vive en el tema hijo
 *    (inc/pp-normalizacion-buscador.php) porque llegó antes; este módulo se
 *    engancha a ella con el filtro 'ppv2_search_variantes_palabra' para
 *    aportar los sinónimos, en vez de competir por el hook 'posts_search'.
 *
 * Todo el módulo es apagable: interruptor maestro + uno por función, desde
 * wp-admin → Personalización Parche → Buscador → Ajustes.
 *
 * @package pp-personalizacion
 * @since   3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PP_BUSCADOR_VERSION', '1.0.0' );
define( 'PP_BUSCADOR_TABLA', 'pp_busquedas' );

/* -------------------------------------------------------------------------
 * Interruptores
 * ---------------------------------------------------------------------- */

/**
 * Ajustes del módulo con sus valores por defecto.
 *
 * @return array<string,string> clave => etiqueta legible.
 */
function pp_buscador_ajustes_disponibles() {
	return array(
		'pp_buscador_activo'          => 'Módulo Buscador (interruptor maestro)',
		'pp_buscador_registro'        => 'Registrar búsquedas (para estadísticas)',
		'pp_buscador_ranking'         => 'Ordenar resultados por relevancia',
		'pp_buscador_sinonimos'       => 'Aplicar diccionario de sinónimos',
		'pp_buscador_quisiste_decir'  => 'Sugerir corrección cuando no hay resultados',
		'pp_buscador_populares'       => 'Mostrar búsquedas populares al enfocar el campo',
		'pp_buscador_categorias'      => 'Sugerir categorías en el desplegable',
		'pp_buscador_redirecciones'   => 'Aplicar redirecciones de búsqueda',
	);
}

/**
 * ¿Está activa una función del módulo?
 *
 * El interruptor maestro apaga todo. Por defecto todo viene ACTIVO salvo que
 * se guarde lo contrario. Además cada función respeta un filtro homónimo, para
 * poder apagarla por código sin tocar la base de datos.
 *
 * @param string $clave Ajuste (sin comprobar el maestro si es el propio maestro).
 * @return bool
 */
function pp_buscador_activo( $clave = 'pp_buscador_activo' ) {
	if ( defined( 'PP_BUSCADOR_OFF' ) && PP_BUSCADOR_OFF ) {
		return false;
	}
	if ( 'pp_buscador_activo' !== $clave && ! pp_buscador_activo() ) {
		return false;
	}
	$valor = get_option( $clave, '1' );
	return (bool) apply_filters( $clave . '_enabled', '1' === (string) $valor );
}

/* -------------------------------------------------------------------------
 * Tabla de búsquedas
 * ---------------------------------------------------------------------- */

/** Nombre completo (con prefijo) de la tabla de búsquedas. */
function pp_buscador_tabla() {
	global $wpdb;
	return $wpdb->prefix . PP_BUSCADOR_TABLA;
}

/**
 * Crea/actualiza la tabla del registro de búsquedas.
 *
 * Se ejecuta al activar el plugin y también en 'admin_init' si cambió la
 * versión (el plugin se instala copiando archivos, así que no siempre pasa
 * por el hook de activación).
 *
 * Sin datos personales: no se guarda IP ni usuario, solo el término, el
 * ámbito, cuántos resultados dio y cuándo.
 */
function pp_buscador_crear_tabla() {
	global $wpdb;
	$tabla   = pp_buscador_tabla();
	$collate = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$sql = "CREATE TABLE {$tabla} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		termino VARCHAR(191) NOT NULL DEFAULT '',
		termino_norm VARCHAR(191) NOT NULL DEFAULT '',
		ambito VARCHAR(40) NOT NULL DEFAULT '',
		resultados INT NOT NULL DEFAULT 0,
		origen VARCHAR(30) NOT NULL DEFAULT 'resultados',
		fecha DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		KEY idx_norm (termino_norm),
		KEY idx_fecha (fecha),
		KEY idx_resultados (resultados)
	) {$collate};";

	dbDelta( $sql );
	update_option( 'pp_buscador_db_version', PP_BUSCADOR_VERSION );
}

add_action( 'admin_init', 'pp_buscador_verificar_tabla' );
function pp_buscador_verificar_tabla() {
	if ( get_option( 'pp_buscador_db_version' ) !== PP_BUSCADOR_VERSION ) {
		pp_buscador_crear_tabla();
	}
}

/* -------------------------------------------------------------------------
 * Purga automática del registro (retención 180 días)
 * ---------------------------------------------------------------------- */

add_action( 'init', 'pp_buscador_programar_purga' );
function pp_buscador_programar_purga() {
	if ( ! wp_next_scheduled( 'pp_buscador_purga_diaria' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'pp_buscador_purga_diaria' );
	}
}

add_action( 'pp_buscador_purga_diaria', 'pp_buscador_purgar_registro' );
function pp_buscador_purgar_registro() {
	global $wpdb;
	$dias  = (int) apply_filters( 'pp_buscador_retencion_dias', 180 );
	$tabla = pp_buscador_tabla();
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$tabla} WHERE fecha < DATE_SUB(NOW(), INTERVAL %d DAY)", $dias ) );

	// Aprovechamos el cron diario para refrescar el índice de palabras
	// que alimenta el "¿Quisiste decir…?".
	if ( function_exists( 'pp_buscador_reconstruir_indice_palabras' ) ) {
		pp_buscador_reconstruir_indice_palabras();
	}
}

/* -------------------------------------------------------------------------
 * Versión del diccionario — invalida cachés sin borrarlas una por una
 *
 * Las sugerencias y los puentes del tema hijo cachean por término durante 10
 * minutos. Al cambiar sinónimos o redirecciones se incrementa esta versión, que
 * forma parte de la clave de caché (filtro 'ppv2_suggest_cache_salt'), de modo
 * que los cambios del panel se ven al instante.
 * ---------------------------------------------------------------------- */

function pp_buscador_dict_version() {
	return (int) get_option( 'pp_buscador_dict_ver', 1 );
}

function pp_buscador_bump_dict_version() {
	update_option( 'pp_buscador_dict_ver', pp_buscador_dict_version() + 1 );
}

add_filter( 'ppv2_suggest_cache_salt', 'pp_buscador_cache_salt' );
function pp_buscador_cache_salt( $salt ) {
	return $salt . '|d' . pp_buscador_dict_version();
}

/* -------------------------------------------------------------------------
 * Utilidades compartidas
 * ---------------------------------------------------------------------- */

/**
 * Normaliza un término reutilizando la normalización del tema hijo.
 * Si el tema no la aporta (tema cambiado, archivo desactivado), degrada a una
 * limpieza mínima para que el módulo siga siendo utilizable.
 *
 * @param string $term Término.
 * @return string
 */
function pp_buscador_normalizar( $term ) {
	if ( function_exists( 'ppv2_normalizar_termino_busqueda' ) ) {
		return ppv2_normalizar_termino_busqueda( $term );
	}
	$term = strip_tags( (string) $term );
	$term = preg_replace( '/\s+/', ' ', trim( mb_strtolower( $term, 'UTF-8' ) ) );
	return $term;
}

/**
 * Ámbito ("tienda", "directorio", "adopcion"…) de la petición actual.
 * Reutiliza el contexto de tipo del módulo Listados para no duplicar reglas.
 *
 * @return string
 */
function pp_buscador_ambito_actual() {
	if ( function_exists( 'is_woocommerce' ) && ( is_woocommerce() || ( is_search() && 'product' === get_query_var( 'post_type' ) ) ) ) {
		return 'tienda';
	}
	if ( function_exists( 'pp_listados_contexto_tipo' ) ) {
		$tipo = pp_listados_contexto_tipo();
		if ( $tipo ) {
			return $tipo;
		}
	}
	return 'directorio';
}

/* -------------------------------------------------------------------------
 * Carga de las partes del módulo
 * ---------------------------------------------------------------------- */

require_once PP_PERS_DIR . 'includes/buscador-registro.php';
require_once PP_PERS_DIR . 'includes/buscador-motor.php';
if ( is_admin() ) {
	require_once PP_PERS_DIR . 'includes/buscador-admin.php';
}
