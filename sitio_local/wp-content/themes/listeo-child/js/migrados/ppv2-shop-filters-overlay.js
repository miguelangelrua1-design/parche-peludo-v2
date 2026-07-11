// migrado de functions.php::ppv2_shop_filters_overlay() 2026-07-10
// Condición de páginas: ppv2_is_shop_context() → is_shop() || is_product_taxonomy() (Tienda WooCommerce)
(function () {
	var html = document.documentElement;
		// Reinicia el slider de precio de WooCommerce: oculta los inputs #min_price/#max_price,
		// muestra el slider y lo reconstruye si falta. Necesario cuando el widget se muestra
		// DESPUÉS de estar oculto (cajón móvil o sección plegada), donde se veían los inputs
		// crudos. WooCommerce expone el gancho 'init_price_filter' para reinicializarlo.
		function ppReinitPrice() {
			if ( ! window.jQuery ) { return; }
			setTimeout( function () { window.jQuery( document.body ).trigger( 'init_price_filter' ); }, 60 );
		}
	document.addEventListener('click', function (e) {
		var t = e.target;
		if ( t.closest && t.closest('.pp-filters-toggle') ) { html.classList.add('pp-filters-open'); ppReinitPrice(); return; }
		if ( ( t.closest && t.closest('.pp-filters-close') ) || ( t.classList && t.classList.contains('pp-filters-overlay') ) ) { html.classList.remove('pp-filters-open'); }
	});
	document.addEventListener('keydown', function (e) { if ( e.key === 'Escape' || e.keyCode === 27 ) { html.classList.remove('pp-filters-open'); } });

	// Acordeón de filtros: cada widget (menos "Buscar producto") se pliega al tocar su título.
	var sidebar = document.querySelector('.listeo-shop-grid .col-sidebar');
	if ( sidebar ) {
		Array.prototype.forEach.call( sidebar.querySelectorAll('section.widget'), function ( w ) {
			if ( w.classList.contains('widget_product_search') || w.classList.contains('widget_layered_nav_filters') ) { return; }
			var title = w.querySelector('.widget-title');
			if ( ! title ) { return; }
			w.classList.add('pp-accordion');
			// Por defecto TODAS las secciones de filtros arrancan plegadas (cerradas);
			// se abren al hacer clic en su título.
			w.classList.add('pp-collapsed');
			title.setAttribute('role', 'button');
			title.setAttribute('tabindex', '0');
			title.addEventListener('click', function () { w.classList.toggle('pp-collapsed'); if ( ! w.classList.contains('pp-collapsed') ) { ppReinitPrice(); } });
			title.addEventListener('keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) { e.preventDefault(); w.classList.toggle('pp-collapsed'); if ( ! w.classList.contains('pp-collapsed') ) { ppReinitPrice(); } }
			});
		} );
	}
})();
