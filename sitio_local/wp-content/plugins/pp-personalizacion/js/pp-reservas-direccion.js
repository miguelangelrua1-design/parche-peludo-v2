/**
 * DIRECCIÓN con ciudad dependiente — modal de reserva (Booking Plus) y
 * sección "Dirección" de Mi Perfil.
 *
 * Mejora progresiva: el campo Ciudad se renderiza en PHP como <input> de
 * texto y aquí se CONVIERTE en <select> con los municipios del departamento
 * elegido (dataset ppv2-ciudades-co.js, indexado por nombre de departamento
 * normalizado — los códigos varían según el plugin activo). Si este script no
 * corre o el dataset falta, queda el input de texto y el flujo de reserva
 * funciona exactamente igual: nada depende de este archivo.
 *
 * También conecta el botón "Editar" del resumen de dirección de la modal.
 * Aquí no hay React (la modal es HTML servido): reemplazos de DOM directos.
 */
(function () {
	'use strict';

	var DATA = window.ppCiudadesCO || null;

	var ALIAS = {
		'valle': 'valle del cauca',
		'bogota d.c.': 'bogota',
		'distrito capital': 'bogota',
		'capital district': 'bogota'
	};

	function normalizar( s ) {
		s = ( s || '' ).normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' ).trim().toLowerCase();
		return ALIAS[ s ] || s;
	}

	function ciudadesDe( selEstado ) {
		if ( ! DATA || ! selEstado || selEstado.tagName !== 'SELECT' || ! selEstado.value ) { return []; }
		var op = selEstado.options[ selEstado.selectedIndex ];
		return DATA[ normalizar( op ? op.textContent : '' ) ] || [];
	}

	/**
	 * Convierte el input de ciudad en un select dependiente del departamento.
	 * Conserva id, name, required y el valor actual. Si el departamento no
	 * tiene datos, restaura/mantiene el input de texto.
	 */
	function vincular( idEstado, idCiudad ) {
		var estado = document.getElementById( idEstado );
		var ciudad = document.getElementById( idCiudad );
		if ( ! estado || ! ciudad || ! DATA ) { return; }

		function reconstruir() {
			var actual = document.getElementById( idCiudad );
			var ciudades = ciudadesDe( estado );

			if ( ! ciudades.length ) {
				// Sin datos → input de texto (por si venía siendo select).
				if ( actual.tagName === 'SELECT' ) {
					var inp = document.createElement( 'input' );
					inp.type = 'text';
					inp.id = actual.id;
					inp.name = actual.name;
					inp.value = '';
					if ( actual.required ) { inp.required = true; }
					actual.parentNode.replaceChild( inp, actual );
				}
				return;
			}

			var valor = actual.value;
			var sel;
			if ( actual.tagName === 'SELECT' ) {
				sel = actual;
				sel.innerHTML = '';
			} else {
				sel = document.createElement( 'select' );
				sel.id = actual.id;
				sel.name = actual.name;
				if ( actual.required ) { sel.required = true; }
				actual.parentNode.replaceChild( sel, actual );
			}
			var ph = document.createElement( 'option' );
			ph.value = '';
			ph.textContent = 'Selecciona tu ciudad…';
			sel.appendChild( ph );
			for ( var i = 0; i < ciudades.length; i++ ) {
				var o = document.createElement( 'option' );
				o.value = ciudades[ i ];
				o.textContent = ciudades[ i ];
				sel.appendChild( o );
			}
			// Conservar la ciudad si pertenece al departamento elegido; si el
			// valor guardado no está en la lista (dato viejo/libre), se añade
			// como opción para no perder la información del usuario.
			if ( valor ) {
				if ( ciudades.indexOf( valor ) !== -1 ) {
					sel.value = valor;
				} else {
					var extra = document.createElement( 'option' );
					extra.value = valor;
					extra.textContent = valor;
					sel.appendChild( extra );
					sel.value = valor;
				}
			}
		}

		estado.addEventListener( 'change', function () {
			// Al cambiar de departamento la ciudad anterior deja de valer.
			var actual = document.getElementById( idCiudad );
			if ( actual ) { actual.value = ''; }
			reconstruir();
		} );
		reconstruir();
	}

	function iniciar() {
		try {
			// Modal de reserva (existe solo en fichas con reserva activa).
			vincular( 'lbp-billing_state', 'lbp-billing_city' );

			// Botones Editar/Cancelar del resumen de dirección de la modal.
			var editar = document.getElementById( 'ppv2-lbp-addr-editar' );
			var cancelar = document.getElementById( 'ppv2-lbp-addr-cancelar' );
			var resumen = document.getElementById( 'ppv2-lbp-addr-resumen' );
			var campos = document.getElementById( 'ppv2-lbp-addr-campos' );

			// Los valores GUARDADOS (los que describe el resumen). Se capturan
			// una sola vez al cargar: son los que el PHP prellenó desde el
			// perfil del usuario.
			function leerDireccion() {
				var ids = [ 'lbp-billing_state', 'lbp-billing_city', 'lbp-billing_address_1', 'lbp-billing_address_2' ];
				var out = {};
				ids.forEach( function ( id ) {
					var el = document.getElementById( id );
					out[ id ] = el ? el.value : '';
				} );
				return out;
			}
			var guardada = ( resumen && campos ) ? leerDireccion() : null;

			if ( editar && campos ) {
				editar.addEventListener( 'click', function () {
					campos.hidden = false;
					if ( resumen ) { resumen.hidden = true; }
					editar.setAttribute( 'aria-expanded', 'true' );
					var primero = campos.querySelector( 'select, input:not([type=hidden])' );
					if ( primero ) { primero.focus(); }
				} );
			}
			if ( cancelar && campos && guardada ) {
				cancelar.addEventListener( 'click', function () {
					// Restaurar ANTES de cerrar: un colapso a secas dejaría el
					// resumen mostrando una dirección distinta de la que
					// viajaría en la reserva.
					var st = document.getElementById( 'lbp-billing_state' );
					if ( st && st.value !== guardada[ 'lbp-billing_state' ] ) {
						st.value = guardada[ 'lbp-billing_state' ];
						// Reconstruye el desplegable de ciudades del depto guardado.
						st.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					}
					[ 'lbp-billing_city', 'lbp-billing_address_1', 'lbp-billing_address_2' ].forEach( function ( id ) {
						var el = document.getElementById( id );
						if ( el ) { el.value = guardada[ id ]; }
					} );
					campos.hidden = true;
					if ( resumen ) { resumen.hidden = false; }
					if ( editar ) {
						editar.setAttribute( 'aria-expanded', 'false' );
						editar.focus();
					}
				} );
			}

			// Sección "Dirección" de Mi Perfil.
			vincular( 'ppv2-perfil-state', 'ppv2-perfil-city' );
		} catch ( e ) {
			// Nunca romper el flujo de reserva: sin select queda texto libre.
			if ( window.console && console.warn ) { console.warn( '[ppv2-lbp-direccion]', e ); }
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', iniciar );
	} else {
		iniciar();
	}
})();
