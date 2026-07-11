// migrado de functions.php::ppv2_dirfilters_close_desktop() 2026-07-10
// Condición de páginas: ppv2_is_listings_archive() → is_post_type_archive('listing') || is_tax(taxonomías de listing) (Directorio)
(function () {
	var inner = document.querySelector('.full-page-sidebar .full-page-sidebar-inner')
		|| document.querySelector('.full-page-sidebar');
	if ( ! inner || inner.querySelector('.pp-dirfilters-close-desktop') ) { return; }

	var btn = document.createElement('button');
	btn.type = 'button';
	btn.className = 'pp-dirfilters-close-desktop';
	btn.setAttribute('aria-label', 'Cerrar filtros');
	btn.innerHTML = '&times;';
	btn.addEventListener('click', function () {
		var toggle = document.querySelector('.enable-filters-button');
		if ( toggle ) { toggle.click(); }
	});
	inner.insertBefore(btn, inner.firstChild);
})();
