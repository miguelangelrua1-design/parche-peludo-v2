<?php
/**
 * Listeo Child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package listeo-child
 */

// La sección "Mis Mascotas" vive ahora en el plugin propio pp-mascotas
// (wp-content/plugins/pp-mascotas/), migrada desde este tema el 2026-07-03.

// Popup de reserva (Booking Plus): en el resumen la cantidad se rotula "Adultos".
// Para Parche Peludo la unidad es la MASCOTA, así que reemplazamos esa etiqueta
// localizada (lbpData.i18n.adults) sin tocar el plugin premium. El inline corre
// justo después de lbp-booking.js, cuando lbpData ya existe pero antes de abrir
// el popup, así el resumen usa la nueva palabra.
add_action( 'wp_enqueue_scripts', 'ppv2_lbp_relabel_pet_unit', 100 );
function ppv2_lbp_relabel_pet_unit() {
	if ( wp_script_is( 'lbp-booking', 'registered' ) || wp_script_is( 'lbp-booking', 'enqueued' ) ) {
		wp_add_inline_script(
			'lbp-booking',
			'if(window.lbpData&&lbpData.i18n){lbpData.i18n.adults="Mascota";lbpData.i18n.adult="Mascota";}',
			'after'
		);
	}
}

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

    // Minicart como panel deslizante (estilo Éxito). Depende de jQuery porque
    // escucha el evento 'added_to_cart' de WooCommerce. Cache-bust con filemtime.
    $mc_js_path = get_stylesheet_directory() . '/js/pp-minicart.js';
    if ( file_exists( $mc_js_path ) ) {
        wp_enqueue_script(
            'pp-minicart',
            get_stylesheet_directory_uri() . '/js/pp-minicart.js',
            array( 'jquery' ),
            filemtime( $mc_js_path ),
            true
        );
        // URL de AJAX + nonce para los controles de cantidad/eliminar del minicart.
        // Usamos el endpoint de WooCommerce (?wc-ajax=) cuando está disponible: es
        // mucho más liviano y rápido que admin-ajax.php (no carga todo el admin).
        $pp_ajax_url = admin_url( 'admin-ajax.php' );
        if ( class_exists( 'WC_AJAX' ) ) {
            $pp_ajax_url = WC_AJAX::get_endpoint( 'pp_update_cart_qty' );
        }
        wp_localize_script( 'pp-minicart', 'PP_MINICART', array(
            'ajax_url' => $pp_ajax_url,
            'nonce'    => wp_create_nonce( 'pp_minicart' ),
        ) );
    }

    // Solo en la página del Carrito: JS de controles en vivo (+/−/papelera).
    if ( function_exists( 'is_cart' ) && is_cart() ) {
        $cart_js_path = get_stylesheet_directory() . '/js/pp-cart.js';
        if ( file_exists( $cart_js_path ) ) {
            wp_enqueue_script(
                'pp-cart',
                get_stylesheet_directory_uri() . '/js/pp-cart.js',
                array( 'jquery' ),
                filemtime( $cart_js_path ),
                true
            );
        }
    }

    // PDP (detalle de listado) — ANTI-SALTO de la galería en móvil.
    // El JS del footer reordena el DOM (galería antes del titlebar) y convierte
    // el grid en el slider full-bleed (280px) DESPUÉS del primer render → se
    // veía un doble salto al cargar. Este CSS va en el <head>: (1) el grid
    // pre-JS ya ocupa la ALTURA FINAL del slider (el contenido de abajo no se
    // mueve) y (2) titlebar+galería quedan invisibles hasta que el JS termina
    // (clase ppv2-pdp-lista en <html>); si el JS no corriera, un keyframes los
    // revela solo a los 0.9s (nunca se queda oculto).
    if ( is_singular( 'listing' ) ) {
        $pp_pdp_css = '@media (max-width: 767.98px) {'
            . 'html:not(.ppv2-pdp-lista) body.single-listing .listeo-single-listing-gallery-grid:not(.ppv2-mobile-slider-container){'
            . 'height:280px !important;overflow:hidden !important;}'
            . 'html:not(.ppv2-pdp-lista) body.single-listing #titlebar,'
            . 'html:not(.ppv2-pdp-lista) body.single-listing #listing-gallery,'
            . 'html:not(.ppv2-pdp-lista) body.single-listing .listeo-single-listing-gallery-grid{'
            . 'opacity:0;animation:ppv2PdpReveal 0s .9s forwards;}'
            . '@keyframes ppv2PdpReveal{to{opacity:1;}}'
            . '}';
        wp_add_inline_style( 'listeo-child-style', $pp_pdp_css );
    }

    // Popup de reserva (Booking Plus): sus estilos dan recuadro a los <input>/
    // <textarea> del formulario, pero NO a los <select>. Al volver "Departamento"
    // (y opcionalmente "País") un desplegable, quedaba SIN recuadro ni alto =
    // invisible (aunque funcionaba al hacer clic). Forzamos que el <select> se
    // vea igual que los campos de texto. Va en el <head> (siempre presente,
    // gane quien gane en especificidad) con !important + selección por id.
    $pp_select_css = '.lbp-modal .lbp-info-form select,'
        . '.lbp-info-form select.lbp-billing-state,'
        . '.lbp-info-form select.lbp-billing-country,'
        . '#lbp-billing_state,#lbp-billing_country{'
        . 'display:block !important;width:100% !important;'
        . 'min-height:46px !important;height:auto !important;'
        . 'padding:10px 14px !important;margin:0 0 18px !important;'
        . 'border:1px solid #ddd !important;border-radius:8px !important;'
        . 'font-size:14px !important;line-height:1.4 !important;'
        . 'color:#333 !important;background-color:#fff !important;'
        . 'opacity:1 !important;visibility:visible !important;'
        . 'position:static !important;clip:auto !important;'
        . '-webkit-appearance:menulist !important;'
        . '-moz-appearance:menulist !important;appearance:menulist !important;'
        . 'box-sizing:border-box !important;}'
        . '.lbp-info-form select:focus{border-color:#66676b !important;}';
    wp_add_inline_style( 'listeo-child-style', $pp_select_css );
}
add_action( 'wp_enqueue_scripts', 'listeo_child_enqueue_styles', 99 );

/**
 * Título de la página del Carrito → "Carrito de compras" (para igualar el diseño).
 * Acotado: solo en la página del carrito y solo para el título de ESA página
 * (no afecta menús ni otros contenidos).
 */
add_filter( 'the_title', 'pp_cart_page_title', 10, 2 );
function pp_cart_page_title( $title, $post_id = 0 ) {
	if ( is_admin() || ! function_exists( 'is_cart' ) || ! function_exists( 'wc_get_page_id' ) ) {
		return $title;
	}
	if ( is_cart() && $post_id && (int) $post_id === (int) wc_get_page_id( 'cart' ) ) {
		return __( 'Carrito de compras', 'listeo-child' );
	}
	return $title;
}

/**
 * Cache-busting robusto del style.css del tema hijo.
 *
 * El sitio carga ese mismo archivo por dos vías; una llega con una versión
 * estática (p. ej. ?ver=1.9.54) que los navegadores cachean con fuerza, por lo
 * que los cambios de CSS no siempre llegan (sobre todo en móvil). Aquí forzamos
 * que CUALQUIER URL de listeo-child/style.css lleve la fecha de modificación del
 * archivo como versión → al editar el CSS, el navegador siempre toma la copia nueva.
 */
add_filter( 'style_loader_src', 'pp_childcss_cache_bust', 10, 2 );
function pp_childcss_cache_bust( $src, $handle ) {
	if ( false !== strpos( $src, 'listeo-child/style.css' ) ) {
		$path = get_stylesheet_directory() . '/style.css';
		if ( file_exists( $path ) ) {
			$src = add_query_arg( 'ver', filemtime( $path ), remove_query_arg( 'ver', $src ) );
		}
	}
	return $src;
}

/**
 * Fallback de imagen de producto: si un producto NO tiene imagen destacada
 * (featured image) pero SÍ tiene imágenes en la galería, usa la primera de la
 * galería como imagen del producto. Así get_image() muestra la imagen en el
 * carrito, el minicart, la tienda y la ficha — igual que ya lo hace el checkout
 * por bloques (Store API), que usa la galería cuando falta la destacada.
 * Esto es útil sobre todo para productos importados por dropshipping que llegan
 * con imágenes en galería pero sin imagen destacada asignada.
 */
add_filter( 'woocommerce_product_get_image_id', 'pp_fallback_gallery_image_id', 10, 2 );
add_filter( 'woocommerce_product_variation_get_image_id', 'pp_fallback_gallery_image_id', 10, 2 );
function pp_fallback_gallery_image_id( $image_id, $product ) {
	if ( empty( $image_id ) && is_a( $product, 'WC_Product' ) ) {
		$gallery = $product->get_gallery_image_ids();
		if ( ! empty( $gallery ) ) {
			return $gallery[0];
		}
	}
	return $image_id;
}

/**
 * PLP móvil: encapsula los filtros de la tienda en un panel deslizante desde la
 * izquierda, abierto con un botón "Filtros".
 *
 * PERFORMANCE: el botón, la cabecera del panel y el overlay se renderizan en el
 * HTML por PHP (server-side) para que el botón aparezca DE INMEDIATO, sin esperar
 * a que corra JavaScript al final de la página. El JS queda mínimo (solo abrir/
 * cerrar por delegación). Solo se inyecta en la tienda y en los archivos de
 * categoría/etiqueta de producto. El diseño vive en style.css bajo @media (max-width:767px).
 */
function ppv2_is_shop_context() {
	return function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() );
}

/**
 * Corrige un WARNING del tema PADRE (listeo/functions.php:776).
 * Su filtro `ts_get_subcategory_terms` recorre los términos y lee `$term->slug`,
 * pero cuando get_terms() se llama con fields=ids/slugs (p. ej. el widget de
 * Categorías de la tienda) recibe strings/ints → "Attempt to read property slug
 * on string". Quitamos su filtro y ponemos una versión que valida el tipo.
 */
add_action( 'after_setup_theme', 'ppv2_fix_listeo_subcategory_terms', 20 );
function ppv2_fix_listeo_subcategory_terms() {
	// Listeo tiene DOS filtros idénticos con el mismo bug (functions.php y inc/woocommerce.php).
	remove_filter( 'get_terms', 'ts_get_subcategory_terms', 10 );
	remove_filter( 'get_terms', 'exclude_listeo_booking_from_shop_page', 10 );
	add_filter( 'get_terms', 'ppv2_get_subcategory_terms', 10, 3 );
}
function ppv2_get_subcategory_terms( $terms, $taxonomies, $args ) {
	if ( ! is_array( $taxonomies ) ) { return $terms; }
	if ( in_array( 'product_cat', $taxonomies, true ) && ! is_admin() && function_exists( 'is_shop' ) && is_shop() ) {
		$new = array();
		foreach ( $terms as $term ) {
			// Si es objeto término, aplicamos la exclusión original de "listeo-booking".
			if ( is_object( $term ) && isset( $term->slug ) ) {
				if ( 'listeo-booking' !== $term->slug ) { $new[] = $term; }
			} else {
				$new[] = $term; // ids/slugs/strings: pasan tal cual (evita el warning)
			}
		}
		$terms = $new;
	}
	return $terms;
}

// 1) Barra de herramientas: envuelve botón "Filtros" + conteo + "Ordenar por" en
//    un contenedor para poder alinearlos (en móvil, Filtros y Ordenar en una línea).
add_action( 'woocommerce_before_shop_loop', 'ppv2_shop_toolbar_open', 4 );
function ppv2_shop_toolbar_open() {
	if ( ppv2_is_shop_context() ) { echo '<div class="pp-shop-toolbar">'; }
}
add_action( 'woocommerce_before_shop_loop', 'ppv2_shop_toolbar_close', 40 );
function ppv2_shop_toolbar_close() {
	if ( ppv2_is_shop_context() ) { echo '</div>'; }
}

// Botón "Filtros" al principio del listado (server-side → aparece al instante).
add_action( 'woocommerce_before_shop_loop', 'ppv2_shop_filters_button', 5 );
function ppv2_shop_filters_button() {
	if ( ! ppv2_is_shop_context() ) { return; }
	echo '<button type="button" class="pp-filters-toggle">'
		. '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>'
		. '<span>Filtros</span></button>';
}

// Botón "Agregar" en las tarjetas de la TIENDA: texto corto (solo en tienda/categoría).
add_filter( 'woocommerce_product_add_to_cart_text', 'ppv2_loop_add_to_cart_text', 10, 2 );
function ppv2_loop_add_to_cart_text( $text, $product ) {
	if ( is_admin() || ! ppv2_is_shop_context() ) { return $text; }
	if ( $product && $product->is_purchasable() && $product->is_in_stock() && ! $product->is_type( 'variable' ) ) {
		return esc_html__( 'Agregar', 'listeo-child' );
	}
	return $text;
}

// Ícono de carrito dentro del botón "Agregar" (solo en tienda/categoría).
add_filter( 'woocommerce_loop_add_to_cart_link', 'ppv2_loop_add_to_cart_icon', 10, 3 );
function ppv2_loop_add_to_cart_icon( $html, $product, $args = array() ) {
	if ( ! ppv2_is_shop_context() ) { return $html; }
	$icon = '<svg class="pp-cart-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.2 11a1.6 1.6 0 0 0 1.6 1.3h8.3a1.6 1.6 0 0 0 1.6-1.2L21.5 7H6"/></svg>';
	return preg_replace( '/(<a\b[^>]*>)/', '$1' . $icon, $html, 1 );
}

// 2) Cabecera del panel (título + cerrar) dentro de la barra lateral de la tienda.
add_action( 'dynamic_sidebar_before', 'ppv2_shop_filters_head', 10, 2 );
function ppv2_shop_filters_head( $index, $has_widgets ) {
	if ( 'sidebar-shop' !== $index || ! ppv2_is_shop_context() ) { return; }
	echo '<div class="pp-filters-head"><span>Filtros</span>'
		. '<button type="button" class="pp-filters-close" aria-label="Cerrar filtros">&times;</button></div>';
}

// 3) Overlay + JS mínimo (delegado) para abrir/cerrar el panel.
add_action( 'wp_footer', 'ppv2_shop_filters_overlay', 120 );
function ppv2_shop_filters_overlay() {
	if ( ! ppv2_is_shop_context() ) { return; }
	echo '<div class="pp-filters-overlay"></div>';
	?>
	<script>
	(function () {
		var html = document.documentElement;
			// Reinicia el slider de precio de WooCommerce: oculta los inputs #min_price/#max_price,
			// muestra el slider y lo reconstruye si falta. Necesario cuando el widget se muestra
			// DESPUÉS de estar oculto (cajón móvil o sección plegada), donde se veían los inputs
			// crudos. WooCommerce expone el gancho 'init_price_filter' para reinicializarlo.
			function ppReinitPrice() {
				if ( ! window.jQuery ) { return; }
				setTimeout( function () { window.jQuery( document.body ).trigger( 'init_price_filter' ); }, 60 );
			}
		document.addEventListener('click', function (e) {
			var t = e.target;
			if ( t.closest && t.closest('.pp-filters-toggle') ) { html.classList.add('pp-filters-open'); ppReinitPrice(); return; }
			if ( ( t.closest && t.closest('.pp-filters-close') ) || ( t.classList && t.classList.contains('pp-filters-overlay') ) ) { html.classList.remove('pp-filters-open'); }
		});
		document.addEventListener('keydown', function (e) { if ( e.key === 'Escape' || e.keyCode === 27 ) { html.classList.remove('pp-filters-open'); } });

		// Acordeón de filtros: cada widget (menos "Buscar producto") se pliega al tocar su título.
		var sidebar = document.querySelector('.listeo-shop-grid .col-sidebar');
		if ( sidebar ) {
			Array.prototype.forEach.call( sidebar.querySelectorAll('section.widget'), function ( w ) {
				if ( w.classList.contains('widget_product_search') || w.classList.contains('widget_layered_nav_filters') ) { return; }
				var title = w.querySelector('.widget-title');
				if ( ! title ) { return; }
				w.classList.add('pp-accordion');
				// Por defecto TODAS las secciones de filtros arrancan plegadas (cerradas);
				// se abren al hacer clic en su título.
				w.classList.add('pp-collapsed');
				title.setAttribute('role', 'button');
				title.setAttribute('tabindex', '0');
				title.addEventListener('click', function () { w.classList.toggle('pp-collapsed'); if ( ! w.classList.contains('pp-collapsed') ) { ppReinitPrice(); } });
				title.addEventListener('keydown', function ( e ) {
					if ( e.key === 'Enter' || e.key === ' ' ) { e.preventDefault(); w.classList.toggle('pp-collapsed'); if ( ! w.classList.contains('pp-collapsed') ) { ppReinitPrice(); } }
				});
			} );
		}
	})();
	</script>
	<?php
}

/**
 * DIRECTORIO (móvil): filtros como PANEL DESLIZANTE desde la izquierda, igual
 * al de la Tienda. No toca el JS de Listeo: su botón "Mostrar Filtros" alterna
 * la clase `enabled-sidebar` del sidebar y aquí solo SINCRONIZAMOS nuestro
 * estado (clase pp-dirfilters-open en <html>) con esa clase. Agrega barra
 * superior (título + X), overlay y botón fijo "Ver N resultados" con loader
 * enganchado al AJAX nativo de Listeo (listeo_get_listings). El look vive en
 * style.css y SOLO aplica en móvil (≤991px); en escritorio no cambia nada.
 */
add_action( 'wp_footer', 'ppv2_dirfilters_drawer', 121 );
function ppv2_dirfilters_drawer() {
	if ( ! ppv2_is_listings_archive() ) { return; }
	?>
	<script>
	(function () {
		var sb = document.querySelector('.full-page-sidebar');
		if ( ! sb ) { return; }
		var html = document.documentElement;
		var OPEN = 'pp-dirfilters-open';

		// Barra superior del panel: título + botón de cierre.
		var head = document.createElement('div');
		head.className = 'pp-dirfilters-head';
		head.innerHTML = '<span>Filtros</span><button type="button" class="pp-dirfilters-close" aria-label="Cerrar filtros">&times;</button>';
		sb.insertBefore(head, sb.firstChild);

		// Barra inferior fija: línea de CONTEO (siempre visible) + botón "Ver resultados".
		var foot = document.createElement('div');
		foot.className = 'pp-dirfilters-foot';
		foot.innerHTML = '<div class="pp-dirfilters-count" aria-live="polite"></div>'
			+ '<button type="button" class="pp-dirfilters-apply">Ver resultados</button>';
		sb.appendChild(foot);

		// Overlay del cajón.
		var overlay = document.createElement('div');
		overlay.className = 'pp-dirfilters-overlay';
		sb.parentNode.insertBefore(overlay, sb.nextSibling);

		// En MÓVIL movemos sidebar+overlay a un host colgado de <html>: el tema
		// envuelve la página en contenedores con transform (menú off-canvas) que
		// ROMPEN position:fixed y dejaban el cajón desanclado — mismo truco que
		// usa el minicarrito (pp-minicart). En escritorio vuelve a su lugar
		// original (marcado con un placeholder invisible).
		var mq = window.matchMedia('(max-width: 991px)');
		var host = null, placeholder = null;
		function placeDrawer() {
			if ( mq.matches ) {
				if ( ! host ) {
					host = document.createElement('div');
					host.className = 'pp-dirfilters-host';
					document.documentElement.appendChild(host);
				}
				if ( ! placeholder ) {
					placeholder = document.createElement('span');
					placeholder.style.display = 'none';
					sb.parentNode.insertBefore(placeholder, sb);
				}
				if ( sb.parentNode !== host ) { host.appendChild(sb); host.appendChild(overlay); }
			} else if ( placeholder && sb.parentNode !== placeholder.parentNode ) {
				placeholder.parentNode.insertBefore(sb, placeholder);
				placeholder.parentNode.insertBefore(overlay, sb.nextSibling);
				closeDrawer();
			}
		}

		function countResults() {
			return document.querySelectorAll('.listings-container .listing-card-container-nl').length;
		}
		function updateApply( loading ) {
			var b = foot.querySelector('.pp-dirfilters-apply');
			var c = foot.querySelector('.pp-dirfilters-count');
			if ( ! b || ! c ) { return; }
			if ( loading ) {
				// Cargando: isotipo de marca latiendo (CSS) + texto, botón atenuado.
				foot.classList.add('is-loading');
				c.classList.remove('is-empty');
				c.textContent = 'Buscando…';
				return;
			}
			foot.classList.remove('is-loading');
			var n = countResults();
			if ( n > 0 ) {
				c.classList.remove('is-empty');
				c.textContent = n + ( n === 1 ? ' resultado encontrado' : ' resultados encontrados' );
				b.classList.remove('is-empty');
				b.textContent = 'Ver resultados';
			} else {
				// SIN resultados: aviso claro en la línea y en el propio botón.
				c.classList.add('is-empty');
				c.textContent = 'Sin resultados con estos filtros';
				b.classList.add('is-empty');
				b.textContent = 'Sin resultados';
			}
		}
		// Sincroniza nuestro estado con la clase que alterna Listeo.
		function syncFromListeo() {
			html.classList.toggle(OPEN, sb.classList.contains('enabled-sidebar'));
			if ( html.classList.contains(OPEN) ) { updateApply(false); }
		}
		function closeDrawer() {
			sb.classList.remove('enabled-sidebar');
			Array.prototype.forEach.call(document.querySelectorAll('.enable-filters-button'), function ( b ) { b.classList.remove('active'); });
			html.classList.remove(OPEN);
		}

		document.addEventListener('click', function ( e ) {
			if ( ! e.target.closest ) { return; }
			// Después del toggle de Listeo: su handler jQuery vive en el ELEMENTO,
			// así que ya corrió cuando el evento burbujea hasta document. Sincronizamos
			// directo (sin setTimeout: los timers se degradan en pestañas en segundo plano).
			if ( e.target.closest('.enable-filters-button') ) { syncFromListeo(); return; }
			if ( e.target.closest('.pp-dirfilters-close') || e.target === overlay || e.target.closest('.pp-dirfilters-apply') ) { closeDrawer(); }
		});
		document.addEventListener('keydown', function ( e ) { if ( e.key === 'Escape' ) { closeDrawer(); } });

		// Loader + conteo: eventos AJAX globales de jQuery para listeo_get_listings.
		if ( window.jQuery ) {
			var isListeo = function ( settings ) {
				var s = ( settings && ( settings.data || '' ) ) + ' ' + ( settings && ( settings.url || '' ) );
				return s.indexOf('listeo_get_listings') !== -1;
			};
			jQuery(document).ajaxSend(function ( ev, xhr, settings ) { if ( isListeo(settings) ) { updateApply(true); } });
			// ajaxComplete corre DESPUÉS del success de Listeo (que ya pintó las
			// tarjetas), así que el conteo directo es fiable — sin setTimeout.
			jQuery(document).ajaxComplete(function ( ev, xhr, settings ) { if ( isListeo(settings) ) { updateApply(false); } });
		}

		// Red de seguridad para el conteo: cualquier re-render del contenedor de
		// resultados (venga del AJAX que venga) actualiza la línea de conteo,
		// aunque los eventos ajaxSend/Complete no se hayan capturado.
		var cont = document.querySelector('.listings-container');
		if ( cont && window.MutationObserver ) {
			new MutationObserver(function () {
				if ( ! foot.classList.contains('is-loading') ) { updateApply(false); }
			}).observe(cont, { childList: true, subtree: true });
		}

		placeDrawer();
		if ( mq.addEventListener ) { mq.addEventListener('change', placeDrawer); }
		updateApply(false);
	})();
	</script>
	<?php
}

/**
 * DIRECTORIO (escritorio): botón ✕ en la esquina superior derecha del panel de
 * filtros. El tema ya trae el conmutador "Mostrar/Ocultar Filtros", pero al
 * estar lejos del panel es fácil perderse; la ✕ da la salida evidente, donde
 * todo el mundo la busca.
 *
 * No reimplementa el cierre: hace clic en el propio `.enable-filters-button` de
 * Listeo, así que el estado (clases, texto del botón, cajón móvil) queda
 * consistente sin duplicar lógica. En móvil no cambia nada: el CSS solo muestra
 * esta ✕ a partir de 992px, porque el cajón ya tiene la suya en su cabecera.
 */
add_action( 'wp_footer', 'ppv2_dirfilters_close_desktop', 124 );
function ppv2_dirfilters_close_desktop() {
	if ( ! ppv2_is_listings_archive() ) { return; }
	?>
	<script>
	(function () {
		var inner = document.querySelector('.full-page-sidebar .full-page-sidebar-inner')
			|| document.querySelector('.full-page-sidebar');
		if ( ! inner || inner.querySelector('.pp-dirfilters-close-desktop') ) { return; }

		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'pp-dirfilters-close-desktop';
		btn.setAttribute('aria-label', 'Cerrar filtros');
		btn.innerHTML = '&times;';
		btn.addEventListener('click', function () {
			var toggle = document.querySelector('.enable-filters-button');
			if ( toggle ) { toggle.click(); }
		});
		inner.insertBefore(btn, inner.firstChild);
	})();
	</script>
	<?php
}

/**
 * DIRECTORIO: el filtro de CATEGORÍAS deja de ser un desplegable flotante
 * (bootstrap-select) y pasa a ser un ACORDEÓN dentro de la propia barra de
 * filtros, con el mismo lenguaje visual que el árbol de categorías de la Tienda.
 *
 * Cómo funciona sin tocar el plugin: el <select multiple id="tax-listing_category">
 * original SIGUE en el DOM (solo se oculta su widget). Nuestro acordeón se limita
 * a marcar/desmarcar sus <option> y a disparar `change`, que es justo lo que
 * escuchan Listeo (búsqueda AJAX, `#listeo_core-search-form.ajax-search select`)
 * y el cargador de "Más Filtros" (`.dynamic select[id*="_category"]`). Si algún
 * día se desactiva esta función, reaparece el desplegable nativo intacto.
 *
 * Reglas de producto (acordadas con Miguel):
 *  - Marcar una categoría madre selecciona también todas sus subcategorías.
 *  - Se conserva la selección múltiple; lo elegido se muestra como etiquetas
 *    quitables encima de la lista.
 *
 * La jerarquía no existe en el HTML de Listeo: las subcategorías se distinguen
 * porque su texto viene prefijado con dos `&nbsp;`. De ahí sale el agrupado.
 */
add_action( 'wp_footer', 'ppv2_dirfilters_categorias_acordeon', 123 );
function ppv2_dirfilters_categorias_acordeon() {
	if ( ! ppv2_is_listings_archive() ) { return; }
	?>
	<script>
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
	</script>
	<?php
}

/**
 * Árbol de CATEGORÍAS de la tienda con estilo "app": envuelve cada fila del
 * widget de categorías en .pp-cat-row, agrega un chevron a la izquierda (en los
 * padres) que pliega/despliega sus hijos SIN navegar, y abre automáticamente el
 * camino de la categoría actual. El nombre sigue siendo un enlace a la página de
 * la categoría. El look lo pone style.css (.pp-cat-row / .pp-cat-toggle).
 */
add_action( 'wp_footer', 'ppv2_shop_category_tree', 122 );
function ppv2_shop_category_tree() {
	if ( ! ppv2_is_shop_context() ) { return; }
	?>
	<script>
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
	</script>
	<?php
}

/**
 * Ordena el panel de filtros de la tienda para que "Categorías" quede como el
 * PRIMER filtro después del buscador (y del resumen "Filtrado por", si está
 * activo). Se hace por código —no en la base de datos— para que el orden viaje
 * con el tema al desplegar a producción. Solo afecta el frontend.
 */
/**
 * Muestra "Directorio" como nombre del archivo de listados (antes "Listados"),
 * tanto en el título de la página (H1) como en el <title> del navegador. Solo
 * afecta el frontend; NO cambia las etiquetas del panel de administración.
 */
// H1 del archivo de listados: el template usa la opción listeo_listings_archive_title.
// La forzamos a "Directorio" en el FRONTEND por código (así viaja con el tema al
// desplegar y no depende de configurar la opción en la BD de producción).
add_filter( 'pre_option_listeo_listings_archive_title', 'ppv2_listings_archive_name' );
function ppv2_listings_archive_name( $value ) {
	if ( is_admin() ) { return $value; } // en el panel, respetar el valor real
	return 'Directorio';
}
// Respaldo si el template usara the_archive_title() en vez de la opción.
add_filter( 'get_the_archive_title', 'ppv2_listings_get_archive_title' );
function ppv2_listings_get_archive_title( $title ) {
	if ( is_post_type_archive( 'listing' ) ) { return 'Directorio'; }
	return $title;
}
// <title> del navegador: lo genera Rank Math (SEO) → cambiamos "Listados" por
// "Directorio" solo en el archivo de listados.
add_filter( 'rank_math/frontend/title', 'ppv2_listings_seo_title' );
function ppv2_listings_seo_title( $title ) {
	if ( is_post_type_archive( 'listing' ) ) { return str_replace( 'Listados', 'Directorio', $title ); }
	return $title;
}

/**
 * Renombra "Marcadores/Bookmarks" → "Favoritos" en el panel Mi Cuenta:
 *  - Etiqueta del menú lateral (dominio 'listeo', cadena "Bookmarks").
 *  - Menú de usuario del header (dominio 'listeo_core', cadena "Bookmarks").
 *  - Mensajes de estado vacío del listado de favoritos (dominio 'listeo_core').
 * Se hace por traducción (gettext) para no editar el tema padre ni el plugin.
 */
add_filter( 'gettext', 'ppv2_rename_bookmarks_to_favoritos', 20, 3 );
function ppv2_rename_bookmarks_to_favoritos( $translated, $text, $domain ) {
	if ( 'listeo' === $domain && 'Bookmarks' === $text ) {
		return 'Favoritos';
	}
	if ( 'listeo_core' === $domain ) {
		switch ( $text ) {
			case 'Bookmarks':                         return 'Favoritos'; // menú de usuario del header
			case 'Bookmarked Listings':               return 'Listados Favoritos'; // título de la página
			case 'No bookmarks!':                     return '¡No hay favoritos!';
			case 'You don\'t have any bookmarks yet.': return 'Aún no tienes ningún favorito.';
		}
	}
	return $translated;
}

add_filter( 'sidebars_widgets', 'ppv2_shop_sidebar_order' );
function ppv2_shop_sidebar_order( $sidebars ) {
	if ( is_admin() || empty( $sidebars['sidebar-shop'] ) || ! is_array( $sidebars['sidebar-shop'] ) ) {
		return $sidebars;
	}
	$widgets = $sidebars['sidebar-shop'];

	// Localizar el widget de Categorías (WC_Widget_Product_Categories).
	$cat_id = '';
	foreach ( $widgets as $wid ) {
		if ( false !== strpos( $wid, 'product_categories' ) ) { $cat_id = $wid; break; }
	}
	if ( '' === $cat_id ) { return $sidebars; }

	// Quitarlo de su posición actual.
	$widgets = array_values( array_filter( $widgets, function ( $w ) use ( $cat_id ) { return $w !== $cat_id; } ) );

	// Punto de inserción: justo después del buscador; si lo que sigue es el
	// resumen "Filtrado por" (layered_nav_filters), va inmediatamente después de ese.
	$insert = 0;
	foreach ( $widgets as $i => $wid ) {
		if ( false !== strpos( $wid, 'product_search' ) ) { $insert = $i + 1; break; }
	}
	if ( isset( $widgets[ $insert ] ) && false !== strpos( $widgets[ $insert ], 'layered_nav_filters' ) ) {
		$insert++;
	}

	array_splice( $widgets, $insert, 0, array( $cat_id ) );
	$sidebars['sidebar-shop'] = $widgets;
	return $sidebars;
}

/**
 * Personalizaciones y ganchos adicionales de Parche Peludo V2
 * Agrega aqui funciones personalizadas para integraciones seguras.
 */

/**
 * ¿Estamos en una página de LISTADOS del directorio? Cubre el archivo principal
 * (/listings/) Y las páginas de taxonomía del CPT "listing" (categorías como
 * /categoria-listado/.../, regiones, características…), que usan el mismo layout
 * con panel de filtros. Se usa para que el botón "Buscar en Directorio", la lupa
 * móvil y el feedback de carga aparezcan en TODAS esas páginas, no solo en /listings/.
 */
function ppv2_is_listings_archive() {
	if ( is_post_type_archive( 'listing' ) ) {
		return true;
	}
	$listing_taxonomies = get_object_taxonomies( 'listing' );
	return ! empty( $listing_taxonomies ) && is_tax( $listing_taxonomies );
}

/**
 * Marca el <body> de todas las páginas de listados con la clase `ppv2-listings`,
 * para poder estilarlas de forma consistente (el archivo principal trae
 * `post-type-archive-listing` pero las taxonomías no → esta clase unifica).
 */
function ppv2_listings_body_class( $classes ) {
	if ( ppv2_is_listings_archive() ) {
		$classes[] = 'ppv2-listings';
	}
	return $classes;
}
add_filter( 'body_class', 'ppv2_listings_body_class' );

/**
 * Página de LISTADOS (directorio) — Renombrar el TÍTULO del panel de filtros.
 *
 * Dentro del panel desplegado, el encabezado "Filtros" es el TÍTULO de un
 * widget (id_base 'widget_search_form_listings', exclusivo de la barra
 * 'sidebar-listings'). Su valor "Filtros" está guardado en la base de datos.
 * En vez de editar el widget en la BD (que habría que repetir en producción),
 * lo renombramos por código a "Filtrar en Directorio": se propaga al subir
 * este functions.php, mantiene local y producción sincronizados y es a prueba
 * de actualizaciones. Scopeado SOLO a ese widget para no afectar otros títulos.
 */
function ppv2_rename_titulo_panel_filtros( $title, $instance = array(), $id_base = '' ) {
	if ( 'widget_search_form_listings' === $id_base && 'Filtros' === trim( $title ) ) {
		return 'Filtrar en Directorio';
	}
	return $title;
}
add_filter( 'widget_title', 'ppv2_rename_titulo_panel_filtros', 20, 3 );

/**
 * Texto "sin resultados" del archivo de listados (plantilla no-found del plugin
 * Listeo Core): ajusta el TÍTULO y el MENSAJE vía filtro gettext (a prueba de
 * actualizaciones, sin tocar el plugin). Solo afecta el front-end.
 */
function ppv2_rename_no_results( $translation, $text, $domain ) {
	if ( is_admin() ) {
		return $translation;
	}
	if ( 'Nothing found' === $text ) {
		return 'Sin resultados';
	}
	if ( 'We&rsquo;re sorry but we do not have any listings matching your search, try to change you search settings' === $text ) {
		return 'Lo sentimos, no encontramos resultados que coincidan con tu búsqueda. Te invitamos a modificar los términos o filtros de búsqueda.';
	}
	// Botón del widget de reservas cuando el usuario no ha iniciado sesión.
	// El plugin usa dos variantes de mayúsculas ("Login to Book" / "Login To Book").
	if ( 'Login to Book' === $text || 'Login To Book' === $text ) {
		return 'Inicia sesión para reservar';
	}
	return $translation;
}
add_filter( 'gettext_listeo_core', 'ppv2_rename_no_results', 20, 3 );

/**
 * "Mi Cuenta" (.user-menu) — DESKTOP: en vez del desplegable por hover, abrir
 * un PANEL DESLIZANTE desde la izquierda al hacer CLIC en el avatar. El estilo
 * (drawer) vive en style.css; aquí solo el comportamiento: toggle de la clase
 * .ppv2-acct-open, cierre por clic-fuera / Escape / botón ✕, y bloqueo de
 * scroll del fondo. Global (Mi Cuenta está en todo el sitio); solo si hay sesión.
 */
function ppv2_account_drawer() {
	if ( ! is_user_logged_in() ) {
		return;
	}
	?>
	<script>
	(function () {
		function setOpen(um, open) {
			// Un solo panel a la vez: al abrir Mi Cuenta, cerrar el panel derecho
			// (Enviar Mensaje / Ver horarios / Carrito) si estuviera abierto.
			if (open && window.ppRDrawer) { window.ppRDrawer.close(); }
			um.classList.toggle('ppv2-acct-open', open);
			document.documentElement.classList.toggle('ppv2-acct-lock', open);
		}
		// Cierre global para que otros paneles puedan cerrar Mi Cuenta al abrirse.
		window.ppCloseAccountDrawer = function () {
			var opened = document.querySelector('#header .user-menu.ppv2-acct-open');
			if (opened) { setOpen(opened, false); }
		};
		// Inyecta la cabecera del drawer (título "Mi Cuenta" + botón ✕) la 1ª vez.
		function ensureHeader(um) {
			var ul = um.querySelector('ul');
			if (!ul || ul.querySelector('.ppv2-acct-header')) return;
			var header = document.createElement('li');
			header.className = 'ppv2-acct-header';
			var title = document.createElement('span');
			title.className = 'ppv2-acct-title';
			title.textContent = 'Mi Cuenta';
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'ppv2-acct-close';
			btn.setAttribute('aria-label', 'Cerrar');
			btn.innerHTML = '&times;';
			btn.addEventListener('click', function (e) { e.stopPropagation(); setOpen(um, false); });
			header.appendChild(title);
			header.appendChild(btn);
			ul.insertBefore(header, ul.firstChild);
		}
		document.addEventListener('click', function (e) {
			var um = document.querySelector('#header .user-menu');
			if (!um) return;
			// Clic en el avatar → abrir/cerrar el drawer.
			if (e.target.closest('.user-menu .user-name')) {
				e.preventDefault();
				ensureHeader(um);
				setOpen(um, !um.classList.contains('ppv2-acct-open'));
				return;
			}
			// Clic FUERA del drawer (en el velo o la página) → cerrar.
			if (um.classList.contains('ppv2-acct-open') && !e.target.closest('.user-menu ul')) {
				setOpen(um, false);
			}
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				var um = document.querySelector('.user-menu.ppv2-acct-open');
				if (um) setOpen(um, false);
			}
		});
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'ppv2_account_drawer', 120 );

/**
 * Menú hamburguesa: cerrarlo al tocar el VELO (el 15% libre a la derecha cuando
 * el panel está al 85% en móvil). El tema abre/cierra alternando la clase
 * `mobile-nav-open` en <body>; aquí la quitamos si se toca fuera del panel y
 * fuera de los disparadores del menú. Global.
 */
function ppv2_mobile_menu_close_outside() {
	?>
	<script>
	(function () {
		document.addEventListener('click', function (e) {
			if (!document.body.classList.contains('mobile-nav-open')) return;
			if (e.target.closest('.mobile-navigation-wrapper')) return; // dentro del panel
			if (e.target.closest('.mmenu-trigger, .menu-icon-toggle, .desktop-mmenu-trigger')) return; // el toggle ya lo maneja el tema
			document.body.classList.remove('mobile-nav-open');
		});
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'ppv2_mobile_menu_close_outside', 121 );

/*
 * NOTA (2026-07-02): aquí existía ppv2_listings_header_search_opens_filters(),
 * que desviaba la lupa móvil del header para abrir el panel de filtros, porque
 * en listados el header no tenía buscador. Desde que el buscador del header se
 * inyecta también en listados (ppv2_listings_inject_header_search), la lupa
 * recuperó su comportamiento NATIVO (mostrar/ocultar el buscador, igual que en
 * el resto del sitio) y los filtros se abren con su botón propio de la página
 * ("Mostrar Filtros").
 *
 * También existía ppv2_listings_header_buscar_button() (botón "Buscar en
 * Directorio" en el header de escritorio, que abría el panel de filtros):
 * retirado el 2026-07-02 por la misma razón — era el sustituto del buscador
 * cuando el header de listados no lo tenía.
 */

/**
 * Página de LISTADOS — Feedback de carga en el panel de filtros (móvil).
 *
 * En móvil el panel de filtros cubre la pantalla, así que el loader central
 * queda detrás y el usuario no ve que el sistema está cargando al cambiar un
 * filtro. Inyectamos una barra de progreso fina al TOPE del panel
 * (.full-page-sidebar-inner) y la activamos mientras Listeo recarga los
 * resultados (clase .loading en #listeo-listings-container, detectada con un
 * MutationObserver — fiable ante cambios dinámicos de clase). El estilo y la
 * visibilidad (solo móvil) viven en style.css (.ppv2-filter-loadingbar).
 */
function ppv2_listings_filter_loading_feedback() {
	if ( ! ppv2_is_listings_archive() ) {
		return;
	}
	?>
	<script>
	(function () {
		document.addEventListener('DOMContentLoaded', function () {
			var container = document.getElementById('listeo-listings-container');
			if (!container) return;

			// Crea (una vez) la barra de progreso + el texto de estado al tope del panel.
			function ensureEls() {
				var inner = document.querySelector('.full-page-sidebar .full-page-sidebar-inner')
					|| document.querySelector('.full-page-sidebar-inner');
				if (!inner) return null;
				var bar = inner.querySelector('.ppv2-filter-loadingbar');
				if (!bar) {
					bar = document.createElement('div');
					bar.className = 'ppv2-filter-loadingbar';
					bar.setAttribute('role', 'progressbar');
					bar.setAttribute('aria-label', 'Cargando resultados');
					inner.insertBefore(bar, inner.firstChild);
				}
				var status = inner.querySelector('.ppv2-filter-status');
				if (!status) {
					status = document.createElement('div');
					status.className = 'ppv2-filter-status';
					status.setAttribute('aria-live', 'polite'); // lectores de pantalla anuncian el conteo
					inner.insertBefore(status, bar.nextSibling);
				}
				return { bar: bar, status: status };
			}

			function update() {
				var els = ensureEls();
				if (!els) return;
				var loading = container.classList.contains('loading');
				els.bar.classList.toggle('is-active', loading);
				if (loading) {
					els.status.textContent = 'Buscando…';      // "Buscando…"
					els.status.classList.add('is-loading');
				} else {
					// Conteo de resultados (tarjetas renderizadas en el contenedor).
					var n = container.querySelectorAll('.listing-card-container-nl').length;
					els.status.classList.remove('is-loading');
					els.status.textContent = n === 0 ? 'Sin resultados'
						: (n === 1 ? '1 resultado' : n + ' resultados');
				}
			}

			// Observa la clase .loading (estado de carga) y los cambios de tarjetas
			// (cuando el AJAX reemplaza los resultados) para recalcular el conteo.
			new MutationObserver(update).observe(container, {
				attributes: true, attributeFilter: ['class'], childList: true
			});
			update();
		});
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'ppv2_listings_filter_loading_feedback', 122 );

/**
 * Página de LISTADOS — Buscador del header (con tabs Directorio|Tienda) también aquí.
 *
 * El layout "halfsidebar" carga header-fullwidthnosearch.php, cuyo contenedor
 * .header-search-container viene VACÍO de fábrica (Listeo comenta el shortcode
 * para no duplicar la búsqueda con el panel de filtros). Inyectamos el mismo
 * formulario del header vía el shortcode oficial, DESARMADO para no chocar con
 * el panel de filtros:
 *  - id del <form> renombrado: el JS de filtros AJAX de Listeo (ajax.search.min.js)
 *    selecciona "#listeo_core-search-form"; con IDs duplicados serializaría el
 *    formulario equivocado y los filtros dejarían de aplicar.
 *  - id del campo keyword renombrado (el name="keyword_search" SE CONSERVA:
 *    es lo que usa la búsqueda del servidor y nuestro autocompletado).
 *  - sin clase ajax-search: Listeo no vigila los campos de este formulario.
 * Se imprime oculto al inicio del footer (prioridad 5) y un script inmediato lo
 * mueve al contenedor ANTES de DOMContentLoaded, para que el autocompletado y
 * los tabs (bloque PPV2 del buscador) lo encuentren como en las demás páginas.
 * La barra visible en ≤1200px la resuelve style.css (body.ppv2-listings).
 */
function ppv2_listings_inject_header_search() {
	if ( ! ppv2_is_listings_archive() ) {
		return;
	}
	// El shortcode de Listeo IMPRIME el formulario (no lo devuelve): lo
	// capturamos con un buffer para poder desarmarlo antes de mostrarlo.
	ob_start();
	$returned = do_shortcode( '[listeo_search_form action=' . get_post_type_archive_link( 'listing' ) . ' source="header" custom_class="main-search-form gray-style"]' );
	$printed  = ob_get_clean();
	$form     = $printed ? $printed : $returned;
	if ( ! $form ) {
		return;
	}
	$form = str_replace( 'id="listeo_core-search-form"', 'id="ppv2-header-search-form"', $form );
	$form = str_replace( 'id="keyword_search"', 'id="ppv2_keyword_search"', $form );
	$form = str_replace( 'ajax-search', '', $form );
	echo '<div id="ppv2-header-search-src" style="display:none">' . $form . '</div>';
	?>
	<script>
	(function () {
		var src = document.getElementById('ppv2-header-search-src');
		if (!src) { return; }
		var dest = document.querySelector('#header .header-search-container');
		if (dest && !dest.querySelector('form')) {
			while (src.firstChild) { dest.appendChild(src.firstChild); }
		}
		src.parentNode.removeChild(src);
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'ppv2_listings_inject_header_search', 5 );

/**
 * FIX — Quitar de Favoritos no funciona sin recargar.
 *
 * Bug en Listeo Core (`assets/js/frontend.js`): el clic para AÑADIR favorito está
 * enganchado de forma DELEGADA — `$("body").on("click", ".listeo_core-bookmark-it", …)`
 * (línea ~24) — y funciona siempre; pero el clic para QUITAR está enganchado de forma
 * DIRECTA — `$(".listeo_core-unbookmark-it").on("click", …)` (línea ~72) — solo a los
 * corazones que YA estaban marcados al cargar la página. Cuando marcas un favorito, el
 * corazón cambia su clase a `unbookmark-it` pero NO recibe el manejador de quitar → al
 * volver a hacer clic no pasa nada hasta recargar.
 *
 * Solución (sin tocar el plugin → a prueba de actualizaciones): reemplazamos ese
 * manejador directo por uno DELEGADO en `document` (replica el AJAX nativo
 * `listeo_core_unbookmark_this`) y, además, devolvemos la clase a `bookmark-it` para que
 * el corazón quede listo para volver a marcarse sin recargar. Se desengancha el manejador
 * directo roto para evitar doble disparo en los corazones ya marcados al cargar.
 * `closest("li").fadeOut()` se conserva igual que Listeo: solo aplica donde la tarjeta va
 * en <li> (panel "Mis Favoritos"); en el directorio (tarjetas en <div>) no hace nada.
 */
function ppv2_fix_unbookmark_delegation() {
	?>
	<script>
	jQuery(function ($) {
		if ( typeof listeo === 'undefined' || ! listeo.ajaxurl ) { return; }

		// Manejador DELEGADO de "quitar favorito" (funciona en corazones recién marcados).
		$( document ).off( 'click.ppFav' ).on( 'click.ppFav', '.listeo_core-unbookmark-it', function ( e ) {
			e.preventDefault();
			var handler = $( this );
			if ( handler.data( 'ppBusy' ) ) { return; }
			handler.data( 'ppBusy', true );
			handler.closest( 'li' ).addClass( 'opacity-05' );

			$.ajax({
				type: 'POST',
				dataType: 'json',
				url: listeo.ajaxurl,
				data: {
					action: 'listeo_core_unbookmark_this',
					post_id: handler.data( 'post_id' ),
					nonce: handler.data( 'nonce' )
				},
				success: function ( response ) {
					if ( response && response.type === 'success' ) {
						handler.closest( 'li' ).fadeOut(); // solo afecta al panel "Mis Favoritos"
						if ( handler.hasClass( 'fa-solid' ) ) {
							handler.removeClass( 'fa-solid' ).addClass( 'fa-regular' );
						}
						// Devolver a estado "marcable" para permitir re-marcar sin recargar.
						handler.removeClass( 'clicked liked listeo_core-unbookmark-it' )
						       .addClass( 'save listeo_core-bookmark-it' );
						handler.children( '.like-icon' ).removeClass( 'liked' );
					} else {
						handler.closest( 'li' ).removeClass( 'opacity-05' );
					}
					handler.data( 'ppBusy', false );
				},
				error: function () {
					handler.closest( 'li' ).removeClass( 'opacity-05' );
					handler.data( 'ppBusy', false );
				}
			});
		});

		// Desenganchar el manejador DIRECTO roto de Listeo (evita doble disparo en los
		// corazones que ya estaban marcados al cargar). Solo afecta a estos elementos.
		$( '.listeo_core-unbookmark-it' ).off( 'click' );
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'ppv2_fix_unbookmark_delegation', 130 );

/**
 * Sistema de PANEL DESLIZANTE genérico desde la derecha (window.ppRDrawer).
 *
 * Reutilizable por "Enviar Mensaje", "Ver horarios" y, a futuro, "Mi Carrito".
 * Toma el cuerpo de un widget, lo MUEVE a un panel fijo a la derecha (con título
 * + ✕) y lo devuelve a su sitio al cerrar. Garantiza que solo UN panel esté
 * abierto a la vez: al abrirse cierra el drawer de "Mi Cuenta" (y viceversa, ver
 * ppv2_account_drawer). Cierra con ✕, clic en el velo o tecla Escape. Mismo
 * lenguaje visual que Mi Cuenta (CSS .ppv2-rdrawer en style.css).
 */
function ppv2_right_drawer_system() {
	?>
	<script>
	(function () {
		var drawer, overlay, titleEl, bodyEl, source, placeholder;

		function build() {
			if ( drawer ) { return; }
			overlay = document.createElement( 'div' );
			overlay.className = 'ppv2-rdrawer-overlay';

			drawer = document.createElement( 'aside' );
			drawer.className = 'ppv2-rdrawer';
			drawer.setAttribute( 'role', 'dialog' );
			drawer.setAttribute( 'aria-modal', 'true' );
			drawer.setAttribute( 'aria-hidden', 'true' );

			var head = document.createElement( 'div' );
			head.className = 'ppv2-rdrawer-head';
			titleEl = document.createElement( 'span' );
			titleEl.className = 'ppv2-rdrawer-title';
			var closeBtn = document.createElement( 'button' );
			closeBtn.type = 'button';
			closeBtn.className = 'ppv2-rdrawer-close';
			closeBtn.setAttribute( 'aria-label', 'Cerrar' );
			closeBtn.innerHTML = '&times;';
			closeBtn.addEventListener( 'click', api.close );
			head.appendChild( titleEl );
			head.appendChild( closeBtn );

			bodyEl = document.createElement( 'div' );
			bodyEl.className = 'ppv2-rdrawer-body';

			drawer.appendChild( head );
			drawer.appendChild( bodyEl );
			document.body.appendChild( overlay );
			document.body.appendChild( drawer );

			overlay.addEventListener( 'click', api.close );
			document.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' ) { api.close(); }
			} );
		}

		// Devuelve el contenido a su lugar original (placeholder).
		function restore() {
			if ( source && placeholder && placeholder.parentNode ) {
				placeholder.parentNode.insertBefore( source, placeholder );
				placeholder.parentNode.removeChild( placeholder );
			}
			source = null;
			placeholder = null;
		}

		var api = {
			open: function ( sourceEl, title ) {
				build();
				if ( ! sourceEl ) { return; }
				// Un solo panel a la vez: cerrar Mi Cuenta si está abierto.
				if ( window.ppCloseAccountDrawer ) { window.ppCloseAccountDrawer(); }
				restore(); // por si quedaba contenido de una apertura anterior
				source = sourceEl;
				placeholder = document.createComment( 'ppv2-rdrawer' );
				sourceEl.parentNode.insertBefore( placeholder, sourceEl );
				bodyEl.appendChild( sourceEl );
				titleEl.textContent = title || '';
				overlay.classList.add( 'is-open' );
				drawer.classList.add( 'is-open' );
				drawer.setAttribute( 'aria-hidden', 'false' );
				document.documentElement.classList.add( 'ppv2-rdrawer-lock' );
			},
			close: function () {
				if ( ! drawer || ! drawer.classList.contains( 'is-open' ) ) { return; }
				overlay.classList.remove( 'is-open' );
				drawer.classList.remove( 'is-open' );
				drawer.setAttribute( 'aria-hidden', 'true' );
				document.documentElement.classList.remove( 'ppv2-rdrawer-lock' );
				restore();
			},
			isOpen: function () { return !! ( drawer && drawer.classList.contains( 'is-open' ) ); }
		};

		window.ppRDrawer = api;
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'ppv2_right_drawer_system', 118 );

/**
 * Traduce al español los mensajes por DEFECTO (en inglés) de Contact Form 7.
 *
 * El formulario "Contacto personalizado" (id 676, el de "Enviar Mensaje") tiene
 * sus mensajes de validación guardados en INGLÉS en la base de datos (p. ej.
 * "Please fill out this field."). En vez de editarlos formulario por formulario en
 * el admin (y repetirlo en producción), los interceptamos en tiempo de ejecución
 * con el filtro `wpcf7_display_message`: si el texto coincide con un mensaje por
 * defecto conocido en inglés, lo devolvemos en español. Solo toca los ingleses
 * conocidos → NO pisa mensajes ya personalizados en español de otros formularios.
 * Portable (viaja con functions.php) y a prueba de actualizaciones.
 */
function ppv2_cf7_messages_es( $message, $status = '' ) {
	$map = array(
		'Please fill out this field.' => 'Por favor, completa este campo.',
		'The field is required.' => 'Este campo es obligatorio.',
		'One or more fields have an error. Please check and try again.' => 'Uno o más campos tienen un error. Por favor revísalo e inténtalo de nuevo.',
		'Thank you for your message. It has been sent.' => 'Gracias por tu mensaje. Ha sido enviado.',
		'There was an error trying to send your message. Please try again later.' => 'Hubo un error al intentar enviar tu mensaje. Por favor, inténtalo de nuevo más tarde.',
		'You must accept the terms and conditions before sending your message.' => 'Debes aceptar los términos y condiciones antes de enviar tu mensaje.',
		'This field has a too long input.' => 'El texto introducido es demasiado largo.',
		'This field has a too short input.' => 'El texto introducido es demasiado corto.',
		'The date format is incorrect.' => 'El formato de fecha es incorrecto.',
		'The number format is invalid.' => 'El formato numérico no es válido.',
		'The e-mail address entered is invalid.' => 'La dirección de correo introducida no es válida.',
		'The URL is invalid.' => 'La URL no es válida.',
		'The telephone number is invalid.' => 'El número de teléfono no es válido.',
		'The code you entered is incorrect.' => 'El código que introdujiste es incorrecto.',
		'There was an unknown error uploading the file.' => 'Se ha producido un error desconocido al subir el archivo.',
		'You are not allowed to upload files of this type.' => 'No tienes permiso para subir archivos de este tipo.',
		'The file is too big.' => 'El archivo es demasiado grande.',
		'There was an error uploading the file.' => 'Se ha producido un error al subir el archivo.',
	);
	$key = trim( (string) $message );
	return isset( $map[ $key ] ) ? $map[ $key ] : $message;
}
add_filter( 'wpcf7_display_message', 'ppv2_cf7_messages_es', 20, 2 );

/**
 * Añade un LABEL guía sobre cada campo del formulario "Enviar Mensaje" (CF7 id 676),
 * manteniendo los placeholders. Se inyecta por JS (en el tema hijo → portable y a
 * prueba de actualizaciones; no hay que editar el formulario en la BD ni repetirlo en
 * producción). Idempotente. Solo en la página individual de listado.
 */
function ppv2_message_form_labels() {
	if ( ! is_singular( 'listing' ) ) {
		return;
	}
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		var form = document.querySelector('.message-vendor .wpcf7-form');
		if (!form) { return; }
		var fields = [
			{ sel: 'input[type="text"]',  text: 'Nombre completo' },
			{ sel: 'input[type="email"]', text: 'Correo electrónico' },
			{ sel: 'input[type="tel"]',   text: 'Celular' },
			{ sel: 'textarea',            text: 'Mensaje' }
		];
		fields.forEach(function (f) {
			var el = form.querySelector(f.sel);
			if (!el) { return; }
			var wrap = el.closest('.wpcf7-form-control-wrap') || el;
			var container = wrap.parentNode || wrap;
			if (container.querySelector('.ppv2-field-label')) { return; } // ya tiene label
			var lab = document.createElement('label');
			lab.className = 'ppv2-field-label';
			lab.textContent = f.text;
			if (el.id) { lab.setAttribute('for', el.id); }
			container.insertBefore(lab, wrap);
		});
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'ppv2_message_form_labels', 101 );

/**
 * Tienda V2 (Home): añade la etiqueta de categoría a cada producto y habilita
 * el filtrado por categoría en vivo (pills .ppv2-shop-filter) sobre el shortcode
 * nativo [products] dentro de .ppv2-shop-products. Scoped: solo actúa si existe
 * la sección en la página, no afecta el resto del sitio.
 */
function ppv2_shop_filter_script() {
	// Solo en las páginas que usan el filtro de productos: Home V2 (1555, hoy
	// portada oficial) y Home Tienda (1610). Evita imprimir el script en todo
	// el sitio (blog, checkout, listados…).
	if ( ! is_page( array( 1555, 1610 ) ) && ! is_front_page() ) {
		return;
	}
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
				if (link && link.href) {
					btn.href = link.href;
				} else {
					// Sin enlace a la ficha: neutralizar el href "?add-to-cart=ID"
					// para que "Ver más" no añada el producto al carrito.
					btn.removeAttribute('href');
				}
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
		var cover = document.getElementById('listing-gallery');
		var bento = document.querySelector('.listeo-single-listing-gallery-grid');
		var content = document.querySelector('.col-lg-8.listeo-single-listing-content');
		
		// En móvil: colocar la galería de primero en la página (antes del #titlebar)
		// para lograr una vista full-bleed y orden idéntico al prototipo.
		// === Galería móvil ===
		// Construye el slider móvil reemplazando el contenido del grid bento.
		// Idempotente: si ya existe no hace nada. Se define a nivel del callback (NO
		// dentro del if de ancho) para que el manejador de 'resize' de más abajo
		// también pueda invocarla cuando el viewport pasa a móvil tras la carga.
		function ppv2BuildMobileGallerySlider(gallery) {
			if (!gallery) return;
			if (gallery.classList.contains('ppv2-mobile-slider-container')) return;
				// Buscar TODOS los anchors con la URL de la foto (la fuente más confiable
				// porque siempre apunta a la imagen full-res), y como fallback los <img>.
				// Incluye soporte para lazy-load (data-src, data-original, data-lazy-src).
				var photos = gallery.querySelectorAll('a.slg-gallery-img, a.listeo-gallery-img, .slg-half img, .slg-grid img, img.listeo-gallery-img, .listeo-gallery-img img, .slg-half a, .slg-grid a');
				var photoUrls = [];
				function extractUrl(el) {
					if (!el) return null;
					// Anchors: href apunta a la imagen full-res
					var href = el.getAttribute('href');
					if (href && href !== '#' && /\.(jpe?g|png|webp|gif|avif)(\?.*)?$/i.test(href)) return href;
					// IMG con src, data-src, data-original, data-lazy-src (Listeo + lazy-load plugins)
					var src = el.getAttribute('src') || el.getAttribute('data-src') ||
					          el.getAttribute('data-original') || el.getAttribute('data-lazy-src');
					if (src && src.indexOf('data:image') !== 0) return src;
					// Buscar IMG anidado
					var nested = el.querySelector('img');
					if (nested) return extractUrl(nested);
					// Background-image inline style
					var bg = el.style && el.style.backgroundImage;
					if (bg && bg.indexOf('url(') === 0) {
						return bg.slice(4, -1).replace(/['"]/g, '');
					}
					return null;
				}
				photos.forEach(function(el) {
					var url = extractUrl(el);
					if (url && photoUrls.indexOf(url) === -1) {
						photoUrls.push(url);
					}
				});
				// Log diagnóstico (visible en Safari Web Inspector / Chrome console)
				if (window.console && window.console.log) {
					console.log('[ppv2-mobile-slider] photos detectadas:', photos.length, 'URLs únicas:', photoUrls.length, photoUrls);
				}

				if (photoUrls.length > 1) {
					gallery.innerHTML = '';
					gallery.classList.add('ppv2-mobile-slider-container');

					var track = document.createElement('div');
					track.className = 'ppv2-mobile-slider-track';
					
					photoUrls.forEach(function(url, idx) {
						var slide = document.createElement('div');
						slide.className = 'ppv2-mobile-slider-slide';
						// La misma foto como fondo del slide: el CSS la difumina detrás
						// (::before) para rellenar las franjas cuando la foto es vertical.
						slide.style.backgroundImage = 'url("' + url + '")';
						
						// Envoltura de link para restaurar la ventana modal (popup lightbox)
						var link = document.createElement('a');
						link.href = url;
						link.className = 'slg-gallery-img listeo-gallery-img';
						
						var img = document.createElement('img');
						img.src = url;
						img.alt = 'Imagen ' + (idx + 1);
						
						link.appendChild(img);
						slide.appendChild(link);
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

					// Re-inicializar Magnific Popup si está disponible para restaurar la vista lightbox en móvil
					if (window.jQuery && typeof window.jQuery.fn.magnificPopup === 'function') {
						window.jQuery(gallery).magnificPopup({
							delegate: 'a.slg-gallery-img, a.listeo-gallery-img',
							type: 'image',
							gallery: {
								enabled: true
							}
						});
					}
				}
			}
		// Coloca la galería arriba (full-bleed, antes del #titlebar) y construye el
		// slider. Idempotente: seguro de re-ejecutar en 'resize'.
		function ppv2SetupMobileGallery() {
			var cover = document.getElementById('listing-gallery');
			var bento = document.querySelector('.listeo-single-listing-gallery-grid');
			var titlebar = document.getElementById('titlebar');
			var gallery = bento || cover;
			if (bento) {
				if (cover) {
					cover.style.setProperty('display', 'none', 'important');
				}
				if (titlebar && titlebar.parentNode) {
					if (cover && cover.contains(titlebar)) {
						cover.parentNode.insertBefore(titlebar, cover.nextSibling);
					}
					// Colocamos el grid bento antes del titlebar (lo primero en verse)
					titlebar.parentNode.insertBefore(bento, titlebar);
				}
			} else if (cover && titlebar && cover.contains(titlebar)) {
				cover.parentNode.insertBefore(titlebar, cover.nextSibling);
			}
			if (gallery) { ppv2BuildMobileGallerySlider(gallery); }
		}

		if (window.innerWidth < 768) {
			ppv2SetupMobileGallery();
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

		// Señal "vista del PDP lista": el CSS anti-salto (inline en el head)
		// mantiene invisibles titlebar+galería en móvil hasta este punto, en el
		// que el reordenado y el slider ya quedaron armados → revelar SIN salto.
		document.documentElement.classList.add('ppv2-pdp-lista');

		// Reconstruir la galería móvil si el viewport pasa a < 768px DESPUÉS de la
		// carga (preview que carga en escritorio y muestra en móvil, redimensionar la
		// ventana, rotar el dispositivo). El bloque de arriba sólo corre una vez en la
		// carga; sin esto, en móvil se ve el grid crudo con el botón "Mostrar todas
		// las fotos" en lugar del slider.
		var ppv2GalleryResizeTimer;
		function ppv2MaybeBuildMobileGallery() {
			if (window.innerWidth < 768 && !document.querySelector('.ppv2-mobile-slider-container')) {
				ppv2SetupMobileGallery();
			}
		}
		window.addEventListener('resize', function () {
			clearTimeout(ppv2GalleryResizeTimer);
			ppv2GalleryResizeTimer = setTimeout(ppv2MaybeBuildMobileGallery, 200);
		});
		window.addEventListener('orientationchange', ppv2MaybeBuildMobileGallery);

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

		// Monitorear SOLO la sección de Google (no todo el body) por si sus
		// botones se re-renderizan por AJAX. Si la sección aún no existe,
		// vigilar el body solo hasta que aparezca (máx. 10 s) y acotar entonces.
		var googleObsTarget = document.getElementById('listing-google-reviews');
		var observer = new MutationObserver(function() {
			adjustGoogleReviewButton();
			if (!googleObsTarget) {
				var sec = document.getElementById('listing-google-reviews');
				if (sec) { // apareció por AJAX: re-acotar el observer a la sección
					observer.disconnect();
					googleObsTarget = sec;
					observer.observe(sec, { childList: true, subtree: true });
				}
			}
		});
		if (googleObsTarget) {
			observer.observe(googleObsTarget, { childList: true, subtree: true });
		} else {
			observer.observe(document.body, { childList: true, subtree: true });
			setTimeout(function () {
				if (!googleObsTarget) observer.disconnect();
			}, 10000);
		}

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
				// Ícono de lupa + "+N foto(s)" (la lupa invita a dar clic para abrir la galería).
				var _fotoWord = ( moreCount === 1 ) ? 'foto' : 'fotos';
				span.innerHTML = '<svg class="ppv2-more-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="20" y1="20" x2="16.05" y2="16.05"></line></svg>'
					+ '<span class="ppv2-more-txt">+' + moreCount + ' ' + _fotoWord + '</span>';
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
			// Homologación: si el widget está marcado con `mobileSheet`, en MÓVIL su
			// clic abre el PANEL DESLIZANTE (el mismo bottom-sheet que el botón de la
			// barra inferior) en vez de expandir el formulario inline. En escritorio
			// mantiene el toggle inline normal.
			function onTitleActivate(e) {
				if (labels && labels.mobileSheet && window.matchMedia('(max-width: 767px)').matches) {
					if (e) { e.preventDefault(); e.stopPropagation(); }
					// Panel deslizante INFERIOR (mismo sistema que Reservar / Enviar Mensaje).
					if (window.ppv2OpenSheet) { window.ppv2OpenSheet(labels.sheetType || 'message'); return; }
					var bsfBtn = document.querySelector('.ppv2-bsf-message-btn');
					if (bsfBtn) { bsfBtn.click(); return; }
				}
				// DESKTOP: abrir el cuerpo del widget en el panel deslizante de la derecha
				// (en vez del acordeón en línea). El cuerpo = todo menos el título.
				if (labels && labels.desktopDrawer && window.ppRDrawer && window.matchMedia('(min-width: 768px)').matches) {
					if (e) { e.preventDefault(); e.stopPropagation(); }
					if (!widget._ppDrawerBody) {
						var body = document.createElement('div');
						body.className = 'ppv2-rdrawer-source';
						Array.prototype.slice.call(widget.children).forEach(function (ch) {
							if (ch !== title) { body.appendChild(ch); }
						});
						widget.appendChild(body);
						widget._ppDrawerBody = body;
					}
					window.ppRDrawer.open(widget._ppDrawerBody, (labels && labels.drawerTitle) || textSpan.textContent);
					return;
				}
				toggle();
			}
			title.addEventListener('click', onTitleActivate);
			title.addEventListener('keydown', function(e){
				if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onTitleActivate(e); }
			});
		}
		var msgWidget = document.querySelector('.listeo-single-listing-sidebar .listing-widget.message-vendor');
		var hoursWidget = document.querySelector('.listeo-single-listing-sidebar .listing-widget.opening-hours');
		// Enviar Mensaje: boton outline teal-parche con chevron para indicar
		// claramente la affordance de toggle (abrir/cerrar el componente).
		ppv2MakeWidgetCollapsible(msgWidget, { collapsed: 'Enviar Mensaje', expanded: 'Enviar mensaje', mobileSheet: true, sheetType: 'message', desktopDrawer: true, drawerTitle: 'Enviar Mensaje' });
		// Ver horarios: pill outline gris, CON chevron a la derecha.
		ppv2MakeWidgetCollapsible(hoursWidget, { collapsed: 'Horarios', expanded: 'Horarios', mobileSheet: true, sheetType: 'hours', desktopDrawer: true, drawerTitle: 'Horarios' });

		// Poner "Enviar Mensaje" y "Ver horarios" en la MISMA fila (lado a lado en
		// desktop; el CSS .ppv2-sidebar-actions los hace flex 50/50). En móvil el
		// contenedor es un bloque normal → siguen apilados. Se envuelven ambos widgets.
		if ( msgWidget && hoursWidget && msgWidget.parentNode && ! document.querySelector( '.ppv2-sidebar-actions' ) ) {
			var actionsRow = document.createElement( 'div' );
			actionsRow.className = 'ppv2-sidebar-actions';
			msgWidget.parentNode.insertBefore( actionsRow, msgWidget );
			actionsRow.appendChild( msgWidget );
			actionsRow.appendChild( hoursWidget );
		}

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

		// (El reposicionamiento móvil del favorito a la derecha del título se
		//  maneja en ppv2_listing_fav_position(), con matchMedia, para que
		//  reaccione al cambio de ancho/modo-móvil aunque no se recargue.)
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'ppv2_listing_header_reorder', 100 );

/**
 * Contador de reseñas responsivo: prepara DOS variantes del texto dentro del
 * mismo enlace (que apunta a #listing-google-reviews), para alternarlas por CSS:
 *   - Escritorio: "(92 reseñas)"  (.ppv2-reviews-full)
 *   - Móvil:      "(92)"           (.ppv2-reviews-short)
 * Así en móvil se libera espacio en la fila de estrellas y el corazón (que se
 * queda en flujo al final de esa fila) no tapa el texto. El número se extrae
 * dinámicamente del DOM (no se quema). El enlace conserva su href, por lo que
 * el clic sigue llevando a la sección de reseñas de Google.
 *
 * El corazón ya NO se mueve por JS: vive en .ppv2-meta-top y se posiciona en
 * flujo (CSS: order:99 + margin-left:auto + align-items:center del contenedor),
 * así queda al final de la fila y verticalmente centrado con las estrellas.
 * Prioridad 101: corre después del reorder principal (100).
 */
function ppv2_listing_fav_position() {
	if ( ! is_singular( 'listing' ) ) {
		return;
	}
	?>
	<script>
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
	</script>
	<?php
}
add_action( 'wp_footer', 'ppv2_listing_fav_position', 101 );

/**
 * Bottom Sheet de Reservar (móvil) conectado a la BARRA NATIVA de Listeo.
 *
 * - NO inyecta barra propia: usa la barra nativa `.booking-sticky-footer`
 *   (la que muestra el precio + "Reservar ahora") que Listeo ya pinta en móvil.
 * - Intercepta el clic de su botón "Reservar ahora" (que por defecto hace
 *   scroll a #booking-widget-anchor) y en su lugar abre un panel deslizante
 *   (bottom sheet) que ocupa ~80% de la pantalla.
 * - El sheet TOMA prestado el widget Reservar de la sidebar (mueve el nodo),
 *   así toda la lógica de booking (form, calendarios, AJAX, login) sigue
 *   funcionando sin duplicar HTML ni perder event listeners. Al cerrar, lo
 *   devuelve a su lugar.
 * - Cierre por: tap en backdrop, tap en botón ×, tap en handle, tecla Escape.
 * - Solo móvil: la barra nativa solo existe en móvil y el CSS del sheet está
 *   bajo @media (max-width:767px); el escritorio no se ve afectado.
 */
function ppv2_listing_mobile_bottom_bar() {
	if ( ! is_singular( 'listing' ) ) {
		return;
	}
	?>
	<!-- Bottom Sheet del widget Reservar (mobile-only). La barra es la nativa de Listeo. -->
	<div class="ppv2-bottom-sheet" id="ppv2-reservar-sheet" hidden>
		<div class="ppv2-bottom-sheet__backdrop" data-ppv2-close-sheet></div>
		<div class="ppv2-bottom-sheet__panel" role="dialog" aria-modal="true" aria-labelledby="ppv2-reservar-sheet-title">
			<div class="ppv2-bottom-sheet__handle" data-ppv2-close-sheet aria-hidden="true"></div>
			<div class="ppv2-bottom-sheet__header">
				<h3 id="ppv2-reservar-sheet-title" class="ppv2-bottom-sheet__title">Reservar</h3>
				<button type="button" class="ppv2-bottom-sheet__close" data-ppv2-close-sheet aria-label="Cerrar">×</button>
			</div>
			<div class="ppv2-bottom-sheet__content" id="ppv2-reservar-sheet-content"></div>
		</div>
	</div>

	<script>
	document.addEventListener('DOMContentLoaded', function () {
		// Barra nativa de Listeo: cambiar "Comenzando desde $40.000" -> "desde $40.000".
		// Solo se elimina la palabra "Comenzando"; el precio (dinámico) se conserva.
		// Listeo re-renderiza la sticky footer tarde (en window.load), pisando el
		// cambio; por eso observamos la barra con MutationObserver y re-aplicamos.
		(function () {
			function fixPrice() {
				var el = document.querySelector('.booking-sticky-footer .bsf-left h4');
				if (el && /Comenzando/i.test(el.innerHTML)) {
					el.innerHTML = el.innerHTML.replace(/Comenzando\s+/i, '');
				}
			}
			// Acortar "Reservar ahora" -> "Reservar" (sin tocar posibles iconos hijos).
			function fixReserveText() {
				var a = document.querySelector('.booking-sticky-footer .bsf-right a, .booking-sticky-footer .bsf-right .button');
				if (!a) return;
				for (var i = 0; i < a.childNodes.length; i++) {
					var n = a.childNodes[i];
					if (n.nodeType === 3 && /Reservar\s+ahora/i.test(n.nodeValue)) {
						n.nodeValue = n.nodeValue.replace(/Reservar\s+ahora/i, 'Reservar');
					}
				}
			}
			// Inyectar el botón de Mensaje a la IZQUIERDA de "Reservar". Usa el
			// MISMO icono que el título de la sección Enviar Mensaje (fa-envelope-o,
			// sobre cerrado).
			function injectMessageBtn() {
				var bsfRight = document.querySelector('.booking-sticky-footer .bsf-right');
				if (!bsfRight || bsfRight.querySelector('.ppv2-bsf-message-btn')) return;
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'ppv2-bsf-message-btn';
				btn.setAttribute('aria-label', 'Enviar mensaje');
				btn.innerHTML = '<i class="fa fa-envelope-o" aria-hidden="true"></i>';
				btn.addEventListener('click', function (e) {
					e.preventDefault();
					e.stopImmediatePropagation();
					openSheet('message'); // openSheet está hoisteada en este scope
				});
				bsfRight.insertBefore(btn, bsfRight.firstChild); // izquierda del botón Reservar
			}
			function applyAll() { fixPrice(); fixReserveText(); injectMessageBtn(); }
			function watch(tries) {
				var bar = document.querySelector('.booking-sticky-footer');
				if (bar) {
					applyAll();
					// Re-aplicar si Listeo re-renderiza el contenido de la barra
					// (vuelve a quitar "Comenzando" y re-inyecta el botón de mensaje).
					var obs = new MutationObserver(applyAll);
					obs.observe(bar, { childList: true, subtree: true, characterData: true });
				} else if (tries > 0) {
					setTimeout(function () { watch(tries - 1); }, 100);
				}
			}
			watch(30);
			// Refuerzo: re-aplicar tras window.load (cuando Listeo suele inicializar).
			window.addEventListener('load', function () { setTimeout(applyAll, 100); });
		})();

		var sheet = document.getElementById('ppv2-reservar-sheet');
		var sheetContent = document.getElementById('ppv2-reservar-sheet-content');
		if (!sheet || !sheetContent) return;

		// Estado del sheet: qué widget está dentro, su tipo y posición original.
		// El MISMO panel sirve para "Reservar" y "Enviar Mensaje": al abrir, se
		// mueve el widget nativo correspondiente desde la sidebar; al cerrar, se
		// devuelve a su lugar (restaurando su estado colapsado si lo tenía).
		var currentWidget = null;
		var currentType = null;
		var widgetWasCollapsed = false;
		var originalParent = null;
		var originalNextSibling = null;
		var currentPlaceholder = null;
		var ppv2SheetScrollY = 0; // scroll del body al abrir (se restaura al cerrar)

		function getWidget(type) {
			if (type === 'message') {
				return document.querySelector('.listeo-single-listing-sidebar .listing-widget.message-vendor')
					|| document.querySelector('.listeo-single-listing-sidebar #widget_contact_widget_listeo-3');
			}
			if (type === 'hours') {
				return document.querySelector('.listeo-single-listing-sidebar .listing-widget.opening-hours');
			}
			return document.querySelector('.listeo-single-listing-sidebar .listing-widget.booking-widget')
				|| document.querySelector('.listeo-single-listing-sidebar #widget_booking_listings-3')
				|| document.querySelector('.listeo-single-listing-sidebar .listing-widget.boxed-widget.booking-widget');
		}

		function openSheet(type) {
			type = type || 'booking';
			var widget = getWidget(type);
			if (!widget) {
				console.warn('[ppv2-sheet] widget no encontrado para:', type);
				return;
			}
			// Título del panel según el tipo
			var titleEl = document.getElementById('ppv2-reservar-sheet-title');
			if (titleEl) titleEl.textContent = (type === 'message') ? 'Enviar Mensaje' : (type === 'hours') ? 'Horarios' : 'Reservar';
			// Recordar si el widget estaba colapsado (el de mensaje es colapsable);
			// dentro del sheet lo queremos expandido para ver el formulario.
			widgetWasCollapsed = widget.classList.contains('is-collapsed');
			if (widgetWasCollapsed) widget.classList.remove('is-collapsed');
			// Guardar posición original para restaurar al cerrar
			currentWidget = widget;
			currentType = type;
			originalParent = widget.parentNode;
			originalNextSibling = widget.nextSibling;
			// Placeholder del MISMO alto en el hueco del widget → la fila de botones no
			// se reacomoda al sacarlo (evita el "salto" del otro botón al abrir/cerrar).
			currentPlaceholder = document.createElement('div');
			currentPlaceholder.className = 'ppv2-sheet-placeholder';
			currentPlaceholder.style.height = widget.getBoundingClientRect().height + 'px';
			originalParent.insertBefore(currentPlaceholder, widget);
			// Mover el widget dentro del sheet content
			sheetContent.appendChild(widget);
			// Mostrar el sheet (display:flex) y, en el siguiente frame, añadir
			// .is-open para que el transform animado interpole correctamente.
			sheet.removeAttribute('hidden');
			void sheet.offsetWidth; // reflow
			sheet.classList.add('is-open');
			// Bloqueo de scroll SIN perder la posición: overflow:hidden a secas
			// pierde el scroll en móvil (salto al cerrar). Patrón estándar:
			// body position:fixed (lo pone la clase) desplazado a -scrollY, y al
			// cerrar se restaura el scroll exacto.
			ppv2SheetScrollY = window.scrollY || document.documentElement.scrollTop || 0;
			document.body.style.top = '-' + ppv2SheetScrollY + 'px';
			document.body.classList.add('ppv2-sheet-open');
			// Foco accesible al botón cerrar
			var closeBtn = sheet.querySelector('.ppv2-bottom-sheet__close');
			if (closeBtn) setTimeout(function(){ closeBtn.focus(); }, 320);
		}

		// Exponer la apertura del panel inferior para otros módulos (botón "Horarios").
		window.ppv2OpenSheet = openSheet;

		function closeSheet() {
			sheet.classList.remove('is-open');
			document.body.classList.remove('ppv2-sheet-open');
			// Restaurar el scroll exacto donde estaba al abrir (sin salto).
			document.body.style.top = '';
			window.scrollTo(0, ppv2SheetScrollY || 0);
			// Capturar las referencias LOCALMENTE y limpiar el estado global ya,
			// para evitar una carrera si se abre otro sheet antes de que termine
			// la animación de salida (las variables compartidas no se corromperían).
			var w = currentWidget, op = originalParent, ons = originalNextSibling, wasColl = widgetWasCollapsed, ph = currentPlaceholder;
			currentWidget = null;
			currentType = null;
			originalParent = null;
			originalNextSibling = null;
			widgetWasCollapsed = false;
			currentPlaceholder = null;
			// Esperar a que termine la animación de salida antes de devolver el
			// widget a la sidebar y ocultar el sheet.
			setTimeout(function () {
				if (w && op) {
					// Devolver el widget al hueco exacto del placeholder (sin reflujo).
					if (ph && ph.parentNode) {
						ph.parentNode.insertBefore(w, ph);
						ph.parentNode.removeChild(ph);
					} else if (ons && ons.parentNode === op) {
						op.insertBefore(w, ons);
					} else {
						op.appendChild(w);
					}
					// Restaurar su estado colapsado original (p.ej. el de mensaje
					// vuelve a su pill colapsada en la sidebar).
					if (wasColl) w.classList.add('is-collapsed');
				} else if (ph && ph.parentNode) {
					ph.parentNode.removeChild(ph);
				}
				// Solo ocultar el sheet si no se reabrió mientras tanto.
				if (!sheet.classList.contains('is-open')) {
					sheet.setAttribute('hidden', '');
				}
			}, 340); // un poco más que la duración de la transición (320ms)
		}

		// Interceptar el botón "Reservar ahora" de la BARRA NATIVA de Listeo
		// (.booking-sticky-footer). Por defecto ese enlace hace scroll suave a
		// #booking-widget-anchor; lo reemplazamos por abrir el panel deslizante.
		// Delegación en FASE DE CAPTURA para correr ANTES del handler de scroll
		// de Listeo y poder cancelarlo (stopImmediatePropagation). Funciona aunque
		// la barra se pinte/recargue después del DOMContentLoaded.
		// Booking Plus: la reserva ya NO es el viejo widget inline de Listeo Core
		// (que se movía a un bottom-sheet). Ahora es un botón que abre el modal
		// propio de Booking Plus (#lbp-booking-modal) o, si el usuario NO está
		// logueado, el diálogo de acceso (#sign-in-dialog) / login. El botón
		// "Reservar" de la barra inferior debe reproducir EXACTAMENTE esa acción,
		// así que disparamos por código el botón REAL de Booking Plus de la barra
		// lateral. Así heredamos sin duplicar nada el comportamiento correcto en
		// ambos estados (logueado abre el modal de reserva; no logueado pide login).
		// El binding de Booking Plus es delegado en document, así que un .click()
		// programático lo activa igual que un clic real del usuario.
		function triggerNativeBooking() {
			var realBtn = document.querySelector('.lbp-book-now-btn, .book-now-notloggedin');
			if (realBtn) { realBtn.click(); return true; }
			return false;
		}

		var NATIVE_BAR_SEL = '.booking-sticky-footer a, .booking-sticky-footer .button, [data-ppv2-open-reservar]';
		document.addEventListener('click', function (e) {
			var trigger = e.target.closest(NATIVE_BAR_SEL);
			if (!trigger) return;
			// Solo en móvil (la barra nativa solo aparece en móvil, pero por
			// seguridad respetamos el breakpoint del sheet).
			if (!window.matchMedia('(max-width: 767px)').matches) return;
			e.preventDefault();
			e.stopImmediatePropagation();
			// Disparar el botón real de Booking Plus (modal o login según sesión).
			// Fallback al sheet antiguo solo si no existe el botón (listados sin
			// Booking Plus), para no dejar el botón "Reservar" sin acción.
			if (!triggerNativeBooking()) {
				openSheet('booking');
			}
		}, true); // true = captura

		// Click en backdrop, handle o botón × cierran el sheet
		var closeTriggers = sheet.querySelectorAll('[data-ppv2-close-sheet]');
		closeTriggers.forEach(function (el) {
			el.addEventListener('click', function (e) {
				e.preventDefault();
				closeSheet();
			});
		});

		// Tecla Escape cierra el sheet
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && sheet.classList.contains('is-open')) {
				closeSheet();
			}
		});
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'ppv2_listing_mobile_bottom_bar', 110 );

/**
 * Panel de login (#sign-in-dialog) en móvil: cuando se muestra como hoja inferior
 * con scroll interno, el mensaje de respuesta (.notification) del formulario de
 * login/registro vive AL FINAL del formulario, por debajo del borde visible del
 * panel. Al enviar, el usuario no veía el "Enviando…/error/éxito" y parecía que el
 * botón no hacía nada. Este script desplaza ese mensaje a la vista en cuanto
 * aparece (se vuelve visible o cambia su texto). Solo móvil; no toca la lógica.
 */
function ppv2_signin_feedback_into_view() {
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		if (!window.matchMedia || !window.matchMedia('(max-width: 768px)').matches) return;

		function isShown(el) {
			return el && el.offsetParent !== null &&
				(el.textContent || '').trim().length > 0;
		}
		function bringIntoView(el) {
			if (!isShown(el)) return;
			try {
				el.scrollIntoView({ block: 'center', behavior: 'smooth' });
			} catch (e) {
				el.scrollIntoView();
			}
		}

		// Observa el diálogo de acceso; cuando aparece una .notification dentro de
		// los formularios de login/registro (o cambia), la lleva a la vista.
		function watchDialog(dialog) {
			if (!dialog || dialog.__ppv2FeedbackWatched) return;
			dialog.__ppv2FeedbackWatched = true;
			var obs = new MutationObserver(function () {
				var notes = dialog.querySelectorAll('form#login .notification, form#register .notification');
				for (var i = 0; i < notes.length; i++) {
					if (isShown(notes[i])) { bringIntoView(notes[i]); break; }
				}
			});
			obs.observe(dialog, { attributes: true, childList: true, subtree: true, characterData: true });
		}

		// El diálogo se mueve dentro de .mfp-content al abrirse (Magnific inline).
		// Observamos el body para engancharlo cuando aparezca.
		var existing = document.getElementById('sign-in-dialog');
		if (existing) watchDialog(existing);
		var bodyObs = new MutationObserver(function () {
			var d = document.querySelector('.mfp-content #sign-in-dialog') || document.getElementById('sign-in-dialog');
			if (d) watchDialog(d);
		});
		bodyObs.observe(document.body, { childList: true, subtree: true });
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'ppv2_signin_feedback_into_view', 111 );

/**
 * Header del Panel de Control: ícono de hamburguesa IGUAL al del Home.
 *
 * El botón del dashboard (.mmenu-trigger) ya existe y ya abre el menú
 * lateral (off-canvas) en cualquier ancho, pero dibuja su ícono con otra
 * librería (.hamburger-inner). Para que se vea idéntico al Home, le
 * cambiamos el contenido por el MISMO markup del Home (.hmb-ico-wrap >
 * .hmb-ico), cuyo estilo ya provee el tema padre globalmente.
 * La caja del botón la replicamos por CSS en style.css.
 * A prueba de actualizaciones (no tocamos plugin ni tema padre).
 */
function ppv2_dashboard_hamburger_icon() {
	?>
	<script>
	(function () {
		function run() {
			var t = document.querySelector('#header-container.dashboard .mmenu-trigger');
			if (!t || t.querySelector('.hmb-ico')) return;
			t.innerHTML = '<div class="hmb-ico-wrap"><span class="hmb-ico"></span></div>';
		}
		if (document.readyState !== 'loading') { run(); }
		else { document.addEventListener('DOMContentLoaded', run); }
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'ppv2_dashboard_hamburger_icon', 123 );

/**
 * Listado individual (MÓVIL): rediseño del bloque de Contacto + Redes + CTA.
 *
 * Listeo genera .listing-links-container con:
 *   - ul.contact-links  → Celular / Correo / Sitio web
 *   - <p> con a.sign-in → mensaje cuando NO hay sesión
 *   - ul.listing-links  → redes (a.listing-links-fb / -ig / -whatsapp / -tiktok…)
 *
 * Aquí reestructuramos ese marcado (en todos los anchos) para que coincida con
 * los diseños (detalle_contacto.html / detalle_quiere_ver_mas_detalles.html):
 * círculo de ícono + etiqueta + valor + chevron en cada contacto, encabezados,
 * ícono "abrir" en cada red, y la tarjeta oscura "¿Quieres ver más detalles?".
 * La DISTRIBUCIÓN la decide style.css: móvil = apilado; escritorio = en fila.
 * El estilo lo pone style.css. NO se sobrescribe plantilla ni se toca el plugin.
 */
function ppv2_listing_contact_redesign() {
	if ( ! is_singular( 'listing' ) ) {
		return;
	}
	?>
	<script>
	(function () {
		function run() {
			var box = document.querySelector('.single-listing .listing-links-container');
			if (!box || box.dataset.ppv2Ct) return;

			/* 1) Filas de CONTACTO (Celular / Correo / Sitio web) */
			var cu = box.querySelector('ul.contact-links');
			if (cu) {
				var h = document.createElement('h3');
				h.className = 'ppv2-ct-h';
				h.textContent = 'Información de Contacto';
				cu.parentNode.insertBefore(h, cu);
				cu.querySelectorAll('li > a').forEach(function (a) {
					var ic = a.querySelector('i');
					var icCls = ic ? ic.className : '';
					var label = /fa-phone/.test(icCls) ? 'Celular'
						: (/envelope/.test(icCls) ? 'Correo' : 'Sitio web');
					var val = a.textContent.trim();
					a.innerHTML =
						'<span class="ppv2-ct-ico">' + (ic ? ic.outerHTML : '') + '</span>' +
						'<span class="ppv2-ct-body">' +
							'<span class="ppv2-ct-label">' + label + '</span>' +
							'<span class="ppv2-ct-value"></span>' +
						'</span>' +
						'<i class="ppv2-ct-chev fa fa-angle-right" aria-hidden="true"></i>';
					a.querySelector('.ppv2-ct-value').textContent = val; // texto seguro
				});
			}

			/* 2) Botones de REDES */
			var su = null;
			box.querySelectorAll('ul.listing-links').forEach(function (u) {
				if (!u.classList.contains('contact-links')) su = u;
			});
			if (su) {
				var hs = document.createElement('h3');
				hs.className = 'ppv2-ct-h ppv2-ct-h-social';
				hs.textContent = 'Encuéntranos en redes';
				su.parentNode.insertBefore(hs, su);
				su.querySelectorAll('li > a').forEach(function (a) {
					if (a.querySelector('.ppv2-soc-launch')) return;
					var l = document.createElement('i');
					l.className = 'ppv2-soc-launch fa fa-external-link';
					l.setAttribute('aria-hidden', 'true');
					a.appendChild(l);
				});
			}

			/* 3) Tarjeta CTA "¿Quieres ver más detalles?" (estado SIN sesión) */
			var p = box.querySelector('p');
			var sign = p && p.querySelector('a.sign-in');
			if (sign) {
				p.classList.add('ppv2-ct-cta');
				var ico = document.createElement('span');
				ico.className = 'ppv2-cta-ico';
				ico.innerHTML = '<i class="fa fa-lock" aria-hidden="true"></i>';
				var body = document.createElement('span');
				body.className = 'ppv2-cta-body';
				var title = document.createElement('strong');
				title.className = 'ppv2-cta-title';
				title.textContent = '¿Quieres ver más detalles?';
				var txt = document.createElement('span');
				txt.className = 'ppv2-cta-text';
				sign.textContent = 'inicia sesión';
				txt.appendChild(document.createTextNode('Por favor '));
				txt.appendChild(sign); // mueve el enlace original → conserva su funcionalidad (popup)
				txt.appendChild(document.createTextNode(' para acceder a la información de contacto y agendar una cita.'));
				body.appendChild(title);
				body.appendChild(txt);
				p.innerHTML = '';
				p.appendChild(ico);
				p.appendChild(body);
			}

			box.dataset.ppv2Ct = '1';
		}
		if (document.readyState !== 'loading') { run(); }
		else { document.addEventListener('DOMContentLoaded', run); }
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'ppv2_listing_contact_redesign', 124 );

/**
 * Tarjetas de resultados (.listing-card-nl): habilitar SWIPE táctil en el
 * carrusel de imágenes.
 *
 * El slider de la tarjeta es propio del tema (js/custom.js) y SOLO escucha
 * clics en las flechas #nextBtn / #prevBtn; no tiene gestos táctiles, por eso
 * en móvil se puede pasar con las flechas pero no con el dedo.
 * Aquí detectamos el swipe horizontal y disparamos esas mismas flechas, así
 * reutilizamos su lógica sin tocar el tema. Se re-aplica tras cargas AJAX
 * (filtros/paginación) usando el evento "ajaxContentLoaded" del propio tema.
 */
function ppv2_listing_card_swipe() {
	?>
	<script>
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
	</script>
	<?php
}
add_action( 'wp_footer', 'ppv2_listing_card_swipe', 125 );

/**
 * Tarjetas de resultados: limitar las ETIQUETAS de servicios a 2 líneas.
 *
 * El CSS convierte los íconos (.amenity-icon-nl) en etiquetas. Para que la
 * tarjeta no crezca de alto, aquí mostramos solo las etiquetas que caben en
 * 2 líneas y, si sobran, añadimos una pill "…" al final (.ppv2-amen-more),
 * ocultando el resto. Se recalcula al cambiar el ancho (resize) y tras cargas
 * AJAX (filtros/paginación).
 */
function ppv2_card_amenities_clamp() {
	?>
	<script>
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
	</script>
	<?php
}
add_action( 'wp_footer', 'ppv2_card_amenities_clamp', 126 );

/* ==========================================================================
 * MINICART V2 — Controles por producto (cantidad y eliminar), estilo Éxito
 * --------------------------------------------------------------------------
 * Todo vive en el tema hijo. Reutiliza las clases y hooks de WooCommerce/Listeo,
 * así que una actualización del tema padre no lo borra. Se compone de:
 *   1) pp_minicart_render_inner()  -> dibuja el interior del minicart con los
 *      controles (–, input, +, papelera) y el `data-cart_item_key` por producto.
 *   2) Reemplazo del fragmento AJAX del tema padre para que, al actualizar el
 *      carrito, WooCommerce vuelva a dibujar el minicart CON los controles.
 *   3) Manejador AJAX que cambia la cantidad o elimina la línea del carrito.
 * La plantilla de carga inicial se sobreescribe en inc/mini-cart.php (tema hijo).
 * ========================================================================== */

/**
 * Icono SVG de papelera (sin depender de fuentes de iconos del tema).
 */
if ( ! function_exists( 'pp_minicart_trash_svg' ) ) {
	function pp_minicart_trash_svg() {
		return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>';
	}
}

/**
 * Dibuja el INTERIOR del minicart (lo que va dentro de div.listeo-mini-cart).
 * Se usa tanto en la carga inicial (plantilla) como en el refresco AJAX (fragmento),
 * garantizando que los controles aparezcan siempre.
 */
if ( ! function_exists( 'pp_minicart_render_inner' ) ) {
	function pp_minicart_render_inner() {
		if ( ! function_exists( 'WC' ) || ! WC() || ! property_exists( WC(), 'cart' ) || is_null( WC()->cart ) ) {
			return;
		}

		do_action( 'woocommerce_before_mini_cart' );

		if ( ! WC()->cart->is_empty() ) : ?>
			<ul class="woocommerce-mini-cart cart_list cart-list product_list_widget">
				<?php
				do_action( 'woocommerce_before_mini_cart_contents' );

				foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
					$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
					$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

					if ( ! ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_widget_cart_item_visible', true, $cart_item, $cart_item_key ) ) ) {
						continue;
					}

					$product_name      = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
					$thumbnail         = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key );
					$product_price     = apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key );
					$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
					$qty               = (int) $cart_item['quantity'];
					$li_class          = esc_attr( apply_filters( 'woocommerce_mini_cart_item_class', 'mini_cart_item', $cart_item, $cart_item_key ) );
					?>
					<li class="woocommerce-mini-cart-item pp-mini-cart-item <?php echo $li_class; ?>" data-cart_item_key="<?php echo esc_attr( $cart_item_key ); ?>">

						<button type="button" class="pp-item-remove" data-cart_item_key="<?php echo esc_attr( $cart_item_key ); ?>" aria-label="Quitar producto del carrito" title="Quitar producto">
							<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
						</button>

						<?php if ( empty( $product_permalink ) ) : ?>
							<span class="pp-item-thumb"><?php echo $thumbnail; // phpcs:ignore ?></span>
						<?php else : ?>
							<a class="pp-item-thumb" href="<?php echo esc_url( $product_permalink ); ?>"><?php echo $thumbnail; // phpcs:ignore ?></a>
						<?php endif; ?>

						<div class="pp-item-body">
							<?php if ( empty( $product_permalink ) ) : ?>
								<span class="mini-cart-product-name"><span class="mini-cart-product-price"><?php echo wp_kses_post( $product_name ); ?></span></span>
							<?php else : ?>
								<a class="mini-cart-product-name" href="<?php echo esc_url( $product_permalink ); ?>"><span class="mini-cart-product-price"><?php echo wp_kses_post( $product_name ); ?></span></a>
							<?php endif; ?>

							<span class="mini-cart-quantity"><?php echo $product_price; // phpcs:ignore ?></span>

							<div class="pp-item-controls">
								<div class="pp-qty" data-cart_item_key="<?php echo esc_attr( $cart_item_key ); ?>">
									<?php if ( $qty <= 1 ) : // Con 1 unidad: el botón izquierdo es la papelera (eliminar). ?>
										<button type="button" class="pp-qty-btn pp-remove" data-cart_item_key="<?php echo esc_attr( $cart_item_key ); ?>" aria-label="Eliminar producto"><?php echo pp_minicart_trash_svg(); // phpcs:ignore ?></button>
									<?php else : // Con 2 o más: el botón izquierdo es el menos (disminuir). ?>
										<button type="button" class="pp-qty-btn pp-qty-minus" aria-label="Disminuir cantidad">&minus;</button>
									<?php endif; ?>
									<input type="number" class="pp-qty-input" value="<?php echo esc_attr( $qty ); ?>" min="0" step="1" inputmode="numeric" aria-label="Cantidad">
									<button type="button" class="pp-qty-btn pp-qty-plus" aria-label="Aumentar cantidad">&plus;</button>
								</div>
							</div>
						</div>

					</li>
					<?php
				}

				do_action( 'woocommerce_mini_cart_contents' );
				?>
			</ul>

			<p class="woocommerce-mini-cart__total total">
				<?php do_action( 'woocommerce_widget_shopping_cart_total' ); ?>
			</p>

			<?php do_action( 'woocommerce_widget_shopping_cart_before_buttons' ); ?>

			<p class="woocommerce-mini-cart__buttons buttons"><?php do_action( 'woocommerce_widget_shopping_cart_buttons' ); ?></p>

			<?php do_action( 'woocommerce_widget_shopping_cart_after_buttons' ); ?>

		<?php else : ?>

			<p class="woocommerce-mini-cart__empty-message"><?php esc_html_e( 'No hay productos en el carrito.', 'listeo-child' ); ?></p>

		<?php endif;

		do_action( 'woocommerce_after_mini_cart' );
	}
}

/**
 * Sustituye el fragmento AJAX del contenido del minicart (definido en el tema
 * padre) por el nuestro, para que el refresco por AJAX incluya los controles.
 * Se hace en 'init' porque el padre registra su filtro al cargar el tema.
 */
add_action( 'init', 'pp_minicart_swap_content_fragment', 20 );
function pp_minicart_swap_content_fragment() {
	remove_filter( 'woocommerce_add_to_cart_fragments', 'woocommerce_header_add_to_cart_content_fragment' );
	add_filter( 'woocommerce_add_to_cart_fragments', 'pp_minicart_content_fragment' );
}
function pp_minicart_content_fragment( $fragments ) {
	ob_start();
	echo '<div class="listeo-mini-cart">';
	pp_minicart_render_inner();
	echo '</div>';
	$fragments['div.listeo-mini-cart'] = ob_get_clean();
	return $fragments;
}

/**
 * AJAX: cambia la cantidad de una línea del carrito (o la elimina si qty <= 0).
 * El cliente, tras esto, dispara 'wc_fragment_refresh' para redibujar el minicart.
 */
add_action( 'wp_ajax_pp_update_cart_qty', 'pp_update_cart_qty' );
add_action( 'wp_ajax_nopriv_pp_update_cart_qty', 'pp_update_cart_qty' );
add_action( 'wc_ajax_pp_update_cart_qty', 'pp_update_cart_qty' ); // ruta rápida (?wc-ajax=)
function pp_update_cart_qty() {
	check_ajax_referer( 'pp_minicart', 'nonce' );

	if ( ! function_exists( 'WC' ) || ! WC() || is_null( WC()->cart ) ) {
		wp_send_json_error( array( 'msg' => 'no-cart' ) );
	}

	$key = isset( $_POST['cart_item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ) ) : '';
	$qty = isset( $_POST['quantity'] ) ? (int) $_POST['quantity'] : 0;

	if ( '' === $key || ! WC()->cart->get_cart_item( $key ) ) {
		wp_send_json_error( array( 'msg' => 'bad-key' ) );
	}

	// Cambiamos la cantidad sin recalcular dentro de set_quantity (refresh=false)
	// y recalculamos UNA sola vez al final, para no duplicar el cálculo del carrito.
	if ( $qty <= 0 ) {
		WC()->cart->remove_cart_item( $key );
	} else {
		WC()->cart->set_quantity( $key, $qty, false );
	}

	WC()->cart->calculate_totals();

	// Devolvemos los fragmentos de WooCommerce (lista, subtotal, badge) para que el
	// JS actualice el minicart al instante. No dependemos de wc-cart-fragments.js,
	// que este sitio no carga (el "añadir al carrito" no es por AJAX).
	wp_send_json_success( array(
		'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', array() ),
		'cart_hash' => WC()->cart->get_cart_hash(),
		'count'     => WC()->cart->get_cart_contents_count(),
	) );
}


/* =========================================================================
   PPV2 — BUSCADOR DEL HEADER UNIFICADO (Directorio + Tienda)
   El buscador de Listeo solo sugiere listados (post_type "listing").
   Este bloque lo reemplaza por un autocompletado propio que sugiere
   Listados Y Productos WooCommerce, agrupados, con foto y precio.
   Estilos: bloque "PPV2 BUSCADOR UNIFICADO" al final de style.css.
   ========================================================================= */

// Endpoint AJAX: devuelve sugerencias de listados y productos para un término.
add_action( 'wp_ajax_ppv2_header_suggest', 'ppv2_header_suggest' );
add_action( 'wp_ajax_nopriv_ppv2_header_suggest', 'ppv2_header_suggest' );
function ppv2_header_suggest() {
	$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
	$len  = function_exists( 'mb_strlen' ) ? mb_strlen( $term, 'UTF-8' ) : strlen( $term );
	if ( $len < 2 ) {
		wp_send_json( array() );
	}

	// Tipo de listado activo en el buscador (chip "+" del plugin Personalización
	// Parche): si viene y es un tipo separado válido, las sugerencias del
	// directorio se limitan a ese tipo (p. ej. con "Mascotas perdidas" activa,
	// no se sugieren listados de Adopción ni de Directorio).
	$tipo      = isset( $_GET['listing_type'] ) ? sanitize_key( wp_unslash( $_GET['listing_type'] ) ) : '';
	$separados = function_exists( 'pp_listados_tipos_separados' ) ? pp_listados_tipos_separados() : array();
	if ( $tipo && ! in_array( $tipo, $separados, true ) ) {
		$tipo = '';
	}

	// Nombres visibles de los tipos separados: cada sugerencia se cataloga
	// bajo su tipo real (Adopción / Mascotas perdidas), no todo "Directorio".
	$nombres_tipo = array();
	if ( $separados && function_exists( 'pp_listados_tipos_activos' ) ) {
		foreach ( pp_listados_tipos_activos() as $t ) {
			if ( in_array( $t->slug, $separados, true ) && ! empty( $t->name ) ) {
				$nombres_tipo[ $t->slug ] = $t->name;
			}
		}
	}

	// Caché por término + tipo (10 min): los términos repetidos —los más comunes
	// con tráfico real— responden sin consultar la base de datos. Se invalida
	// solo al expirar; 10 min de desfase máximo en sugerencias es aceptable.
	$term_key  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $term, 'UTF-8' ) : strtolower( $term );
	$cache_key = 'ppv2_suggest_' . md5( $term_key . '|' . $tipo );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached && is_array( $cached ) ) {
		wp_send_json( $cached );
	}

	// El orden según el tab activo lo aplica el navegador (sin re-consultar al servidor).
	$listing_items = array();
	$product_items = array();

	// --- Listados (directorio / adopción / mascotas perdidas) ---
	// Regla de las sugerencias: el DIRECTORIO es la base y SIEMPRE acompaña.
	// Con un tipo separado elegido, sus resultados van PRIMERO (prioridad) y
	// además se muestra el Directorio debajo; se excluyen los OTROS tipos
	// separados. Sin tipo elegido, se muestran todos agrupados.
	if ( $tipo ) {
		// 1) El tipo elegido (prioridad).
		$sel_posts = get_posts( array(
			's'              => $term,
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			'meta_query'     => array(
				array( 'key' => '_listing_type', 'value' => $tipo, 'compare' => '=' ),
			),
		) );
		// 2) Base = Directorio y listados sin tipo; excluye TODOS los separados.
		$base_posts = get_posts( array(
			's'              => $term,
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'posts_per_page' => 4,
			'meta_query'     => array(
				'relation' => 'OR',
				array( 'key' => '_listing_type', 'value' => $separados, 'compare' => 'NOT IN' ),
				array( 'key' => '_listing_type', 'compare' => 'NOT EXISTS' ),
			),
		) );
		$listings = array_merge( $sel_posts, $base_posts );
	} else {
		$listings = get_posts( array(
			's'              => $term,
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'posts_per_page' => 5,
		) );
	}

	// Agrupar por tipo real para que cada uno salga bajo su encabezado
	// (y los encabezados no se repitan al intercalarse).
	$por_grupo = array();
	foreach ( $listings as $p ) {
		$tipo_item = get_post_meta( $p->ID, '_listing_type', true );
		$grupo     = isset( $nombres_tipo[ $tipo_item ] ) ? $nombres_tipo[ $tipo_item ] : 'Directorio';
		$por_grupo[ $grupo ][] = array(
			'label' => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
			'value' => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
			'link'  => get_permalink( $p ),
			'group' => $grupo,
			'img'   => get_the_post_thumbnail_url( $p, 'thumbnail' ),
			'meta'  => '',
		);
	}
	// Orden de grupos: el tipo elegido primero (si hay), luego Directorio,
	// luego el resto (solo aplica cuando no hay tipo elegido).
	$orden_grupos = array();
	if ( $tipo && isset( $nombres_tipo[ $tipo ] ) ) {
		$orden_grupos[] = $nombres_tipo[ $tipo ];
	}
	$orden_grupos[] = 'Directorio';
	foreach ( $nombres_tipo as $nombre_grupo ) {
		if ( ! in_array( $nombre_grupo, $orden_grupos, true ) ) {
			$orden_grupos[] = $nombre_grupo;
		}
	}
	foreach ( $orden_grupos as $g ) {
		if ( isset( $por_grupo[ $g ] ) ) {
			$listing_items = array_merge( $listing_items, $por_grupo[ $g ] );
		}
	}
	// Cap para no alargar el desplegable (el tipo elegido ya va primero).
	if ( count( $listing_items ) > 6 ) {
		$listing_items = array_slice( $listing_items, 0, 6 );
	}

	// --- Productos (tienda WooCommerce) ---
	$products = get_posts( array(
		's'              => $term,
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 5,
		// Respeta la visibilidad de catálogo: no sugerir productos ocultos de la búsqueda.
		'tax_query'      => array(
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array( 'exclude-from-search' ),
				'operator' => 'NOT IN',
			),
		),
	) );
	foreach ( $products as $p ) {
		$price = '';
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $p->ID );
			if ( $product && '' !== $product->get_price() ) {
				$price = html_entity_decode( wp_strip_all_tags( wc_price( $product->get_price() ) ), ENT_QUOTES, 'UTF-8' );
			}
		}
		$product_items[] = array(
			'label' => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
			'value' => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
			'link'  => get_permalink( $p ),
			'group' => 'Tienda',
			'img'   => get_the_post_thumbnail_url( $p, 'thumbnail' ),
			'meta'  => $price,
		);
	}

	$suggestions = array_merge( $listing_items, $product_items );
	set_transient( $cache_key, $suggestions, 10 * MINUTE_IN_SECONDS );
	wp_send_json( $suggestions );
}

// Garantiza que la librería de autocompletado esté cargada aunque Listeo
// tenga desactivada su opción de autocompletado.
add_action( 'wp_enqueue_scripts', 'ppv2_header_suggest_assets' );
function ppv2_header_suggest_assets() {
	if ( is_admin() ) {
		return;
	}
	wp_enqueue_script( 'jquery-ui-autocomplete' );
}

// JS del buscador. Se imprime en prioridad 50 para ejecutarse DESPUÉS del
// script de Listeo (prioridad 11) y así reemplazar su autocompletado.
add_action( 'wp_print_footer_scripts', 'ppv2_header_suggest_js', 50 );
function ppv2_header_suggest_js() {
	?>
	<script type="text/javascript">
	(function($) {
		if (!$ || !$.fn || typeof $.fn.autocomplete === 'undefined') { return; }
		$(function() {
			var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var homeUrl = <?php echo wp_json_encode( home_url( '/' ) ); ?>;
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
	</script>
	<?php
}

// El chat de creación de listados ([ppv2_chat_listado]) vive ahora en el
// plugin propio pp-chat-listados (wp-content/plugins/pp-chat-listados/),
// migrado desde este tema el 2026-07-03.

// --- Nombres de los tipos de cuenta en el registro -------------------------
// Listeo llama a los roles "Guest" y "Owner"; en Parche Peludo son
// "Usuario" y "Prestador de servicio". Se renombran vía gettext para no
// tocar el plugin. Solo en el front: en wp-admin se conservan los nombres
// de Listeo para no confundir la administración de roles.
add_filter( 'gettext_with_context', 'ppv2_role_label_guest', 20, 4 );
function ppv2_role_label_guest( $translated, $text, $context, $domain ) {
	if ( 'listeo_core' === $domain && 'User role' === $context && 'Guest' === $text && ! is_admin() ) {
		return 'Usuario';
	}
	return $translated;
}
add_filter( 'gettext', 'ppv2_role_label_owner', 20, 3 );
function ppv2_role_label_owner( $translated, $text, $domain ) {
	if ( 'listeo_core' === $domain && 'Owner' === $text && ! is_admin() ) {
		return 'Negocio/Servicios';
	}
	return $translated;
}

// --- Español para cadenas de Listeo Booking Plus sin traducir --------------
// El popup de reserva (paso final, resumen) trae textos en inglés que la
// traducción oficial del plugin aún no cubre. Filtro gettext del dominio del
// plugin: no toca el plugin y sobrevive a sus actualizaciones.
add_filter( 'gettext_listeo-booking-plus', 'ppv2_traducir_booking_plus', 20, 2 );
function ppv2_traducir_booking_plus( $translated, $text ) {
	$mapa = array(
		'Your booking request has been submitted and is awaiting approval.' => 'Tu solicitud de reserva fue enviada y está pendiente de aprobación.',
		'Thank you for your booking!'                  => '¡Gracias por tu reserva!',
		'Booking Confirmed'                            => 'Reserva confirmada',
		'Booking confirmed! Redirecting to payment...' => '¡Reserva confirmada! Redirigiendo al pago…',
	);
	return isset( $mapa[ $text ] ) ? $mapa[ $text ] : $translated;
}

// --- Mensajes de validación HTML5 del navegador en español -----------------
// El globo nativo ("Please fill out this field.") sale en el idioma del
// navegador del visitante. Con setCustomValidity forzamos español en TODOS
// los formularios del sitio (registro, reservas, mascotas, contacto).
add_action( 'wp_footer', 'ppv2_validacion_html5_es', 130 );
function ppv2_validacion_html5_es() {
	if ( is_admin() ) {
		return;
	}
	?>
	<script id="ppv2-validacion-es">
	(function () {
		function mensaje(el) {
			var v = el.validity;
			if (!v || v.valid) { return ''; }
			if (v.valueMissing) { return 'Por favor completa este campo.'; }
			if (v.typeMismatch && el.type === 'email') { return 'Ingresa un correo electrónico válido.'; }
			if (v.typeMismatch && el.type === 'url') { return 'Ingresa una dirección web válida.'; }
			if (v.typeMismatch) { return 'El formato no es válido.'; }
			if (v.patternMismatch) { return 'El formato no coincide con el solicitado.'; }
			if (v.tooShort) { return 'Escribe al menos ' + el.minLength + ' caracteres.'; }
			if (v.tooLong) { return 'El texto es demasiado largo.'; }
			if (v.rangeUnderflow) { return 'El valor mínimo es ' + el.min + '.'; }
			if (v.rangeOverflow) { return 'El valor máximo es ' + el.max + '.'; }
			if (v.stepMismatch) { return 'Ingresa un valor válido.'; }
			if (v.badInput) { return 'Ingresa un valor válido.'; }
			return 'Ingresa un valor válido.';
		}
		// Al fallar la validación: mensaje en español.
		document.addEventListener('invalid', function (e) {
			var el = e.target;
			if (el && el.setCustomValidity) { el.setCustomValidity(mensaje(el)); }
		}, true);
		// Al escribir/cambiar: limpiar el mensaje para no bloquear el reenvío.
		['input', 'change'].forEach(function (ev) {
			document.addEventListener(ev, function (e) {
				var el = e.target;
				if (el && el.setCustomValidity) { el.setCustomValidity(''); }
			}, true);
		});
	})();
	</script>
	<?php
}

// === Listados por rol: guests publican Adopción y Mascotas perdidas ========
// MIGRADO al plugin Personalización Parche (módulo Listados,
// pp-personalizacion/includes/listados.php) el 2026-07-05. Allí viven los
// helpers pp_tipos_listado_guest()/pp_tipo_listado_permitido_para_rol()/etc.,
// el candado del guardado, el menú del dashboard para guests y las
// plantillas sobrescritas (antes en listeo-core/ de este tema).

/* =========================================================================
 * Pestaña "Ficha técnica" en la página de producto (WooCommerce)
 * La ficha se guarda por producto en el meta `_pp_ficha_tecnica` (HTML de tabla,
 * generado por el publicador de catálogo). Si el producto no tiene ficha, la
 * pestaña no aparece. Relacionado con el catálogo automático desde Laika.
 * ========================================================================= */
add_filter( 'woocommerce_product_tabs', 'pp_tab_ficha_tecnica' );
function pp_tab_ficha_tecnica( $tabs ) {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return $tabs;
	}
	$ficha = get_post_meta( $product->get_id(), '_pp_ficha_tecnica', true );
	if ( ! empty( $ficha ) ) {
		$tabs['pp_ficha_tecnica'] = array(
			'title'    => __( 'Ficha técnica', 'listeo-child' ),
			'priority' => 15, // entre Descripción (10) e Información adicional (20)
			'callback' => 'pp_tab_ficha_tecnica_contenido',
		);
	}
	return $tabs;
}
function pp_tab_ficha_tecnica_contenido() {
	global $product;
	$ficha = get_post_meta( $product->get_id(), '_pp_ficha_tecnica', true );
	echo '<div class="pp-ficha-tecnica-wrap">' . wp_kses_post( $ficha ) . '</div>';
}

/* =========================================================================
 * Filtros de tienda: se usa el sistema NATIVO de WooCommerce (widgets
 * "Filtrar productos por atributo" en el sidebar de la tienda, params
 * filter_<atributo>). Los atributos globales pa_marca / pa_especie /
 * pa_etapa-de-vida / pa_tipo-de-alimento / pa_peso los asigna el publicador
 * del catálogo (Laika). No hay código de filtrado propio.
 * ========================================================================= */

/* =========================================================================
 * CHECKOUT — Departamento antes que Ciudad + Ciudad como desplegable
 * =========================================================================
 * El checkout es el de BLOQUES de WooCommerce (React), así que los filtros
 * clásicos de campos no aplican. Se usan los dos mecanismos que el bloque SÍ
 * respeta:
 *
 * 1) Orden: prioridades del "locale" del país (woocommerce_get_country_locale).
 *    Departamento (state) pasa a prioridad 65, delante de Ciudad (70, la de
 *    fábrica). Aplica igual al checkout clásico y a Mi Cuenta → Direcciones,
 *    lo cual mantiene los formularios coherentes entre sí.
 *
 * 2) Ciudad desplegable: JS que superpone un <select> dependiente del
 *    departamento sobre el input nativo de React (js/ppv2-checkout-ciudad.js
 *    + dataset js/ppv2-ciudades-co.js: 1.123 municipios de los 33
 *    departamentos, generado de api-colombia.com y agrupado por el código
 *    CO-XXX que usa WooCommerce). El input original NUNCA se quita del DOM:
 *    sigue siendo el que React lee y el que viaja al servidor; el select solo
 *    escribe en él. Si el JS fallara por cualquier motivo, el campo vuelve a
 *    ser el input de texto de siempre y el checkout sigue operando.
 *
 * Performance: los dos scripts (≈18 KB en total, ≈6 KB comprimidos) se
 * encolan SOLO en la página del checkout, en el footer. Ninguna otra página
 * los carga.
 * ========================================================================= */
add_filter( 'woocommerce_get_country_locale', 'ppv2_checkout_departamento_primero' );
function ppv2_checkout_departamento_primero( $locale ) {
	if ( ! isset( $locale['CO'] ) ) { $locale['CO'] = array(); }
	if ( ! isset( $locale['CO']['state'] ) ) { $locale['CO']['state'] = array(); }
	if ( ! isset( $locale['CO']['city'] ) ) { $locale['CO']['city'] = array(); }
	// Orden pedido: Departamento → Ciudad → Dirección. OJO: en el checkout de
	// BLOQUES address_1 (Dirección) tiene índice 40 (no 50 como el clásico) y
	// en empate gana el campo definido primero — por eso 30/35, estrictamente
	// menores. El "+ Añadir apartamento…" (address_2) cuelga de Dirección y se
	// mueve con ella. En el checkout clásico y Mi Cuenta (address_1 = 50) este
	// orden da el mismo resultado.
	$locale['CO']['state']['priority'] = 30;
	$locale['CO']['city']['priority']  = 35;
	return $locale;
}

add_action( 'wp_enqueue_scripts', 'ppv2_checkout_ciudad_scripts' );
function ppv2_checkout_ciudad_scripts() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) { return; }
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	if ( ! file_exists( $dir . '/js/ppv2-ciudades-co.js' ) || ! file_exists( $dir . '/js/ppv2-checkout-ciudad.js' ) ) { return; }
	wp_enqueue_script( 'ppv2-ciudades-co', $uri . '/js/ppv2-ciudades-co.js', array(), filemtime( $dir . '/js/ppv2-ciudades-co.js' ), true );
	wp_enqueue_script( 'ppv2-checkout-ciudad', $uri . '/js/ppv2-checkout-ciudad.js', array( 'ppv2-ciudades-co' ), filemtime( $dir . '/js/ppv2-checkout-ciudad.js' ), true );
}
