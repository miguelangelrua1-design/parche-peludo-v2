/**
 * PDP de producto (Parche Peludo V2) — editor de cantidad EN VIVO + variaciones.
 *
 * Comportamiento (feedback PO 2026-07-11):
 *  · Presentación obligatoria sin elegir → cantidad y botón deshabilitados
 *    (lo pinta el CSS con las clases nativas de WooCommerce; aquí no forzamos).
 *  · Presentación con una sola opción → se elige sola (ahorra un paso).
 *  · Selector de cantidad conectado al carrito:
 *      - NO está en el carrito: solo el botón "+"; el "−" aparece al llegar a 2.
 *        "Añadir al carrito" agrega esa cantidad (AJAX, sin recargar).
 *      - YA está en el carrito: el "−" y el "+" cambian la cantidad EN el carrito
 *        al instante; cuando queda en 1, el botón izquierdo es una PAPELERA que
 *        elimina el producto del carrito. El botón grande pasa a "Ver carrito".
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

	function setMain( mode ) { // 'add' | 'view'
		if ( ! $btn || ! $btn.length ) { return; }
		if ( 'view' === mode ) {
			$btn.addClass( 'pp-view-cart' ).attr( 'data-pp-mode', 'view' ).text( CFG.i18n ? CFG.i18n.view : 'Ver carrito' );
		} else {
			$btn.removeClass( 'pp-view-cart' ).attr( 'data-pp-mode', 'add' );
			if ( btnAddText ) { $btn.text( btnAddText ); }
		}
	}

	function render() {
		if ( ! $wrap || ! $wrap.length ) { return; }
		var sel   = selection();
		var entry = entryFor( sel );
		if ( entry ) {
			$input.val( entry.qty );
			setLeft( entry.qty >= 2 ? 'minus' : 'trash' );
			setMain( 'view' );
		} else {
			var q = parseInt( $input.val(), 10 ) || 1;
			if ( q < 1 ) { q = 1; $input.val( 1 ); }
			setLeft( q >= 2 ? 'minus' : 'hidden' );
			setMain( 'add' );
		}
	}

	function busy( on ) { $wrap.toggleClass( 'pp-busy', !! on ); }

	function refreshMini() { $( document.body ).trigger( 'wc_fragment_refresh' ); }

	function ajax( op, data, done ) {
		busy( true );
		$.post( CFG.ajaxUrl, $.extend( { action: 'pp_pdp_cart', nonce: CFG.nonce, op: op }, data ) )
			.done( function ( res ) {
				busy( false );
				if ( res && res.success ) { done( res.data ); }
				else { refreshMini(); }
			} )
			.fail( function () { busy( false ); } );
	}

	// --- Interacciones ----------------------------------------------------------
	$( document ).on( 'click', '.single-product .pp-qty-minus', function ( e ) {
		e.preventDefault();
		var sel = selection(), entry = entryFor( sel ), mode = $( this ).attr( 'data-mode' );
		if ( entry && 'trash' === mode ) {
			ajax( 'remove', { key: entry.key }, function () {
				delete cart[ sel.slot ];
				$input.val( 1 );
				render();
				refreshMini();
			} );
		} else if ( entry && 'minus' === mode ) {
			ajax( 'set', { key: entry.key, qty: entry.qty - 1 }, function ( d ) {
				cart[ sel.slot ] = { key: d.key, qty: d.qty };
				render();
				refreshMini();
			} );
		} else {
			$input.val( Math.max( 1, ( parseInt( $input.val(), 10 ) || 1 ) - 1 ) );
			render();
		}
	} );

	$( document ).on( 'click', '.single-product .pp-qty-plus', function ( e ) {
		e.preventDefault();
		var sel = selection(), entry = entryFor( sel );
		if ( entry ) {
			ajax( 'set', { key: entry.key, qty: entry.qty + 1 }, function ( d ) {
				cart[ sel.slot ] = { key: d.key, qty: d.qty };
				render();
				refreshMini();
			} );
		} else {
			$input.val( ( parseInt( $input.val(), 10 ) || 1 ) + 1 );
			render();
		}
	} );

	// Botón grande: "Añadir al carrito" (AJAX) o "Ver carrito" según el estado.
	$( document ).on( 'click', '.single-product .single_add_to_cart_button', function ( e ) {
		var $b = $( this );
		if ( 'view' === $b.attr( 'data-pp-mode' ) ) {
			e.preventDefault();
			window.location.href = CFG.cartUrl;
			return;
		}
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

	$( function () {
		$form = $( '.single-product form.cart' ).first();
		$btn  = $( '.single-product .single_add_to_cart_button' ).first();
		if ( $btn.length ) { btnAddText = $.trim( $btn.text() ) || ( CFG.i18n ? CFG.i18n.add : 'Añadir al carrito' ); }
		enhance();

		if ( CFG.isVariable && $form.length ) {
			$form.on( 'show_variation', function () {
				enhance();
				$btn = $( '.single-product .single_add_to_cart_button' ).first();
				render();
			} );
			$form.on( 'hide_variation', function () { render(); } );
			autoSelectSingle();
		}
		render();
	} );

	// Reintento tras la carga completa (la variations_form inicializa en su
	// propio ready; asegura la auto-selección y el primer render correcto).
	$( window ).on( 'load', function () {
		autoSelectSingle();
		render();
	} );
})( jQuery );
