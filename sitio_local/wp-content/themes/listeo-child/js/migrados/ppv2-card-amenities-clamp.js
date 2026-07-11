/* migrado de functions.php::ppv2_card_amenities_clamp() 2026-07-10
   Se imprime en TODAS las páginas del front (sin condición PHP); actúa sobre .listing-card-nl .listing-amenities-nl donde existan. */
(function () {
	function clamp(container) {
		var labels = [].slice.call(container.querySelectorAll('.amenity-icon-nl'));
		if (!labels.length) return;
		// reset
		labels.forEach(function (l) { l.style.removeProperty('display'); });
			void container.offsetHeight; // reflow para medir bien
		var old = container.querySelector('.ppv2-amen-more');
		if (old) old.parentNode.removeChild(old);

		// Altura objetivo: 2 filas. La medimos con la altura de una etiqueta + gap.
		var cs = getComputedStyle(container);
		var padV = (parseFloat(cs.paddingTop) || 0) + (parseFloat(cs.paddingBottom) || 0);
		var gap = parseFloat(cs.rowGap || cs.gap) || 6;
		var labelH = labels[0].offsetHeight || 25;
		var rows = window.matchMedia('(min-width: 1025px)').matches ? 3 : 2; // escritorio 3, móvil 2
		var maxH = padV + labelH * rows + gap * (rows - 1) + 4; // N filas + tolerancia

		if (container.offsetHeight <= maxH) return; // ya cabe en N filas

		// Hay más etiquetas de las que caben: añadimos "…" y ocultamos desde el
		// final (con prioridad important, porque el CSS las pone display:inline-flex)
		// hasta que el contenedor quepa en 2 filas.
		var more = document.createElement('span');
		more.className = 'ppv2-amen-more';
		more.textContent = '…';
		container.appendChild(more);

		var vis = labels.slice();
		var guard = 0;
		while (container.offsetHeight > maxH && vis.length > 1 && guard < labels.length) {
			vis.pop().style.setProperty('display', 'none', 'important');
			guard++;
		}
	}
	function run() {
		var conts = document.querySelectorAll('.listing-card-nl .listing-amenities-nl');
		for (var i = 0; i < conts.length; i++) { clamp(conts[i]); }
	}
	var t;
	function runStable() { requestAnimationFrame(function () { requestAnimationFrame(run); }); }
		function debounced() { clearTimeout(t); t = setTimeout(runStable, 150); }
	if (document.readyState !== 'loading') { run(); }
	else { document.addEventListener('DOMContentLoaded', run); }
	window.addEventListener('load', runStable);           // tras fuentes/imágenes
	window.addEventListener('resize', debounced);
	if (window.jQuery) {
			jQuery(document).on('ajaxContentLoaded', function () {
				[120, 450, 1000].forEach(function (ms) { setTimeout(runStable, ms); });
			});
		}
})();
