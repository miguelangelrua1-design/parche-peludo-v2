/**
 * DIRECTORIO — Conmutador de vistas (grid/lista) visible en móvil.
 *
 * El tema oculta .layout-switcher en ≤768px (display:none). Aquí, en móvil,
 * el conmutador se MUEVE junto al selector "Ordenar por" (.sort-by) y se le
 * pone la clase ppv2-switcher-movil (el CSS del child lo re-muestra y lo
 * alinea a la derecha). Al volver a escritorio regresa a su columna original.
 *
 * Mover el nodo conserva los listeners AJAX que el tema le puso a los
 * enlaces .grid/.list, así que el conmutador sigue funcionando igual.
 * Si la página no tiene conmutador o no tiene "Ordenar por", no hace nada.
 */
(function () {
	'use strict';

	function iniciar() {
		var switcher = document.querySelector( '.layout-switcher' );
		var sortBy = document.querySelector( '.sort-by' );
		if ( ! switcher || ! sortBy ) { return; }

		// Marcador de posición para poder devolverlo exactamente donde estaba.
		var marcador = document.createComment( 'ppv2-switcher-origen' );
		switcher.parentNode.insertBefore( marcador, switcher );

		var mq = window.matchMedia( '(max-width: 768px)' );

		function aplicar() {
			try {
				if ( mq.matches ) {
					if ( switcher.parentNode !== sortBy ) {
						sortBy.appendChild( switcher );
						switcher.classList.add( 'ppv2-switcher-movil' );
						sortBy.classList.add( 'ppv2-sort-con-switcher' );
					}
				} else if ( switcher.classList.contains( 'ppv2-switcher-movil' ) ) {
					switcher.classList.remove( 'ppv2-switcher-movil' );
					sortBy.classList.remove( 'ppv2-sort-con-switcher' );
					marcador.parentNode.insertBefore( switcher, marcador );
				}
			} catch ( e ) {
				// Nunca romper la página de resultados por un detalle visual.
				if ( window.console && console.warn ) { console.warn( '[ppv2-switcher]', e ); }
			}
		}

		if ( mq.addEventListener ) {
			mq.addEventListener( 'change', aplicar );
		} else if ( mq.addListener ) {
			mq.addListener( aplicar ); // Safari viejo
		}
		aplicar();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', iniciar );
	} else {
		iniciar();
	}
})();
