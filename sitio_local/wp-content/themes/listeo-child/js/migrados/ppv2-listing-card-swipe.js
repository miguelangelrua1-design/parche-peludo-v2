/* migrado de functions.php::ppv2_listing_card_swipe() 2026-07-10
   Se imprime en TODAS las páginas del front (sin condición PHP); actúa sobre tarjetas .listing-card-nl donde existan. */
(function () {
	function bind() {
		var cards = document.querySelectorAll('.listing-card-nl');
		for (var i = 0; i < cards.length; i++) {
			(function (card) {
				if (card.dataset.ppv2Swipe) return;
				card.dataset.ppv2Swipe = '1';
				var slides = card.querySelectorAll('.slider-image-nl');
				if (slides.length <= 1) return;
				var area = card.querySelector('.slider-wrapper-nl');
				if (!area) return;
				var x0 = 0, y0 = 0, on = false;
				area.addEventListener('touchstart', function (e) {
					if (e.touches.length !== 1) { on = false; return; }
					x0 = e.touches[0].clientX;
					y0 = e.touches[0].clientY;
					on = true;
				}, { passive: true });
				area.addEventListener('touchend', function (e) {
					if (!on) return;
					on = false;
					var t = e.changedTouches[0];
					var dx = t.clientX - x0, dy = t.clientY - y0;
					// swipe horizontal claro (umbral 40px y más horizontal que vertical)
					if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
						var sel = dx < 0 ? '#nextBtn' : '#prevBtn';
						var btn = card.querySelector(sel);
						if (btn) btn.click();
					}
				}, { passive: true });
			})(cards[i]);
		}
	}
	if (document.readyState !== 'loading') { bind(); }
	else { document.addEventListener('DOMContentLoaded', bind); }
	if (window.jQuery) { jQuery(document).on('ajaxContentLoaded', bind); }
})();
