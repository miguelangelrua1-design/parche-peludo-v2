/**
 * Carga progresiva del listado de productos (PLP) — Parche Peludo V2.
 *
 * Funciona por CICLOS: al acercarse al final de la grilla se cargan hasta
 * MAX_CARGAS páginas más (las "siguientes" según la paginación NATIVA de
 * WooCommerce) de forma automática y aparece el botón "Ver más productos"
 * (con el contador "Mostrando 1–N de M" debajo). Cada clic carga una tanda y
 * REINICIA el ciclo: otras MAX_CARGAS cargas automáticas al scrollear y el
 * botón de nuevo. Cuando ya no queda nada, el botón desaparece (fin natural
 * de la lista; el footer siempre es alcanzable). El botón es un ENLACE REAL
 * a la página siguiente: los buscadores recorren el catálogo profundo y, sin
 * JS, el clic navega a la paginación clásica (que también queda como
 * respaldo si una carga falla).
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
	function activarScroll() {
		// El centinela vuelve justo después de la grilla (que creció).
		if (!sentinel.parentNode) { grid.parentNode.insertBefore(sentinel, grid.nextSibling); }
		window.addEventListener('scroll', alScroll, { passive: true });
		window.addEventListener('resize', alScroll, { passive: true });
		alScroll(); // por si lo visible no llena la pantalla
	}
	activarScroll();

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

	// --- Botón "Ver más productos" (cierra cada ciclo de cargas automáticas) --
	var botonWrap    = null;
	var boton        = null;
	var botonInfo    = null;
	var clicPendiente = false;

	function desactivarScroll() {
		window.removeEventListener('scroll', alScroll);
		window.removeEventListener('resize', alScroll);
		if (sentinel.parentNode) { sentinel.parentNode.removeChild(sentinel); }
	}

	function textoContador() {
		var rc = document.querySelector('.woocommerce-result-count');
		if (!rc) { return ''; }
		// Primera línea con contenido ("Mostrando 1–72 de 512 resultados").
		var linea = rc.textContent.split('\n').filter(function (s) { return s.trim(); })[0];
		return linea ? linea.trim() : '';
	}

	function crearBoton() {
		if (botonWrap || !nextUrl) { return; }
		botonWrap = document.createElement('div');
		botonWrap.className = 'pp-vermas-wrap';

		// Enlace REAL a la página siguiente: SEO y sin-JS siguen funcionando.
		boton = document.createElement('a');
		boton.className = 'pp-vermas';
		boton.href = nextUrl;
		boton.rel = 'nofollow';
		boton.textContent = 'Ver más productos';
		boton.addEventListener('click', function (e) {
			e.preventDefault();
			clicPendiente = true; // al completar, se REINICIA el ciclo automático
			cargar();
		});

		// Contador DEBAJO del botón.
		botonInfo = document.createElement('div');
		botonInfo.className = 'pp-vermas-info';
		botonInfo.textContent = textoContador();

		botonWrap.appendChild(boton);
		botonWrap.appendChild(botonInfo);
		pag.parentNode.insertBefore(botonWrap, pag);
	}

	function mostrarBoton() {
		crearBoton();
		if (!botonWrap) { return; }
		if (botonInfo) { botonInfo.textContent = textoContador(); }
		if (boton && nextUrl) { boton.href = nextUrl; }
		if (boton) {
			boton.classList.remove('is-loading');
			boton.textContent = 'Ver más productos';
		}
		botonWrap.style.display = '';
	}

	function ocultarBoton() {
		if (!botonWrap) { return; }
		if (boton) {
			boton.classList.remove('is-loading');
			boton.textContent = 'Ver más productos';
		}
		botonWrap.style.display = 'none';
	}

	// Se mostró todo el catálogo: sin botón y sin paginación (fin natural).
	function finalizar() {
		desactivarScroll();
		clicPendiente = false;
		if (botonWrap) {
			botonWrap.style.display = '';
			botonInfo.textContent = textoContador();
			if (boton) {
				botonWrap.removeChild(boton);
				boton = null;
			}
		}
	}

	// Error de red: restaurar la paginación clásica (respaldo seguro).
	function fallback() {
		desactivarScroll();
		if (botonWrap && botonWrap.parentNode) { botonWrap.parentNode.removeChild(botonWrap); }
		botonWrap = null;
		pag.style.display = '';
	}

	function cargar() {
		if (cargando || !nextUrl) { return; }
		cargando = true;
		sentinel.classList.add('is-loading');
		if (boton) {
			boton.classList.add('is-loading');
			boton.textContent = 'Cargando…';
		}

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

				if (!nextUrl) {
					finalizar();
					return;
				}
				if (clicPendiente) {
					// El clic del botón abre un CICLO nuevo: contador de cargas
					// automáticas a cero, botón oculto y scroll re-armado.
					clicPendiente = false;
					cargas = 0;
					ocultarBoton();
					activarScroll();
					return;
				}
				if (cargas >= MAX_CARGAS) {
					// Ciclo automático agotado: scroll fuera, turno del botón.
					desactivarScroll();
					mostrarBoton();
				}
			})
			.catch(function () {
				cargando = false;
				sentinel.classList.remove('is-loading');
				fallback();
			});
	}
})();
