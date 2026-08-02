/**
 * Tarjetas de producto (PLP, sugeridos del PDP y carruseles [products]):
 * pastillas de presentación + STEPPER de cantidad conectado al carrito.
 *
 * Pastillas (original): vienen pintadas del servidor (content-product.php) con
 * la más ECONÓMICA activa. Un clic activa la pastilla, muestra su precio y
 * apunta el botón "Agregar" a esa variación; el alta real la hace el AJAX
 * NATIVO de WooCommerce (wc-add-to-cart.js).
 *
 * Stepper (pedido del PO 2026-08-01): cuando la presentación activa YA está
 * en el carrito, el botón "Agregar" se sustituye por un control de cantidad
 * [caneca/−] N [+] — N es TEXTO, no un input editable. La caneca aparece con
 * 1 unidad (eliminar); el "−" con 2 o más. El enlace "Ver carrito" que Woo
 * añade tras agregar NO se muestra (también retirado por CSS).
 *
 * Conexión con el carrito:
 *  · Estado inicial y tras cada alta: GET /wc/store/v1/cart (Store API, nunca
 *    cacheada) → mapa productId→{key,qty}. Una sola petición por página/alta.
 *  · +/−/caneca: POST pp_update_cart_qty (el MISMO endpoint del minicart, con
 *    su nonce PP_MINICART, fresco vía ESI). La cantidad REAL se lee del
 *    fragmento del minicart que responde el servidor (respeta el tope de
 *    stock) y sus fragments re-pintan badge y minicart sin peticiones extra.
 *  · Si algo falla, el botón "Agregar" nativo sigue operando: todo es capa.
 */
(function ($) {
	'use strict';

	var ICON = {
		minus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/></svg>',
		plus:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
		trash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>'
	};

	/* ==================== PASTILLAS (comportamiento original) ================ */

	$(document).on('click', '.pp-product-card .pp-pres-pill', function (e) {
		e.preventDefault();
		var $pill = $(this);
		if ($pill.hasClass('is-active')) { return; }

		var $card = $pill.closest('.pp-product-card');

		// 1) Activa la pastilla (y accesibilidad aria-pressed).
		$card.find('.pp-pres-pill').removeClass('is-active').attr('aria-pressed', 'false');
		$pill.addClass('is-active').attr('aria-pressed', 'true');

		// 2) Precio de la presentación seleccionada.
		$card.find('.pp-card-price').first().html($pill.attr('data-price'));

		// 3) El botón "Agregar" ahora agrega ESTA variación. Se actualiza tanto
		//    el atributo como el caché .data() de jQuery (wc-add-to-cart.js lee
		//    .data('product_id') y jQuery no relee el atributo si ya hay caché).
		var vid  = $pill.attr('data-vid');
		var $btn = $card.find('a.add_to_cart_button').first();
		$btn.attr('data-product_id', vid).data('product_id', vid);

		// 4) Estado visual según la NUEVA presentación: si esa variación ya está
		//    en el carrito se muestra su stepper; si no, el botón "Agregar" limpio.
		$btn.removeClass('added loading');
		$card.find('a.added_to_cart').remove();
		paintCard($card);
	});

	/* ==================== STEPPER conectado al carrito ======================= */

	var cartMap = {};      // productId (string) → { key, qty }
	var reqSeq  = 0;       // anti-carrera: solo la respuesta más nueva pinta

	function cartBootstrap(done) {
		fetch('/wp-json/wc/store/v1/cart', { credentials: 'same-origin' })
			.then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
			.then(function (j) {
				cartMap = {};
				(j.items || []).forEach(function (it) {
					cartMap[String(it.id)] = { key: it.key, qty: it.quantity };
				});
				if (done) { done(); }
			})
			.catch(function () { /* sin red: las tarjetas quedan con "Agregar" */ });
	}

	// Crea (una sola vez) el stepper de la tarjeta, oculto; se muestra al pintar.
	function ensureStepper($card) {
		var $st = $card.find('.pp-card-qty');
		if ($st.length) { return $st; }
		$st = $(
			'<div class="pp-card-qty" hidden>' +
				'<button type="button" class="pp-cq-btn pp-cq-minus" data-mode="trash" aria-label="Eliminar del carrito">' + ICON.trash + '</button>' +
				'<span class="pp-cq-num" aria-live="polite">1</span>' +
				'<button type="button" class="pp-cq-btn pp-cq-plus" aria-label="Aumentar cantidad">' + ICON.plus + '</button>' +
			'</div>'
		);
		var $btn = $card.find('a.add_to_cart_button').first();
		if ($btn.length) { $btn.after($st); } else { $card.find('.pp-card-body').append($st); }
		return $st;
	}

	// Pinta UNA tarjeta según el carrito: stepper si su presentación activa está
	// en el carrito; botón "Agregar" limpio si no.
	function paintCard($card) {
		var $btn = $card.find('a.add_to_cart_button').first();
		if (!$btn.length) { return; }
		var pid   = String($btn.attr('data-product_id') || '');
		var entry = cartMap[pid];
		var $st   = ensureStepper($card);

		$card.find('a.added_to_cart').remove(); // "Ver carrito" de Woo: nunca se muestra

		if (entry) {
			$st.attr('data-key', entry.key).attr('data-pid', pid);
			$st.find('.pp-cq-num').text(entry.qty);
			$st.find('.pp-cq-minus')
				.attr('data-mode', entry.qty >= 2 ? 'minus' : 'trash')
				.attr('aria-label', entry.qty >= 2 ? 'Disminuir cantidad' : 'Eliminar del carrito')
				.html(entry.qty >= 2 ? ICON.minus : ICON.trash);
			$st.removeAttr('hidden');
			$btn.addClass('pp-cc-hide').removeClass('added loading');
		} else {
			$st.attr('hidden', 'hidden').removeAttr('data-key');
			$btn.removeClass('pp-cc-hide added loading');
		}
	}

	function paintAll(root) {
		$(root || document).find('.pp-product-card').each(function () { paintCard($(this)); });
	}

	// Cantidad real de una línea, leída del fragmento del minicart que devuelve
	// el servidor (contempla el tope de stock aplicado en PHP).
	function qtyFromFragments(fragments, key) {
		var html = fragments && fragments['div.listeo-mini-cart'];
		if (!html) { return null; }
		var $frag = $('<div>').html(html);
		var $inp  = $frag.find('[data-cart_item_key="' + key + '"] input.pp-qty-input, input.pp-qty-input[data-cart_item_key="' + key + '"]');
		if (!$inp.length) {
			var $box = $frag.find('[data-cart_item_key="' + key + '"]');
			$inp = $box.find('input');
		}
		return $inp.length ? (parseInt($inp.val(), 10) || null) : null; // null = línea ausente (eliminada)
	}

	var opPropia = false; // evita re-leer el carrito por el eco de NUESTROS fragments

	function applyFragments(fragments) {
		if (!fragments) { return; }
		opPropia = true;
		$.each(fragments, function (selector, html) { $(selector).replaceWith(html); });
		$(document.body).trigger('wc_fragments_refreshed');
		setTimeout(function () { opPropia = false; }, 50);
	}

	// Agrupa una ráfaga de clics y envía UNA sola petición con la intención
	// final (mismo patrón que el minicart): además de ahorrar peticiones,
	// evita que dos POST en vuelo (p. ej. "−" y caneca seguidos) lleguen al
	// servidor en orden invertido y dejen el carrito en un estado viejo.
	var qtyTimers = {};
	function scheduleQty($card, key, pid, qty) {
		if (qtyTimers[key]) { clearTimeout(qtyTimers[key]); }
		qtyTimers[key] = setTimeout(function () {
			delete qtyTimers[key];
			sendQty($card, key, pid, qty);
		}, 350);
	}

	// Operación de cantidad desde la tarjeta (+ / − / caneca).
	function sendQty($card, key, pid, qty) {
		if (!window.PP_MINICART || !PP_MINICART.nonce) { return; }
		var mySeq = ++reqSeq;
		$card.addClass('pp-cq-busy');
		$.post(PP_MINICART.ajax_url, {
			action: 'pp_update_cart_qty',
			cart_item_key: key,
			quantity: qty,
			nonce: PP_MINICART.nonce
		}).then(function (resp) {
			if (mySeq !== reqSeq) { return; }
			$card.removeClass('pp-cq-busy');
			if (resp && resp.success) {
				var real = qtyFromFragments(resp.data && resp.data.fragments, key);
				if (qty <= 0 || null === real) {
					delete cartMap[pid];
				} else {
					cartMap[pid] = { key: key, qty: real };
				}
				applyFragments(resp.data && resp.data.fragments); // badge + minicart
				paintAll(); // todas las tarjetas de ese producto en la página
			} else {
				cartBootstrap(function () { paintAll(); }); // estado real del servidor
			}
		}, function () {
			if (mySeq !== reqSeq) { return; }
			$card.removeClass('pp-cq-busy');
			cartBootstrap(function () { paintAll(); });
		});
	}

	// Los clics responden AL INSTANTE (UI optimista) y el servidor confirma
	// después: sin esto, la espera de 1-3s sin cambio visible hacía pulsar la
	// caneca varias veces creyendo que no funcionaba. Si el servidor
	// discrepa (tope de stock, línea inexistente), la respuesta re-pinta con
	// la verdad y el estado se corrige solo.
	$(document).on('click', '.pp-product-card .pp-cq-plus', function (e) {
		e.preventDefault();
		var $card = $(this).closest('.pp-product-card');
		var $st   = $(this).closest('.pp-card-qty');
		var pid   = String($st.attr('data-pid') || '');
		var entry = cartMap[pid];
		if (!entry) { return; }
		cartMap[pid] = { key: entry.key, qty: entry.qty + 1 }; // optimista
		paintCard($card);
		scheduleQty($card, entry.key, pid, entry.qty + 1);
	});

	$(document).on('click', '.pp-product-card .pp-cq-minus', function (e) {
		e.preventDefault();
		var $card = $(this).closest('.pp-product-card');
		var $st   = $(this).closest('.pp-card-qty');
		var pid   = String($st.attr('data-pid') || '');
		var entry = cartMap[pid];
		if (!entry) { return; }
		var mode = $(this).attr('data-mode');
		if ('trash' === mode) {
			delete cartMap[pid]; // optimista: el botón "Agregar" vuelve YA
			paintCard($card);
			scheduleQty($card, entry.key, pid, 0);
		} else {
			cartMap[pid] = { key: entry.key, qty: entry.qty - 1 }; // optimista
			paintCard($card);
			scheduleQty($card, entry.key, pid, entry.qty - 1);
		}
	});

	// Alta por el AJAX nativo de Woo: el evento added_to_cart llega con los
	// fragments ya aplicados por wc-add-to-cart.js. La KEY de la línea no viene
	// en el evento, así que se relee el carrito (una petición) y se pinta.
	$(document.body).on('added_to_cart', function (e, fragments, cartHash, $button) {
		var $card = $button && $button.length ? $button.closest('.pp-product-card') : $();
		if ($card.length) { $card.find('a.added_to_cart').remove(); }
		cartBootstrap(function () { paintAll(); });
	});

	// El grid puede repintarse (filtros AJAX, scroll infinito): pintar lo nuevo.
	function watchGrids() {
		var grid = document.querySelector('.listeo-shop-grid ul.products, ul.products');
		if (!grid || !window.MutationObserver) { return; }
		var timer = null;
		new MutationObserver(function () {
			if (timer) { return; }
			timer = setTimeout(function () { timer = null; paintAll(); }, 150);
		}).observe(grid, { childList: true, subtree: true });
	}

	// El carrito puede cambiar por FUERA de las tarjetas (drawer del minicart,
	// página del carrito, otra pestaña): sin esto las tarjetas se quedaban con
	// steppers y cantidades viejas tras vaciar el carrito, y la caneca/− dejaba
	// de responder (su key ya no existía). Cualquier anuncio de fragmentos
	// re-lee el carrito y re-pinta (con debounce; se salta el eco de las
	// operaciones propias, que ya actualizan el mapa con datos exactos).
	var syncTimer = null;
	$(document.body).on('wc_fragments_refreshed wc_fragment_refresh added_to_cart removed_from_cart updated_wc_div', function () {
		if (opPropia) { return; }
		if (syncTimer) { clearTimeout(syncTimer); }
		syncTimer = setTimeout(function () {
			syncTimer = null;
			cartBootstrap(function () { paintAll(); });
		}, 250);
	});

	// Volver con el botón Atrás (bfcache): la página revive con el estado
	// congelado de antes → re-leer el carrito real.
	window.addEventListener('pageshow', function (e) {
		if (e.persisted && document.querySelector('.pp-product-card')) {
			cartBootstrap(function () { paintAll(); });
		}
	});

	$(function () {
		if (!document.querySelector('.pp-product-card')) { return; }
		cartBootstrap(function () { paintAll(); });
		watchGrids();
	});
})(jQuery);
