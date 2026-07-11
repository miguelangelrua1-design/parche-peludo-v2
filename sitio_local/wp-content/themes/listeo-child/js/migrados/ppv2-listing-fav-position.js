// Migrado de functions.php::ppv2_listing_fav_position() 2026-07-10 (wp_footer, prio 101).
// Condición original: if ( ! is_singular( 'listing' ) ) return; — solo página individual de listado.
	document.addEventListener('DOMContentLoaded', function () {
		function setupReviewCount(tries) {
			var counter = document.querySelector('.ppv2-meta-top .rating-counter')
				|| document.querySelector('#titlebar .rating-counter');
			if (!counter) {
				if (tries > 0) setTimeout(function () { setupReviewCount(tries - 1); }, 100);
				return;
			}
			if (counter.querySelector('.ppv2-reviews-short')) return; // ya preparado

			// Buscar el nodo de texto que contiene "(N reseñas)" dentro de algún <a>
			var links = counter.querySelectorAll('a');
			for (var i = 0; i < links.length; i++) {
				var a = links[i];
				for (var j = 0; j < a.childNodes.length; j++) {
					var n = a.childNodes[j];
					if (n.nodeType === 3 && /\(\s*\d+\s*rese/i.test(n.nodeValue)) {
						var m = n.nodeValue.match(/(\d+)/);
						var num = m ? m[1] : '';
						var full = document.createElement('span');
						full.className = 'ppv2-reviews-full';
						full.textContent = '(' + num + ' reseñas)';
						var short = document.createElement('span');
						short.className = 'ppv2-reviews-short';
						short.textContent = '(' + num + ')';
						var space = document.createTextNode(' ');
						a.replaceChild(short, n);   // el nodo de texto -> variante corta
						a.insertBefore(full, short); // variante larga antes
						a.insertBefore(space, full); // espacio tras "4.6"
						return;
					}
				}
			}
		}
		setupReviewCount(25);
	});
