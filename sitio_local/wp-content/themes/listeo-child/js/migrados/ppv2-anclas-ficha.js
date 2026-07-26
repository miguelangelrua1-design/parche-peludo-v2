/**
 * Anclas de la ficha de publicación (#add-review)
 * ============================================================================
 *
 * PARA QUÉ
 * El correo «¿Cómo le fue a tu peludo?» enlaza a {listing_url}#add-review para
 * dejar al usuario justo en el formulario de opinión.
 *
 * POR QUÉ NO BASTA EL SALTO NATIVO DEL NAVEGADOR — dos motivos, medidos:
 *
 *   1. LLEGA DEMASIADO PRONTO. El navegador resuelve el hash al encontrar el
 *      elemento, cuando galería, mapa e imágenes diferidas todavía no tienen su
 *      altura final. Salta a unas coordenadas que dejan de ser válidas medio
 *      segundo después.
 *
 *   2. EL DESTINO ESTÁ CASI AL FINAL. Medido en producción: el bloque está en
 *      Y≈3695 de una página de ≈4374 px. El desplazamiento máximo posible es
 *      (alto de página − alto de ventana), así que pedirle al navegador que lo
 *      suba hasta arriba es IMPOSIBLE: se queda en el tope y lo que se ve es el
 *      pie de página. Esto es lo que hacía que «llevara al footer».
 *
 * CÓMO SE RESUELVE
 *   a) Se abre el formulario ANTES de desplazarse. Además de ser lo que el
 *      usuario quiere (llega con el campo listo), alarga la página y con ello
 *      crea el margen de desplazamiento que faltaba en el punto 2.
 *   b) Se espera a que la altura de la página deje de cambiar, en vez de
 *      confiar en un temporizador fijo.
 *   c) Se posiciona el bloque a un tercio de la ventana, no pegado arriba: si
 *      aun así no cupiera, sigue quedando a la vista.
 *   d) Se comprueba el resultado y se corrige una vez si hizo falta.
 *
 * Solo actúa si la URL trae el ancla; en una visita normal no hace nada.
 *
 * @package listeo-child
 */
( function () {
	'use strict';

	var ANCLAS = [ '#add-review', '#listing-reviews', '#listing-google-reviews' ];

	var hash = window.location.hash;
	if ( ! hash || ANCLAS.indexOf( hash ) === -1 ) {
		return;
	}

	/**
	 * Alto de los elementos fijos que taparían el destino.
	 * Se mide en vivo: el header cambia entre escritorio y móvil, y la barra de
	 * administración solo existe para usuarios con sesión.
	 */
	function alturaFija() {
		var alto = 0;
		var header = document.querySelector( '#header-container, #header' );
		if ( header ) {
			var cs = window.getComputedStyle( header );
			if ( cs.position === 'fixed' || cs.position === 'sticky' ) {
				alto = header.getBoundingClientRect().height;
			}
		}
		var barra = document.getElementById( 'wpadminbar' );
		if ( barra ) {
			alto += barra.getBoundingClientRect().height;
		}
		return alto;
	}

	/**
	 * Abre el formulario de opinión si está plegado.
	 *
	 * El botón «+ Añadir Opinión» (.ppv2-add-review-btn) muestra el formulario,
	 * que de fábrica llega oculto. Se pulsa mediante click() para respetar la
	 * lógica del tema en vez de forzar estilos por nuestra cuenta.
	 */
	function abrirFormulario() {
		var caja = document.querySelector( '#add-review' );
		if ( ! caja ) {
			return;
		}
		var form = caja.querySelector( 'form, #commentform' );
		if ( form && form.offsetParent !== null ) {
			return; // Ya estaba abierto.
		}
		var boton = caja.querySelector( '.ppv2-add-review-btn' );
		if ( boton ) {
			boton.click();
		}
	}

	/**
	 * Coloca el destino a un tercio de la ventana, por debajo de la cabecera.
	 */
	function posicionar( suave ) {
		var destino = document.querySelector( hash );
		if ( ! destino ) {
			return;
		}
		var margen = alturaFija() + Math.round( window.innerHeight / 3 );
		var y      = destino.getBoundingClientRect().top + window.pageYOffset - margen;

		window.scrollTo( {
			top: Math.max( 0, y ),
			behavior: suave ? 'smooth' : 'auto'
		} );
	}

	/**
	 * ¿Se ve el destino dentro de la ventana, por debajo de lo fijo?
	 */
	function estaALaVista() {
		var destino = document.querySelector( hash );
		if ( ! destino ) {
			return true; // Nada que corregir.
		}
		var r = destino.getBoundingClientRect();
		return r.top >= alturaFija() - 10 && r.top <= window.innerHeight - 60;
	}

	/**
	 * Espera a que el alto de la página se estabilice.
	 *
	 * Se considera estable cuando no cambia en 3 comprobaciones seguidas, con un
	 * tope de ~4 s para no quedarse esperando indefinidamente si algo sigue
	 * moviéndose (anuncios, mapas que recalculan…).
	 */
	function cuandoSeEstabilice( fn ) {
		var ultimo  = -1;
		var iguales = 0;
		var vueltas = 0;

		var reloj = setInterval( function () {
			var alto = document.body.scrollHeight;
			iguales  = ( alto === ultimo ) ? iguales + 1 : 0;
			ultimo   = alto;
			vueltas++;

			if ( iguales >= 3 || vueltas > 40 ) {
				clearInterval( reloj );
				fn();
			}
		}, 100 );
	}

	window.addEventListener( 'load', function () {
		abrirFormulario();

		cuandoSeEstabilice( function () {
			posicionar( true );

			// Una única corrección si el desplazamiento suave se quedó corto
			// (puede pasar si algo terminó de cargar durante la animación).
			setTimeout( function () {
				if ( ! estaALaVista() ) {
					posicionar( false );
				}
			}, 900 );
		} );
	} );
} )();
