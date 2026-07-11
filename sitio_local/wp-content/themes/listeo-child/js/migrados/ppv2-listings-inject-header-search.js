/* Migrado de functions.php::ppv2_listings_inject_header_search() 2026-07-10 (wp_footer, prio 5).
   Condición de páginas: solo si ppv2_is_listings_archive(). OJO: el <div id="ppv2-header-search-src">
   con el formulario (shortcode PHP) se queda en PHP; este script debe ejecutarse INMEDIATAMENTE
   después de ese div (sin defer), antes de DOMContentLoaded, para mover el formulario al header. */
(function () {
	var src = document.getElementById('ppv2-header-search-src');
	if (!src) { return; }
	var dest = document.querySelector('#header .header-search-container');
	if (dest && !dest.querySelector('form')) {
		while (src.firstChild) { dest.appendChild(src.firstChild); }
	}
	src.parentNode.removeChild(src);
})();
