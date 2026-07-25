<?php
/**
 * Corrección de la clave de API para las reseñas de Google
 * =============================================================================
 *
 * EL PROBLEMA
 * `listeo_get_google_reviews()` elige la clave así (listeo-core-template-functions.php ~2902):
 *
 *     $api_key = get_option('listeo_google_reviews_api_key');               // 1º PRIORITARIA
 *     if (empty($api_key)) $api_key = get_option('listeo_maps_api_server'); // 2º
 *
 * En esta instalación `listeo_google_reviews_api_key` contiene la clave del
 * NAVEGADOR (restringida por dominio). Como las reseñas se piden desde el
 * servidor, Google la rechaza:
 *
 *     REQUEST_DENIED — "API keys with referer restrictions cannot be used with this API"
 *
 * Resultado: no se guardan `_google_rating` / `_google_review_count`, no se
 * calcula la nota combinada y la plantilla no pinta nada. Ni calificación ni
 * reseñas. La clave correcta SÍ estaba en `listeo_maps_api_server`, pero nunca
 * llegaba a usarse.
 *
 * ESA OPCIÓN NO TIENE INTERFAZ: aparece una sola vez en todo el plugin, en la
 * lectura de arriba. Ningún formulario del administrador la escribe (es un
 * resto de una versión anterior). Por eso no se puede corregir desde el panel.
 *
 * LA CORRECCIÓN
 * Un filtro que hace que esa opción devuelva la clave de servidor. Nada más.
 * No se toca ninguna plantilla ni se duplica lógica: a partir de aquí Listeo
 * funciona por sí solo — guarda los metas, recalcula la nota combinada y pinta
 * el bloque con su propio diseño.
 *
 * SE APAGA SOLO
 * `pre_option_` no escribe en la base de datos: solo intercepta la lectura. Si
 * Listeo corrige el bug o expone el campo en el panel, borrar este archivo
 * devuelve todo a su comportamiento original sin dejar rastro.
 *
 * RENDIMIENTO
 * No añade ni una petición: hace que la que ya se hacía deje de fallar. Además
 * fija la caché de reseñas en 30 días (el máximo que permite Google), con lo
 * que cada listado consulta una vez al mes en vez de una al día.
 *
 * ⚠️ AL SUBIR CAMBIOS DE ESTE ARCHIVO A PRODUCCIÓN: purgar la caché de OPCODE
 * (LiteSpeed → Herramientas → "Purgar todo - Caché opcode"). PHP mantiene el
 * bytecode en memoria y sin ese purgado sigue ejecutando la versión anterior.
 *
 * KILL-SWITCH
 *     define( 'PP_PLACES_COMPAT_OFF', true );   // en wp-config.php
 *
 * Verificado en producción 2026-07-24: Punto Vet 4.1 (1.187 reseñas),
 * CatDog Hospital 4.5 (4.298 reseñas), con bloque de reseñas completo.
 *
 * @package listeo-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pp_places_compat_activo() {
	if ( defined( 'PP_PLACES_COMPAT_OFF' ) && PP_PLACES_COMPAT_OFF ) {
		return false;
	}
	return (bool) apply_filters( 'pp_places_compat_activo', true );
}

/**
 * LA CORRECCIÓN: que la opción prioritaria devuelva la clave de SERVIDOR.
 */
add_filter(
	'pre_option_listeo_google_reviews_api_key',
	function ( $pre ) {
		if ( ! pp_places_compat_activo() ) {
			return $pre;
		}
		$servidor = get_option( 'listeo_maps_api_server' );
		return $servidor ? $servidor : $pre;
	}
);

/**
 * Reseñas por RELEVANCIA en lugar de por fecha.
 *
 * Listeo fija `reviews_sort=newest` en la URL, así que Google devuelve las 5
 * más recientes. El problema es que las reseñas recientes suelen ser solo
 * estrellas, sin texto: en CatDog venían 3 de 5 completamente vacías.
 *
 * Con el orden por relevancia (que es el valor POR DEFECTO de la API) las 5
 * traen texto sustancial. Medido sobre el mismo negocio:
 *   newest        -> 906, 0, 0, 48, 0 caracteres
 *   most_relevant -> 716, 1291, 250, 208, 337 caracteres
 *
 * Esto mejora dos cosas a la vez, porque son la misma fuente: lo que se muestra
 * en la ficha y el material del que la IA extrae cualidades para la descripción.
 *
 * NOTA: "relevante" es el ranking de Google (reseñas útiles y detalladas), NO
 * "positiva". El conjunto puede incluir críticas, y está bien: da transparencia.
 * Que la descripción no diga nada negativo de un aliado se resuelve en el prompt,
 * no escondiendo reseñas.
 *
 * El tope de 5 lo impone Google y no se puede subir por API.
 */
function pp_reviews_por_relevancia( $pre, $args, $url ) {
	static $reentrante = false;

	if ( $reentrante || ! pp_places_compat_activo() ) {
		return $pre;
	}
	if ( false === strpos( $url, 'maps.googleapis.com/maps/api/place/details/json' ) ) {
		return $pre;
	}
	if ( false === strpos( $url, 'reviews_sort=newest' ) ) {
		return $pre; // Ya viene por relevancia (o Listeo cambió su URL): no tocamos nada.
	}

	$nueva = str_replace( 'reviews_sort=newest', 'reviews_sort=most_relevant', $url );

	$reentrante = true; // Evita que nuestra propia petición vuelva a entrar aquí.
	$respuesta  = wp_remote_get( $nueva, $args );
	$reentrante = false;

	// Ante un fallo de red devolvemos el control a WordPress para que haga la
	// petición original: nunca dejamos el sitio peor de como estaba.
	if ( is_wp_error( $respuesta ) ) {
		return $pre;
	}

	return $respuesta;
}
add_filter( 'pre_http_request', 'pp_reviews_por_relevancia', 10, 3 );

/**
 * Purga única de las reseñas ya cacheadas.
 *
 * Sin esto los listados existentes seguirían mostrando hasta 30 días las 5
 * "más recientes" que quedaron guardadas antes de este cambio. Se ejecuta una
 * sola vez; subir PP_REVIEWS_ORDEN_VERSION la vuelve a lanzar.
 */
define( 'PP_REVIEWS_ORDEN_VERSION', '1' );

add_action(
	'admin_init',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_option( 'pp_reviews_orden' ) === PP_REVIEWS_ORDEN_VERSION ) {
			return;
		}

		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_listeo_reviews_%'
			    OR option_name LIKE '_transient_timeout_listeo_reviews_%'"
		);

		update_option( 'pp_reviews_orden', PP_REVIEWS_ORDEN_VERSION, false );
	}
);

/**
 * Caché de reseñas a 30 días (máximo permitido por Google).
 * La opción existe en Listeo pero no está expuesta; su valor por defecto es 1 día.
 */
add_filter(
	'pre_option_listeo_google_reviews_cache_days',
	function () {
		return pp_places_compat_activo() ? 30 : 1;
	}
);
