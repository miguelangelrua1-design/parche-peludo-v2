<?php
/**
 * MÓDULO BUSCADOR — Registro de búsquedas
 * -----------------------------------------------------------------------------
 * Guarda qué busca la gente para poder mejorar el buscador con datos y no con
 * intuición. Alimenta el panel (más buscadas / sin resultados) y la lista de
 * "búsquedas populares" del desplegable.
 *
 * POR QUÉ SE REGISTRA DESDE EL NAVEGADOR (beacon) Y NO EN PHP:
 * LiteSpeed cachea las páginas de resultados. Un registro hecho en PHP NO se
 * ejecutaría cuando la página se sirve desde caché — justo en las búsquedas más
 * repetidas, que son las que más importan. El beacon corre siempre, no bloquea
 * la navegación y además permite registrar los clics en sugerencias y puentes.
 *
 * PRIVACIDAD: no se guarda IP, ni usuario, ni identificador de sesión. La IP se
 * usa solo, hasheada y en memoria temporal (10 s), para no duplicar el mismo
 * envío; nunca se escribe en la tabla.
 *
 * @package pp-personalizacion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Ingesta (endpoint del beacon)
 * ---------------------------------------------------------------------- */

add_action( 'wp_ajax_pp_buscador_log', 'pp_buscador_log_endpoint' );
add_action( 'wp_ajax_nopriv_pp_buscador_log', 'pp_buscador_log_endpoint' );
function pp_buscador_log_endpoint() {
	// Sin nonce a propósito: la página que dispara el beacon puede venir de la
	// caché de LiteSpeed, donde un nonce ya estaría vencido o sería el de otro
	// visitante. A cambio, el endpoint solo INSERTA datos no personales, valida
	// con dureza y limita ráfagas.
	if ( ! pp_buscador_activo( 'pp_buscador_registro' ) ) {
		wp_send_json_success( array( 'skipped' => 'off' ) );
	}

	$termino = isset( $_POST['termino'] ) ? sanitize_text_field( wp_unslash( $_POST['termino'] ) ) : '';
	$termino = trim( $termino );
	if ( '' === $termino ) {
		wp_send_json_error( array( 'msg' => 'vacio' ) );
	}
	// Tope de longitud: ningún usuario real busca más de 100 caracteres y evita
	// que un bot infle la tabla.
	if ( function_exists( 'mb_substr' ) ) {
		$termino = mb_substr( $termino, 0, 100, 'UTF-8' );
	} else {
		$termino = substr( $termino, 0, 100 );
	}

	$ambito     = isset( $_POST['ambito'] ) ? sanitize_key( wp_unslash( $_POST['ambito'] ) ) : '';
	$origen     = isset( $_POST['origen'] ) ? sanitize_key( wp_unslash( $_POST['origen'] ) ) : 'resultados';
	$resultados = isset( $_POST['resultados'] ) ? max( 0, (int) $_POST['resultados'] ) : 0;

	$origenes_validos = array( 'resultados', 'sugerencia', 'sugerencia-click', 'puente-click', 'correccion-click' );
	if ( ! in_array( $origen, $origenes_validos, true ) ) {
		$origen = 'resultados';
	}

	pp_buscador_registrar( $termino, $ambito, $resultados, $origen );
	wp_send_json_success();
}

/**
 * Inserta una búsqueda en el registro (con deduplicación por ráfaga).
 *
 * @param string $termino    Lo que escribió el usuario.
 * @param string $ambito     tienda | directorio | adopcion | mascotas-perdidas.
 * @param int    $resultados Cuántos resultados obtuvo.
 * @param string $origen     Qué originó el registro.
 * @return bool True si se insertó.
 */
function pp_buscador_registrar( $termino, $ambito = '', $resultados = 0, $origen = 'resultados' ) {
	if ( ! pp_buscador_activo( 'pp_buscador_registro' ) ) {
		return false;
	}

	$norm = pp_buscador_normalizar( $termino );
	if ( '' === $norm ) {
		return false;
	}

	// Deduplicación: el mismo término+ámbito+origen desde el mismo visitante en
	// menos de 10 segundos cuenta una sola vez (evita dobles envíos y recargas).
	$huella = md5( $norm . '|' . $ambito . '|' . $origen . '|' . pp_buscador_huella_visitante() );
	if ( get_transient( 'pp_bsq_' . $huella ) ) {
		return false;
	}
	set_transient( 'pp_bsq_' . $huella, 1, 10 );

	global $wpdb;
	$ok = $wpdb->insert(
		pp_buscador_tabla(),
		array(
			'termino'      => $termino,
			'termino_norm' => $norm,
			'ambito'       => $ambito,
			'resultados'   => (int) $resultados,
			'origen'       => $origen,
			'fecha'        => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%d', '%s', '%s' )
	);

	return (bool) $ok;
}

/**
 * Huella efímera del visitante, solo para deduplicar (nunca se almacena).
 * Se hashea con las sales de WordPress para que no sea reversible.
 */
function pp_buscador_huella_visitante() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	return substr( wp_hash( $ip ), 0, 12 );
}

/* -------------------------------------------------------------------------
 * Consultas para el panel
 * ---------------------------------------------------------------------- */

/**
 * Términos más buscados.
 *
 * @param int  $dias           Ventana de días.
 * @param int  $limite         Máximo de filas.
 * @param bool $solo_sin_resultados Solo búsquedas que no encontraron nada.
 * @return array<int,object>
 */
function pp_buscador_top_terminos( $dias = 30, $limite = 20, $solo_sin_resultados = false ) {
	global $wpdb;
	$tabla = pp_buscador_tabla();
	$cond  = $solo_sin_resultados ? 'AND resultados = 0' : '';

	// Solo se cuentan los registros de página de resultados para no inflar el
	// conteo con los clics (que se registran aparte con otro origen).
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT termino_norm, MAX(termino) AS termino, COUNT(*) AS veces,
			        MAX(resultados) AS resultados, MAX(fecha) AS ultima
			 FROM {$tabla}
			 WHERE fecha >= DATE_SUB(NOW(), INTERVAL %d DAY)
			   AND origen = 'resultados' {$cond}
			 GROUP BY termino_norm
			 ORDER BY veces DESC, ultima DESC
			 LIMIT %d",
			(int) $dias,
			(int) $limite
		)
	);
}

/**
 * Métricas de cabecera del panel.
 *
 * @param int $dias Ventana.
 * @return array{total:int,sin_resultados:int,porcentaje:float,unicos:int}
 */
function pp_buscador_metricas( $dias = 30 ) {
	global $wpdb;
	$tabla = pp_buscador_tabla();

	$fila = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT COUNT(*) AS total,
			        SUM(CASE WHEN resultados = 0 THEN 1 ELSE 0 END) AS sin_resultados,
			        COUNT(DISTINCT termino_norm) AS unicos
			 FROM {$tabla}
			 WHERE fecha >= DATE_SUB(NOW(), INTERVAL %d DAY) AND origen = 'resultados'",
			(int) $dias
		)
	);

	$total = $fila ? (int) $fila->total : 0;
	$cero  = $fila ? (int) $fila->sin_resultados : 0;

	return array(
		'total'          => $total,
		'sin_resultados' => $cero,
		'porcentaje'     => $total > 0 ? round( $cero * 100 / $total, 1 ) : 0.0,
		'unicos'         => $fila ? (int) $fila->unicos : 0,
	);
}

/** Últimas búsquedas registradas (tabla del panel). */
function pp_buscador_recientes( $limite = 50 ) {
	global $wpdb;
	$tabla = pp_buscador_tabla();
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tabla} ORDER BY id DESC LIMIT %d", (int) $limite ) );
}

/* -------------------------------------------------------------------------
 * Búsquedas populares (para el desplegable del buscador)
 * ---------------------------------------------------------------------- */

/**
 * Términos populares que SÍ dieron resultados, para ofrecerlos al enfocar el
 * campo vacío. Cacheado 1 hora (se recalcula solo).
 *
 * @param int $limite Máximo.
 * @return array<int,string>
 */
function pp_buscador_populares( $limite = 6 ) {
	$cache = get_transient( 'pp_buscador_populares' );
	if ( is_array( $cache ) ) {
		return array_slice( $cache, 0, $limite );
	}

	global $wpdb;
	$tabla = pp_buscador_tabla();
	$filas = $wpdb->get_results(
		"SELECT MAX(termino) AS termino, COUNT(*) AS veces
		 FROM {$tabla}
		 WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)
		   AND origen = 'resultados' AND resultados > 0
		 GROUP BY termino_norm
		 ORDER BY veces DESC
		 LIMIT 12"
	);

	$terminos = array();
	foreach ( (array) $filas as $f ) {
		$t = trim( (string) $f->termino );
		if ( '' !== $t ) {
			$terminos[] = $t;
		}
	}

	set_transient( 'pp_buscador_populares', $terminos, HOUR_IN_SECONDS );
	return array_slice( $terminos, 0, $limite );
}

add_action( 'wp_ajax_pp_buscador_populares', 'pp_buscador_populares_endpoint' );
add_action( 'wp_ajax_nopriv_pp_buscador_populares', 'pp_buscador_populares_endpoint' );
function pp_buscador_populares_endpoint() {
	if ( ! pp_buscador_activo( 'pp_buscador_populares' ) ) {
		wp_send_json( array() );
	}
	wp_send_json( pp_buscador_populares( 6 ) );
}
