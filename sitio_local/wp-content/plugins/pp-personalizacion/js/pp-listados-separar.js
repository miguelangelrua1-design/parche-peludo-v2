/**
 * Personalización Parche — Separar resultados por tipo de listado.
 *
 * El botón "+" vive en el BUSCADOR PRINCIPAL del header (junto a los tabs
 * Directorio | Tienda del buscador unificado), en TODO el sitio:
 *  - Clic en "+": despliega las etiquetas de los tipos separados (Adopción,
 *    Mascotas Perdidas…). Solo puede haber UNA activa.
 *  - Con una activa, el "+" NO cambia: muestra un chulo (✓) como insignia
 *    en su esquina superior derecha (decisión de Miguel 2026-07-05).
 *  - La selección modifica la BÚSQUEDA GENERAL: el formulario del header
 *    lleva un input oculto `_listing_type`, así que buscar cae en
 *    /directorio/?…&_listing_type=tipo y el servidor filtra (candado en
 *    includes/listados.php). Elegir un tipo fuerza el ámbito Directorio
 *    (si estaba Tienda); clic en Directorio o Tienda limpia la selección.
 *  - En el ARCHIVO (/directorio/): la selección además refresca los
 *    resultados al instante (AJAX nativo de Listeo) y cambia el carrusel
 *    superior a las categorías del tipo.
 *  - En páginas de categoría de un tipo separado: el "+" llega
 *    preseleccionado con ese tipo y el carrusel muestra sus categorías.
 *
 * Datos: window.PP_LISTADOS_SEP
 *   { contexto, esArchivo, esTaxTipo, tipos:{slug:nombre},
 *     carruseles:{slug:[items]}, termActual, archivoUrl }
 */
(function ($) {
	'use strict';

	$(function () {
		var D = window.PP_LISTADOS_SEP;
		if (!D) { return; }
		var tipos = D.tipos || {};
		var slugs = Object.keys(tipos);
		if (!slugs.length) { return; }

		var esArchivo = !!parseInt(D.esArchivo, 10);
		var esTaxTipo = !!parseInt(D.esTaxTipo, 10);
		var activo    = '';
		// Bandera de reentrada: cuando NOSOTROS forzamos el cambio de ámbito a
		// "Directorio" (trigger de clic), el handler de .ppv2-scope-tab corre
		// SÍNCRONO y no debe limpiar la selección que acabamos de hacer.
		var cambiandoAmbito = false;

		// Escapa también comillas: esc() se interpola dentro de atributos
		// (data-slug="…", data-tipo="…") y $('<span>').text().html() no las cubría.
		function esc(s) {
			return String(s || '').replace(/[&<>"']/g, function (c) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
			});
		}

		/* ---------------- Carrusel contextual ---------------- */
		var originalHTML = null;

		function renderCarrusel(tipo) {
			var $slider = $('#categorySlider');
			if (!$slider.length) { return; }
			var items = (D.carruseles && D.carruseles[tipo]) ? D.carruseles[tipo] : [];
			if (originalHTML === null) { originalHTML = $slider.html(); }

			// Ítem "Todos" — equivalente al del carrusel nativo del Directorio:
			// muestra TODO el tipo (todas las adopciones / todas las perdidas).
			// Activo cuando no estamos dentro de una categoría del tipo.
			var todosUrl = (D.archivoUrl || '/');
			todosUrl += (todosUrl.indexOf('?') === -1 ? '?' : '&') + '_listing_type=' + encodeURIComponent(tipo);
			var h = '<a class="category-item pp-sep-item pp-sep-item-todos' + (D.termActual ? '' : ' active') + '" data-slug="all" href="' + todosUrl + '">' +
					'<div class="icon-container"><i class="sl sl-icon-grid"></i></div>' +
					'<div class="category-name">Todos</div>' +
				'</a>';

			items.forEach(function (it) {
				var act = (D.termActual && it.slug === D.termActual) ? ' active' : '';
				h += '<a class="category-item pp-sep-item' + act + '" data-slug="' + esc(it.slug) + '" href="' + it.url + '">' +
						'<div class="icon-container">' + it.icono + '</div>' +
						'<div class="category-name">' + esc(it.nombre) + '</div>' +
					'</a>';
			});
			$slider.html(h);
		}

		function restaurarCarrusel() {
			var $slider = $('#categorySlider');
			if ($slider.length && originalHTML !== null) {
				$slider.html(originalHTML);
			}
		}

		/* --------- Armar los formularios con el tipo elegido --------- */

		// Formulario del BUSCADOR del header (búsqueda general del sitio).
		function armarFormHeader(valor) {
			$('.header-search-container form').each(function () {
				var form = this;
				var input = form.querySelector('input[name="_listing_type"]');
				if (!valor) {
					if (input) { input.parentNode.removeChild(input); }
					return;
				}
				if (!input) {
					input = document.createElement('input');
					input.type = 'hidden';
					input.name = '_listing_type';
					form.appendChild(input);
				}
				input.disabled = false;
				input.removeAttribute('disabled');
				input.value = valor;
			});
		}

		// Formulario de FILTROS del archivo + refresco AJAX (solo /directorio/).
		function filtrarResultados(valor) {
			var form = document.querySelector('#listeo_core-search-form');
			if (!form) { return; }
			var input = form.querySelector('input[name="_listing_type"]');
			if (!input) {
				input = document.createElement('input');
				input.type = 'hidden';
				input.name = '_listing_type';
				form.appendChild(input);
			}
			input.disabled = false;
			input.removeAttribute('disabled');
			input.value = valor || '';
			var $target = $('#listeo-listings-container');
			if ($target.length) {
				$target.triggerHandler('update_results', [1, false]);
			}
		}

		/* ---------------- Chip "+" en el buscador del header ---------------- */

		function pintarEstado($sep) {
			$sep.find('.pp-sep-chip').removeClass('activa')
				.filter('[data-tipo="' + activo + '"]').addClass('activa');
			$sep.find('.pp-sep-mas').toggleClass('pp-con-seleccion', !!activo)
				.attr('title', activo ? 'Buscando en: ' + (tipos[activo] || activo) : 'Buscar en Adopción o Mascotas Perdidas');
			$sep.find('.pp-sep-mas__badge').prop('hidden', !activo);
		}

		function abrirLista($sep, abrir) {
			$sep.find('.pp-sep-lista').prop('hidden', !abrir);
			$sep.find('.pp-sep-mas').attr('aria-expanded', abrir ? 'true' : 'false');
		}

		function aplicar(tipo, interactivo) {
			activo = tipo || '';
			$('.pp-sep').each(function () { pintarEstado($(this)); });
			armarFormHeader(activo);

			if (activo) {
				// La búsqueda con tipo va al directorio: si el ámbito estaba en
				// "Tienda", volver a "Directorio" (el tab del buscador unificado).
				var $tiendaActiva = $('.ppv2-scope-tab-active[data-scope="tienda"]').first();
				if ($tiendaActiva.length) {
					var $dir = $('.ppv2-scope-tab[data-scope="directorio"]').first();
					if ($dir.length) {
						cambiandoAmbito = true;
						try { $dir.trigger('click'); } finally { cambiandoAmbito = false; }
					}
				}
			}

			if (esArchivo && interactivo) {
				if (activo) { renderCarrusel(activo); } else { restaurarCarrusel(); }
				filtrarResultados(activo);
			} else if (esArchivo && activo) {
				renderCarrusel(activo);
			}

			// Si hay texto escrito en el buscador, refrescar las sugerencias
			// con el nuevo tipo (mismo patrón que los tabs Directorio|Tienda).
			if (interactivo) {
				var $campo = $('.header-search-container input[name="keyword_search"]').first();
				if ($campo.length && $campo.val() && $campo.val().trim().length >= 2 && $campo.data('ui-autocomplete')) {
					$campo.autocomplete('search', $campo.val());
				}
			}
		}

		function inyectarChip($item) {
			if ($item.find('.pp-sep').length) { return; }
			var html = '<div class="pp-sep">' +
				'<button type="button" class="pp-sep-mas" aria-expanded="false" title="Buscar en Adopción o Mascotas Perdidas">' +
					'<span class="pp-sep-mas__signo">+</span>' +
					'<span class="pp-sep-mas__badge" hidden>&#10003;</span>' +
				'</button>' +
				'<div class="pp-sep-lista" hidden>';
			slugs.forEach(function (s) {
				html += '<button type="button" class="pp-sep-chip" data-tipo="' + esc(s) + '">' + esc(tipos[s]) + '</button>';
			});
			html += '</div></div>';

			var $tabs = $item.find('.ppv2-scope-tabs').first();
			if ($tabs.length) {
				$tabs.append(html); // al final de la fila Directorio | Tienda
			} else {
				$item.append(html); // sin buscador unificado: solo, junto al campo
			}
			pintarEstado($item.find('.pp-sep').first());
		}

		// El buscador unificado inyecta sus tabs por JS: reintentar hasta verlas.
		var intentos = 0;
		function intentarInyeccion() {
			var $items = $('.header-search-container .main-search-input-item.text');
			var listo = false;
			$items.each(function () {
				var $item = $(this);
				if (!$item.find('input[name="keyword_search"]').length) { return; }
				if ($item.find('.ppv2-scope-tabs').length || intentos >= 20) {
					inyectarChip($item);
					listo = true;
				}
			});
			if (!listo && intentos < 25) {
				intentos++;
				setTimeout(intentarInyeccion, 150);
				return;
			}
			// Estado inicial: contexto del servidor (?_listing_type= o página
			// de categoría de un tipo separado). Sin AJAX: ya viene filtrado.
			if (D.contexto) {
				aplicar(D.contexto, false);
			}
		}
		intentarInyeccion();

		/* ---------------- Interacción ---------------- */
		$(document).on('click', '.pp-sep-mas', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $sep = $(this).closest('.pp-sep');
			abrirLista($sep, $sep.find('.pp-sep-lista').prop('hidden'));
		});
		$(document).on('click', '.pp-sep-chip', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var tipo = $(this).attr('data-tipo');
			aplicar(tipo === activo ? '' : tipo, true);
		});
		// Elegir un ámbito principal (Directorio / Tienda) limpia la selección —
		// salvo cuando el cambio de ámbito lo disparamos nosotros desde aplicar().
		$(document).on('click', '.ppv2-scope-tab', function () {
			if (cambiandoAmbito) { return; }
			if (activo) { aplicar('', true); }
		});
		// Cerrar la lista al hacer clic fuera o con Escape.
		$(document).on('click', function (e) {
			if (!$(e.target).closest('.pp-sep').length) {
				$('.pp-sep').each(function () { abrirLista($(this), false); });
			}
		});
		$(document).on('keydown', function (e) {
			if ('Escape' === e.key) {
				$('.pp-sep').each(function () { abrirLista($(this), false); });
			}
		});

		/* ---- Página de categoría de un tipo separado: carrusel del tipo ---- */
		if (esTaxTipo && D.contexto) {
			setTimeout(function () { renderCarrusel(D.contexto); }, 0);
			// Los filtros AJAX de la página conservan el contexto del tipo.
			var formFiltros = document.querySelector('#listeo_core-search-form');
			if (formFiltros && !formFiltros.querySelector('input[name="_listing_type"]')) {
				var inp = document.createElement('input');
				inp.type = 'hidden';
				inp.name = '_listing_type';
				inp.value = D.contexto;
				formFiltros.appendChild(inp);
			}
		}
	});
})(jQuery);
