/**
 * Parche Peludo V2 — Minicart como panel deslizante desde la derecha (estilo Éxito).
 *
 * El tema padre (Listeo) muestra el minicart como un dropdown que aparece al pasar
 * el ratón sobre el ícono del carrito. Aquí NO tocamos ese HTML: mediante CSS lo
 * convertimos en un panel fijo a la derecha, y con este JS controlamos abrir/cerrar
 * con clic (más overlay, botón de cierre, tecla ESC y apertura automática al añadir
 * un producto).
 *
 * Puntos clave:
 *  - El estado abierto/cerrado vive en la clase `pp-cart-open` del <body>.
 *  - El minicart original está dentro de `#header-container`, que tiene su propio
 *    z-index (1001). Si dejáramos el panel ahí, el fondo oscuro (que cuelga del
 *    <body>) lo taparía. Por eso movemos `.listeo-mini-cart` a un contenedor
 *    propio colgado del <body> (`.pp-cart-host`), que además conserva la clase
 *    `listeo-cart-container` para no perder los estilos de los ítems del tema padre.
 *  - Todo (host, barra de título, overlay) se crea UNA sola vez y sobrevive a los
 *    refrescos AJAX de WooCommerce (que reemplazan `div.listeo-mini-cart` en su sitio).
 */
(function () {
	'use strict';

	var OPEN_CLASS = 'pp-cart-open';
	var built = false;

	function buildChrome() {
		if (built) { return; }
		built = true;

		// Contenedor propio del panel. Lo colgamos directamente del <html>
		// (documentElement), NO del <body>, para escapar de cualquier contenedor
		// con `transform` que el tema aplique al <body> en móvil (menú off-canvas),
		// el cual "atrapa" los elementos position:fixed y los deja en una banda.
		// Mantiene la clase `listeo-cart-container` para heredar estilos de ítems.
		var host = document.createElement('div');
		host.className = 'listeo-cart-container pp-cart-host';
		document.documentElement.appendChild(host);

		// Fondo oscuro (dentro del host, para que también escape del transform).
		var overlay = document.createElement('div');
		overlay.className = 'pp-cart-overlay';
		overlay.addEventListener('click', closeCart);
		host.appendChild(overlay);

		// Barra superior fija del panel: título + botón de cierre.
		var headbar = document.createElement('div');
		headbar.className = 'pp-cart-headbar';
		headbar.innerHTML =
			'<span class="pp-cart-title">Tu carrito</span>' +
			'<button type="button" class="pp-cart-close" aria-label="Cerrar carrito">&times;</button>';
		headbar.querySelector('.pp-cart-close').addEventListener('click', closeCart);
		host.appendChild(headbar);

		// Movemos el minicart real dentro de nuestro contenedor. WooCommerce sigue
		// refrescándolo en su nueva ubicación porque busca `div.listeo-mini-cart`.
		var panel = document.querySelector('.listeo-mini-cart');
		if (panel) { host.appendChild(panel); }

		// Marcamos si existe la barra de administración de WordPress, para el offset.
		if (document.body.classList.contains('admin-bar')) {
			document.documentElement.classList.add('pp-adminbar');
		}
	}

	function openCart() {
		buildChrome();
		// La clase de "abierto" va en <html> (donde cuelga el panel) y en <body>
		// (para el bloqueo de scroll).
		document.documentElement.classList.add(OPEN_CLASS);
		document.body.classList.add(OPEN_CLASS);
	}

	function closeCart() {
		document.documentElement.classList.remove(OPEN_CLASS);
		document.body.classList.remove(OPEN_CLASS);
	}

	function toggleCart(e) {
		if (e) { e.preventDefault(); e.stopPropagation(); }
		if (document.body.classList.contains(OPEN_CLASS)) {
			closeCart();
		} else {
			openCart();
		}
	}

	// Clic en el ícono del carrito (funciona con header original y clonado).
	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('.mini-cart-button') : null;
		if (btn) { toggleCart(e); }
	});

	// Tecla ESC cierra el panel.
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' || e.keyCode === 27) { closeCart(); }
	});

	// Al añadir un producto (evento AJAX de WooCommerce) abrimos el panel.
	if (window.jQuery) {
		jQuery(document.body).on('added_to_cart', function () { openCart(); });
	}

	// --- Controles por producto: cantidad (–, input, +) y eliminar (papelera) ---
	//
	// Rendimiento: la cantidad cambia AL INSTANTE en pantalla (optimista) y el envío
	// al servidor se agrupa (debounce) para no lanzar una petición por cada clic.
	// Mientras la petición viaja, mostramos el loader y deshabilitamos el panel.

	var PP_DEBOUNCE = 350;   // ms de espera para agrupar clics rápidos
	var ppQtyTimers = {};    // temporizadores por producto

	function setCartLoading(on) {
		var h = document.querySelector('.pp-cart-host .listeo-mini-cart');
		if (h) { h.classList[on ? 'add' : 'remove']('pp-cart-loading'); }
	}

	function applyFragments(resp) {
		if (resp && resp.success && resp.data && resp.data.fragments) {
			jQuery.each(resp.data.fragments, function (selector, html) {
				jQuery(selector).replaceWith(html);
			});
			jQuery(document.body).trigger('wc_fragments_refreshed');
		}
	}

	// Envío real al servidor (redibuja lista, subtotal y badge con los fragmentos).
	function sendQty(key, qty) {
		if (!key || !window.PP_MINICART || !window.jQuery) { return; }
		setCartLoading(true);
		// .then() (Promises/A+) en lugar de .done() para máxima fiabilidad.
		jQuery.post(PP_MINICART.ajax_url, {
			action: 'pp_update_cart_qty',
			cart_item_key: key,
			quantity: qty,
			nonce: PP_MINICART.nonce
		}).then(applyFragments).then(function () { setCartLoading(false); }, function () { setCartLoading(false); });
	}

	// Cambios de cantidad (+/−/input): agrupa clics rápidos y envía solo el valor final.
	function scheduleQty(key, qty) {
		if (!key) { return; }
		if (ppQtyTimers[key]) { clearTimeout(ppQtyTimers[key]); }
		ppQtyTimers[key] = setTimeout(function () {
			delete ppQtyTimers[key];
			sendQty(key, qty);
		}, PP_DEBOUNCE);
	}

	// Clics en –, + y papelera (delegado, sobrevive a los refrescos AJAX).
	document.addEventListener('click', function (e) {
		if (!e.target.closest) { return; }

		// Papelera: eliminar de inmediato (sin debounce).
		var remove = e.target.closest('.pp-remove');
		if (remove) {
			e.preventDefault();
			sendQty(remove.getAttribute('data-cart_item_key'), 0);
			return;
		}

		var stepBtn = e.target.closest('.pp-qty-minus, .pp-qty-plus');
		if (stepBtn) {
			e.preventDefault();
			var box = stepBtn.closest('.pp-qty');
			var input = box.querySelector('.pp-qty-input');
			var val = parseInt(input.value, 10);
			if (isNaN(val)) { val = 1; }
			if (stepBtn.classList.contains('pp-qty-plus')) {
				val = val + 1;
			} else {
				val = val - 1;
				if (val < 1) { val = 1; } // el menos nunca elimina; para eso está la papelera
			}
			input.value = val;                 // feedback inmediato
			scheduleQty(box.getAttribute('data-cart_item_key'), val); // envío agrupado
		}
	});

	// Edición manual del input de cantidad.
	document.addEventListener('change', function (e) {
		var input = e.target.closest ? e.target.closest('.pp-qty-input') : null;
		if (!input) { return; }
		var box = input.closest('.pp-qty');
		var val = parseInt(input.value, 10);
		if (isNaN(val) || val < 0) { val = 0; }
		input.value = val;
		scheduleQty(box.getAttribute('data-cart_item_key'), val);
	});

	// Preparamos el "chrome" en cuanto el DOM esté listo.
	if (document.readyState !== 'loading') {
		buildChrome();
	} else {
		document.addEventListener('DOMContentLoaded', buildChrome);
	}
})();
