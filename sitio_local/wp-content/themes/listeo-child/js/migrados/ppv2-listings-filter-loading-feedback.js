/* Migrado de functions.php::ppv2_listings_filter_loading_feedback() 2026-07-10 (wp_footer, prio 122).
   Condición de páginas: solo si ppv2_is_listings_archive() (archivo de listing o taxonomías de listing). */
(function () {
	document.addEventListener('DOMContentLoaded', function () {
		var container = document.getElementById('listeo-listings-container');
		if (!container) return;

		// Crea (una vez) la barra de progreso + el texto de estado al tope del panel.
		function ensureEls() {
			var inner = document.querySelector('.full-page-sidebar .full-page-sidebar-inner')
				|| document.querySelector('.full-page-sidebar-inner');
			if (!inner) return null;
			var bar = inner.querySelector('.ppv2-filter-loadingbar');
			if (!bar) {
				bar = document.createElement('div');
				bar.className = 'ppv2-filter-loadingbar';
				bar.setAttribute('role', 'progressbar');
				bar.setAttribute('aria-label', 'Cargando resultados');
				inner.insertBefore(bar, inner.firstChild);
			}
			var status = inner.querySelector('.ppv2-filter-status');
			if (!status) {
				status = document.createElement('div');
				status.className = 'ppv2-filter-status';
				status.setAttribute('aria-live', 'polite'); // lectores de pantalla anuncian el conteo
				inner.insertBefore(status, bar.nextSibling);
			}
			return { bar: bar, status: status };
		}

		function update() {
			var els = ensureEls();
			if (!els) return;
			var loading = container.classList.contains('loading');
			els.bar.classList.toggle('is-active', loading);
			if (loading) {
				els.status.textContent = 'Buscando…';      // "Buscando…"
				els.status.classList.add('is-loading');
			} else {
				// Conteo de resultados (tarjetas renderizadas en el contenedor).
				var n = container.querySelectorAll('.listing-card-container-nl').length;
				els.status.classList.remove('is-loading');
				els.status.textContent = n === 0 ? 'Sin resultados'
					: (n === 1 ? '1 resultado' : n + ' resultados');
			}
		}

		// Observa la clase .loading (estado de carga) y los cambios de tarjetas
		// (cuando el AJAX reemplaza los resultados) para recalcular el conteo.
		new MutationObserver(update).observe(container, {
			attributes: true, attributeFilter: ['class'], childList: true
		});
		update();
	});
})();
