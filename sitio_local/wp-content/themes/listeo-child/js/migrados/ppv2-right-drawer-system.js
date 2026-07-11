/* Migrado de functions.php::ppv2_right_drawer_system() 2026-07-10 (wp_footer, prio 118).
   Condición de páginas: global (sin condición). Expone window.ppRDrawer. */
(function () {
	var drawer, overlay, titleEl, bodyEl, source, placeholder;

	function build() {
		if ( drawer ) { return; }
		overlay = document.createElement( 'div' );
		overlay.className = 'ppv2-rdrawer-overlay';

		drawer = document.createElement( 'aside' );
		drawer.className = 'ppv2-rdrawer';
		drawer.setAttribute( 'role', 'dialog' );
		drawer.setAttribute( 'aria-modal', 'true' );
		drawer.setAttribute( 'aria-hidden', 'true' );

		var head = document.createElement( 'div' );
		head.className = 'ppv2-rdrawer-head';
		titleEl = document.createElement( 'span' );
		titleEl.className = 'ppv2-rdrawer-title';
		var closeBtn = document.createElement( 'button' );
		closeBtn.type = 'button';
		closeBtn.className = 'ppv2-rdrawer-close';
		closeBtn.setAttribute( 'aria-label', 'Cerrar' );
		closeBtn.innerHTML = '&times;';
		closeBtn.addEventListener( 'click', api.close );
		head.appendChild( titleEl );
		head.appendChild( closeBtn );

		bodyEl = document.createElement( 'div' );
		bodyEl.className = 'ppv2-rdrawer-body';

		drawer.appendChild( head );
		drawer.appendChild( bodyEl );
		document.body.appendChild( overlay );
		document.body.appendChild( drawer );

		overlay.addEventListener( 'click', api.close );
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) { api.close(); }
		} );
	}

	// Devuelve el contenido a su lugar original (placeholder).
	function restore() {
		if ( source && placeholder && placeholder.parentNode ) {
			placeholder.parentNode.insertBefore( source, placeholder );
			placeholder.parentNode.removeChild( placeholder );
		}
		source = null;
		placeholder = null;
	}

	var api = {
		open: function ( sourceEl, title ) {
			build();
			if ( ! sourceEl ) { return; }
			// Un solo panel a la vez: cerrar Mi Cuenta si está abierto.
			if ( window.ppCloseAccountDrawer ) { window.ppCloseAccountDrawer(); }
			restore(); // por si quedaba contenido de una apertura anterior
			source = sourceEl;
			placeholder = document.createComment( 'ppv2-rdrawer' );
			sourceEl.parentNode.insertBefore( placeholder, sourceEl );
			bodyEl.appendChild( sourceEl );
			titleEl.textContent = title || '';
			overlay.classList.add( 'is-open' );
			drawer.classList.add( 'is-open' );
			drawer.setAttribute( 'aria-hidden', 'false' );
			document.documentElement.classList.add( 'ppv2-rdrawer-lock' );
		},
		close: function () {
			if ( ! drawer || ! drawer.classList.contains( 'is-open' ) ) { return; }
			overlay.classList.remove( 'is-open' );
			drawer.classList.remove( 'is-open' );
			drawer.setAttribute( 'aria-hidden', 'true' );
			document.documentElement.classList.remove( 'ppv2-rdrawer-lock' );
			restore();
		},
		isOpen: function () { return !! ( drawer && drawer.classList.contains( 'is-open' ) ); }
	};

	window.ppRDrawer = api;
})();
