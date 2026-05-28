<?php
/**
 * Listeo Child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package listeo-child
 */

function listeo_child_enqueue_styles() {
    // Cargar estilo del tema padre
    wp_enqueue_style( 'listeo-parent-style', get_template_directory_uri() . '/style.css' );

    // Cargar estilo del tema hijo (hereda y sobrescribe con tokens de marca V2)
    // Usamos filemtime() para forzar cache-bust automatico en cada edicion del CSS
    $child_css_path = get_stylesheet_directory() . '/style.css';
    $child_css_ver  = file_exists( $child_css_path ) ? filemtime( $child_css_path ) : wp_get_theme()->get('Version');
    wp_enqueue_style( 'listeo-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array( 'listeo-parent-style' ),
        $child_css_ver
    );
}
add_action( 'wp_enqueue_scripts', 'listeo_child_enqueue_styles', 99 );

/**
 * Personalizaciones y ganchos adicionales de Parche Peludo V2
 * Agrega aqui funciones personalizadas para integraciones seguras.
 */

/**
 * Tienda V2 (Home): añade la etiqueta de categoría a cada producto y habilita
 * el filtrado por categoría en vivo (pills .ppv2-shop-filter) sobre el shortcode
 * nativo [products] dentro de .ppv2-shop-products. Scoped: solo actúa si existe
 * la sección en la página, no afecta el resto del sitio.
 */
function ppv2_shop_filter_script() {
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		var shop = document.querySelector('.ppv2-shop-products');
		if (!shop) return;

		// 1) CTA "Ver más" (enlaza a la ficha del producto) movido al final de la tarjeta.
		//    La etiqueta de categoría ya la imprime Listeo (span.product-category).
		shop.querySelectorAll('li.product').forEach(function (li) {
			var link = li.querySelector('a.woocommerce-LoopProduct-link');
			var btn = li.querySelector('a.button');
			if (btn) {
				btn.textContent = 'Ver más';
				if (link && link.href) { btn.href = link.href; }
				btn.classList.remove('add_to_cart_button', 'ajax_add_to_cart');
				btn.removeAttribute('data-quantity');
				btn.removeAttribute('data-product_id');
				li.appendChild(btn); // mover el botón al pie de la tarjeta
			}
		});

		// 2) Filtrado en vivo con las pills de categoría.
		//    Delegación en fase de captura: se ejecuta antes de cualquier
		//    handler que detenga la propagación en el <a> del botón Elementor.
		document.addEventListener('click', function (e) {
			var pill = e.target.closest ? e.target.closest('.ppv2-shop-filter') : null;
			if (!pill) return;
			e.preventDefault();
			e.stopPropagation();
			var cls = pill.className.match(/ppv2-filter-([a-z0-9\-]+)/);
			var cat = cls ? cls[1] : 'all';
			document.querySelectorAll('.ppv2-shop-filter').forEach(function (x) { x.classList.remove('is-active'); });
			pill.classList.add('is-active');
			shop.querySelectorAll('li.product').forEach(function (li) {
				var show = (cat === 'all') || li.classList.contains('product_cat-' + cat);
				// Clase (no estilo en línea): el CSS oculta con !important y mayor
				// especificidad para ganar a la regla base display:flex !important del carrusel.
				li.classList.toggle('ppv2-hidden', !show);
			});
		}, true);
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'ppv2_shop_filter_script', 99 );

/**
 * Detalle de listado V2: reposiciona a la cabecera (como el prototipo) el sello
 * "verificado" (hoy en el sidebar) y el resumen de calificación (hoy bajo el
 * título), moviendo los elementos EXISTENTES a una fila superior. No crea
 * contenido nuevo, no duplica nada y no toca la administración. Solo en la
 * página individual de listado.
 */
function ppv2_listing_header_reorder() {
	if ( ! is_singular( 'listing' ) ) {
		return;
	}
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		// 1a + 1b) Mover la cabecera (título) y la galería "bento" nativa de Listeo
		//   al inicio de la columna de contenido, en el orden del prototipo:
		//   [cabecera] → [galería] → [nav, características, ...]. Así el bloque de
		//   reserva queda a la derecha desde arriba, en paralelo, como el prototipo.
		var cover = document.querySelector('#listing-gallery.listeo-grid-gallery-title');
		var bento = document.querySelector('.listeo-single-listing-gallery-grid');
		var content = document.querySelector('.col-lg-8.listeo-single-listing-content');
		
		// En móvil: colocar la galería de primero en la página (antes del #titlebar)
		// para lograr una vista full-bleed y orden idéntico al prototipo.
		if (window.innerWidth < 768) {
			var titlebar = document.getElementById('titlebar');
			var gallery = bento || cover;
			if (titlebar && gallery && titlebar.parentNode) {
				titlebar.parentNode.insertBefore(gallery, titlebar);
			}

			// === Inicializar Slider Móvil de la Galería si tiene múltiples imágenes ===
			if (gallery) {
				var photos = gallery.querySelectorAll('a.slg-gallery-img, .slg-half img, .slg-grid img, img.listeo-gallery-img, .listeo-gallery-img img');
				var photoUrls = [];
				photos.forEach(function(img) {
					var url = img.getAttribute('href') || img.getAttribute('src') || (img.querySelector('img') ? img.querySelector('img').getAttribute('src') : null);
					if (url && photoUrls.indexOf(url) === -1) {
						photoUrls.push(url);
					}
				});

				if (photoUrls.length > 1) {
					gallery.innerHTML = '';
					gallery.className = 'ppv2-mobile-slider-container';

					var track = document.createElement('div');
					track.className = 'ppv2-mobile-slider-track';
					
					photoUrls.forEach(function(url, idx) {
						var slide = document.createElement('div');
						slide.className = 'ppv2-mobile-slider-slide';
						var img = document.createElement('img');
						img.src = url;
						img.alt = 'Imagen ' + (idx + 1);
						slide.appendChild(img);
						track.appendChild(slide);
					});
					gallery.appendChild(track);

					var prevBtn = document.createElement('button');
					prevBtn.type = 'button';
					prevBtn.className = 'ppv2-slider-arrow ppv2-slider-arrow--prev';
					prevBtn.innerHTML = '‹';
					prevBtn.style.display = 'none';

					var nextBtn = document.createElement('button');
					nextBtn.type = 'button';
					nextBtn.className = 'ppv2-slider-arrow ppv2-slider-arrow--next';
					nextBtn.innerHTML = '›';

					gallery.appendChild(prevBtn);
					gallery.appendChild(nextBtn);

					var dotsContainer = document.createElement('div');
					dotsContainer.className = 'ppv2-slider-dots';
					photoUrls.forEach(function(_, idx) {
						var dot = document.createElement('span');
						dot.className = 'ppv2-slider-dot' + (idx === 0 ? ' is-active' : '');
						dot.dataset.index = idx;
						dotsContainer.appendChild(dot);
					});
					gallery.appendChild(dotsContainer);

					function updateNavigation() {
						var scrollLeft = track.scrollLeft;
						var slideWidth = gallery.offsetWidth || 300;
						var activeIndex = Math.round(scrollLeft / slideWidth);

						var dots = dotsContainer.querySelectorAll('.ppv2-slider-dot');
						dots.forEach(function(dot, idx) {
							if (idx === activeIndex) {
								dot.classList.add('is-active');
							} else {
								dot.classList.remove('is-active');
							}
						});

						if (activeIndex === 0) {
							prevBtn.style.display = 'none';
						} else {
							prevBtn.style.display = 'flex';
						}

						if (activeIndex === photoUrls.length - 1) {
							nextBtn.style.display = 'none';
						} else {
							nextBtn.style.display = 'flex';
						}
					}

					track.addEventListener('scroll', updateNavigation);

					prevBtn.addEventListener('click', function(e) {
						e.preventDefault();
						var slideWidth = gallery.offsetWidth || 300;
						track.scrollLeft -= slideWidth;
					});
					nextBtn.addEventListener('click', function(e) {
						e.preventDefault();
						var slideWidth = gallery.offsetWidth || 300;
						track.scrollLeft += slideWidth;
					});

					dotsContainer.addEventListener('click', function(e) {
						if (e.target.classList.contains('ppv2-slider-dot')) {
							e.preventDefault();
							var idx = parseInt(e.target.dataset.index);
							var slideWidth = gallery.offsetWidth || 300;
							track.scrollLeft = idx * slideWidth;
						}
					});
					
					setTimeout(updateNavigation, 100);
				}
			}
		} else {
			// En desktop: mantener la galería dentro de la columna de contenido
			if (cover && content && !content.contains(cover)) {
				content.insertBefore(cover, content.firstChild);
			}
			if (bento && content && !content.contains(bento)) {
				var after = (cover && cover.parentElement === content) ? cover.nextSibling : content.firstChild;
				content.insertBefore(bento, after);
			}
		}
		// Disparar resize para que cualquier slider/grid responsivo recalcule
		try { window.dispatchEvent(new Event('resize')); } catch(e) {}

		// Traer de vuelta la pill de ciudad (.listing-tag última) y ponerla en
		// la misma fila que la dirección (a la izquierda).
		var tagsBox = document.querySelector('#titlebar .listing-titlebar-tags');
		var addressLink = document.querySelector('#titlebar .listing-address');
		var addressSpan = addressLink ? addressLink.closest('span') : null;
		if (tagsBox && addressSpan && !document.querySelector('.ppv2-location-row')) {
			var allTags = tagsBox.querySelectorAll('.listing-tag');
			var cityTag = allTags.length ? allTags[allTags.length - 1] : null;
			if (cityTag) {
				var row = document.createElement('div');
				row.className = 'ppv2-location-row';
				addressSpan.parentNode.insertBefore(row, addressSpan);
				row.appendChild(cityTag);
				row.appendChild(addressSpan);
			}
		}

		// Marcar parrafos de review vacios (reviewers que solo dieron estrellas
		// en Google sin escribir comentario). Esto cubre tambien el caso de
		// whitespace que :empty CSS no detecta. La clase .ppv2-empty-p activa
		// la regla en style.css que oculta el parrafo y colapsa el grid.
		function ppv2MarkEmptyReviewParagraphs(){
			var ps = document.querySelectorAll('#listing-google-reviews .comments li > .comment-content > p, #listing-reviews .comments li > .comment-content > p');
			for (var i = 0; i < ps.length; i++) {
				var p = ps[i];
				if (p.textContent.trim().length === 0) {
					p.classList.add('ppv2-empty-p');
				} else {
					p.classList.remove('ppv2-empty-p');
				}
			}
		}
		ppv2MarkEmptyReviewParagraphs();
		// Re-evaluar tras cualquier carga AJAX de Listeo (algunos sitios cargan
		// las reseñas de forma asincrona)
		var ppv2ReviewObserver = new MutationObserver(ppv2MarkEmptyReviewParagraphs);
		var reviewsRoot = document.querySelector('#listing-google-reviews, #listing-reviews');
		if (reviewsRoot) ppv2ReviewObserver.observe(reviewsRoot, { childList: true, subtree: true });

		// Hacer colapsable la sección Opiniones de Google. Por defecto cerrada:
		// se muestra el resumen + botón "Leer más opiniones / Cerrar opiniones".
		// Replicando el prototipo (resenas_google_compactada.html línea 95):
		//   estado cerrado → "Leer más opiniones" + chevron abajo
		//   estado abierto → "Cerrar opiniones"  + chevron arriba
		var googleSection = document.getElementById('listing-google-reviews');
		var googleSummary = googleSection ? googleSection.querySelector('.google-reviews-summary') : null;
		if (googleSection && googleSummary && !googleSummary.querySelector('.ppv2-google-toggle')) {
			googleSection.classList.add('ppv2-collapsible', 'is-collapsed');

			// Construir el botón Texto + Icono
			var toggleBtn = document.createElement('button');
			toggleBtn.type = 'button';
			toggleBtn.className = 'ppv2-google-toggle';
			toggleBtn.setAttribute('aria-expanded', 'false');

			var toggleText = document.createElement('span');
			toggleText.className = 'ppv2-google-toggle-text';
			toggleText.textContent = 'Leer más opiniones';

			var toggleIcon = document.createElement('span');
			toggleIcon.className = 'ppv2-google-toggle-icon';
			toggleIcon.setAttribute('aria-hidden', 'true');
			toggleIcon.textContent = '▾';

			toggleBtn.appendChild(toggleText);
			toggleBtn.appendChild(toggleIcon);
			googleSummary.appendChild(toggleBtn);

			function ppv2GoogleSync() {
				var collapsed = googleSection.classList.contains('is-collapsed');
				toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
				toggleText.textContent = collapsed ? 'Leer más opiniones' : 'Cerrar opiniones';
				toggleIcon.textContent = collapsed ? '▾' : '▴';
			}
			ppv2GoogleSync();

			// Click en cualquier parte del resumen alterna el acordeón
			googleSummary.style.cursor = 'pointer';
			googleSummary.setAttribute('role', 'button');
			googleSummary.addEventListener('click', function(e){
				if (e.target.closest('a')) return;
				googleSection.classList.toggle('is-collapsed');
				ppv2GoogleSync();
			});
			// Click directo en el botón también (sin propagación duplicada gracias al toggle)
			toggleBtn.addEventListener('click', function(e){
				e.stopPropagation();
				googleSection.classList.toggle('is-collapsed');
				ppv2GoogleSync();
			});
		}

		// Mover el botón "Añadir opinión" de Google al bloque inferior y cambiar su etiqueta de forma robusta (soporta carga dinámica AJAX)
		function adjustGoogleReviewButton() {
			var googleAddLink = document.querySelector('#listing-google-reviews .google-reviews-summary .google-reviews-read-more a');
			var googleBottomReadMore = document.querySelector('#listing-google-reviews .google-reviews-read-more.bottom');
			if (googleAddLink && googleBottomReadMore) {
				var expectedText = 'Añadir opinión en Google';
				var gLogoMatch = /google-reviews-logo\.svg/;
				// Buscar el src del logo G en el otro botón ("Leer más opiniones") para reusar la misma ruta
				var refImgs = googleBottomReadMore.querySelectorAll('a img');
				var gLogoSrc = '';
				for (var ri = 0; ri < refImgs.length; ri++) {
					if (gLogoMatch.test(refImgs[ri].src)) { gLogoSrc = refImgs[ri].src; break; }
				}
				var currentImg = googleAddLink.querySelector('img');
				var hasGLogo = currentImg && gLogoMatch.test(currentImg.src);
				var hasCorrectText = googleAddLink.textContent.trim() === expectedText;
				if (!hasGLogo || !hasCorrectText) {
					if (!gLogoSrc && currentImg) {
						// Fallback: derivar la ruta del logo a partir del icon actual del mismo tema
						gLogoSrc = currentImg.src.replace(/[^\/]+$/, 'google-reviews-logo.svg');
					}
					googleAddLink.innerHTML = '';
					var newImg = document.createElement('img');
					newImg.src = gLogoSrc;
					newImg.alt = 'Google';
					googleAddLink.appendChild(newImg);
					googleAddLink.appendChild(document.createTextNode(' ' + expectedText));
				}
				if (googleAddLink.parentElement !== googleBottomReadMore) {
					googleBottomReadMore.appendChild(googleAddLink);
				}
				// Ocultar el contenedor que quedó vacío arriba (summary > .google-reviews-read-more)
				var emptyTopBtn = document.querySelector('#listing-google-reviews .google-reviews-summary .google-reviews-read-more');
				if (emptyTopBtn && !emptyTopBtn.querySelector('a')) {
					emptyTopBtn.style.display = 'none';
				}
			}
		}

		// Ejecutar de inmediato
		adjustGoogleReviewButton();

		// Monitorear cambios en el body por si la sección o los botones de Google se cargan por AJAX
		var observer = new MutationObserver(function() {
			adjustGoogleReviewButton();
		});
		observer.observe(document.body, { childList: true, subtree: true });

		// Reordenar el FAQ al final del bloque de contenido (orden del prototipo:
		// descripción → precios → ubicación → reseñas → características → FAQ).
		var contentCol = document.querySelector('.col-lg-8.listeo-single-listing-content');
		var faq = document.getElementById('listing-faq');
		if (contentCol && faq) {
			contentCol.appendChild(faq); // mueve FAQ al final
			// Renombrar el heading "FAQ" → "Preguntas frecuentes" (como el prototipo)
			var faqHead = faq.querySelector('.listing-desc-headline');
			if (faqHead && /^FAQ\s*$/i.test(faqHead.textContent.trim())) {
				faqHead.textContent = 'Preguntas frecuentes';
			}
		}

		// Renombrar el heading "Precios" → "Planes y Precios"
		var pricingSection = document.getElementById('listing-pricing-list');
		if (pricingSection) {
			var pricingHead = pricingSection.querySelector('.listing-desc-headline, h2');
			if (pricingHead && /^Precios\s*$/i.test(pricingHead.textContent.trim())) {
				pricingHead.textContent = 'Planes y Precios';
			}
		}

		// Inyectar un encabezado "Descripción" al inicio de #listing-overview.
		// Listeo no provee título nativo para esta sección, así que añadimos uno
		// para dar jerarquía visual (consistente con "Planes y Precios", "Sobre…",
		// "Preguntas frecuentes"). La clase .ppv2-overview-headline sirve de marca
		// de idempotencia para no duplicar el h2 en re-ejecuciones del script.
		var overviewSection = document.getElementById('listing-overview');
		if (overviewSection && !overviewSection.querySelector('.ppv2-overview-headline')) {
			var descHead = document.createElement('h2');
			descHead.className = 'listing-desc-headline ppv2-overview-headline';
			descHead.textContent = 'Sobre el servicio';
			overviewSection.insertBefore(descHead, overviewSection.firstChild);
		}

		// Separar Características (h2 + listado) del bloque "overview" y moverlas
		// como sección propia justo antes del FAQ. Renombrar el título a
		// "Sobre {nombre del aliado}" usando el nombre dinámico del listado.
		var overview = document.getElementById('listing-overview');
		if (overview && faq && !document.getElementById('ppv2-listing-features')) {
			var caractHeading = null;
			[].forEach.call(overview.querySelectorAll('h2'), function(h){
				if (/Características|caracter[ií]stica/i.test(h.textContent || '')) caractHeading = h;
			});
			var featuresList = overview.querySelector('ul.listing-features');
			if (caractHeading && featuresList) {
				// Renombrar "Características" → "Sobre {nombre del listado}"
				var titleH1 = document.querySelector('#titlebar h1');
				var listingName = titleH1 ? (titleH1.textContent || '').trim() : '';
				if (listingName) {
					caractHeading.textContent = 'Sobre ' + listingName;
				}
				var newSec = document.createElement('div');
				newSec.id = 'ppv2-listing-features';
				newSec.className = 'listing-section';
				newSec.appendChild(caractHeading);
				newSec.appendChild(featuresList);
				faq.parentNode.insertBefore(newSec, faq);
			}
		}

		// Colapsar la sección "Añadir opinión" detrás de un botón. Al hacer clic
		// se expande el formulario completo de reseñas.
		var addRev = document.getElementById('add-review');
		if (addRev && !addRev.querySelector('.ppv2-add-review-btn')) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'ppv2-add-review-btn';
			var labelOpen = '<span class="ppv2-add-review-btn__icon">+</span> Añadir Opinión';
			var labelClose = '<span class="ppv2-add-review-btn__icon">×</span> Cerrar';
			btn.innerHTML = labelOpen;
			btn.addEventListener('click', function(){
				var nowOpen = addRev.classList.toggle('is-open');
				btn.innerHTML = nowOpen ? labelClose : labelOpen;
			});
			addRev.insertBefore(btn, addRev.firstChild);
		}

		// Galería bento: si hay más fotos que las 3 visibles, mostrar overlay
		// "+N fotos" sobre la última imagen visible (la pequeña inferior derecha).
		var grid = document.querySelector('#single-listing-grid-gallery');
		if (grid) {
			// Total fotos = todos los anchors de galería (cada uno = 1 foto en lightbox)
			var allPhotos = grid.querySelectorAll('a.slg-gallery-img');
			var totalPhotos = allPhotos.length;
			var visibleSlots = 3;
			var moreCount = Math.max(0, totalPhotos - visibleSlots);
			var lastSlot = grid.querySelector('.slg-grid-bottom .slg-grid-inner');
			if (moreCount > 0 && lastSlot && !lastSlot.querySelector('.ppv2-more-overlay')) {
				var overlay = document.createElement('div');
				overlay.className = 'ppv2-more-overlay';
				var span = document.createElement('span');
				span.textContent = '+' + moreCount + ' fotos';
				overlay.appendChild(span);
				lastSlot.appendChild(overlay);
			}
		}

		var titleBlock = document.querySelector('#titlebar .listing-titlebar-title');
		if (!titleBlock) return;
		if (titleBlock.querySelector('.ppv2-meta-top')) return; // evitar repetición
		var verified = document.querySelector('.listeo-single-listing-sidebar .verified-badge');
		var rating = titleBlock.querySelector('.star-rating');
		var meta = document.createElement('div');
		meta.className = 'ppv2-meta-top';
		if (verified) meta.appendChild(verified);   // mover sello verificado a la cabecera
		if (rating) meta.appendChild(rating);       // mover calificación arriba
		// Mover también el botón de favorito a la misma fila (queda alineado a la
		// derecha, a la misma altura del chip "Servicio Verificado").
		var titlebar0 = document.getElementById('titlebar');
		var btnsWidget0 = titlebar0 ? titlebar0.querySelector(':scope > .listing-widget.widget_buttons') : null;
		if (btnsWidget0) {
			meta.appendChild(btnsWidget0);
			// Forzar dimensiones (Listeo aplica width:300px de los widgets de sidebar)
			btnsWidget0.style.setProperty('width', 'auto', 'important');
			btnsWidget0.style.setProperty('max-width', 'none', 'important');
			btnsWidget0.style.setProperty('margin', '0 0 0 auto', 'important');
			btnsWidget0.style.setProperty('padding', '0', 'important');
			btnsWidget0.style.setProperty('min-width', '0', 'important');
			var shareEl = btnsWidget0.querySelector('.listing-share');
			if (shareEl) {
				shareEl.style.setProperty('width', 'auto', 'important');
				shareEl.style.setProperty('min-width', '0', 'important');
				shareEl.style.setProperty('margin', '0', 'important');
				shareEl.style.setProperty('padding', '0', 'important');
			}
		}
		if (meta.children.length) {
			titleBlock.insertBefore(meta, titleBlock.firstChild);
		}
		// Renombrar el texto visible del sello: "Listado verificado" → "Servicio Verificado"
		var vb = verified || document.querySelector('.ppv2-meta-top .verified-badge');
		if (vb) {
			for (var i = 0; i < vb.childNodes.length; i++) {
				var n = vb.childNodes[i];
				if (n.nodeType === 3 && /verificado/i.test(n.nodeValue)) {
					n.nodeValue = ' Servicio Verificado ';
					break;
				}
			}
			// Forzar altura del tooltip (Listeo aplica height:20px;
			// 'auto' lo gana CSS layout, pero 'max-content' inline sí funciona).
			var tip = vb.querySelector('.tip-content');
			if (tip) {
				tip.style.setProperty('height', 'max-content', 'important');
				tip.style.setProperty('min-height', '0', 'important');
				tip.style.setProperty('max-height', 'none', 'important');
			}
		}
		// Sacar el widget "Marca este listado" + contador del titlebar (queda como
		// botón flotante arriba-derecha de la columna de contenido — ver CSS).
		var titlebar = document.getElementById('titlebar');
		var btnsWidget = titlebar ? titlebar.querySelector(':scope > .listing-widget.widget_buttons') : null;
		if (btnsWidget && titlebar.parentNode) {
			titlebar.parentNode.insertBefore(btnsWidget, titlebar.nextSibling);
		}
		// Construir fila "Prestado por [logo Naturalia]" después del título.
		// Mueve el .listing-logo existente como chip pequeño (no se duplica markup).
		var h1 = titleBlock.querySelector('h1');
		var logoBox = titlebar ? titlebar.querySelector(':scope > .listing-logo') : null;
		if (h1 && logoBox && !titleBlock.querySelector('.ppv2-prestado-por')) {
			var listingName = (h1.textContent || '').trim();
			var row = document.createElement('div');
			row.className = 'ppv2-prestado-por';
			var label = document.createElement('span');
			label.className = 'ppv2-prestado-label';
			label.textContent = 'Prestado por';
			row.appendChild(label);
			var chip = document.createElement('span');
			chip.className = 'ppv2-provider-chip';
			chip.appendChild(logoBox); // mueve el logo box existente al chip
			// Forzar tamaño de la imagen al chip pequeño en pixeles exactos
			// (Listeo aplica reglas sobre img que no se vencen con %).
			var logoImg = logoBox.querySelector('img');
			if (logoImg) {
				logoImg.style.setProperty('width', '24px', 'important');
				logoImg.style.setProperty('height', '24px', 'important');
				logoImg.style.setProperty('min-width', '0', 'important');
				logoImg.style.setProperty('max-width', 'none', 'important');
				logoImg.style.setProperty('display', 'block', 'important');
				logoImg.style.setProperty('object-fit', 'cover', 'important');
				logoImg.style.setProperty('border-radius', '50%', 'important');
				logoImg.removeAttribute('width');
				logoImg.removeAttribute('height');
			}
			var nm = document.createElement('span');
			nm.className = 'ppv2-provider-name';
			nm.textContent = listingName;
			chip.appendChild(nm);
			row.appendChild(chip);
			// Insertar justo después del título
			if (h1.nextSibling) {
				h1.parentNode.insertBefore(row, h1.nextSibling);
			} else {
				h1.parentNode.appendChild(row);
			}
		}

		// === Hacer colapsables los widgets "Enviar mensaje" y "Horarios" ===
		// Por defecto cerrados con apariencia de "boton tipo prototipo"; click expande.
		// labels = {collapsed: 'Texto cuando cerrado', expanded: 'Texto cuando abierto', noChevron: bool}
		function ppv2MakeWidgetCollapsible(widget, labels) {
			if (!widget || widget.classList.contains('ppv2-collapsible')) return;
			// Buscar el titulo: primero por clase .widget-title, sino el primer h2/h3 directo
			var title = widget.querySelector('.widget-title');
			if (!title) {
				for (var i = 0; i < widget.children.length; i++) {
					var c = widget.children[i];
					if (c.tagName === 'H2' || c.tagName === 'H3' || c.tagName === 'H4') {
						title = c;
						title.classList.add('widget-title'); // homogeneizar para que aplique el CSS
						break;
					}
				}
			}
			if (!title) return;
			widget.classList.add('ppv2-collapsible', 'is-collapsed');

			// Limpiar el contenido del titulo y reconstruirlo con iconos + texto controlado
			var iconEl = title.querySelector('i');
			var iconHTML = iconEl ? iconEl.outerHTML : '';
			title.innerHTML = '';
			if (iconHTML) {
				var ic = document.createElement('span');
				ic.innerHTML = iconHTML;
				title.appendChild(ic.firstChild);
			}
			var textSpan = document.createElement('span');
			textSpan.className = 'ppv2-widget-title-text';
			textSpan.textContent = labels && labels.collapsed ? labels.collapsed : title.textContent.trim();
			title.appendChild(textSpan);

			if (!(labels && labels.noChevron)) {
				var chev = document.createElement('span');
				chev.className = 'ppv2-widget-chevron';
				chev.setAttribute('aria-hidden', 'true');
				chev.textContent = '▾';
				title.appendChild(chev);
			}

			title.style.cursor = 'pointer';
			title.setAttribute('role', 'button');
			title.setAttribute('tabindex', '0');
			title.setAttribute('aria-expanded', 'false');
			function toggle() {
				var nowCollapsed = widget.classList.toggle('is-collapsed');
				title.setAttribute('aria-expanded', nowCollapsed ? 'false' : 'true');
				// Alternar texto si se proporciono ambas variantes
				if (labels && labels.collapsed && labels.expanded) {
					textSpan.textContent = nowCollapsed ? labels.collapsed : labels.expanded;
				}
			}
			title.addEventListener('click', toggle);
			title.addEventListener('keydown', function(e){
				if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
			});
		}
		var msgWidget = document.querySelector('.listeo-single-listing-sidebar .listing-widget.message-vendor');
		var hoursWidget = document.querySelector('.listeo-single-listing-sidebar .listing-widget.opening-hours');
		// Enviar Mensaje: boton outline teal-parche con chevron para indicar
		// claramente la affordance de toggle (abrir/cerrar el componente).
		ppv2MakeWidgetCollapsible(msgWidget, { collapsed: 'Enviar Mensaje', expanded: 'Enviar mensaje' });
		// Ver horarios: pill outline gris, CON chevron a la derecha.
		ppv2MakeWidgetCollapsible(hoursWidget, { collapsed: 'Ver horarios', expanded: 'Horarios' });

		// === Etiqueta de estado "Ahora Abierto / Ahora Cerrado" siempre visible ===
		// Mover el badge nativo de Listeo dentro del header del widget Horarios.
		// Si Listeo no emite badge (algunas configuraciones no lo renderizan), lo
		// inyectamos como "now-closed" por seguridad.
		if (hoursWidget) {
			var hoursTitle = hoursWidget.querySelector('.widget-title');
			var hoursChev  = hoursTitle ? hoursTitle.querySelector('.ppv2-widget-chevron') : null;
			var statusBadge = hoursWidget.querySelector('.listing-badge');
			if (!statusBadge) {
				// Fallback: si no existe, crear uno como "cerrado"
				statusBadge = document.createElement('div');
				statusBadge.className = 'listing-badge now-closed';
				statusBadge.textContent = 'Ahora Cerrado';
				hoursWidget.appendChild(statusBadge);
			}
			// Mover el badge al header, antes del chevron, para que sea visible
			// tanto en colapsado como en expandido sin volver a ser el ribbon diagonal.
			if (hoursTitle && statusBadge.parentElement !== hoursTitle) {
				if (hoursChev) {
					hoursTitle.insertBefore(statusBadge, hoursChev);
				} else {
					hoursTitle.appendChild(statusBadge);
				}
			}
		}
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'ppv2_listing_header_reorder', 100 );

/**
 * Inyecta la barra inferior fija en móvil para reservar y chatear,
 * imitando el prototipo de Stitch (pagina_servicio_detalle_movil.html).
 */
function ppv2_listing_mobile_bottom_bar() {
	if ( ! is_singular( 'listing' ) ) {
		return;
	}
	?>
	<!-- Fixed Bottom Action Bar for Mobile -->
	<div class="ppv2-mobile-bottom-bar">
		<button class="ppv2-mobile-bottom-bar__chat-btn" aria-label="Chat con el prestador">
			<i class="sl sl-icon-bubble"></i>
		</button>
		<button class="ppv2-mobile-bottom-bar__reserve-btn">
			Solicitar Reserva
			<i class="sl sl-icon-calendar"></i>
		</button>
	</div>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		var chatBtn = document.querySelector('.ppv2-mobile-bottom-bar__chat-btn');
		var reserveBtn = document.querySelector('.ppv2-mobile-bottom-bar__reserve-btn');

		if (chatBtn) {
			chatBtn.addEventListener('click', function (e) {
				e.preventDefault();
				var msgWidget = document.querySelector('.listing-widget.message-vendor');
				if (msgWidget) {
					msgWidget.scrollIntoView({ behavior: 'smooth', block: 'center' });
					// Si está colapsado, expandirlo
					if (msgWidget.classList.contains('is-collapsed')) {
						var title = msgWidget.querySelector('.widget-title');
						if (title) { title.click(); }
					}
				}
			});
		}

		if (reserveBtn) {
			reserveBtn.addEventListener('click', function (e) {
				e.preventDefault();
				var bookingWidget = document.querySelector('.listing-widget.boxed-widget, #booking-widget-anchor, .boxed-widget');
				if (bookingWidget) {
					bookingWidget.scrollIntoView({ behavior: 'smooth', block: 'center' });
				}
			});
		}
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'ppv2_listing_mobile_bottom_bar', 110 );

