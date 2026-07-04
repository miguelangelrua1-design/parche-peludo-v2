<?php
/**
 * Plugin Name: PP Chat de Listados
 * Plugin URI:  https://parchepeludo.com
 * Description: Asistente conversacional (chat guiado, sin IA) para que los negocios creen su listado tipo "directorio". Shortcode [ppv2_chat_listado]. Crea el borrador en estado "preview" y envía al dueño al formulario nativo de Listeo prellenado para completar fotos/horarios y enviarlo a revisión.
 * Version:     1.1.0
 * Author:      Parche Peludo
 * Text Domain: pp-chat-listados
 *
 * Migrado desde el tema hijo listeo-child el 2026-07-03. Requiere el tema
 * Listeo + plugin listeo-core (usa su página "agregar listado", su mecanismo
 * de reanudación por cookie y sus taxonomías listing_category/region).
 * Los estilos usan los tokens de marca definidos en el tema hijo
 * (--teal-parche, --ink, --r-*, --s-*…), con valores de respaldo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PP_CHAT_LISTADOS_DIR', plugin_dir_path( __FILE__ ) );
define( 'PP_CHAT_LISTADOS_URL', plugin_dir_url( __FILE__ ) );

/* -------------------------------------------------------------------------
 * 1. Assets (registrados siempre, encolados solo donde vive el shortcode)
 * ---------------------------------------------------------------------- */

function ppcl_register_assets() {
	$js  = PP_CHAT_LISTADOS_DIR . 'js/pp-chat-listados.js';
	$css = PP_CHAT_LISTADOS_DIR . 'css/pp-chat-listados.css';

	wp_register_script(
		'pp-chat-listados',
		PP_CHAT_LISTADOS_URL . 'js/pp-chat-listados.js',
		array( 'jquery' ),
		file_exists( $js ) ? filemtime( $js ) : '1.0.0',
		true
	);
	wp_register_style(
		'pp-chat-listados',
		PP_CHAT_LISTADOS_URL . 'css/pp-chat-listados.css',
		array(),
		file_exists( $css ) ? filemtime( $css ) : '1.0.0'
	);

	// Si el contenido de la página trae el shortcode, encolar desde el <head>
	// (evita el parpadeo de estilos que causaría encolarlos a mitad de página).
	if ( is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post && has_shortcode( (string) $post->post_content, 'ppv2_chat_listado' ) ) {
			ppcl_enqueue_assets();
		}
	}

	// Tarjeta "Crear con el chat" en la pantalla "Elige el tipo de listado"
	// (página "Agregar listado" de Listeo). Solo para roles que pueden crear
	// listados tipo directorio, y solo si la página del chat está publicada.
	$submit_page = absint( get_option( 'listeo_submit_page' ) );
	if ( $submit_page && is_page( $submit_page ) && ppcl_user_can_use_chat() ) {
		$chat_url = ppcl_chat_page_url();
		if ( $chat_url ) {
			$card_js = PP_CHAT_LISTADOS_DIR . 'js/pp-chat-listados-card.js';
			wp_enqueue_style( 'pp-chat-listados' );
			wp_enqueue_script(
				'pp-chat-listados-card',
				PP_CHAT_LISTADOS_URL . 'js/pp-chat-listados-card.js',
				array(),
				file_exists( $card_js ) ? filemtime( $card_js ) : '1.1.0',
				true
			);
			wp_localize_script( 'pp-chat-listados-card', 'ppclCard', array(
				'chatUrl' => $chat_url,
				'title'   => __( 'Crear con el chat', 'pp-chat-listados' ),
				'badge'   => __( 'Nuevo', 'pp-chat-listados' ),
				'sub'     => __( 'Te guiamos paso a paso', 'pp-chat-listados' ),
			) );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'ppcl_register_assets' );

function ppcl_enqueue_assets() {
	wp_enqueue_style( 'pp-chat-listados' );
	wp_enqueue_script( 'pp-chat-listados' );
	wp_localize_script( 'pp-chat-listados', 'ppv2ChatListado', array(
		'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
		'nonce'      => wp_create_nonce( 'ppv2_chat_listado' ),
		'loggedIn'   => is_user_logged_in(),
		'loginUrl'   => wp_login_url( get_permalink() ),
		'backUrl'    => ( $submit = absint( get_option( 'listeo_submit_page' ) ) ) ? get_permalink( $submit ) : home_url( '/' ),
		'categories' => ppcl_term_tree( 'listing_category' ),
		'regions'    => ppcl_term_tree( 'region' ),
	) );
}

/**
 * ¿Puede el usuario actual crear listados tipo "directorio" (y por tanto usar
 * el chat)? Reutiliza el candado por rol del tema hijo si está disponible;
 * si no, aplica el mismo criterio de Listeo (roles de dueño de negocio).
 */
function ppcl_user_can_use_chat() {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	$user  = wp_get_current_user();
	$roles = (array) $user->roles;
	$role  = array_shift( $roles );

	if ( function_exists( 'pp_tipo_listado_permitido_para_rol' ) ) {
		return pp_tipo_listado_permitido_para_rol( 'directorio', $role );
	}
	return in_array( $role, array( 'administrator', 'admin', 'owner', 'seller' ), true );
}

/**
 * URL de la página que contiene el shortcode del chat (cacheada 6 h).
 */
function ppcl_chat_page_url() {
	$cached = get_transient( 'ppcl_chat_page_url' );
	if ( false !== $cached ) {
		return $cached; // puede ser '' si no se encontró
	}
	$url   = '';
	$pages = get_posts( array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'numberposts' => -1,
		's'           => '[ppv2_chat_listado',
		'fields'      => 'ids',
	) );
	foreach ( $pages as $page_id ) {
		if ( has_shortcode( (string) get_post_field( 'post_content', $page_id ), 'ppv2_chat_listado' ) ) {
			$url = get_permalink( $page_id );
			break;
		}
	}
	set_transient( 'ppcl_chat_page_url', $url, 6 * HOUR_IN_SECONDS );
	return $url;
}

// Refrescar la caché cuando se guarde cualquier página.
add_action( 'save_post_page', function () {
	delete_transient( 'ppcl_chat_page_url' );
} );

/**
 * Árbol de términos (2 niveles) para los botones del chat.
 */
function ppcl_term_tree( $taxonomy ) {
	$tree    = array();
	$parents = get_terms( array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
		'parent'     => 0,
	) );
	if ( is_wp_error( $parents ) ) {
		return $tree;
	}
	foreach ( $parents as $parent ) {
		$children = get_terms( array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'parent'     => $parent->term_id,
		) );
		$kids = array();
		if ( ! is_wp_error( $children ) ) {
			foreach ( $children as $child ) {
				$kids[] = array( 'id' => $child->term_id, 'name' => $child->name );
			}
		}
		$tree[] = array( 'id' => $parent->term_id, 'name' => $parent->name, 'children' => $kids );
	}
	return $tree;
}

/* -------------------------------------------------------------------------
 * 2. Shortcode [ppv2_chat_listado]
 * ---------------------------------------------------------------------- */

function ppcl_shortcode() {
	// Respaldo para páginas donde el shortcode no está en post_content
	// (p. ej. dentro de un widget de Elementor): encolar aquí mismo.
	if ( ! wp_script_is( 'pp-chat-listados', 'enqueued' ) ) {
		ppcl_enqueue_assets();
	}
	return '<div id="ppv2-chat-listado" class="ppv2-chat" aria-live="polite"></div>';
}
add_shortcode( 'ppv2_chat_listado', 'ppcl_shortcode' );

/* -------------------------------------------------------------------------
 * 3. AJAX: crear el listado borrador con los datos del chat
 * ---------------------------------------------------------------------- */

function ppcl_create_listing() {
	check_ajax_referer( 'ppv2_chat_listado', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'code' => 'login' ) );
	}

	// Mismo candado por rol que el flujo nativo: los listados tipo
	// "directorio" son solo para cuentas de prestador de servicios.
	if ( ! ppcl_user_can_use_chat() ) {
		wp_send_json_error( array(
			'code'    => 'role',
			'message' => __( 'Los listados de negocios son para cuentas de Prestador de servicios. Tu cuenta actual no puede publicar en el directorio; escríbenos si quieres convertirla.', 'pp-chat-listados' ),
		) );
	}

	$user_id = get_current_user_id();

	// Anti-doble-clic / spam: máximo un listado por minuto por usuario.
	if ( get_transient( 'ppv2_chat_listado_lock_' . $user_id ) ) {
		wp_send_json_error( array(
			'code'    => 'rate',
			'message' => __( 'Acabas de crear un listado. Espera un minuto e inténtalo de nuevo.', 'pp-chat-listados' ),
		) );
	}

	$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
	$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

	if ( '' === $title || mb_strlen( $description ) < 40 ) {
		wp_send_json_error( array(
			'code'    => 'invalid',
			'message' => __( 'Falta el nombre del negocio o la descripción es muy corta.', 'pp-chat-listados' ),
		) );
	}

	$listing_id = wp_insert_post( array(
		'post_title'     => $title,
		'post_content'   => $description,
		'post_status'    => 'preview',
		'post_type'      => 'listing',
		'post_author'    => $user_id,
		'comment_status' => 'open',
	), true );

	if ( is_wp_error( $listing_id ) ) {
		wp_send_json_error( array( 'code' => 'error', 'message' => $listing_id->get_error_message() ) );
	}

	update_post_meta( $listing_id, '_listing_type', 'directorio' );

	// Taxonomías (se validan contra su taxonomía real).
	$category = isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0;
	if ( $category && get_term( $category, 'listing_category' ) instanceof WP_Term ) {
		wp_set_object_terms( $listing_id, array( $category ), 'listing_category' );
	}
	$region = isset( $_POST['region'] ) ? absint( $_POST['region'] ) : 0;
	if ( $region && get_term( $region, 'region' ) instanceof WP_Term ) {
		wp_set_object_terms( $listing_id, array( $region ), 'region' );
	}

	// Campos opcionales de contacto.
	$metas = array(
		'_address'   => isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '',
		'_phone'     => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
		'_whatsapp'  => isset( $_POST['whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) ) : '',
		'_email'     => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
		'_website'   => isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '',
		'_instagram' => isset( $_POST['instagram'] ) ? sanitize_text_field( wp_unslash( $_POST['instagram'] ) ) : '',
	);
	foreach ( $metas as $key => $value ) {
		if ( '' !== $value ) {
			update_post_meta( $listing_id, $key, $value );
		}
	}

	// Mecanismo nativo de Listeo para "reanudar" el envío: clave + cookies.
	$submitting_key = wp_generate_password( 32, false );
	update_post_meta( $listing_id, '_submitting_key', $submitting_key );
	setcookie( 'listeo-submitting-listing-id', (string) $listing_id, 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
	setcookie( 'listeo-submitting-listing-key', $submitting_key, 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

	set_transient( 'ppv2_chat_listado_lock_' . $user_id, 1, MINUTE_IN_SECONDS );

	$submit_page  = get_option( 'listeo_submit_page' );
	$continue_url = $submit_page
		? add_query_arg( array( 'step' => 'submit', 'listing_id' => $listing_id ), get_permalink( $submit_page ) )
		: home_url( '/' );

	wp_send_json_success( array(
		'listing_id'   => $listing_id,
		'continue_url' => $continue_url,
	) );
}
add_action( 'wp_ajax_ppv2_chat_listado_create', 'ppcl_create_listing' );
add_action( 'wp_ajax_nopriv_ppv2_chat_listado_create', 'ppcl_create_listing' );
