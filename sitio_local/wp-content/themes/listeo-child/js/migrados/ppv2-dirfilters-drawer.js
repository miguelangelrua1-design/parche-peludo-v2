// migrado de functions.php::ppv2_dirfilters_drawer() 2026-07-10
// Condición de páginas: ppv2_is_listings_archive() → is_post_type_archive('listing') || is_tax(taxonomías de listing) (Directorio)
(function () {
	var sb = document.querySelector('.full-page-sidebar');
	if ( ! sb ) { return; }
	var html = document.documentElement;
	var OPEN = 'pp-dirfilters-open';

	// Barra superior del panel: título + botón de cierre.
	var head = document.createElement('div');
	head.className = 'pp-dirfilters-head';
	head.innerHTML = '<span>Filtros</span><button type="button" class="pp-dirfilters-close" aria-label="Cerrar filtros">&times;</button>';
	sb.insertBefore(head, sb.firstChild);

	// Barra inferior fija: línea de CONTEO (siempre visible) + botón "Ver resultados".
	var foot = document.createElement('div');
	foot.className = 'pp-dirfilters-foot';
	foot.innerHTML = '<div class="pp-dirfilters-count" aria-live="polite"></div>'
		+ '<button type="button" class="pp-dirfilters-apply">Ver resultados</button>';
	sb.appendChild(foot);

	// Overlay del cajón.
	var overlay = document.createElement('div');
	overlay.className = 'pp-dirfilters-overlay';
	sb.parentNode.insertBefore(overlay, sb.nextSibling);

	// En MÓVIL movemos sidebar+overlay a un host colgado de <html>: el tema
	// envuelve la página en contenedores con transform (menú off-canvas) que
	// ROMPEN position:fixed y dejaban el cajón desanclado — mismo truco que
	// usa el minicarrito (pp-minicart). En escritorio vuelve a su lugar
	// original (marcado con un placeholder invisible).
	var mq = window.matchMedia('(max-width: 991px)');
	var host = null, placeholder = null;
	function placeDrawer() {
		if ( mq.matches ) {
			if ( ! host ) {
				host = document.createElement('div');
				host.className = 'pp-dirfilters-host';
				document.documentElement.appendChild(host);
			}
			if ( ! placeholder ) {
				placeholder = document.createElement('span');
				placeholder.style.display = 'none';
				sb.parentNode.insertBefore(placeholder, sb);
			}
			if ( sb.parentNode !== host ) { host.appendChild(sb); host.appendChild(overlay); }
		} else if ( placeholder && sb.parentNode !== placeholder.parentNode ) {
			placeholder.parentNode.insertBefore(sb, placeholder);
			placeholder.parentNode.insertBefore(overlay, sb.nextSibling);
			closeDrawer();
		}
	}

	function countResults() {
		return document.querySelectorAll('.listings-container .listing-card-container-nl').length;
	}
	function updateApply( loading ) {
		var b = foot.querySelector('.pp-dirfilters-apply');
		var c = foot.querySelector('.pp-dirfilters-count');
		if ( ! b || ! c ) { return; }
		if ( loading ) {
			// Cargando: isotipo de marca latiendo (CSS) + texto, botón atenuado.
			foot.classList.add('is-loading');
			c.classList.remove('is-empty');
			c.textContent = 'Buscando…';
			return;
		}
		foot.classList.remove('is-loading');
		var n = countResults();
		if ( n > 0 ) {
			c.classList.remove('is-empty');
			c.textContent = n + ( n === 1 ? ' resultado encontrado' : ' resultados encontrados' );
			b.classList.remove('is-empty');
			b.textContent = 'Ver resultados';
		} else {
			// SIN resultados: aviso claro en la línea y en el propio botón.
			c.classList.add('is-empty');
			c.textContent = 'Sin resultados con estos filtros';
			b.classList.add('is-empty');
			b.textContent = 'Sin resultados';
		}
	}
	// Sincroniza nuestro estado con la clase que alterna Listeo.
	function syncFromListeo() {
		html.classList.toggle(OPEN, sb.classList.contains('enabled-sidebar'));
		if ( html.classList.contains(OPEN) ) { updateApply(false); }
	}
	function closeDrawer() {
		sb.classList.remove('enabled-sidebar');
		Array.prototype.forEach.call(document.querySelectorAll('.enable-filters-button'), function ( b ) { b.classList.remove('active'); });
		html.classList.remove(OPEN);
	}

	document.addEventListener('click', function ( e ) {
		if ( ! e.target.closest ) { return; }
		// Después del toggle de Listeo: su handler jQuery vive en el ELEMENTO,
		// así que ya corrió cuando el evento burbujea hasta document. Sincronizamos
		// directo (sin setTimeout: los timers se degradan en pestañas en segundo plano).
		if ( e.target.closest('.enable-filters-button') ) { syncFromListeo(); return; }
		if ( e.target.closest('.pp-dirfilters-close') || e.target === overlay || e.target.closest('.pp-dirfilters-apply') ) { closeDrawer(); }
	});
	document.addEventListener('keydown', function ( e ) { if ( e.key === 'Escape' ) { closeDrawer(); } });

	// Loader + conteo: eventos AJAX globales de jQuery para listeo_get_listings.
	if ( window.jQuery ) {
		var isListeo = function ( settings ) {
			var s = ( settings && ( settings.data || '' ) ) + ' ' + ( settings && ( settings.url || '' ) );
			return s.indexOf('listeo_get_listings') !== -1;
		};
		jQuery(document).ajaxSend(function ( ev, xhr, settings ) { if ( isListeo(settings) ) { updateApply(true); } });
		// ajaxComplete corre DESPUÉS del success de Listeo (que ya pintó las
		// tarjetas), así que el conteo directo es fiable — sin setTimeout.
		jQuery(document).ajaxComplete(function ( ev, xhr, settings ) { if ( isListeo(settings) ) { updateApply(false); } });
	}

	// Red de seguridad para el conteo: cualquier re-render del contenedor de
	// resultados (venga del AJAX que venga) actualiza la línea de conteo,
	// aunque los eventos ajaxSend/Complete no se hayan capturado.
	var cont = document.querySelector('.listings-container');
	if ( cont && window.MutationObserver ) {
		new MutationObserver(function () {
			if ( ! foot.classList.contains('is-loading') ) { updateApply(false); }
		}).observe(cont, { childList: true, subtree: true });
	}

	placeDrawer();
	if ( mq.addEventListener ) { mq.addEventListener('change', placeDrawer); }
	updateApply(false);
})();
