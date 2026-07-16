/**
 * Carga progresiva del listado de productos (PLP) — Parche Peludo V2.
 *
 * Al acercarse al final de la grilla se cargan hasta MAX_CARGAS páginas más
 * (las "siguientes" según la paginación NATIVA de WooCommerce) y después
 * vuelve a mostrarse la paginación — pero la de la ÚLTIMA página cargada,
 * cuyo "Siguiente" apunta a lo aún no visto: al paginar no se repiten
 * productos.
 *
 * - Filtros/buscador intactos: se usa el href real del enlace "siguiente"
 *   (conserva ?filter_*, orderby, s=… y cualquier parámetro).
 * - Performance: listener de scroll PASIVO acelerado con requestAnimationFrame
 *   (máx. una medición de un elemento por frame; no bloquea el scroll), una
 *   petición por carga y solo al acercarse al final; DOMParser no ejecuta
 *   scripts del HTML recibido; imágenes con lazy-loading nativo de WordPress.
 *   (Se prefirió esto a IntersectionObserver por ser igual de liviano para un
 *   solo elemento y comprobable en más entornos.)
 * - Robustez: guarda de IDs vistos (nunca se pinta dos veces un producto) y,
 *   si una carga falla, se restaura la paginación normal.
 * - Es agnóstico al tamaño de página: usa el que esté configurado en
 *   Personalizador → WooCommerce → Catálogo de productos.
 */
(function () {
	'use strict';

	var MAX_CARGAS = 2;

	var grid = document.querySelector('.listeo-shop-grid ul.products');
	var pag  = document.querySelector('.listeo-shop-grid .woocommerce-pagination');
	if (!grid || !pag) { return; }

	var enlaceNext = pag.querySelector('a.next.page-numbers, a.next');
	if (!enlaceNext) { return; } // una sola página: nada que hacer

	var cargas   = 0;
	var nextUrl  = enlaceNext.href;
	var cargando = false;

	// IDs ya pintados (clase post-123 de cada tarjeta): nunca repetir.
	var vistos = {};
	function idDe(li) {
		var m = (li.className || '').match(/post-(\d+)/);
		return m ? m[1] : null;
	}
	Array.prototype.forEach.call(grid.querySelectorAll('li.product'), function (li) {
		var id = idDe(li);
		if (id) { vistos[id] = 1; }
	});

	// Mientras la carga progresiva está activa, la paginación se oculta.
	pag.style.display = 'none';

	// Centinela (dispara la carga) + indicador "cargando".
	var sentinel = document.createElement('div');
	sentinel.className = 'pp-scroll-sentinel';
	sentinel.innerHTML = '<span class="pp-scroll-spinner" aria-hidden="true"></span><span>Cargando más productos…</span>';
	grid.parentNode.insertBefore(sentinel, grid.nextSibling);

	// Proximidad: carga cuando el centinela entra en pantalla + 600px de
	// antelación. Listener pasivo acelerado con setTimeout (máx. una medición
	// de UN elemento cada 120 ms): no bloquea el scroll y funciona en todos
	// los entornos (rAF/IntersectionObserver no corren en algunos webviews).
	var tick = false;
	function cerca() {
		return sentinel.getBoundingClientRect().top < window.innerHeight + 600;
	}
	function alScroll() {
		if (tick) { return; }
		tick = true;
		setTimeout(function () {
			tick = false;
			if (cerca()) { cargar(); }
		}, 120);
	}
	window.addEventListener('scroll', alScroll, { passive: true });
	window.addEventListener('resize', alScroll, { passive: true });
	alScroll(); // por si la primera página no llena la pantalla

	// "Mostrando 1–24 de 512" → al anexar, el fin crece pero el inicio se
	// conserva (usa el texto de la página recibida cambiándole el inicio).
	function actualizarContador(doc) {
		try {
			var mio  = document.querySelector('.woocommerce-result-count');
			var suyo = doc.querySelector('.woocommerce-result-count');
			if (!mio || !suyo) { return; }
			if (!mio.dataset.ppIni) {
				var ini = mio.textContent.match(/\d[\d.,]*/);
				if (!ini) { return; }
				mio.dataset.ppIni = ini[0];
			}
			var t = suyo.textContent;
			var m = t.match(/\d[\d.,]*/);
			if (m) { mio.textContent = t.replace(m[0], mio.dataset.ppIni); }
		} catch (e) { /* cosmético: nunca romper por esto */ }
	}

	function terminar(docFinal) {
		window.removeEventListener('scroll', alScroll);
		window.removeEventListener('resize', alScroll);
		sentinel.parentNode && sentinel.parentNode.removeChild(sentinel);
		if (docFinal) {
			// La paginación de la ÚLTIMA página cargada: su página "actual" y su
			// "Siguiente" ya quedan más allá de lo visto (sin repetidos).
			var nueva = docFinal.querySelector('.woocommerce-pagination');
			if (nueva) {
				pag.parentNode.replaceChild(document.importNode(nueva, true), pag);
				pag = document.querySelector('.listeo-shop-grid .woocommerce-pagination');
			}
		}
		if (pag) { pag.style.display = ''; }
	}

	function cargar() {
		if (cargando || !nextUrl) { return; }
		cargando = true;
		sentinel.classList.add('is-loading');

		fetch(nextUrl, { credentials: 'same-origin' })
			.then(function (r) {
				if (!r.ok) { throw new Error('HTTP ' + r.status); }
				return r.text();
			})
			.then(function (html) {
				var doc   = new DOMParser().parseFromString(html, 'text/html');
				var items = doc.querySelectorAll('.listeo-shop-grid ul.products li.product');
				Array.prototype.forEach.call(items, function (li) {
					var id = idDe(li);
					if (id && vistos[id]) { return; }
					if (id) { vistos[id] = 1; }
					grid.appendChild(document.importNode(li, true));
				});
				actualizarContador(doc);

				cargas++;
				var n = doc.querySelector('.woocommerce-pagination a.next.page-numbers, .woocommerce-pagination a.next');
				nextUrl = n ? n.href : null;

				cargando = false;
				sentinel.classList.remove('is-loading');
				if (cargas >= MAX_CARGAS || !nextUrl) { terminar(doc); }
			})
			.catch(function () {
				// Falló la red: vuelve la paginación normal (nada queda roto).
				cargando = false;
				sentinel.classList.remove('is-loading');
				terminar(null);
			});
	}
})();
