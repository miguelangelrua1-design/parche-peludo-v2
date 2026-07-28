/**
 * Core Web Vitals de usuarios reales → Google Analytics 4
 * ============================================================================
 *
 * POR QUÉ HACE FALTA
 * El sitio NO tiene datos de campo en CrUX (el informe de Chrome): se comprobó
 * con la API de PageSpeed y devuelve «sin datos», tanto por URL como por
 * origen. Eso pasa cuando el tráfico no alcanza el umbral que Google exige
 * para publicarlos. Consecuencias:
 *
 *   - El informe de Core Web Vitals de Search Console está vacío.
 *   - Toda la optimización de rendimiento se ha medido solo en LABORATORIO,
 *     con una red y una CPU simuladas que pueden no parecerse en nada a las de
 *     un usuario real en Medellín con un gama media en 4G.
 *   - El INP (que sustituyó al FID en 2024) NO se puede medir en laboratorio.
 *     Hoy es un dato completamente desconocido.
 *
 * Medir desde el propio sitio es, por tanto, la ÚNICA forma de saber cómo va
 * de verdad. Y tiene una ventaja sobre CrUX: permite segmentar por tipo de
 * página, en vez de un promedio de todo el sitio que no dice dónde está el
 * problema.
 *
 * QUÉ ENVÍA
 * Un evento GA4 por métrica, con estos parámetros:
 *
 *   metric_name .......... LCP | INP | CLS | FCP | TTFB
 *   metric_value ......... valor redondeado (ms; en CLS ×1000 porque GA4 no
 *                          admite decimales en métricas personalizadas)
 *   metric_rating ........ good | needs-improvement | poor  (umbrales oficiales)
 *   metric_id ............ identificador único de la medición, para poder
 *                          contar mediciones distintas sin duplicar
 *   pp_tipo_pagina ....... portada | ficha | tienda | producto | blog…
 *   metric_debug ......... QUÉ causó el problema: el selector CSS del elemento
 *                          del LCP, el script que bloqueó el INP, el elemento
 *                          que se desplazó en el CLS. Esto es lo que convierte
 *                          el dato en accionable.
 *
 * POR QUÉ LA LIBRERÍA VA ALOJADA AQUÍ Y NO EN UN CDN
 * Cargarla desde unpkg o jsdelivr añadiría una petición a un dominio externo
 * —DNS + TLS— que es justo lo que se ha estado quitando del sitio. Pesa 12 KB
 * y se sirve desde el mismo dominio, aprovechando la conexión ya abierta.
 *
 * PRECISIÓN DEL DATO
 * Las métricas se envían cuando son DEFINITIVAS, no antes:
 *   - El CLS y el INP solo se conocen del todo cuando el usuario abandona la
 *     página, porque pueden empeorar en cualquier momento. La librería usa
 *     `visibilitychange` para capturarlos a tiempo.
 *   - Se usa `sendBeacon` implícitamente vía gtag, que sobrevive a la descarga
 *     de la página.
 *
 * KILL-SWITCH
 *     Filtro PHP `pp_web_vitals_activo` a false, o quitar el encolado.
 *
 * @package listeo-child
 */
( function () {
	'use strict';

	// Sin la librería o sin gtag no hay nada que hacer. No se rompe nada.
	if ( typeof window.webVitals === 'undefined' || typeof window.gtag !== 'function' ) {
		return;
	}

	/*
	 * No medir fuera de producción.
	 *
	 * El sitio local (LocalWP) tiene el mismo gtag configurado, así que sin
	 * este corte las métricas de «parche-peludo-local.local» —con tiempos que
	 * no se parecen en nada a los de un usuario real, porque no hay latencia de
	 * red— acabarían mezcladas con las de producción y falsearían las medias.
	 * Se comprobó en la prueba: en local salía un TTFB de 25 s por un artefacto
	 * del entorno.
	 */
	var host = window.location.hostname;
	if ( host !== 'parchepeludo.com' && host !== 'www.parchepeludo.com' ) {
		return;
	}

	/**
	 * Clasifica la página para poder comparar plantillas entre sí.
	 *
	 * Un promedio de todo el sitio esconde el problema: la portada puede ir
	 * bien y la ficha de publicación fatal, y en el agregado no se ve.
	 * Se deduce de la URL y de las clases del body, que es lo que WordPress
	 * ya expone sin necesidad de imprimir nada extra en el HTML.
	 */
	function tipoDePagina() {
		var c = document.body ? document.body.className : '';
		var p = window.location.pathname;

		if ( /(^|\s)home(\s|$)/.test( c ) || p === '/' )      { return 'portada'; }
		if ( /single-listing/.test( c ) || /\/publicacion\//.test( p ) ) { return 'ficha-publicacion'; }
		if ( /single-product/.test( c ) || /\/producto\//.test( p ) )    { return 'ficha-producto'; }
		if ( /woocommerce-checkout/.test( c ) )               { return 'checkout'; }
		if ( /woocommerce-cart/.test( c ) )                   { return 'carrito'; }
		if ( /(post-type-archive-product|tax-product_cat)/.test( c ) || /\/categoria-producto\//.test( p ) ) { return 'tienda'; }
		if ( /(post-type-archive-listing|tax-listing_category)/.test( c ) || /\/listings?\//.test( p ) )     { return 'directorio'; }
		if ( /\/blog-mascotas\//.test( p ) || /single-post/.test( c ) )  { return 'blog'; }
		if ( /(page-template|page)/.test( c ) )               { return 'pagina'; }
		return 'otro';
	}

	var TIPO = tipoDePagina();

	/**
	 * Extrae la pista de por qué la métrica salió mal.
	 *
	 * Sin esto solo se sabría «el LCP es de 11 s»; con esto se sabe «el LCP es
	 * la imagen de cabecera con este selector», que es lo que permite actuar.
	 * Cada métrica guarda su pista en un campo distinto de `attribution`.
	 */
	function pista( metrica ) {
		var a = metrica.attribution;
		if ( ! a ) {
			return '(sin datos)';
		}
		switch ( metrica.name ) {
			case 'LCP':
				// Elemento que tardó en pintarse + en qué fase se fue el tiempo.
				return ( a.element || '(desconocido)' ) +
					' | carga:' + Math.round( a.resourceLoadDuration || 0 ) + 'ms' +
					' | espera:' + Math.round( a.resourceLoadDelay || 0 ) + 'ms';
			case 'INP':
				// Qué se pulsó y cuánto tardó cada fase de la respuesta.
				return ( a.interactionTarget || '(desconocido)' ) +
					' | ' + ( a.interactionType || '?' ) +
					' | proceso:' + Math.round( a.processingDuration || 0 ) + 'ms';
			case 'CLS':
				// El elemento que más contribuyó al desplazamiento.
				return ( a.largestShiftTarget || '(desconocido)' );
			case 'TTFB':
				return 'espera:' + Math.round( a.waitingDuration || 0 ) + 'ms';
			case 'FCP':
				return ( a.loadState || '(sin estado)' );
			default:
				return '(sin datos)';
		}
	}

	/**
	 * Envía una métrica a GA4.
	 */
	function enviar( metrica ) {
		// GA4 no admite decimales en métricas personalizadas: el CLS (que va de
		// 0 a ~1) se multiplica por 1000 para no perderlo por redondeo.
		var valor = ( metrica.name === 'CLS' )
			? Math.round( metrica.value * 1000 )
			: Math.round( metrica.value );

		window.gtag( 'event', 'web_vitals', {
			metric_name:    metrica.name,
			metric_value:   valor,
			metric_rating:  metrica.rating,          // good | needs-improvement | poor
			metric_id:      metrica.id,
			metric_delta:   Math.round( metrica.delta ),
			metric_debug:   String( pista( metrica ) ).slice( 0, 100 ),
			pp_tipo_pagina: TIPO,
			// No cuenta como interacción del usuario: evita ensuciar la tasa de
			// rebote y la duración de sesión con eventos técnicos.
			non_interaction: true
		} );
	}

	// Las cinco métricas. LCP, INP y CLS son las Core Web Vitals que Google usa
	// para posicionamiento; FCP y TTFB ayudan a saber si el problema está en el
	// servidor o en el navegador.
	window.webVitals.onLCP( enviar );
	window.webVitals.onINP( enviar );
	window.webVitals.onCLS( enviar );
	window.webVitals.onFCP( enviar );
	window.webVitals.onTTFB( enviar );
} )();
