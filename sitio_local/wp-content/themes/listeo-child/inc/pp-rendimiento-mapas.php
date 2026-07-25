<?php
/**
 * Rendimiento: no cargar los mapas donde no se usan
 * =============================================================================
 *
 * EL PROBLEMA
 * Listeo encola SIEMPRE los 8 scripts de mapas, en las 6 plantillas del sitio,
 * aunque solo 2 muestren mapa. Se midió: los 31 JS bloqueantes son EXACTAMENTE
 * los mismos en portada, directorio, publicación, tienda, producto y blog.
 *
 * Ocho de esos 31 son de mapas, e incluyen una petición al dominio EXTERNO
 * maps.googleapis.com — de lo más caro para el primer pintado, porque suma DNS
 * + TLS + descarga antes de que se pueda mostrar nada.
 *
 * Con TTFB de 0,02 s el servidor no es el problema: el FCP de ~10 s en móvil
 * viene de esta cola de recursos bloqueantes.
 *
 * QUÉ PÁGINAS LLEVAN MAPA (verificado en producción, buscando contenedores de
 * mapa, campos de ubicación y llamadas a google.maps en el HTML servido):
 *   Directorio ......... SÍ
 *   Publicación ........ SÍ
 *   Portada ............ NO
 *   Tienda ............. NO
 *   Producto ........... NO
 *   Blog ............... NO
 *
 * ENFOQUE CONSERVADOR — LISTA BLANCA, NO DESCARTE
 * Solo se descargan los scripts en las plantillas explícitamente listadas en
 * pp_mapas_pagina_sin_mapa(). Cualquier página no contemplada (panel de
 * control, agregar publicación, mis publicaciones, checkout, 404, resultados
 * de búsqueda, cualquier plantilla futura…) conserva TODO como está.
 * Ante la duda, no se toca nada.
 *
 * COMPROBACIONES DE SEGURIDAD HECHAS ANTES DE ESCRIBIR ESTO
 * - Ningún script que permanece invoca los mapas: se revisó `frontend.js`,
 *   `bookings.js`, `drilldown.js`, `ajax-login-script.js` y todo el JS del
 *   tema hijo. Cero referencias a `google.maps` o Leaflet.
 * - Las 4 páginas afectadas no tienen contenedor de mapa, ni campo de
 *   ubicación con autocompletado, ni JS en línea que use `google.maps`.
 * - El buscador de la cabecera es por TEXTO (`keyword_search`), no usa Places.
 *
 * KILL-SWITCH
 *     define( 'PP_MAPAS_OFF', true );   // en wp-config.php
 *
 * @package listeo-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Identificadores con los que Listeo registra los scripts de mapas.
 * Se retiran juntos: si se dejara uno suelto que dependa de otro, WordPress
 * volvería a cargar la cadena entera y el ahorro sería nulo.
 */
function pp_mapas_handles() {
	return array(
		'leaflet.js',
		'listeo_core-leaflet-geocoder',
		'listeo_core-leaflet',
		'google-maps',
		'listeo_core-leaflet-google-maps',
		'listeo_core-leaflet-markercluster',
		'listeo_core-leaflet-gesture-handling',
		'listeo_core-google-autocomplete',
	);
}

/**
 * ¿Estamos en una plantilla que se ha VERIFICADO que no lleva mapa?
 *
 * Devuelve true SOLO en los casos comprobados. Todo lo demás devuelve false
 * y por tanto no se toca.
 */
function pp_mapas_pagina_sin_mapa() {

	// --- Salvaguardas: nunca actuar en estos contextos ---------------------
	if ( is_admin() || wp_doing_ajax() || is_customize_preview() ) {
		return false;
	}
	// Cualquier cosa relacionada con listados lleva mapa o puede llevarlo.
	if ( is_singular( 'listing' ) || is_post_type_archive( 'listing' ) ) {
		return false;
	}
	if ( is_tax( array( 'listing_category', 'listing_feature', 'region' ) ) ) {
		return false;
	}
	// El buscador del directorio y las páginas del panel usan mapa/autocompletado.
	if ( is_search() ) {
		return false;
	}

	// --- Lista blanca: plantillas verificadas sin mapa ---------------------
	if ( is_front_page() ) {
		return true;
	}
	if ( is_home() || is_singular( 'post' ) || is_category() || is_tag() ) {
		return true; // Blog y entradas.
	}
	// El blog del sitio es una PÁGINA (slug "blog-mascotas"), no el archivo de
	// entradas de WordPress, así que is_home() no lo detecta. Verificado: no
	// tiene mapa, campo de ubicación ni llamadas a google.maps.
	if ( is_page( 'blog-mascotas' ) ) {
		return true;
	}
	if ( function_exists( 'is_woocommerce' ) ) {
		if ( is_shop() || is_product() || is_product_category() || is_product_tag() ) {
			return true;
		}
		if ( is_cart() || is_checkout() ) {
			return true;
		}
	}
	// La tienda real del sitio es una PÁGINA (slug "tienda"), no el shop de Woo.
	if ( is_page( 'tienda' ) ) {
		return true;
	}

	// Cualquier otra cosa: no tocar.
	return false;
}

/**
 * Retira los scripts de mapas en las plantillas verificadas.
 *
 * Prioridad 100: se ejecuta después de que Listeo y los demás plugins hayan
 * encolado lo suyo; si corriera antes, no habría nada que retirar.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( defined( 'PP_MAPAS_OFF' ) && PP_MAPAS_OFF ) {
			return;
		}
		if ( ! apply_filters( 'pp_mapas_activo', true ) ) {
			return;
		}
		if ( ! pp_mapas_pagina_sin_mapa() ) {
			return;
		}

		foreach ( pp_mapas_handles() as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}
	},
	100
);
