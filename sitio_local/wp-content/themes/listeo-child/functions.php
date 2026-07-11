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
    // FIX PERF (2026-07-10): el CSS del hijo (~434 KB) se aplicaba DOS veces.
    // El padre encola get_stylesheet_uri() como 'listeo-style', y en un tema
    // hijo esa URI ES el style.css del HIJO → copia temprana duplicada.
    // Re-apuntamos 'listeo-style' al style.css del PADRE (su posición temprana
    // es donde Listeo lo imprime en una instalación sin hijo, y el CSS inline
    // del Customizer que el padre cuelga de ese handle queda donde estaba).
    // El CSS del hijo se sigue encolando UNA sola vez, al final, como siempre
    // → la cascada de overrides del hijo no cambia.
    $pp_styles = wp_styles();
    if ( isset( $pp_styles->registered['listeo-style'] ) ) {
        $pp_styles->registered['listeo-style']->src = get_template_directory_uri() . '/style.css';
        $pp_child_deps = array( 'listeo-style' );
    } else {
        // Fallback (el padre cambió de handle): cargar el CSS del padre aparte.
        wp_enqueue_style( 'listeo-parent-style', get_template_directory_uri() . '/style.css' );
        $pp_child_deps = array( 'listeo-parent-style' );
    }

    // Cargar estilo del tema hijo (hereda y sobrescribe con tokens de marca V2)
    // Usamos filemtime() para forzar cache-bust automatico en cada edicion del CSS
    $child_css_path = get_stylesheet_directory() . '/style.css';
    $child_css_ver  = file_exists( $child_css_path ) ? filemtime( $child_css_path ) : wp_get_theme()->get('Version');
    wp_enqueue_style( 'listeo-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        $pp_child_deps,
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
	<!-- JS migrado a js/migrados/ppv2-shop-filters-overlay.js (2026-07-10) -->
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
// ppv2_dirfilters_drawer(): JS migrado a js/migrados/ppv2-dirfilters-drawer.js (2026-07-10).
// Encolado condicional en ppv2_enqueue_js_migrados() al final de este archivo.

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
// ppv2_dirfilters_close_desktop(): JS migrado a js/migrados/ppv2-dirfilters-close-desktop.js (2026-07-10).
// Encolado condicional en ppv2_enqueue_js_migrados() al final de este archivo.

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
// ppv2_dirfilters_categorias_acordeon(): JS migrado a js/migrados/ppv2-dirfilters-categorias-acordeon.js (2026-07-10).
// Encolado condicional en ppv2_enqueue_js_migrados() al final de este archivo.

/**
 * Árbol de CATEGORÍAS de la tienda con estilo "app": envuelve cada fila del
 * widget de categorías en .pp-cat-row, agrega un chevron a la izquierda (en los
 * padres) que pliega/despliega sus hijos SIN navegar, y abre automáticamente el
 * camino de la categoría actual. El nombre sigue siendo un enlace a la página de
 * la categoría. El look lo pone style.css (.pp-cat-row / .pp-cat-toggle).
 */
// ppv2_shop_category_tree(): JS migrado a js/migrados/ppv2-shop-category-tree.js (2026-07-10).
// Encolado condicional en ppv2_enqueue_js_migrados() al final de este archivo.

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

// ppv2_account_drawer(): JS migrado a js/migrados/ppv2-account-drawer.js (2026-07-10).
// Encolado condicional en ppv2_enqueue_js_migrados() al final de este archivo.

// ppv2_mobile_menu_close_outside(): JS migrado a js/migrados/ppv2-mobile-menu-close-outside.js (2026-07-10).
// Encolado condicional en ppv2_enqueue_js_migrados() al final de este archivo.

// ppv2_listings_filter_loading_feedback(): JS migrado a js/migrados/ppv2-listings-filter-loading-feedback.js (2026-07-10).
// Encolado condicional en ppv2_enqueue_js_migrados() al final de este archivo.

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
	<!-- JS migrado a js/migrados/ppv2-listings-inject-header-search.js (2026-07-10) -->
	<?php
}
add_action( 'wp_footer', 'ppv2_listings_inject_header_search', 5 );

/**
 * PANEL DE CONTROL / MI CUENTA — Buscador del header, inyectado SERVER-SIDE.
 *
 * El dashboard usa header-dashboard.php, que NO trae .header-search-container.
 * En vez de montarlo con JS en el footer (lo que hacía que el buscador —y la
 * lupa en móvil— "apareciera" un instante DESPUÉS de pintar el header, un salto
 * visible), inyectamos el markup DIRECTAMENTE en el HTML del header con un buffer
 * de salida acotado a ESTA plantilla: el buscador viene desde el primer render,
 * sin pop-in y sin un archivo JS extra.
 *
 * Insertamos:
 *   - .header-search-container (con el formulario oficial) antes de .right-side.
 *   - .mobile-search-trigger (la lupa) como primer hijo de .header-widget.
 * Las pestañas Directorio|Tienda y el autocompletado los añade
 * ppv2-header-suggest.js (carga en todas las páginas) y el toggle de la lupa lo
 * maneja custom.js del tema padre — igual que en el resto del sitio.
 * Mismo desarme que en listados: ids renombrados (evita duplicados con otros
 * formularios) y sin clase ajax-search (Listeo no vigila este formulario).
 */
add_action( 'template_redirect', 'ppv2_dashboard_search_ssr' );
function ppv2_dashboard_search_ssr() {
	if ( ! is_page_template( 'template-dashboard.php' ) ) {
		return;
	}
	// Render el formulario AHORA (timing normal: sus assets aún pueden encolarse
	// para wp_head/footer); el buffer de página solo hará el str_replace al final.
	ob_start();
	do_shortcode( '[listeo_search_form action=' . get_post_type_archive_link( 'listing' ) . ' source="header" custom_class="main-search-form gray-style"]' );
	$form = ob_get_clean();
	if ( ! $form ) {
		return;
	}
	$form = str_replace( 'id="listeo_core-search-form"', 'id="ppv2-dash-search-form"', $form );
	$form = str_replace( 'id="keyword_search"', 'id="ppv2_dash_keyword_search"', $form );
	$form = str_replace( 'ajax-search', '', $form );

	ob_start( function ( $html ) use ( $form ) {
		// Solo si el header esperado está y aún no tiene buscador (idempotente).
		if ( strpos( $html, '<div class="right-side">' ) === false || strpos( $html, 'header-search-container' ) !== false ) {
			return $html;
		}
		$container = '<div class="header-search-container">' . $form . '</div>';
		$trigger   = '<div class="mobile-search-trigger"><i class="gg-search"></i><span class="gg-close rounded"></span></div>';
		$html = preg_replace( '/<div class="right-side">/',   $container . '$0', $html, 1 );
		$html = preg_replace( '/<div class="header-widget">/', '$0' . $trigger,  $html, 1 );
		return $html;
	} );
}

// ppv2_fix_unbookmark_delegation(): JS migrado a js/migrados/ppv2-fix-unbookmark-delegation.js (2026-07-10).
// Encolado condicional en ppv2_enqueue_js_migrados() al final de este archivo.

// ppv2_right_drawer_system(): JS migrado a js/migrados/ppv2-right-drawer-system.js (2026-07-10).
// Encolado condicional en ppv2_enqueue_js_migrados() al final de este archivo.

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

// ppv2_message_form_labels(): JS migrado a js/migrados/ppv2-message-form-labels.js (2026-07-10).
// Encolado condicional en ppv2_enqueue_js_migrados() al final de este archivo.

// ppv2_shop_filter_script(): JS migrado a js/migrados/ppv2-shop-filter-script.js (2026-07-10).
// Encolado condicional en ppv2_enqueue_js_migrados() al final de este archivo.

// ppv2_listing_header_reorder(): JS migrado a js/migrados/ppv2-listing-header-reorder.js (2026-07-10).
// Encolado condicional en ppv2_enqueue_js_migrados() al final de este archivo.

// ppv2_listing_fav_position(): JS migrado a js/migrados/ppv2-listing-fav-position.js (2026-07-10).
// Encolado condicional en ppv2_enqueue_js_migrados() al final de este archivo.

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
	<!-- JS migrado a js/migrados/ppv2-listing-mobile-bottom-bar.js (2026-07-10) -->
	<?php
}
add_action( 'wp_footer', 'ppv2_listing_mobile_bottom_bar', 110 );

// ppv2_signin_feedback_into_view(): JS migrado a js/migrados/ppv2-signin-feedback-into-view.js (2026-07-10).
// Encolado condicional en ppv2_enqueue_js_migrados() al final de este archivo.

// ppv2_dashboard_hamburger_icon(): JS migrado a js/migrados/ppv2-dashboard-hamburger-icon.js (2026-07-10).
// Encolado condicional en ppv2_enqueue_js_migrados() al final de este archivo.

// ppv2_listing_contact_redesign(): JS migrado a js/migrados/ppv2-listing-contact-redesign.js (2026-07-10).
// Encolado condicional en ppv2_enqueue_js_migrados() al final de este archivo.

// ppv2_listing_card_swipe(): JS migrado a js/migrados/ppv2-listing-card-swipe.js (2026-07-10).
// Encolado condicional en ppv2_enqueue_js_migrados() al final de este archivo.

// ppv2_card_amenities_clamp(): JS migrado a js/migrados/ppv2-card-amenities-clamp.js (2026-07-10).
// Encolado condicional en ppv2_enqueue_js_migrados() al final de este archivo.

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
		// FIX 2026-07-10: tope contra el stock/límite de compra del producto.
		// set_quantity() no valida stock, así que desde el minicart se podía
		// fijar 999999 y el error solo aparecía en el checkout.
		$item     = WC()->cart->get_cart_item( $key );
		$producto = ( $item && isset( $item['data'] ) && is_object( $item['data'] ) ) ? $item['data'] : null;
		if ( $producto && method_exists( $producto, 'get_max_purchase_quantity' ) ) {
			$max = (int) $producto->get_max_purchase_quantity(); // -1 = sin límite
			if ( $max > 0 && $qty > $max ) {
				$qty = $max;
			}
		}
		$qty = min( $qty, 9999 ); // techo absoluto de cordura
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
	// FIX 2026-07-10: tope de longitud. Este endpoint es público y cada
	// término no cacheado crea un transient (2 filas en wp_options) y hasta
	// 3 búsquedas LIKE: sin tope, un bot enviando términos aleatorios largos
	// infla la base de datos a voluntad. Ningún usuario real busca >60
	// caracteres; se trunca (las sugerencias siguen saliendo).
	if ( $len > 60 ) {
		$term = function_exists( 'mb_substr' ) ? mb_substr( $term, 0, 60, 'UTF-8' ) : substr( $term, 0, 60 );
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
// ppv2_header_suggest_js(): JS migrado a js/migrados/ppv2-header-suggest.js (2026-07-10).
// Encolado condicional en ppv2_enqueue_js_migrados() al final de este archivo.

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
// ppv2_validacion_html5_es(): JS migrado a js/migrados/ppv2-validacion-html5-es.js (2026-07-10).
// Encolado condicional en ppv2_enqueue_js_migrados() al final de este archivo.

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
	// is_checkout() también es true en "pedido recibido" (order-received),
	// donde no hay campo de ciudad: el polling de arranque del JS corría sus
	// 20 s completos en cada página de gracias. Ahí no se encola.
	if ( function_exists( 'is_wc_endpoint_url' ) && ( is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'order-pay' ) ) ) { return; }
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	if ( ! file_exists( $dir . '/js/ppv2-ciudades-co.js' ) || ! file_exists( $dir . '/js/ppv2-checkout-ciudad.js' ) ) { return; }
	wp_enqueue_script( 'ppv2-ciudades-co', $uri . '/js/ppv2-ciudades-co.js', array(), filemtime( $dir . '/js/ppv2-ciudades-co.js' ), true );
	wp_enqueue_script( 'ppv2-checkout-ciudad', $uri . '/js/ppv2-checkout-ciudad.js', array( 'ppv2-ciudades-co' ), filemtime( $dir . '/js/ppv2-checkout-ciudad.js' ), true );
}

/* =========================================================================
   PPV2 — JS MIGRADO A ARCHIVOS ESTÁTICOS (2026-07-10)
   Antes: ~2.500 líneas de <script> inline impresas en wp_footer en CADA
   carga (no cacheables, no minificables). Ahora: archivos en js/migrados/
   encolados condicionalmente con la MISMA condición de página y en el
   MISMO orden de impresión que tenían los bloques inline (el orden
   importa: p. ej. fav-position lee el DOM que crea header-reorder, y
   account-drawer usa window.ppRDrawer de right-drawer-system).
   Las funciones PHP que además imprimían HTML (overlay de filtros, form
   fantasma del buscador, bottom sheet) SIGUEN imprimiéndolo: solo su
   <script> se movió al archivo correspondiente.
   ========================================================================= */
add_action( 'wp_enqueue_scripts', 'ppv2_enqueue_js_migrados', 100 );
function ppv2_enqueue_js_migrados() {
	$dir = get_stylesheet_directory() . '/js/migrados/';
	$uri = get_stylesheet_directory_uri() . '/js/migrados/';

	$es_pdp      = is_singular( 'listing' );
	$es_archivo  = function_exists( 'ppv2_is_listings_archive' ) && ppv2_is_listings_archive();
	$es_tienda   = function_exists( 'ppv2_is_shop_context' ) && ppv2_is_shop_context();

	// [handle/archivo, condición, deps] — EN ORDEN DE IMPRESIÓN ORIGINAL
	// (prioridades wp_footer 5→99→100→101…130; empatadas = orden del archivo).
	$scripts = array(
		array( 'ppv2-listings-inject-header-search', $es_archivo, array() ),                            // prio 5
		// Por SLUG y no por ID: los IDs de página pueden diferir entre local y
		// producción (o cambiar si se recrea la página) y el filtro moriría en
		// silencio. La portada (antes ID 1555) ya la cubre is_front_page().
		array( 'ppv2-shop-filter-script', is_page( 'home-tienda' ) || is_front_page(), array() ),      // 99
		array( 'ppv2-listing-header-reorder', $es_pdp, array() ),                                       // 100
		array( 'ppv2-message-form-labels', $es_pdp, array() ),                                          // 101
		array( 'ppv2-listing-fav-position', $es_pdp, array( 'ppv2-listing-header-reorder' ) ),          // 101
		array( 'ppv2-listing-mobile-bottom-bar', $es_pdp, array() ),                                    // 110
		array( 'ppv2-signin-feedback-into-view', true, array() ),                                       // 111
		array( 'ppv2-right-drawer-system', true, array() ),                                             // 118
		array( 'ppv2-shop-filters-overlay', $es_tienda, array() ),                                      // 120
		array( 'ppv2-account-drawer', is_user_logged_in(), array( 'ppv2-right-drawer-system' ) ),       // 120
		array( 'ppv2-dirfilters-drawer', $es_archivo, array( 'jquery' ) ),                              // 121
		array( 'ppv2-mobile-menu-close-outside', true, array() ),                                       // 121
		array( 'ppv2-shop-category-tree', $es_tienda, array() ),                                        // 122
		array( 'ppv2-listings-filter-loading-feedback', $es_archivo, array() ),                         // 122
		array( 'ppv2-dirfilters-categorias-acordeon', $es_archivo, array( 'jquery' ) ),                 // 123
		array( 'ppv2-dashboard-hamburger-icon', true, array() ),                                        // 123
		array( 'ppv2-dirfilters-close-desktop', $es_archivo, array() ),                                 // 124
		array( 'ppv2-listing-contact-redesign', $es_pdp, array() ),                                     // 124
		array( 'ppv2-listing-card-swipe', true, array() ),                                              // 125
		array( 'ppv2-card-amenities-clamp', true, array() ),                                            // 126
		array( 'ppv2-fix-unbookmark-delegation', true, array( 'jquery' ) ),                             // 130
		array( 'ppv2-validacion-html5-es', true, array() ),                                             // 130
		array( 'ppv2-header-suggest', true, array( 'jquery-ui-autocomplete' ) ),                        // print_footer_scripts 50
	);

	foreach ( $scripts as $s ) {
		if ( ! $s[1] ) {
			continue;
		}
		$path = $dir . $s[0] . '.js';
		if ( ! file_exists( $path ) ) {
			continue;
		}
		wp_enqueue_script( $s[0], $uri . $s[0] . '.js', $s[2], filemtime( $path ), true );
	}

	// Config PHP → JS del buscador unificado (las únicas interpolaciones PHP
	// que había en todos los bloques inline migrados).
	if ( wp_script_is( 'ppv2-header-suggest', 'enqueued' ) ) {
		wp_add_inline_script(
			'ppv2-header-suggest',
			'window.PPV2_CFG = Object.assign({ajaxUrl:' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ',homeUrl:' . wp_json_encode( home_url( '/' ) ) . '}, window.PPV2_CFG || {});',
			'before'
		);
	}
}
