/**
 * CHECKOUT — Ciudad como desplegable dependiente del Departamento.
 *
 * Contexto: checkout de BLOQUES de WooCommerce (React). No se puede convertir
 * el campo Ciudad en <select> por PHP, así que se superpone uno propio:
 *
 *  - El <input id="billing-city"> original QUEDA en el DOM (oculto por CSS via
 *    :has). Sigue siendo la fuente de verdad de React y lo que viaja al
 *    servidor. Nuestro select solo escribe en él usando el setter nativo del
 *    prototipo + eventos input/change/focusout, que es como React acepta
 *    valores externos (misma técnica que usan las herramientas de testing).
 *  - Si el departamento no tiene datos, o cualquier cosa falla, el select se
 *    esconde y el input de texto vuelve a verse: el checkout nunca se bloquea.
 *  - React re-renderiza el formulario cuando quiere (validación, cambio de
 *    país…) y puede recrear los nodos; un MutationObserver con debounce
 *    reconstruye/resincroniza el select cuando eso pasa.
 *  - Se replica la estructura del select de Departamento (contenedor
 *    wc-blocks-components-select + label flotante + flecha) para heredar el
 *    estilo del bloque sin CSS a medida.
 */
(function () {
	'use strict';

	var DATA = window.ppCiudadesCO;
	if ( ! DATA ) { return; }

	var desc = Object.getOwnPropertyDescriptor( window.HTMLInputElement.prototype, 'value' );
	if ( ! desc || ! desc.set ) { return; }
	var setNativo = desc.set;

	var FLECHA = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24" class="wc-blocks-components-select__expand" aria-hidden="true" focusable="false"><path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path></svg>';

	// El dataset se indexa por NOMBRE de departamento normalizado (minúsculas,
	// sin acentos), no por código: los códigos dependen del plugin activo
	// (MercadoPago registra AMZ/ANT/GUJ…, WooCommerce core usa CO-AMA/CO-ANT…)
	// y cambian; el nombre visible es estable. Alias para las variantes vistas.
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

	// Ciudades del departamento seleccionado, resolviendo por la ETIQUETA de la
	// opción elegida (su value es el código, que varía según el plugin).
	function ciudadesDe( estado ) {
		if ( ! estado.value ) { return []; }
		var op = estado.options[ estado.selectedIndex ];
		var nombre = normalizar( op ? op.textContent : '' );
		return DATA[ nombre ] || [];
	}

	// Escribe en el input de React y dispara los eventos que el bloque escucha
	// (input = valor controlado; focusout = persistencia/validación al salir).
	function escribirEnInput( input, valor ) {
		if ( input.value === valor ) { return; }
		setNativo.call( input, valor );
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		input.dispatchEvent( new FocusEvent( 'focusout', { bubbles: true } ) );
	}

	function construir( prefijo ) {
		var input = document.getElementById( prefijo + '-city' );
		var estado = document.getElementById( prefijo + '-state' );
		if ( ! input || ! estado || input.tagName !== 'INPUT' || estado.tagName !== 'SELECT' ) { return; }
		var celda = input.closest( '.wc-block-components-address-form__city' );
		if ( ! celda ) { return; }

		var wrap = celda.querySelector( '.ppv2-ciudad-wrap' );
		var select;

		if ( ! wrap ) {
			wrap = document.createElement( 'div' );
			wrap.className = 'wc-blocks-components-select ppv2-ciudad-wrap';
			var cont = document.createElement( 'div' );
			cont.className = 'wc-blocks-components-select__container';
			var label = document.createElement( 'label' );
			label.className = 'wc-blocks-components-select__label';
			label.htmlFor = prefijo + '-city-ppv2';
			label.textContent = 'Ciudad / Municipio';
			select = document.createElement( 'select' );
			select.className = 'wc-blocks-components-select__select ppv2-ciudad-select';
			select.id = prefijo + '-city-ppv2';
			select.setAttribute( 'size', '1' );
			cont.appendChild( label );
			cont.appendChild( select );
			cont.insertAdjacentHTML( 'beforeend', FLECHA );
			wrap.appendChild( cont );
			celda.insertBefore( wrap, celda.firstChild );

			// IMPORTANTE: ningún listener guarda referencias a los nodos de
			// React. React RECREA el input/select de dirección al re-renderizar
			// (p. ej. tras una validación) y una referencia capturada quedaría
			// apuntando a un nodo muerto: las escrituras se perderían en
			// silencio. Nuestro select sí es nuestro (referencia estable).
			select.addEventListener( 'change', function () {
				var inp = document.getElementById( prefijo + '-city' );
				if ( inp ) { escribirEnInput( inp, select.value ); }
				wrap.classList.toggle( 'ppv2-ciudad-vacia', ! select.value );
			} );
		}

		llenar( prefijo );
	}

	// Listeners DELEGADOS en document (una sola vez): sobreviven a que React
	// recree los nodos, porque resuelven el elemento en el momento del evento.
	document.addEventListener( 'change', function ( e ) {
		var id = e.target && e.target.id;
		if ( id === 'billing-state' ) { llenar( 'billing' ); }
		if ( id === 'shipping-state' ) { llenar( 'shipping' ); }
	}, true );
	document.addEventListener( 'input', function ( e ) {
		var id = e.target && e.target.id;
		// Autorrelleno del navegador sobre el input oculto → reflejarlo.
		if ( id === 'billing-city' ) { sincronizar( 'billing' ); }
		if ( id === 'shipping-city' ) { sincronizar( 'shipping' ); }
	}, true );

	// (Re)construye las opciones según el departamento elegido.
	function llenar( prefijo ) {
		var input = document.getElementById( prefijo + '-city' );
		var estado = document.getElementById( prefijo + '-state' );
		var celda = input && input.closest( '.wc-block-components-address-form__city' );
		var wrap = celda && celda.querySelector( '.ppv2-ciudad-wrap' );
		if ( ! wrap ) { return; }
		var select = wrap.querySelector( 'select' );

		var ciudades = ciudadesDe( estado );
		if ( ! ciudades.length ) {
			// Sin datos para ese departamento → input de texto normal.
			if ( ! wrap.hidden ) { wrap.hidden = true; }
			return;
		}
		if ( wrap.hidden ) { wrap.hidden = false; }

		// Reconstruir SOLO si el departamento cambió. llenar() corre en cada
		// pasada del MutationObserver; si reconstruyera siempre, cada rebuild
		// dispararía otra mutación → bucle infinito de trabajo cada 150 ms.
		if ( select.dataset.ppv2Depto === estado.value && select.options.length > 1 ) {
			sincronizar( prefijo );
			return;
		}
		select.dataset.ppv2Depto = estado.value;

		var actual = input.value;
		select.innerHTML = '';
		// Opción placeholder VACÍA y oculta: con texto se pintaba encima de la
		// etiqueta flotante. El patrón del bloque para "campo vacío" es que la
		// ETIQUETA haga de placeholder (grande y centrada — lo pone el CSS con
		// la clase ppv2-ciudad-vacia en el wrap), igual que sus inputs de texto.
		var ph = document.createElement( 'option' );
		ph.value = '';
		ph.textContent = '';
		ph.disabled = true;
		ph.hidden = true;
		select.appendChild( ph );
		for ( var i = 0; i < ciudades.length; i++ ) {
			var op = document.createElement( 'option' );
			op.value = ciudades[ i ];
			op.textContent = ciudades[ i ];
			select.appendChild( op );
		}
		if ( actual && ciudades.indexOf( actual ) !== -1 ) {
			select.value = actual;
		} else {
			select.selectedIndex = 0;
			if ( actual ) {
				// Ciudad de otro departamento → limpiar para no enviar pares
				// incoherentes (p. ej. Medellín + Cundinamarca).
				escribirEnInput( input, '' );
			}
		}
		wrap.classList.toggle( 'ppv2-ciudad-vacia', ! select.value );
	}

	// Tras re-render o autofill: el store de React manda; alinear el select.
	function sincronizar( prefijo ) {
		var input = document.getElementById( prefijo + '-city' );
		var celda = input && input.closest( '.wc-block-components-address-form__city' );
		var wrap = celda && celda.querySelector( '.ppv2-ciudad-wrap' );
		if ( ! wrap || wrap.hidden ) { return; }
		var select = wrap.querySelector( 'select' );
		if ( input.value !== select.value ) {
			// Match exacto y, si no, NORMALIZADO: el autofill del navegador
			// puede escribir "BELLO"/"bello" y sin esto el usuario veía una
			// ciudad en el select mientras al servidor viajaba otra.
			var exacta = null;
			var normalizada = null;
			var meta = normalizar( input.value );
			[].forEach.call( select.options, function ( o ) {
				if ( ! o.value ) { return; }
				if ( o.value === input.value ) { exacta = o.value; }
				if ( ! normalizada && normalizar( o.value ) === meta ) { normalizada = o.value; }
			} );
			if ( exacta ) {
				select.value = exacta;
			} else if ( normalizada ) {
				select.value = normalizada;
				// Devolver al input la forma canónica ("Bello"): es lo que
				// viaja al servidor y lo que el usuario ve, ahora idénticos.
				escribirEnInput( input, normalizada );
			} else if ( input.value ) {
				// Ciudad que no está en el catálogo del departamento: mostrar
				// el input nativo para no ocultarle al usuario lo que se envía.
				wrap.hidden = true;
				return;
			}
			wrap.classList.toggle( 'ppv2-ciudad-vacia', ! select.value );
		}
	}

	function pasada() {
		try {
			construir( 'billing' );
			construir( 'shipping' );
		} catch ( e ) {
			// Nunca romper el checkout: sin select se queda el input nativo.
			if ( window.console && console.warn ) { console.warn( '[ppv2-ciudad]', e ); }
		}
	}

	// Arranque: el bloque monta sus campos de forma asíncrona.
	var intentos = 0;
	var arranque = setInterval( function () {
		intentos++;
		if ( document.getElementById( 'billing-city' ) || document.getElementById( 'shipping-city' ) || intentos > 80 ) {
			clearInterval( arranque );
			if ( intentos <= 80 ) {
				pasada();
				vigilar();
			}
		}
	}, 250 );

	// React puede recrear nodos en cualquier re-render → reconstruir con calma.
	function vigilar() {
		var raiz = document.querySelector( '.wp-block-woocommerce-checkout' ) || document.body;
		var timer = null;
		new MutationObserver( function () {
			if ( timer ) { return; }
			timer = setTimeout( function () {
				timer = null;
				pasada();
				sincronizar( 'billing' );
				sincronizar( 'shipping' );
			}, 150 );
		} ).observe( raiz, { childList: true, subtree: true } );
	}
})();
