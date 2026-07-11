// migrado de functions.php::ppv2_shop_category_tree() 2026-07-10
// Condición de páginas: ppv2_is_shop_context() → is_shop() || is_product_taxonomy() (Tienda WooCommerce)
(function () {
	var root = document.querySelector('.listeo-shop-grid .col-sidebar ul.product-categories');
	if ( ! root ) { return; }

	// 1) Construir cada fila: [chevron | espaciador] + <a> + <span.count>
	Array.prototype.forEach.call( root.querySelectorAll('li.cat-item'), function ( li ) {
		if ( li.querySelector(':scope > .pp-cat-row') ) { return; } // ya procesado
		var a = li.querySelector(':scope > a');
		if ( ! a ) { return; }
		var count   = li.querySelector(':scope > .count');
		var childUl = li.querySelector(':scope > ul.children');
		var isParent = li.classList.contains('cat-parent') && !! childUl;

		var row = document.createElement('div');
		row.className = 'pp-cat-row';

		if ( isParent ) {
			li.classList.add('pp-has-children');
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'pp-cat-toggle';
			btn.setAttribute('aria-label', 'Mostrar u ocultar subcategorías');
			btn.setAttribute('aria-expanded', 'false');
			row.appendChild(btn);
		} else {
			var sp = document.createElement('span');
			sp.className = 'pp-cat-spacer';
			row.appendChild(sp);
		}

		row.appendChild(a); // mueve el enlace dentro de la fila
		if ( count ) {
			count.textContent = count.textContent.replace(/[()\s]/g, ''); // "(4)" -> "4"
			row.appendChild(count);
		}
		li.insertBefore(row, childUl || null);
	});

	// 2) Abrir automáticamente el camino de la categoría actual (si la hay).
	Array.prototype.forEach.call( root.querySelectorAll('li.current-cat'), function ( li ) {
		var p = li;
		while ( p && p !== root ) {
			if ( p.tagName === 'LI' && p.classList.contains('pp-has-children') ) {
				p.classList.add('pp-open');
				var b = p.querySelector(':scope > .pp-cat-row > .pp-cat-toggle');
				if ( b ) { b.setAttribute('aria-expanded', 'true'); }
			}
			p = p.parentElement;
		}
	});

	// 3) Plegar/desplegar al pulsar el chevron (sin navegar a la categoría).
	root.addEventListener('click', function ( e ) {
		var btn = e.target.closest('.pp-cat-toggle');
		if ( ! btn ) { return; }
		e.preventDefault();
		e.stopPropagation();
		var li = btn.closest('li.cat-item');
		var open = li.classList.toggle('pp-open');
		btn.setAttribute('aria-expanded', open ? 'true' : 'false');
	});
})();
