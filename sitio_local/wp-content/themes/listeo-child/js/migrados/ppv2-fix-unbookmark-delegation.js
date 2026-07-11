/* Migrado de functions.php::ppv2_fix_unbookmark_delegation() 2026-07-10 (wp_footer, prio 130).
   Condición de páginas: global (sin condición). Requiere jQuery y el objeto global `listeo` (listeo.ajaxurl). */
jQuery(function ($) {
	if ( typeof listeo === 'undefined' || ! listeo.ajaxurl ) { return; }

	// Manejador DELEGADO de "quitar favorito" (funciona en corazones recién marcados).
	$( document ).off( 'click.ppFav' ).on( 'click.ppFav', '.listeo_core-unbookmark-it', function ( e ) {
		e.preventDefault();
		var handler = $( this );
		if ( handler.data( 'ppBusy' ) ) { return; }
		handler.data( 'ppBusy', true );
		handler.closest( 'li' ).addClass( 'opacity-05' );

		$.ajax({
			type: 'POST',
			dataType: 'json',
			url: listeo.ajaxurl,
			data: {
				action: 'listeo_core_unbookmark_this',
				post_id: handler.data( 'post_id' ),
				nonce: handler.data( 'nonce' )
			},
			success: function ( response ) {
				if ( response && response.type === 'success' ) {
					handler.closest( 'li' ).fadeOut(); // solo afecta al panel "Mis Favoritos"
					if ( handler.hasClass( 'fa-solid' ) ) {
						handler.removeClass( 'fa-solid' ).addClass( 'fa-regular' );
					}
					// Devolver a estado "marcable" para permitir re-marcar sin recargar.
					handler.removeClass( 'clicked liked listeo_core-unbookmark-it' )
					       .addClass( 'save listeo_core-bookmark-it' );
					handler.children( '.like-icon' ).removeClass( 'liked' );
				} else {
					handler.closest( 'li' ).removeClass( 'opacity-05' );
				}
				handler.data( 'ppBusy', false );
			},
			error: function () {
				handler.closest( 'li' ).removeClass( 'opacity-05' );
				handler.data( 'ppBusy', false );
			}
		});
	});

	// Desenganchar el manejador DIRECTO roto de Listeo (evita doble disparo en los
	// corazones que ya estaban marcados al cargar). Solo afecta a estos elementos.
	$( '.listeo_core-unbookmark-it' ).off( 'click' );
});
