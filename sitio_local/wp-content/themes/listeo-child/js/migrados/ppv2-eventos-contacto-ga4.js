/**
 * Clics de contacto (WhatsApp, teléfono, correo, web) → Google Analytics 4
 * ============================================================================
 *
 * POR QUÉ HACE FALTA
 * En un directorio, el clic al WhatsApp del prestador ES la conversión: es el
 * momento en que el usuario sale de la plataforma para cerrar el trato. Hoy no
 * se mide en ningún punto, así que el valor que Parche Peludo genera a sus
 * aliados es invisible en Analytics: se ve cuánta gente entra a una ficha, pero
 * no cuánta gente termina escribiéndole al prestador.
 *
 * QUÉ CAPTURA
 * Cualquier enlace de contacto, esté donde esté, porque la escucha es por
 * delegación en `document` y clasifica por el `href`, no por la clase CSS:
 *
 *   href="https://wa.me/…" | api.whatsapp.com | web.whatsapp.com  → whatsapp
 *   href="tel:…"                                                  → telefono
 *   href="mailto:…"                                               → correo
 *   enlace externo marcado como web del listado                   → sitio_web
 *
 * Así siguen contando aunque cambien las plantillas de Listeo o aparezcan
 * botones nuevos (barra móvil, tarjetas del listado, perfil del prestador…),
 * que es justo lo que pasaría con selectores rígidos.
 *
 * QUÉ ENVÍA
 *   evento ............... contacto_prestador
 *   pp_metodo ............ whatsapp | telefono | correo | sitio_web
 *   pp_ubicacion ......... dónde se pulsó: ficha-contacto | ficha-barra-movil |
 *                          tarjeta | perfil-prestador | pie | otro
 *   pp_listado ........... título del listado (solo en una ficha)
 *   pp_listado_id ........ ID del listado, para cruzar con la base de datos
 *   pp_tipo_pagina ....... portada | ficha-publicacion | directorio | …
 *
 * POR QUÉ NO SE RETRASA EL CLIC
 * El patrón clásico —`preventDefault`, esperar al `callback` de gtag y luego
 * navegar— aquí haría daño: en móvil, `tel:` y `wa.me` abren otra aplicación y
 * el retraso se percibe como que el botón no responde; además iOS bloquea la
 * apertura si no ocurre dentro del gesto del usuario. gtag usa `sendBeacon`,
 * que sobrevive a la descarga de la página, así que el evento llega igual.
 *
 * KILL-SWITCH
 *     add_filter( 'pp_eventos_contacto_activo', '__return_false' );
 *
 * @package listeo-child
 */
( function () {
	'use strict';

	// Sin gtag no hay a dónde enviar. No se rompe nada.
	if ( typeof window.gtag !== 'function' ) {
		return;
	}

	/*
	 * Solo producción.
	 *
	 * El sitio local tiene configurado el MISMO identificador de GA4, así que
	 * sin este corte las pruebas de escritorio acabarían contadas como
	 * conversiones reales. Es el mismo guardián que usa el módulo de Core Web
	 * Vitals, y por el mismo motivo.
	 */
	var host = window.location.hostname;
	if ( host !== 'parchepeludo.com' && host !== 'www.parchepeludo.com' ) {
		return;
	}

	// Datos que pone PHP (tipo de página, listado). Puede no existir.
	var DATOS = window.ppContactoDatos || {};

	/**
	 * Clasifica el enlace por su destino.
	 *
	 * @param {HTMLAnchorElement} a Enlace pulsado.
	 * @return {string|null} Método de contacto, o null si no es de contacto.
	 */
	function metodo( a ) {
		// getAttribute y no `a.href`: el navegador normaliza `href` y algunos
		// esquemas (tel:, mailto:) se leen mejor tal como están escritos.
		var href = ( a.getAttribute( 'href' ) || '' ).trim();

		if ( /^tel:/i.test( href ) )    { return 'telefono'; }
		if ( /^mailto:/i.test( href ) ) { return 'correo'; }
		if ( /(^https?:)?\/\/(wa\.me|api\.whatsapp\.com|web\.whatsapp\.com)/i.test( href ) ) {
			return 'whatsapp';
		}
		// La web del prestador: Listeo la marca con esta clase en la ficha.
		if ( a.classList.contains( 'listing-website' ) ) { return 'sitio_web'; }

		return null;
	}

	/**
	 * Deduce en qué parte de la página estaba el enlace.
	 *
	 * Importa porque no es lo mismo un clic desde la ficha —donde el usuario ya
	 * leyó al prestador— que uno desde la tarjeta de un listado; y el WhatsApp
	 * del pie o del menú es el de atención de Parche Peludo, no el de un aliado.
	 *
	 * Los selectores se comprobaron uno a uno contra el DOM real de una ficha
	 * (`#footer`, `.booking-sticky-footer`, `.listeo-single-listing-sidebar`…);
	 * no son los nombres «de manual» de Listeo, que en varios casos no existen.
	 * Cuidado al ampliar la lista: la clase `pp-barra-superior` está en el
	 * <body>, así que usarla en un `closest` haría coincidir la página entera.
	 *
	 * @param {HTMLElement} a Enlace pulsado.
	 * @return {string} Etiqueta de ubicación.
	 */
	function ubicacion( a ) {
		// El perfil del prestador marca sus enlaces con el sufijo `-profile`
		// (whatsapp-profile, facebook-profile…). Es la señal más fiable, así que
		// va primero.
		if ( /(^|\s)[a-z]+-profile(\s|$)/.test( a.className ) )       { return 'perfil-prestador'; }

		if ( a.closest( '#footer' ) )                                 { return 'pie'; }
		if ( a.closest( '.mobile-navigation-wrapper, #navigation' ) ) { return 'menu'; }
		// Barra fija inferior en móvil + panel deslizante del tema hijo.
		if ( a.closest( '.booking-sticky-footer, .ppv2-bottom-sheet' ) ) { return 'ficha-barra-movil'; }
		if ( a.closest( '.listing-links-container, ul.listing-links' ) ) { return 'ficha-contacto'; }
		if ( a.closest( '.listeo-single-listing-sidebar' ) )          { return 'ficha-sidebar'; }
		if ( a.closest( '.listing-item, .listing-item-container, .details-sidebar-col-nl' ) ) { return 'tarjeta'; }

		return 'otro';
	}

	/*
	 * Escucha única en la fase de captura.
	 *
	 * En captura y no en burbuja porque algunos scripts de Listeo llaman a
	 * `stopPropagation()` en sus propios manejadores; si se escuchara al subir,
	 * esos clics no llegarían nunca aquí. `passive` deja claro que no se va a
	 * interferir con la navegación.
	 */
	document.addEventListener( 'click', function ( e ) {
		var a = e.target && e.target.closest ? e.target.closest( 'a' ) : null;
		if ( ! a ) {
			return;
		}

		var m = metodo( a );
		if ( ! m ) {
			return;
		}

		var datos = {
			pp_metodo:      m,
			pp_ubicacion:   ubicacion( a ),
			pp_tipo_pagina: DATOS.tipo_pagina || 'otro'
		};

		// Solo en una ficha se sabe de qué prestador se trata.
		if ( DATOS.listado_id ) {
			datos.pp_listado    = DATOS.listado_titulo || '';
			datos.pp_listado_id = String( DATOS.listado_id );
		}

		window.gtag( 'event', 'contacto_prestador', datos );
	}, true );
} )();
