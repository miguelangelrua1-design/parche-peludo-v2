/**
 * PDP de producto (Parche Peludo V2) — editor de cantidad EN VIVO + variaciones.
 *
 * Comportamiento (feedback PO 2026-07-11, revisado 2026-08-01):
 *  · Presentación obligatoria sin elegir → botón deshabilitado (lo pinta el
 *    CSS con las clases nativas de WooCommerce; aquí no forzamos).
 *  · Presentación con una sola opción → se elige sola (ahorra un paso).
 *  · Estados del bloque de compra (pedido del PO 2026-08-01):
 *      - NO está en el carrito: SOLO el botón "Agregar al carrito" (el selector
 *        de cantidad no se muestra; agregar añade 1 unidad).
 *      - YA está en el carrito: el botón se sustituye por el control de
 *        cantidad [caneca/−] N [+]: el "−" y el "+" cambian la cantidad EN el
 *        carrito al instante; con 1 unidad el botón izquierdo es una PAPELERA
 *        que elimina el producto (y vuelve el botón "Agregar al carrito").
 *        N no es editable (readonly): la cantidad se maneja con los botones.
 *  · El mini-cart del header se refresca con el evento wc_fragment_refresh.
 *
 * Todo se apoya en la API nativa (WC()->cart vía admin-ajax) y en los eventos de
 * la variations_form; si el JS fallara, la ficha sigue operando con el formulario
 * nativo (el botón "Añadir al carrito" recarga y agrega igual).
 */
(function ($) {
	'use strict';

	var CFG  = window.PP_PDP || {};
	// PHP serializa un carrito vacío como [] (array); lo normalizamos a objeto
	// para que cart[slot] / delete cart[slot] se comporten como mapa.
	var cart = CFG.cart || {};
	if ( Array.isArray( cart ) ) { cart = {}; }

	var ICON = {
		minus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/></svg>',
		plus:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
		trash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>'
	};

	var $form, $wrap, $input, $btn, btnAddText;

	// --- Selección actual (para variables, la variación válida elegida) --------
	function selection() {
		if ( CFG.isVariable ) {
			var vid = parseInt( $form.find( 'input.variation_id' ).val(), 10 ) || 0;
			return { slot: vid ? String( vid ) : null, variationId: vid, valid: vid > 0 };
		}
		return { slot: 'simple', variationId: 0, valid: true };
	}

	function entryFor( sel ) {
		return ( sel.valid && sel.slot && cart[ sel.slot ] ) ? cart[ sel.slot ] : null;
	}

	// --- Pintado de estados -----------------------------------------------------
	function setLeft( mode ) { // 'hidden' | 'minus' | 'trash'
		var $left = $wrap.find( '.pp-qty-minus' );
		if ( 'hidden' === mode ) {
			$left.addClass( 'pp-hidden' ).attr( 'data-mode', 'none' );
			return;
		}
		$left.removeClass( 'pp-hidden' )
			.attr( 'data-mode', mode )
			.attr( 'aria-label', 'trash' === mode ? 'Eliminar del carrito' : 'Disminuir cantidad' )
			.html( 'trash' === mode ? ICON.trash : ICON.minus );
	}

	function setMain( mode ) { // siempre 'add': restaura texto y estado del botón
		if ( ! $btn || ! $btn.length ) { return; }
		$btn.removeClass( 'pp-view-cart' ).attr( 'data-pp-mode', 'add' );
		if ( btnAddText ) { $btn.text( btnAddText ); }
	}

	// Estados (PO 2026-08-01): SIN entrada en el carrito solo se ve el botón
	// "Agregar al carrito"; CON entrada, el botón se oculta y el control de
	// cantidad [caneca/−] N [+] ocupa su lugar.
	function render() {
		if ( ! $wrap || ! $wrap.length ) { return; }
		var sel   = selection();
		var entry = entryFor( sel );
		if ( entry ) {
			$input.val( entry.qty );
			setLeft( entry.qty >= 2 ? 'minus' : 'trash' );
			// Limpiar el display inline que un .show() de jQuery (tema/Woo)
			// pudo dejar mientras estaba oculta: "display:block" rompía el
			// flex y el número quedaba fuera de sitio (el CSS además blinda
			// con !important, esto evita depender solo de él).
			$wrap.removeClass( 'pp-cc-hide' ).css( 'display', '' );
			if ( $btn && $btn.length ) { $btn.addClass( 'pp-cc-hide' ); }
		} else {
			$input.val( 1 ); // el próximo "Agregar" siempre añade 1
			$wrap.addClass( 'pp-cc-hide' );
			if ( $btn && $btn.length ) { $btn.removeClass( 'pp-cc-hide' ); }
			setMain( 'add' );
		}
	}

	function busy( on ) { $wrap.toggleClass( 'pp-busy', !! on ); }

	var refrescoPropio = false; // para ignorar el eco de nuestros propios avisos

	function refreshMini() {
		refrescoPropio = true;
		$( document.body ).trigger( 'wc_fragment_refresh' );
		setTimeout( function () { refrescoPropio = false; }, 50 );
	}

	// Reconstruye el mapa del carrito desde el SERVIDOR (Store API, nunca
	// cacheada) y re-pinta. Es la red de seguridad cuando el estado local se
	// desincroniza: el producto se eliminó desde el minicart o la página del
	// carrito, una operación falló (key inexistente, nonce), o se volvió a la
	// ficha con el botón Atrás. Sin esto, el stepper quedaba "congelado" con
	// cantidades viejas y sus botones dejaban de responder.
	function syncFromServer() {
		fetch( '/wp-json/wc/store/v1/cart', { credentials: 'same-origin' } )
			.then( function ( r ) { if ( ! r.ok ) { throw new Error( 'HTTP ' + r.status ); } return r.json(); } )
			.then( function ( j ) {
				cart = {};
				( j.items || [] ).forEach( function ( it ) {
					// Producto simple → slot 'simple'; variaciones → slot por id.
					// Se registran TODAS las variaciones del carrito: entryFor()
					// solo consulta las de ESTE producto, el resto es inofensivo.
					if ( it.id === CFG.productId ) { cart.simple = { key: it.key, qty: it.quantity }; }
					cart[ String( it.id ) ] = { key: it.key, qty: it.quantity };
				} );
				render();
			} )
			.catch( function () { /* sin red: no rompemos la ficha */ } );
	}

	function ajax( op, data, done ) {
		busy( true );
		$.post( CFG.ajaxUrl, $.extend( { action: 'pp_pdp_cart', nonce: CFG.nonce, op: op }, data ) )
			.done( function ( res ) {
				busy( false );
				if ( res && res.success ) { done( res.data ); }
				else { syncFromServer(); refreshMini(); } // estado real + minicart
			} )
			.fail( function () { busy( false ); syncFromServer(); } );
	}

	// --- Interacciones ----------------------------------------------------------
	// UI OPTIMISTA + intención final (mismo patrón que las tarjetas): la
	// pantalla responde al instante y, tras una pausa de 350 ms, viaja UNA
	// sola petición con el estado final (una ráfaga de −/+/caneca no genera
	// peticiones intermedias ni puede llegar al servidor en orden invertido).
	var opTimers = {};
	function scheduleCartOp( slot, key ) {
		if ( opTimers[ slot ] ) { clearTimeout( opTimers[ slot ] ); }
		opTimers[ slot ] = setTimeout( function () {
			delete opTimers[ slot ];
			var entry = cart[ slot ];
			if ( ! entry ) {
				ajax( 'remove', { key: key }, function () { render(); refreshMini(); } );
			} else {
				ajax( 'set', { key: entry.key, qty: entry.qty }, function ( d ) {
					cart[ slot ] = { key: d.key, qty: d.qty };
					render();
					refreshMini();
				} );
			}
		}, 350 );
	}

	$( document ).on( 'click', '.single-product .pp-qty-minus', function ( e ) {
		e.preventDefault();
		var sel = selection(), entry = entryFor( sel ), mode = $( this ).attr( 'data-mode' );
		if ( entry && 'trash' === mode ) {
			var key = entry.key;
			delete cart[ sel.slot ]; // optimista: vuelve "Agregar al carrito" YA
			$input.val( 1 );
			render();
			scheduleCartOp( sel.slot, key );
		} else if ( entry && 'minus' === mode ) {
			cart[ sel.slot ] = { key: entry.key, qty: entry.qty - 1 }; // optimista
			render();
			scheduleCartOp( sel.slot, entry.key );
		} else {
			$input.val( Math.max( 1, ( parseInt( $input.val(), 10 ) || 1 ) - 1 ) );
			render();
		}
	} );

	$( document ).on( 'click', '.single-product .pp-qty-plus', function ( e ) {
		e.preventDefault();
		var sel = selection(), entry = entryFor( sel );
		if ( entry ) {
			cart[ sel.slot ] = { key: entry.key, qty: entry.qty + 1 }; // optimista
			render();
			scheduleCartOp( sel.slot, entry.key );
		} else {
			$input.val( ( parseInt( $input.val(), 10 ) || 1 ) + 1 );
			render();
		}
	} );

	// Botón grande: "Añadir al carrito" (AJAX). Tras agregar, render() lo oculta
	// y muestra el control de cantidad en su lugar.
	$( document ).on( 'click', '.single-product .single_add_to_cart_button', function ( e ) {
		var $b = $( this );
		// Sin variación válida o deshabilitado: dejar que WooCommerce avise.
		if ( $b.hasClass( 'disabled' ) || $b.hasClass( 'wc-variation-selection-needed' ) ) { return; }
		var sel = selection();
		if ( ! sel.valid ) { return; }
		e.preventDefault();
		var variation = {};
		$form.find( '.variations select' ).each( function () {
			var name = $( this ).attr( 'name' ) || $( this ).data( 'attribute_name' );
			if ( name ) { variation[ name ] = $( this ).val(); }
		} );
		ajax( 'add', {
			product_id:   CFG.productId,
			variation_id: sel.variationId,
			qty:          parseInt( $input.val(), 10 ) || 1,
			variation:    variation
		}, function ( d ) {
			cart[ sel.slot ] = { key: d.key, qty: d.qty };
			render();
			refreshMini();
		} );
	} );

	// Edición manual del número (solo relevante cuando NO está en el carrito).
	$( document ).on( 'change input', '.single-product .pp-qty-enhanced input.qty', function () {
		if ( ! entryFor( selection() ) ) { render(); }
	} );

	// --- Montaje ----------------------------------------------------------------
	function enhance() {
		$wrap = $( '.single-product div.product .quantity' ).first();
		if ( ! $wrap.length || $wrap.hasClass( 'pp-qty-enhanced' ) ) { return; }
		$input = $wrap.find( 'input.qty' );
		if ( ! $input.length ) { return; }
		$wrap.addClass( 'pp-qty-enhanced' );
		// La cantidad no es editable a mano (PO 2026-08-01): se maneja solo con
		// los botones. readonly (y no disabled) para que el valor sí viaje al
		// enviar el formulario nativo si el JS de carrito fallara.
		$input.attr( 'readonly', 'readonly' ).attr( 'aria-live', 'polite' );
		$input.before( '<button type="button" class="pp-qty-btn pp-qty-minus" aria-label="Disminuir cantidad">' + ICON.minus + '</button>' );
		$input.after( '<button type="button" class="pp-qty-btn pp-qty-plus" aria-label="Aumentar cantidad">' + ICON.plus + '</button>' );
	}

	function autoSelectSingle() {
		if ( ! CFG.isVariable || ! $form.length ) { return; }
		$form.find( '.variations select' ).each( function () {
			var $sel  = $( this );
			var $real = $sel.find( 'option' ).filter( function () { return $( this ).val() !== ''; } );
			if ( 1 === $real.length && $sel.val() !== $real.val() ) {
				$sel.val( $real.val() ).trigger( 'change' );
			}
		} );
	}

	// --- Presentaciones como PASTILLAS (en vez del select nativo) --------------
	// El select QUEDA en el DOM (oculto por CSS con la clase pp-pills-on): las
	// pastillas solo escriben en él y disparan 'change', así toda la maquinaria
	// nativa de variaciones (precio, stock, variation_id, carrito) sigue igual.
	// Si este JS fallara, el select vuelve a verse y la ficha opera normal.

	function variationsData() {
		if ( ! $form.length ) { return []; }
		var d = $form.data( 'product_variations' );
		return ( d && d.length ) ? d : []; // false = modo AJAX (30+ variaciones)
	}

	function buildPills() {
		if ( ! CFG.isVariable || ! $form.length || $form.hasClass( 'pp-pills-on' ) ) { return; }
		var built = false;
		$form.find( '.variations select' ).each( function () {
			var $sel = $( this );
			if ( $sel.next( '.pp-pres-pills' ).length ) { return; }
			// Recolectar las opciones con su gramaje (número del texto: "4 LB"→4).
			var opts = [];
			$sel.find( 'option' ).each( function () {
				var v = $( this ).val();
				if ( '' === v ) { return; }
				var txt = $( this ).text();
				var m   = txt.replace( ',', '.' ).match( /[\d.]+/ );
				opts.push( { value: v, text: txt, num: m ? parseFloat( m[0] ) : NaN } );
			} );
			// Ordenar de MENOR a MAYOR gramaje (decisión del PO) SOLO si todas las
			// presentaciones son numéricas (por peso); si alguna no lo es (talla,
			// color…), se conserva el orden original de WooCommerce.
			if ( opts.length && opts.every( function ( o ) { return ! isNaN( o.num ); } ) ) {
				opts.sort( function ( a, b ) { return a.num - b.num; } );
			}
			var html = '';
			opts.forEach( function ( o ) {
				html += '<button type="button" class="pp-pres-pill" data-value="' + o.value.replace( /"/g, '&quot;' ) + '" aria-pressed="false">' + o.text + '</button>';
			} );
			if ( html ) {
				$sel.after( '<div class="pp-pres-pills" role="group">' + html + '</div>' );
				built = true;
			}
		} );
		if ( built ) { $form.addClass( 'pp-pills-on' ); }
	}

	function syncPills() {
		if ( ! $form.hasClass( 'pp-pills-on' ) ) { return; }
		var data = variationsData();
		$form.find( '.variations select' ).each( function () {
			var $sel  = $( this );
			var name  = $sel.data( 'attribute_name' ) || $sel.attr( 'name' );
			var cur   = $sel.val();
			$sel.next( '.pp-pres-pills' ).find( '.pp-pres-pill' ).each( function () {
				var $p  = $( this );
				var val = $p.attr( 'data-value' );
				$p.toggleClass( 'is-active', val === cur ).attr( 'aria-pressed', val === cur ? 'true' : 'false' );
				// Sin stock → pastilla atenuada y sin clic (solo si hay datos).
				if ( data.length ) {
					var hay = data.some( function ( v ) {
						var a = v.attributes[ name ];
						return ( '' === a || a === val ) && v.is_in_stock && false !== v.is_purchasable;
					} );
					$p.toggleClass( 'is-off', ! hay );
				}
			} );
		} );
	}

	// Presentación más ECONÓMICA disponible seleccionada por defecto.
	function autoSelectCheapest() {
		if ( ! CFG.isVariable || ! $form.length ) { return; }
		// ¿Ya hay selección completa? No pisar lo que eligió el usuario.
		var falta = false;
		$form.find( '.variations select' ).each( function () { if ( ! $( this ).val() ) { falta = true; } } );
		if ( ! falta ) { syncPills(); return; }

		var data = variationsData().filter( function ( v ) {
			return v.is_in_stock && false !== v.is_purchasable;
		} );
		if ( ! data.length ) { autoSelectSingle(); syncPills(); return; }

		var best = data.reduce( function ( a, b ) {
			return ( parseFloat( b.display_price ) < parseFloat( a.display_price ) ) ? b : a;
		} );
		$form.find( '.variations select' ).each( function () {
			var $sel = $( this );
			var name = $sel.data( 'attribute_name' ) || $sel.attr( 'name' );
			var val  = best.attributes[ name ];
			if ( '' === val || null == val ) {
				// "Cualquiera": toma la primera opción real.
				val = $sel.find( 'option' ).filter( function () { return $( this ).val() !== ''; } ).first().val();
			}
			if ( $sel.val() !== val ) { $sel.val( val ).trigger( 'change' ); }
		} );
		syncPills();
	}

	// Clic en pastilla → escribe en el select nativo y dispara su change.
	$( document ).on( 'click', '.single-product form.cart .pp-pres-pill', function ( e ) {
		e.preventDefault();
		var $p = $( this );
		if ( $p.hasClass( 'is-active' ) || $p.hasClass( 'is-off' ) ) { return; }
		$p.closest( '.pp-pres-pills' ).prev( 'select' ).val( $p.attr( 'data-value' ) ).trigger( 'change' );
	} );

	// Precio ÚNICO que cambia con la presentación: al elegir variación se pinta
	// su precio en el p.price principal (el bloque nativo de precio de variación
	// queda oculto por CSS para no duplicar).
	var precioOriginal = null;

	$( function () {
		$form = $( '.single-product form.cart' ).first();
		$btn  = $( '.single-product .single_add_to_cart_button' ).first();
		if ( $btn.length ) { btnAddText = $.trim( $btn.text() ) || ( CFG.i18n ? CFG.i18n.add : 'Agregar al carrito' ); }
		enhance();

		if ( CFG.isVariable && $form.length ) {
			var $price = $( '.single-product div.product p.price' ).first();
			precioOriginal = $price.length ? $price.html() : null;

			$form.on( 'found_variation', function ( e, variation ) {
				if ( variation && variation.price_html && $price.length ) {
					$price.html( variation.price_html );
				}
			} );
			$form.on( 'reset_data', function () {
				if ( null !== precioOriginal && $price.length ) { $price.html( precioOriginal ); }
				syncPills();
			} );
			$form.on( 'show_variation', function () {
				enhance();
				$btn = $( '.single-product .single_add_to_cart_button' ).first();
				render();
				syncPills();
			} );
			$form.on( 'hide_variation', function () { render(); syncPills(); } );
			$form.find( '.variations select' ).on( 'change', function () { syncPills(); } );

			buildPills();
			autoSelectCheapest();
		}
		montarWhatsApp();
		render();
		// El estado que viene en el HTML (PP_PDP.cart) puede estar VIEJO si la
		// página llegó de una caché (pública o privada): la ficha decía
		// "Agregar al carrito" con el producto ya en el carrito. La verdad se
		// pide siempre al servidor al cargar (Store API, nunca cacheada).
		syncFromServer();
	} );

	// Reintento tras la carga completa (la variations_form inicializa en su
	// propio ready; asegura pastillas + selección económica + primer render).
	$( window ).on( 'load', function () {
		buildPills();
		autoSelectCheapest();
		render();
	} );

	// --- Comprar por WhatsApp (PO 2026-08-01) -------------------------------
	// Botón bajo "Agregar al carrito" que abre el WhatsApp de Parche Peludo
	// con un mensaje que lleva el nombre del producto y su SKU. El SKU se lee
	// del DOM AL MOMENTO del clic: en productos variables WooCommerce lo
	// actualiza al elegir presentación, así el mensaje lleva el código de la
	// variación seleccionada. Sin PHP: vive en este archivo y en style.css.
	var WA_NUMERO = '573012773594';

	function waMensaje() {
		var nombre = $.trim( $( '.single-product h1.product_title' ).first().text() ) || document.title.split( '|' )[0].trim();
		var sku    = $.trim( $( '.single-product .product_meta .sku' ).first().text() );
		var msg    = 'Hola, me interesa el producto "' + nombre + '"';
		if ( sku && 'N/A' !== sku ) { msg += ' con código "' + sku + '"'; }
		return msg;
	}

	function waUrl() {
		return 'https://wa.me/' + WA_NUMERO + '?text=' + encodeURIComponent( waMensaje() );
	}

	function montarWhatsApp() {
		if ( ! $form || ! $form.length || $( '.pp-wa-comprar' ).length ) { return; }
		var $bloque = $(
			'<div class="pp-wa-bloque">' +
				'<a class="pp-wa-comprar" target="_blank" rel="noopener nofollow" href="#">' +
					'<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>' +
					'<span>Comprar por WhatsApp</span>' +
				'</a>' +
				'<p class="pp-wa-nota">Te atendemos en minutos</p>' +
			'</div>'
		);
		$form.after( $bloque );
	}

	// La URL se construye al momento del clic (SKU de la variación actual).
	$( document ).on( 'click', '.pp-wa-comprar', function () {
		$( this ).attr( 'href', waUrl() );
	} );

	// El carrito puede cambiar por FUERA de la ficha (drawer del minicart,
	// página del carrito): cualquier anuncio de fragmentos re-sincroniza el
	// estado desde el servidor (saltando el eco de los avisos propios).
	var pdpSyncTimer = null;
	$( document.body ).on( 'wc_fragments_refreshed wc_fragment_refresh removed_from_cart', function () {
		if ( refrescoPropio ) { return; }
		if ( pdpSyncTimer ) { clearTimeout( pdpSyncTimer ); }
		pdpSyncTimer = setTimeout( function () { pdpSyncTimer = null; syncFromServer(); }, 250 );
	} );

	// Volver con el botón Atrás (bfcache): revive el estado congelado → sync.
	window.addEventListener( 'pageshow', function ( e ) {
		if ( e.persisted ) { syncFromServer(); }
	} );
})( jQuery );
