/* migrado de functions.php::ppv2_header_suggest_js() 2026-07-10 (hook: wp_print_footer_scripts, prioridad 50 — DESPUÉS del script de Listeo prio 11)
   Se imprime en TODAS las páginas del front (sin condición PHP). Requiere jQuery + jQuery UI Autocomplete (encolado en ppv2_header_suggest_assets) y window.PPV2_CFG = { ajaxUrl: admin_url('admin-ajax.php'), homeUrl: home_url('/') }. */
(function($) {
	if (!$ || !$.fn || typeof $.fn.autocomplete === 'undefined') { return; }
	$(function() {
		var ajaxUrl = window.PPV2_CFG.ajaxUrl;
		var homeUrl = window.PPV2_CFG.homeUrl;
		var SCOPE_KEY = 'ppv2SearchScope';
		var cache = {}; // resultados por término: el cambio de tab no vuelve a consultar al servidor

		// Tab activo ("directorio" | "tienda"). Se recuerda solo durante la sesión.
		function getScope() {
			try {
				return window.sessionStorage.getItem(SCOPE_KEY) === 'tienda' ? 'tienda' : 'directorio';
			} catch (e) { return 'directorio'; }
		}
		function setScope(scope) {
			try { window.sessionStorage.setItem(SCOPE_KEY, scope); } catch (e) {}
		}

		// Reordena en el navegador: el grupo del tab activo va primero.
		function orderByScope(items) {
			var listings = [], products = [];
			$.each(items || [], function(index, item) {
				(item.group === 'Tienda' ? products : listings).push(item);
			});
			return getScope() === 'tienda' ? products.concat(listings) : listings.concat(products);
		}

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
			$.each([
				{ id: 'directorio', label: 'Directorio' },
				{ id: 'tienda', label: 'Tienda' }
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
					if (ui.item && ui.item.link) { window.location.href = ui.item.link; }
					return false;
				},
				focus: function() { return false; }
			});

			// Ciclo de estado: al iniciar la búsqueda → "Buscando…"; al llegar
			// la respuesta → ocultar (hay menú) o "Sin resultados" (vacía).
			$input.on('autocompletesearch', function() {
				showStatus($input, true);
			});
			$input.on('autocompleteresponse', function(event, ui) {
				if (ui && ui.content && ui.content.length) {
					hideStatus();
				} else {
					showStatus($input, false);
				}
			});
			$input.on('blur', function() { window.setTimeout(hideStatus, 150); });
			$input.on('input', function() {
				if ($input.val().trim().length < 2) { hideStatus(); }
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
