<?php
/**
 * Rendimiento: no descargar fuentes de Google que el sitio no usa
 * =============================================================================
 *
 * EL PROBLEMA
 * El tema padre (Listeo) encola SIEMPRE dos familias de Google Fonts:
 *   - Open Sans (500,600,700)  → handle 'google-fonts-open-sans'
 *   - Raleway (300,400,500,600,700) → handle 'google-fonts-raleway'
 * (listeo/functions.php, líneas 321-334)
 *
 * Son dos peticiones EXTERNAS a fonts.googleapis.com que bloquean el primer
 * pintado (DNS + TLS + descarga del CSS de la fuente + descarga de los .woff2).
 *
 * PERO EL SITIO NO LAS USA: la tipografía de Parche Peludo es Ubuntu.
 * Se verificó en producción (2026-07-25) recorriendo TODOS los elementos del
 * DOM en portada, publicación, tienda y producto: CERO elementos visibles
 * pintan con Open Sans o Raleway.
 *
 * ¿Y las 11 reglas del tema padre que declaran "Open Sans"?
 * Son fragmentos menores (botones de cantidad, etiquetas numéricas de rating,
 * cupones) y TODAS llevan fallback a sans-serif — al faltar la fuente, el
 * navegador usa la del sistema y no se rompe nada. Raleway solo aparece en el
 * CSS de los datepickers (con fallback a Helvetica) y en plantillas que no
 * usamos (facturas, revolution slider).
 *
 * PRUEBA VISUAL HECHA ANTES DE ESCRIBIR ESTO
 * Se eliminaron ambas hojas <link> del DOM en producción, en vivo, y se
 * comparó la familia tipográfica computada de todos los elementos clave
 * antes/después: NINGÚN cambio en portada ni en publicación.
 *
 * KILL-SWITCH
 *     define( 'PP_FUENTES_OFF', true );   // en wp-config.php
 *
 * @package listeo-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retira las dos fuentes no usadas.
 *
 * Prioridad 100: después de que el tema padre las haya encolado (encola en
 * wp_enqueue_scripts con prioridad por defecto 10); antes no habría nada
 * que retirar.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( defined( 'PP_FUENTES_OFF' ) && PP_FUENTES_OFF ) {
			return;
		}
		if ( ! apply_filters( 'pp_fuentes_activo', true ) ) {
			return;
		}

		wp_dequeue_style( 'google-fonts-open-sans' );
		wp_deregister_style( 'google-fonts-open-sans' );
		wp_dequeue_style( 'google-fonts-raleway' );
		wp_deregister_style( 'google-fonts-raleway' );
	},
	100
);
