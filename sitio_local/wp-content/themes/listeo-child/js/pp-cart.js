/**
 * Parche Peludo V2 — Página del Carrito (/cart/): controles en vivo.
 *
 * Los botones +/− y la papelera actualizan el carrito SIN recargar la página:
 *  - Cambian el número al instante (feedback inmediato / optimista).
 *  - Reenvían el formulario NATIVO de WooCommerce (update_cart) por fetch, de modo
 *    que el servidor recalcula todo correctamente (subtotales, total, cupones,
 *    impuestos) y luego reemplazamos el contenido del carrito con la respuesta.
 *  - La papelera pone la cantidad en 0 (WooCommerce elimina la línea).
 * Solo actúa dentro de `.woocommerce-cart-form` (no interfiere con el minicart).
 */
(function () {
	'use strict';

	var DEBOUNCE = 400;
	var refreshTimer = null;
	// Nº de secuencia de peticiones. Si el usuario encadena cambios rápidos
	// (p. ej. "+" y enseguida la papelera) puede haber dos POST en vuelo y
	// el que responda ÚLTIMO no es necesariamente el más NUEVO: sin esta
	// guarda, una respuesta vieja re-pintaba un estado obsoleto (el producto
	// eliminado "reaparecía"). Cada respuesta solo aplica si sigue siendo la
	// más reciente.
	var reqSeq = 0;

	function cartForm() {
		return document.querySelector('.woocommerce-cart-form');
	}

	function cartWrap() {
		return document.querySelector('div.woocommerce');
	}

	function setLoading(on) {
		var w = cartWrap();
		if (w) { w.classList[on ? 'add' : 'remove']('pp-cart-busy'); }
	}

	// Aplica al DOM el HTML del carrito que devolvió el servidor.
	function applyCartHtml(html) {
		var wrap = cartWrap();
		if (!wrap) { return; }
		var doc = new DOMParser().parseFromString(html, 'text/html');
		var fresh = doc.querySelector('div.woocommerce');
		if (fresh) {
			// Evita el aviso "Carrito actualizado" en cada cambio de cantidad.
			var notices = fresh.querySelector('.woocommerce-notices-wrapper');
			if (notices) { notices.innerHTML = ''; }
			wrap.innerHTML = fresh.innerHTML;
		}

		// Sincroniza el contador (badge) del ícono del carrito en el header.
		// (Este sitio no carga cart-fragments.js, así que lo hacemos a mano
		// tomando el valor ya renderizado por el servidor en la respuesta.)
		var freshBadge = doc.querySelector('.mini-cart-button .badge');
		if (freshBadge) {
			var badges = document.querySelectorAll('.mini-cart-button .badge');
			for (var i = 0; i < badges.length; i++) { badges[i].textContent = freshBadge.textContent; }
		}
		// Sincroniza el contenido del panel del minicart (reubicado en <html>).
		var freshMini = doc.querySelector('.listeo-mini-cart');
		var curMini = document.querySelector('.pp-cart-host .listeo-mini-cart');
		if (freshMini && curMini) { curMini.innerHTML = freshMini.innerHTML; }

		if (window.jQuery) { jQuery(document.body).trigger('wc_fragment_refresh'); }
		document.body.dispatchEvent(new CustomEvent('pp_cart_refreshed'));
	}

	// Re-lee el carrito del servidor (GET, sin cambios) y lo re-pinta: se usa
	// cuando un POST falló, para que la UI optimista no quede mostrando una
	// cantidad que el servidor no tiene.
	function resyncCart(mySeq) {
		fetch(window.location.href, {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		}).then(function (r) {
			if (!r.ok) { throw new Error('HTTP ' + r.status); }
			return r.text();
		}).then(function (html) {
			if (mySeq !== reqSeq) { return; } // ya hay una petición más nueva
			applyCartHtml(html);
		}).catch(function () {
			/* sin conexión: no rompemos la página */
		});
	}

	// Reenvía el formulario del carrito (update_cart) y reemplaza el contenido.
	function refreshCart() {
		var form = cartForm();
		var wrap = cartWrap();
		if (!form || !wrap) { setLoading(false); return; }

		var mySeq = ++reqSeq;
		setLoading(true);
		var fd = new FormData(form);
		fd.append('update_cart', 'Actualizar carrito');

		fetch(form.getAttribute('action') || window.location.href, {
			method: 'POST',
			body: fd,
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		}).then(function (r) {
			if (!r.ok) { throw new Error('HTTP ' + r.status); } // 403/500: al catch, no pintar la página de error
			return r.text();
		}).then(function (html) {
			if (mySeq !== reqSeq) { return; } // respuesta vieja: la ignora
			applyCartHtml(html);
		}).catch(function () {
			if (mySeq !== reqSeq) { return; }
			resyncCart(mySeq); // re-lee el estado real para no dejar la UI desincronizada
		}).then(function () {
			if (mySeq === reqSeq) { setLoading(false); }
		});
	}

	// Agrupa cambios rápidos de cantidad en una sola actualización.
	function scheduleRefresh() {
		if (refreshTimer) { clearTimeout(refreshTimer); }
		setLoading(true); // señal inmediata de "actualizando"
		refreshTimer = setTimeout(function () {
			refreshTimer = null;
			refreshCart();
		}, DEBOUNCE);
	}

	// Clics en +/−/papelera dentro de la tabla del carrito (delegado).
	document.addEventListener('click', function (e) {
		if (!e.target.closest || !e.target.closest('.woocommerce-cart-form')) { return; }

		// "×" de la esquina: quita el producto COMPLETO (cualquier cantidad). Inmediato.
		var removeX = e.target.closest('.pp-item-remove');
		if (removeX) {
			e.preventDefault();
			var row = removeX.closest('.pp-cart-row');
			var xinput = row ? row.querySelector('input[name^="cart"]') : null; // .pp-qty-input o el hidden de "vender individualmente"
			if (xinput) { xinput.value = 0; }
			if (refreshTimer) { clearTimeout(refreshTimer); refreshTimer = null; }
			refreshCart();
			return;
		}

		// Papelera: cantidad 0 → WooCommerce elimina la línea. Inmediato.
		var remove = e.target.closest('.pp-remove');
		if (remove) {
			e.preventDefault();
			var rbox = remove.closest('.pp-qty');
			var rinput = rbox ? rbox.querySelector('.pp-qty-input') : null;
			if (rinput) { rinput.value = 0; }
			if (refreshTimer) { clearTimeout(refreshTimer); refreshTimer = null; }
			refreshCart();
			return;
		}

		var step = e.target.closest('.pp-qty-minus, .pp-qty-plus');
		if (step) {
			e.preventDefault();
			var box = step.closest('.pp-qty');
			var input = box.querySelector('.pp-qty-input');
			var v = parseInt(input.value, 10);
			if (isNaN(v)) { v = 1; }
			if (step.classList.contains('pp-qty-plus')) {
				v = v + 1;
			} else {
				v = v - 1;
				if (v < 1) { v = 1; } // el menos no elimina; para eso está la papelera
			}
			input.value = v;        // feedback inmediato
			scheduleRefresh();
		}
	});

	// Edición manual del número de cantidad.
	document.addEventListener('change', function (e) {
		var input = e.target.closest ? e.target.closest('.woocommerce-cart-form .pp-qty-input') : null;
		if (!input) { return; }
		var v = parseInt(input.value, 10);
		if (isNaN(v) || v < 0) { v = 0; }
		input.value = v;
		scheduleRefresh();
	});
})();
