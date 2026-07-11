// migrado de functions.php::ppv2_dirfilters_categorias_acordeon() 2026-07-10
// Condición de páginas: ppv2_is_listings_archive() → is_post_type_archive('listing') || is_tax(taxonomías de listing) (Directorio)
(function () {
	var sel = document.getElementById('tax-listing_category');
	if ( ! sel || ! window.jQuery ) { return; }

	var $ = window.jQuery;
	var field = sel.closest('#listeo-search-form_tax-listing_category') || sel.parentNode;
	if ( ! field || field.querySelector('.pp-dircats') ) { return; }

	// 1) Agrupar las <option> planas en madres + hijas (prefijo de &nbsp;).
	var grupos = [];
	Array.prototype.forEach.call(sel.options, function ( opt ) {
		var bruto  = opt.textContent || '';
		var esHija = /^(\u00a0|\s){2}/.test(bruto);
		var texto  = bruto.replace(/\u00a0/g, ' ').trim();
		if ( ! esHija || ! grupos.length ) {
			grupos.push({ opt: opt, label: texto, hijas: [] });
		} else {
			grupos[grupos.length - 1].hijas.push({ opt: opt, label: texto });
		}
	});
	if ( ! grupos.length ) { return; }

	// 2) Construir el acordeón. Arranca CERRADO: solo se ve la cabecera
	//    "Categorías" con su contador. Las etiquetas de lo seleccionado
	//    quedan FUERA del bloque plegable, para que sigan a la vista con el
	//    acordeón cerrado (si no, no habría forma de saber qué hay filtrado).
	var caja = document.createElement('div');
	caja.className = 'pp-dircats';
	caja.innerHTML = '<button type="button" class="pp-dircats-head" aria-expanded="false">'
		+ '<span class="pp-dircats-title">Categorías</span>'
		+ '<span class="pp-dircats-count" hidden></span>'
		+ '</button>'
		+ '<div class="pp-dircats-chips" hidden></div>'
		+ '<div class="pp-dircats-body" hidden><ul class="pp-dircats-list"></ul></div>';
	var cabecera = caja.querySelector('.pp-dircats-head');
	var contador = caja.querySelector('.pp-dircats-count');
	var cuerpo   = caja.querySelector('.pp-dircats-body');
	var chips    = caja.querySelector('.pp-dircats-chips');
	var lista    = caja.querySelector('.pp-dircats-list');

	cabecera.addEventListener('click', function () {
		var abierto = caja.classList.toggle('pp-open');
		cuerpo.hidden = ! abierto;
		cabecera.setAttribute('aria-expanded', abierto ? 'true' : 'false');
	});

	function casilla( label, extraClase ) {
		var l = document.createElement('label');
		l.className = 'pp-dircat-check' + ( extraClase ? ' ' + extraClase : '' );
		var i = document.createElement('input');
		i.type = 'checkbox';
		var s = document.createElement('span');
		s.textContent = label;
		l.appendChild(i);
		l.appendChild(s);
		return { label: l, input: i };
	}

	grupos.forEach(function ( g ) {
		var li = document.createElement('li');
		li.className = 'pp-dircat';

		var fila = document.createElement('div');
		fila.className = 'pp-dircat-row';
		var c = casilla(g.label, 'is-parent');
		g.cb = c.input;
		fila.appendChild(c.label);

		if ( g.hijas.length ) {
			li.classList.add('pp-has-children');
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'pp-dircat-toggle';
			btn.setAttribute('aria-expanded', 'false');
			btn.setAttribute('aria-label', 'Mostrar u ocultar subcategorías de ' + g.label);
			fila.appendChild(btn);
		}
		li.appendChild(fila);

		if ( g.hijas.length ) {
			var ul = document.createElement('ul');
			ul.className = 'pp-dircat-children';
			ul.hidden = true;
			g.hijas.forEach(function ( h ) {
				var hli = document.createElement('li');
				var hc = casilla(h.label);
				h.cb = hc.input;
				hli.appendChild(hc.label);
				ul.appendChild(hli);
			});
			li.appendChild(ul);
		}
		lista.appendChild(li);
	});

	field.classList.add('pp-dircats-ready'); // el CSS oculta el bootstrap-select
	field.appendChild(caja);

	// 3) Estado.
	// La madre solo queda marcada cuando TODAS sus hijas lo están; si hay unas
	// sí y otras no, se muestra en estado intermedio (guion), que es la señal
	// universal de selección parcial.
	function refrescarMadre( g ) {
		if ( ! g.hijas.length ) { return; }
		var n = g.hijas.filter(function ( h ) { return h.cb.checked; }).length;
		g.cb.checked = ( n === g.hijas.length );
		g.cb.indeterminate = ( n > 0 && n < g.hijas.length );
	}

	// Vuelca el estado de las casillas sobre las <option> del select nativo.
	// `avisar` = false al inicializar, para no lanzar una búsqueda al cargar.
	//
	// A propósito NO se llama a $(sel).selectpicker('refresh'): repintar el
	// widget de bootstrap-select cuesta ~66 ms medidos, y ese widget está
	// oculto (nadie ve el resultado). Con el refresh cada clic bloqueaba el
	// hilo principal ~146 ms; sin él, ~20 ms, de los cuales 16 son el propio
	// manejador de Listeo que relanza la búsqueda. Lo que viaja en la
	// búsqueda son las <option> seleccionadas, no el widget.
	//
	// Cuando la madre está marcada se envía SOLO su slug, nunca el de las
	// hijas: WordPress ya incluye las subcategorías al filtrar por la madre
	// (`include_children`). Mandarlas todas era redundante y, como Listeo
	// tiene la opción `listeo_listing_categorysearch_mode` en AND, exigía que
	// un listado estuviera a la vez en la madre y en las 5 hijas → 0
	// resultados siempre. Verificado: "salud-mascotas" sola devuelve 2.
	function volcarASelect( avisar ) {
		grupos.forEach(function ( g ) {
			if ( g.cb.checked ) {
				g.opt.selected = true;
				g.hijas.forEach(function ( h ) { h.opt.selected = false; });
			} else {
				g.opt.selected = false;
				g.hijas.forEach(function ( h ) { h.opt.selected = h.cb.checked; });
			}
		});
		if ( avisar ) { $(sel).trigger('change'); }
	}

	function pintarChips() {
		var items = [];
		grupos.forEach(function ( g ) {
			if ( g.cb.checked ) {
				items.push({ label: g.label, g: g, h: null });
			} else {
				g.hijas.forEach(function ( h ) {
					if ( h.cb.checked ) { items.push({ label: h.label, g: g, h: h }); }
				});
			}
		});
		chips.innerHTML = '';
		chips.hidden = ! items.length;
		// Contador en la cabecera: única pista de que hay filtro activo cuando
		// el acordeón está cerrado y las etiquetas no caben de un vistazo.
		contador.textContent = items.length;
		contador.hidden = ! items.length;
		items.forEach(function ( it ) {
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'pp-dircat-chip';
			b.setAttribute('aria-label', 'Quitar ' + it.label);
			b.innerHTML = '<span></span><i aria-hidden="true">&times;</i>';
			b.querySelector('span').textContent = it.label;
			b.addEventListener('click', function () {
				if ( it.h ) {
					it.h.cb.checked = false;
					refrescarMadre(it.g);
				} else {
					it.g.cb.checked = false;
					it.g.cb.indeterminate = false;
					it.g.hijas.forEach(function ( h ) { h.cb.checked = false; });
				}
				aplicar();
			});
			chips.appendChild(b);
		});
		if ( items.length > 1 ) {
			var limpiar = document.createElement('button');
			limpiar.type = 'button';
			limpiar.className = 'pp-dircats-clear';
			limpiar.textContent = 'Limpiar';
			limpiar.addEventListener('click', function () {
				grupos.forEach(function ( g ) {
					g.cb.checked = false;
					g.cb.indeterminate = false;
					g.hijas.forEach(function ( h ) { h.cb.checked = false; });
				});
				aplicar();
			});
			chips.appendChild(limpiar);
		}
	}

	function aplicar() {
		pintarChips();
		volcarASelect(true);
	}

	// 4) Interacción.
	grupos.forEach(function ( g ) {
		g.cb.addEventListener('change', function () {
			// Madre marcada = madre + todas sus hijas (regla de producto).
			g.cb.indeterminate = false;
			g.hijas.forEach(function ( h ) { h.cb.checked = g.cb.checked; });
			aplicar();
		});
		g.hijas.forEach(function ( h ) {
			h.cb.addEventListener('change', function () {
				refrescarMadre(g);
				aplicar();
			});
		});
	});

	lista.addEventListener('click', function ( e ) {
		var btn = e.target.closest('.pp-dircat-toggle');
		if ( ! btn ) { return; }
		var li = btn.closest('.pp-dircat');
		var ul = li.querySelector('.pp-dircat-children');
		var abierto = li.classList.toggle('pp-open');
		if ( ul ) { ul.hidden = ! abierto; }
		btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
	});

	// 5) Estado inicial desde lo que ya trae el select (p. ej. al llegar desde
	//    una página de categoría). Si la madre venía seleccionada, se marcan
	//    también sus hijas, coherente con la regla de arriba.
	grupos.forEach(function ( g ) {
		if ( g.opt.selected ) {
			g.cb.checked = true;
			g.hijas.forEach(function ( h ) { h.cb.checked = true; });
		} else {
			g.hijas.forEach(function ( h ) { h.cb.checked = h.opt.selected; });
			refrescarMadre(g);
		}
		// Abrir de entrada los grupos que traigan algo seleccionado.
		if ( g.cb.checked || g.cb.indeterminate ) {
			var li = g.cb.closest('.pp-dircat');
			var btn = li && li.querySelector('.pp-dircat-toggle');
			if ( btn ) {
				li.classList.add('pp-open');
				li.querySelector('.pp-dircat-children').hidden = false;
				btn.setAttribute('aria-expanded', 'true');
			}
		}
	});
	pintarChips();
	volcarASelect(false);
})();
