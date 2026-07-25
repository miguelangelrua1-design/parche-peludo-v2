/* migrado de functions.php::ppv2_header_suggest_js() 2026-07-10 (hook: wp_print_footer_scripts, prioridad 50 — DESPUÉS del script de Listeo prio 11)
   Se imprime en TODAS las páginas del front (sin condición PHP). Requiere jQuery + jQuery UI Autocomplete (encolado en ppv2_header_suggest_assets) y window.PPV2_CFG = { ajaxUrl: admin_url('admin-ajax.php'), homeUrl: home_url('/') }. */
(function($) {
	if (!$ || !$.fn || typeof $.fn.autocomplete === 'undefined') { return; }
	$(function() {
		var ajaxUrl = window.PPV2_CFG.ajaxUrl;
		var homeUrl = window.PPV2_CFG.homeUrl;
		var SCOPE_KEY = 'ppv2SearchScope';
		var cache = {}; // resultados por término: el cambio de tab no vuelve a consultar al servidor

		// Tab activo ("tienda" | "directorio"). Reglas (2026-07-18):
		// 1) La SECCIÓN manda al cargar (PPV2_CFG.pageScope): en zona de compra
		//    → Tienda; en el directorio/Adopción/M. perdidas → Directorio,
		//    aunque el usuario hubiera elegido otra cosa antes.
		// 2) En páginas neutras aplica la elección guardada de la sesión.
		// 3) Sin nada de lo anterior, el default global es TIENDA.
		// Los clics manuales dentro de la página siguen funcionando normal.
		function getScope() {
			try {
				var s = window.sessionStorage.getItem(SCOPE_KEY);
				if (s === 'tienda' || s === 'directorio') { return s; }
			} catch (e) {}
			return 'tienda';
		}
		function setScope(scope) {
			try { window.sessionStorage.setItem(SCOPE_KEY, scope); } catch (e) {}
		}
		var pageScope = (window.PPV2_CFG.pageScope === 'tienda' || window.PPV2_CFG.pageScope === 'directorio')
			? window.PPV2_CFG.pageScope : '';
		if (pageScope) { setScope(pageScope); }

		// Reordena en el navegador: el grupo del tab activo va primero.
		function orderByScope(items) {
			var listings = [], products = [];
			$.each(items || [], function(index, item) {
				(item.group === 'Tienda' ? products : listings).push(item);
			});
			return getScope() === 'tienda' ? products.concat(listings) : listings.concat(products);
		}

		// --- REGISTRO DE BÚSQUEDAS (módulo Buscador de pp-personalizacion) ---
		// Se envía por beacon desde el navegador porque LiteSpeed cachea las
		// páginas de resultados: un registro en PHP no correría en los hits de
		// caché. No bloquea la navegación y sobrevive al cambio de página.
		function logBusqueda(termino, ambito, resultados, origen) {
			if (!termino) { return; }
			try {
				var datos = new FormData();
				datos.append('action', 'pp_buscador_log');
				datos.append('termino', termino);
				datos.append('ambito', ambito || '');
				datos.append('resultados', typeof resultados === 'number' ? resultados : 0);
				datos.append('origen', origen || 'resultados');
				if (navigator.sendBeacon) {
					navigator.sendBeacon(ajaxUrl, datos);
				} else {
					$.post(ajaxUrl, {
						action: 'pp_buscador_log', termino: termino, ambito: ambito || '',
						resultados: resultados || 0, origen: origen || 'resultados'
					});
				}
			} catch (e) {}
		}

		// Al cargar una página de resultados, reportar qué se buscó y cuántos
		// resultados dio (el dato lo publica ppv2_publicar_contexto_busqueda).
		if (window.PPV2_SEARCH_CTX && window.PPV2_SEARCH_CTX.termino) {
			logBusqueda(
				window.PPV2_SEARCH_CTX.termino,
				window.PPV2_SEARCH_CTX.ambito,
				window.PPV2_SEARCH_CTX.resultados,
				'resultados'
			);
		}

		// Clics de rescate: miden si los puentes y las correcciones funcionan.
		$(document).on('click', '.ppv2-quisiste-decir a[data-ppv2-correccion]', function() {
			var ctx = window.PPV2_SEARCH_CTX || {};
			logBusqueda($(this).attr('data-ppv2-correccion'), ctx.ambito, 0, 'correccion-click');
		});
		$(document).on('click', '.ppv2-cross-search-btn', function() {
			var ctx = window.PPV2_SEARCH_CTX || {};
			if (ctx.termino) { logBusqueda(ctx.termino, ctx.ambito, 0, 'puente-click'); }
		});

		// --- Panel de búsquedas populares (al enfocar el campo vacío) ---
		var $pop = null, popCargadas = null;
		function mostrarPopulares($field) {
			if (!window.PPV2_CFG.populares) { return; }
			if (popCargadas === null) {
				popCargadas = [];
				$.getJSON(ajaxUrl, { action: 'pp_buscador_populares' })
					.done(function(data) {
						popCargadas = data || [];
						if ($field.is(':focus') && !$field.val().trim()) { pintarPopulares($field); }
					});
				return;
			}
			pintarPopulares($field);
		}
		function pintarPopulares($field) {
			if (!popCargadas || !popCargadas.length) { return; }
			if (!$pop) { $pop = $('<div class="ppv2-populares"></div>').appendTo(document.body); }
			var html = '<div class="ppv2-populares-titulo">Lo más buscado</div>';
			$.each(popCargadas, function(i, t) {
				html += '<button type="button" class="ppv2-popular">' + $('<i>').text(t).html() + '</button>';
			});
			var o = $field.offset();
			$pop.html(html).css({
				top: (o.top + $field.outerHeight() + 4) + 'px',
				left: o.left + 'px',
				minWidth: $field.outerWidth() + 'px'
			}).show();
		}
		function ocultarPopulares() { if ($pop) { $pop.hide(); } }

		$(document).on('mousedown', '.ppv2-popular', function(event) {
			event.preventDefault();
			var termino = $(this).text();
			var $field = $('input[name="keyword_search"]:visible').first();
			if (!$field.length) { $field = $('input[name="keyword_search"]').first(); }
			$field.val(termino);
			ocultarPopulares();
			if ($field.data('ui-autocomplete')) { $field.autocomplete('search', termino); }
		});

		// --- Panel de estado bajo el campo: "Buscando…" apenas arranca la
		// búsqueda (feedback inmediato) y "Sin resultados" cuando no hay nada.
		var $status = $('<div class="ppv2-suggest-status" style="display:none"></div>').appendTo(document.body);
		function showStatus($field, busy) {
			var o = $field.offset();
			$status
				.html(busy
					? '<span class="ppv2-suggest-spinner"></span><span>Buscando…</span>'
					: '<span>Sin resultados en Directorio ni Tienda</span>')
				.css({
					top: (o.top + $field.outerHeight() + 4) + 'px',
					left: o.left + 'px',
					minWidth: $field.outerWidth() + 'px'
				})
				.show();
		}
		function hideStatus() { $status.hide(); }

		// --- Conteo de coincidencias en las pestañas ("Directorio · 0 | Tienda · 5"):
		// con la respuesta de sugerencias (trae ambos grupos, máx. 5 c/u) se pinta
		// cuántas hay en cada mundo; "5+" cuando llega al tope. Señal clave para
		// quien busca con el tab equivocado. Se limpia al vaciar el campo.
		function updateTabCounts(items) {
			var listings = 0, products = 0;
			$.each(items || [], function(index, item) {
				if (item.group === 'Tienda') { products++; } else { listings++; }
			});
			var fmt = function(n) { return n >= 5 ? '5+' : String(n); };
			$('.ppv2-scope-tab').each(function() {
				var n = ($(this).attr('data-scope') === 'tienda') ? products : listings;
				var $badge = $(this).find('.ppv2-scope-count');
				if (!$badge.length) {
					$badge = $('<span class="ppv2-scope-count"></span>').appendTo(this);
				}
				$badge.text(fmt(n));
				$(this).toggleClass('ppv2-scope-zero', n === 0);
			});
			syncFieldPadding();
		}
		function clearTabCounts() {
			$('.ppv2-scope-tab .ppv2-scope-count').remove();
			$('.ppv2-scope-tab').removeClass('ppv2-scope-zero');
			syncFieldPadding();
		}

		// --- Reserva dinámica de espacio en el campo (solo escritorio): el texto
		// escrito nunca queda debajo de la fila de pills. El ancho de la fila
		// VARÍA (conteos "· 5", chip "+" del módulo de tipos que se monta dentro
		// de la misma fila), así que se mide el ancho real y se fija el
		// padding-left. setProperty con 'important' porque la regla base del
		// style.css (220px de fallback) también usa !important.
		function syncFieldPadding() {
			$('.ppv2-has-scope-tabs').each(function() {
				var tabs = $(this).find('.ppv2-scope-tabs').get(0);
				var field = $(this).find('input[name="keyword_search"]').get(0);
				if (!tabs || !field) { return; }
				if (window.matchMedia && window.matchMedia('(max-width: 767px)').matches) {
					field.style.removeProperty('padding-left'); // móvil: tabs arriba del campo
					return;
				}
				var w = Math.ceil(tabs.getBoundingClientRect().width) + 10 + 16; // offset izq + aire
				field.style.setProperty('padding-left', w + 'px', 'important');
			});
		}

		// --- Tabs Directorio | Tienda siempre visibles en el buscador del header ---
		function updateTabsUI() {
			var scope = getScope();
			$('.ppv2-scope-tab').each(function() {
				$(this).toggleClass('ppv2-scope-tab-active', $(this).attr('data-scope') === scope);
			});
		}

		$('.header-search-container .main-search-input-item.text').each(function() {
			var $item = $(this);
			if (!$item.find('input[name="keyword_search"]').length || $item.find('.ppv2-scope-tabs').length) {
				return;
			}
			$item.addClass('ppv2-has-scope-tabs');
			var $tabs = $('<div class="ppv2-scope-tabs" role="tablist"></div>');
			// Orden pedido por Miguel (2026-07-18): Tienda primero, Directorio después.
			$.each([
				{ id: 'tienda', label: 'Tienda' },
				{ id: 'directorio', label: 'Directorio' }
			], function(index, tab) {
				$('<button type="button"></button>')
					.addClass('ppv2-scope-tab')
					.attr('data-scope', tab.id)
					.text(tab.label)
					.appendTo($tabs);
			});
			$item.append($tabs);
		});
		updateTabsUI();
		syncFieldPadding();
		// El chip "+" (módulo de tipos) se monta DESPUÉS dentro de la fila y los
		// conteos cambian su texto: cualquier mutación re-mide la reserva.
		if (window.MutationObserver) {
			var ppv2PadObserver = new MutationObserver(syncFieldPadding);
			$('.ppv2-scope-tabs').each(function() {
				ppv2PadObserver.observe(this, { childList: true, subtree: true, characterData: true });
			});
		}
		$(window).on('resize load', syncFieldPadding);

		$(document).on('click', '.ppv2-scope-tab', function(event) {
			event.preventDefault();
			setScope($(this).attr('data-scope') === 'tienda' ? 'tienda' : 'directorio');
			updateTabsUI();
			// Reordena las sugerencias abiertas al instante (desde caché, sin servidor).
			var $field = $(this).closest('.main-search-input-item').find('input[name="keyword_search"]');
			if ($field.length) {
				$field.trigger('focus');
				if ($field.val().trim().length >= 2 && $field.data('ui-autocomplete')) {
					$field.autocomplete('search', $field.val());
				}
			}
		});

		// Enter o botón "Buscar" del header con el tab Tienda: ir a los resultados
		// de la tienda (búsqueda nativa de WooCommerce, solo productos). Con
		// Directorio no intervenimos: el formulario sigue yendo a listados.
		// Captura (true) para adelantarnos a los handlers de Listeo (AJAX browsing).
		// Solo aplica al buscador del header (donde están los tabs visibles).
		document.addEventListener('submit', function(event) {
			var form = event.target;
			if (!form || !form.closest || !form.closest('.header-search-container')) { return; }
			var field = form.querySelector('input[name="keyword_search"]');
			if (!field || getScope() !== 'tienda') { return; }
			event.preventDefault();
			event.stopImmediatePropagation();
			var term = (field.value || '').trim();
			window.location.href = term
				? homeUrl + '?s=' + encodeURIComponent(term) + '&post_type=product'
				: homeUrl + '?post_type=product';
		}, true);

		$('input[name="keyword_search"]').each(function() {
			var $input = $(this);

			// Si Listeo ya montó su autocompletado (solo listados), lo quitamos.
			if ($input.data('ui-autocomplete')) {
				try { $input.autocomplete('destroy'); } catch (e) {}
			}

			$input.autocomplete({
				minLength: 2,
				delay: 350,
				source: function(req, response) {
					var term = req.term;
					// Tipo de listado activo (chip "+" de Personalización Parche):
					// viaja como input oculto en el form del buscador. Las
					// sugerencias se limitan a ese tipo y se cachean aparte.
					var tipo = '';
					var tipoInput = document.querySelector('.header-search-container form input[name="_listing_type"]');
					if (tipoInput && tipoInput.value) { tipo = tipoInput.value; }
					var cacheKey = term + '|' + tipo;
					if (cache[cacheKey]) {
						response(orderByScope(cache[cacheKey]));
						return;
					}
					$.getJSON(ajaxUrl, { action: 'ppv2_header_suggest', term: term, listing_type: tipo })
						.done(function(data) {
							cache[cacheKey] = data || [];
							response(orderByScope(cache[cacheKey]));
						})
						.fail(function() { response([]); });
				},
				select: function(event, ui) {
					if (ui.item && ui.item.link) {
						// Clic en sugerencia = intención fuerte: se registra aparte
						// (no infla el conteo de "búsquedas", que usa origen 'resultados').
						logBusqueda($input.val(), getScope(), 1, 'sugerencia-click');
						window.location.href = ui.item.link;
					}
					return false;
				},
				focus: function() { return false; }
			});

			// Campo vacío + foco → "Lo más buscado"; al escribir, desaparece.
			$input.on('focus click', function() {
				if (!$input.val().trim()) { mostrarPopulares($input); }
			});
			$input.on('input', function() {
				if ($input.val().trim()) { ocultarPopulares(); }
				else { mostrarPopulares($input); }
			});
			$input.on('blur', function() { window.setTimeout(ocultarPopulares, 180); });

			// Ciclo de estado: al iniciar la búsqueda → "Buscando…"; al llegar
			// la respuesta → ocultar (hay menú) o "Sin resultados" (vacía).
			$input.on('autocompletesearch', function() {
				showStatus($input, true);
			});
			$input.on('autocompleteresponse', function(event, ui) {
				updateTabCounts(ui && ui.content ? ui.content : []);
				if (ui && ui.content && ui.content.length) {
					hideStatus();
				} else {
					showStatus($input, false);
				}
			});
			$input.on('blur', function() { window.setTimeout(hideStatus, 150); });
			$input.on('input', function() {
				if ($input.val().trim().length < 2) { hideStatus(); clearTabCounts(); }
			});

			var inst = $input.autocomplete('instance');
			if (!inst) { return; }

			// Los encabezados de grupo no son opciones seleccionables.
			inst.menu.option('items', '> :not(.ppv2-suggest-group)');

			// Menú con encabezados "Directorio" / "Tienda".
			inst._renderMenu = function(ul, items) {
				var that = this, currentGroup = '';
				ul.addClass('ppv2-suggest-menu');
				$.each(items, function(index, item) {
					if (item.group && item.group !== currentGroup) {
						currentGroup = item.group;
						$('<li>').addClass('ppv2-suggest-group').text(item.group).appendTo(ul);
					}
					that._renderItemData(ul, item);
				});
			};

			// Fila de sugerencia: foto + nombre + precio (si es producto).
			inst._renderItem = function(ul, item) {
				var $row = $('<div>').addClass('ppv2-suggest-row');
				if (item.img) {
					$row.append($('<img>').attr({ src: item.img, alt: '' }).addClass('ppv2-suggest-img'));
				} else {
					$row.append($('<span>').addClass('ppv2-suggest-img ppv2-suggest-img-empty').text('🐾'));
				}
				$row.append($('<span>').addClass('ppv2-suggest-label').text(item.label));
				if (item.meta) {
					$row.append($('<span>').addClass('ppv2-suggest-price').text(item.meta));
				}
				return $('<li>').addClass('ppv2-suggest-item').append($row).appendTo(ul);
			};
		});
	});
})(window.jQuery);
