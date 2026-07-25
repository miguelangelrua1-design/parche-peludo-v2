<?php
/**
 * Rendimiento: no cargar el CSS de modo oscuro del chat cuando no puede aplicarse
 * =============================================================================
 *
 * EL PROBLEMA
 * El plugin ai-chat-search encola DOS recursos de modo oscuro:
 *
 *   chatbot-dark-mode.js   → SOLO si listeo_ai_color_scheme === 'auto'
 *   chatbot-dark-mode.css  → SIEMPRE, sin condición    ← la asimetría
 *
 * (class-chat-shortcode.php líneas 82-99 y class-floating-chat-widget.php
 * líneas 112-129: en ambos archivos el JS va dentro de un if y el CSS fuera.)
 *
 * POR QUÉ ESO ES PESO MUERTO
 * Las 130 reglas del CSS cuelgan SIN EXCEPCIÓN del selector
 * `.listeo-ai-chat-wrapper.dark-mode`. Se comprobó: cero selectores del archivo
 * prescinden de la clase `dark-mode`. Y esa clase la añade únicamente
 * chatbot-dark-mode.js, que no se carga salvo en modo 'auto'.
 *
 * Resultado: sin modo 'auto', son ~20 KB que el navegador descarga y analiza
 * en CADA página para no aplicar ni una sola regla.
 *
 * VERIFICADO EN PRODUCCIÓN (2026-07-25)
 *   - chatbot-dark-mode.js  cargado ....... NO
 *   - chatbot-dark-mode.css cargado ....... SÍ (20 KB)
 *   - elementos con clase .dark-mode ...... 0
 *   - selectores del CSS sin .dark-mode ... 0 de 130
 *
 * POR QUÉ SE CONSULTA LA MISMA OPCIÓN Y NO SE QUITA SIN MÁS
 * Si algún día se cambia el esquema de color a 'auto' desde el panel del
 * plugin, el JS volverá a cargarse y el CSS hará falta. Al replicar aquí la
 * MISMA condición que usa el plugin para su JS, el CSS reaparece solo, sin
 * tener que acordarse de desactivar nada.
 *
 * NOTA SOBRE LOS OTROS CSS DEL CHAT
 * El plugin se auto-excluye de LiteSpeed (ai-chat-search.php líneas 418-430,
 * filtro `litespeed_optimize_css_excludes`), por eso sus 4 hojas no se
 * minifican ni combinan. Es una decisión deliberada de su autor y NO se toca:
 * presumiblemente la optimización le rompía el chat.
 *
 * KILL-SWITCH
 *     define( 'PP_CHAT_OSCURO_OFF', true );   // en wp-config.php
 *
 * @package listeo-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retira el CSS de modo oscuro del chat mientras el esquema no sea 'auto'.
 *
 * Prioridad 100: después de que el plugin haya encolado lo suyo (lo hace en
 * wp_enqueue_scripts con la prioridad por defecto); antes no habría nada que
 * retirar.
 *
 * El identificador 'listeo-ai-chat-dark-mode' es el mismo en los dos sitios
 * donde el plugin lo encola (atajo y widget flotante), así que una sola
 * retirada cubre ambos casos.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( defined( 'PP_CHAT_OSCURO_OFF' ) && PP_CHAT_OSCURO_OFF ) {
			return;
		}
		if ( ! apply_filters( 'pp_chat_oscuro_activo', true ) ) {
			return;
		}

		// Misma condición que usa el plugin para decidir si carga su JS.
		if ( 'auto' === get_option( 'listeo_ai_color_scheme', 'light' ) ) {
			return; // En modo 'auto' el CSS sí hace falta: no se toca.
		}

		wp_dequeue_style( 'listeo-ai-chat-dark-mode' );
		wp_deregister_style( 'listeo-ai-chat-dark-mode' );
	},
	100
);
