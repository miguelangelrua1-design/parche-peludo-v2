<?php
/**
 * Plugin Name: PP Chat de Listados
 * Plugin URI:  https://parchepeludo.com
 * Description: Asistente conversacional (chat guiado, sin IA) para crear listados. Soporta todas las tipologías activas según los permisos por rol (módulo Listados de Personalización Parche), genera sus preguntas desde el Editor de Formularios de Listeo (se sincroniza solo al agregar/quitar campos), permite subir imágenes, y se integra embebido en la página "Agregar Listado" (sección "Agregar por chat").
 * Version:     2.1.0
 * Author:      Parche Peludo
 * Text Domain: pp-chat-listados
 *
 * Crea el borrador en estado "preview" y envía al dueño al formulario nativo
 * de Listeo prellenado (?step=submit&listing_id=X + cookies de reanudación)
 * para revisar/completar y enviarlo a aprobación.
 *
 * Requiere: tema Listeo + listeo-core. Si está activo el módulo Listados de
 * pp-personalizacion, usa sus permisos por rol (pp_tipo_listado_permitido_
 * para_rol / pp_filtrar_tipos_listado_por_rol); si no, aplica el criterio
 * nativo de Listeo (roles owner/seller/admin ven todo).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PP_CHAT_LISTADOS_DIR', plugin_dir_path( __FILE__ ) );
define( 'PP_CHAT_LISTADOS_URL', plugin_dir_url( __FILE__ ) );
define( 'PP_CHAT_LISTADOS_VER', '2.1.0' );

/** Campos "esenciales" del modo exprés: los obligatorios siempre lo son;
 *  estos otros también se preguntan de entrada por su valor de negocio. */
function ppcl_essential_keys() {
	return array( 'listing_title', 'listing_description', '_phone', '_whatsapp', '_email', '_address' );
}

/** ¿El tipo maneja horarios de atención? */
function ppcl_type_supports_hours( $slug ) {
	if ( ! function_exists( 'listeo_core_custom_listing_types' ) ) {
		return false;
	}
	$mgr = listeo_core_custom_listing_types();
	return method_exists( $mgr, 'type_supports_opening_hours' )
		? (bool) $mgr->type_supports_opening_hours( $slug )
		: false;
}

/* =========================================================================
 * 1. PERMISOS Y TIPOS
 * ======================================================================= */

/** Rol principal del usuario actual ('' si no hay sesión). */
function ppcl_user_role() {
	if ( ! is_user_logged_in() ) {
		return '';
	}
	$user  = wp_get_current_user();
	$roles = (array) $user->roles;
	return (string) array_shift( $roles );
}

/**
 * Tipos de listado que el usuario actual puede crear: los tipos ACTIVOS de
 * Listeo filtrados por la matriz de permisos por rol del módulo Listados
 * (si está disponible). Devuelve array de objetos {slug, name}.
 */
function ppcl_allowed_types_for_user() {
	if ( ! is_user_logged_in() || ! function_exists( 'listeo_core_custom_listing_types' ) ) {
		return array();
	}
	$types = listeo_core_custom_listing_types()->get_listing_types( true );
	if ( ! is_array( $types ) ) {
		return array();
	}
	$role = ppcl_user_role();
	if ( function_exists( 'pp_filtrar_tipos_listado_por_rol' ) ) {
		$types = pp_filtrar_tipos_listado_por_rol( $types, $role );
	} elseif ( ! in_array( $role, array( 'administrator', 'admin', 'owner', 'seller' ), true ) ) {
		$types = array();
	}
	return array_values( (array) $types );
}

/** ¿Puede el usuario actual crear ESTE tipo? (candado de servidor). */
function ppcl_type_allowed( $slug ) {
	foreach ( ppcl_allowed_types_for_user() as $t ) {
		if ( isset( $t->slug ) && $t->slug === $slug ) {
			return true;
		}
	}
	return false;
}

/** ¿El usuario puede usar el chat? (tiene al menos un tipo permitido). */
function ppcl_user_can_use_chat() {
	return (bool) ppcl_allowed_types_for_user();
}

/* =========================================================================
 * 2. ESQUEMA DE CAMPOS POR TIPO (sincronizado con el Editor de Formularios)
 * ======================================================================= */

/**
 * Traduce la configuración de campos de Listeo (Forms Editor u origen por
 * defecto) al "guion" del chat. Cada entrada:
 *   { key, group, groupTitle, kind, label, required, options?, tree?, inputMode? }
 * kind ∈ text | number | textarea | options | multioptions | boolean |
 *        terms | image | images
 */
function ppcl_field_schema( $type_slug ) {
	if ( ! class_exists( 'Listeo_Core_Submit' ) ) {
		return new WP_Error( 'no_listeo', 'listeo-core no está activo.' );
	}
	$groups = Listeo_Core_Submit::instance()->get_fields_for_listing_type( $type_slug );
	if ( ! is_array( $groups ) ) {
		return array();
	}

	// Campos técnicos que el chat no debe preguntar (el formulario nativo
	// los completa después: mapa, horarios, tarifas repetibles…).
	$blocklist = array( '_geolocation_lat', '_geolocation_long', '_place_id', '_opening_hours', '_menu', '_mandatory_fees' );

	$schema = array();
	foreach ( $groups as $group_key => $group ) {
		if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
			continue;
		}
		$group_title = isset( $group['title'] ) ? wp_strip_all_tags( stripslashes( $group['title'] ) ) : $group_key;

		foreach ( $group['fields'] as $key => $field ) {
			if ( ! is_array( $field ) || in_array( $key, $blocklist, true ) ) {
				continue;
			}
			$type  = isset( $field['type'] ) ? $field['type'] : 'text';
			$label = isset( $field['label'] ) ? wp_strip_all_tags( stripslashes( $field['label'] ) ) : $key;

			$item = array(
				'key'        => $key,
				'group'      => $group_key,
				'groupTitle' => $group_title,
				'label'      => $label,
				'required'   => ! empty( $field['required'] ),
			);
			// Modo exprés: esencial = obligatorio, campo de negocio clave,
			// taxonomías o fotos; el resto se ofrece como "afinar detalles".
			$item['essential'] = $item['required']
				|| in_array( $key, ppcl_essential_keys(), true )
				|| in_array( $type, array( 'term-select', 'term-checklist', 'term-multiselect', 'drilldown-taxonomy', 'file', 'files' ), true );
			if ( ! empty( $field['placeholder'] ) ) {
				$item['placeholder'] = wp_strip_all_tags( stripslashes( $field['placeholder'] ) );
			}

			switch ( $type ) {
				case 'text':
				case 'email':
				case 'url':
				case 'tel':
				case 'date':
					$item['kind'] = 'text';
					if ( in_array( $type, array( 'email', 'url', 'tel' ), true ) ) {
						$item['inputMode'] = $type;
					}
					break;
				case 'number':
					$item['kind'] = 'number';
					break;
				case 'textarea':
				case 'wp-editor':
					$item['kind'] = 'textarea';
					break;
				case 'select':
				case 'radio':
					if ( empty( $field['options'] ) || ! is_array( $field['options'] ) ) {
						continue 2;
					}
					$item['kind']    = 'options';
					$item['options'] = array_map( 'strval', $field['options'] );
					break;
				case 'checkboxes':
				case 'multiselect':
				case 'multicheck':
					if ( empty( $field['options'] ) || ! is_array( $field['options'] ) ) {
						continue 2;
					}
					$item['kind']    = 'multioptions';
					$item['options'] = array_map( 'strval', $field['options'] );
					break;
				case 'checkbox':
					$item['kind'] = 'boolean';
					break;
				case 'term-select':
				case 'term-checklist':
				case 'term-multiselect':
				case 'drilldown-taxonomy':
					if ( empty( $field['taxonomy'] ) || ! taxonomy_exists( $field['taxonomy'] ) ) {
						continue 2;
					}
					$item['kind']     = 'terms';
					$item['taxonomy'] = $field['taxonomy'];
					$item['tree']     = ppcl_term_tree( $field['taxonomy'] );
					if ( empty( $item['tree'] ) ) {
						continue 2; // taxonomía sin términos: nada que preguntar
					}
					break;
				case 'file':
					$item['kind'] = 'image';
					break;
				case 'files':
					$item['kind'] = 'images';
					break;
				default:
					continue 2; // tipo no soportado por el chat (header, repeatable…)
			}

			$schema[] = $item;
		}
	}
	return $schema;
}

/** Árbol de términos (2 niveles) para los botones del chat. */
function ppcl_term_tree( $taxonomy ) {
	$tree    = array();
	$parents = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'parent' => 0 ) );
	if ( is_wp_error( $parents ) ) {
		return $tree;
	}
	foreach ( $parents as $parent ) {
		$children = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'parent' => $parent->term_id ) );
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

/* =========================================================================
 * 3. ASSETS + SECCIÓN "AGREGAR POR CHAT" EN LA PÁGINA AGREGAR LISTADO
 * ======================================================================= */

/** ¿Estamos en la pantalla "Elige el tipo de listado" de Agregar Listado? */
function ppcl_is_type_screen() {
	$submit_page = absint( get_option( 'listeo_submit_page' ) );
	return $submit_page
		&& is_page( $submit_page )
		&& empty( $_GET['step'] )
		&& empty( $_GET['listing_id'] )
		&& empty( $_GET['action'] );
}

function ppcl_register_assets() {
	$js  = PP_CHAT_LISTADOS_DIR . 'js/pp-chat-listados.js';
	$css = PP_CHAT_LISTADOS_DIR . 'css/pp-chat-listados.css';

	wp_register_script( 'pp-chat-listados', PP_CHAT_LISTADOS_URL . 'js/pp-chat-listados.js', array( 'jquery' ), file_exists( $js ) ? filemtime( $js ) : PP_CHAT_LISTADOS_VER, true );
	wp_register_style( 'pp-chat-listados', PP_CHAT_LISTADOS_URL . 'css/pp-chat-listados.css', array(), file_exists( $css ) ? filemtime( $css ) : PP_CHAT_LISTADOS_VER );

	// (a) Página propia con el shortcode → modo "page".
	if ( is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post && has_shortcode( (string) $post->post_content, 'ppv2_chat_listado' ) ) {
			ppcl_enqueue_assets( 'page' );
			return;
		}
	}
	// (b) Pantalla de tipos de Agregar Listado → modo "embedded".
	if ( ppcl_is_type_screen() && ppcl_user_can_use_chat() ) {
		ppcl_enqueue_assets( 'embedded' );
	}
}
add_action( 'wp_enqueue_scripts', 'ppcl_register_assets' );

function ppcl_enqueue_assets( $mode ) {
	wp_enqueue_style( 'pp-chat-listados' );
	wp_enqueue_script( 'pp-chat-listados' );

	$submit_page = absint( get_option( 'listeo_submit_page' ) );
	$max_mb      = (int) get_option( 'listeo_max_filesize', 10 );
	$wp_max_mb   = (int) floor( wp_max_upload_size() / MB_IN_BYTES );
	if ( $wp_max_mb > 0 ) {
		$max_mb = min( $max_mb, $wp_max_mb );
	}

	wp_localize_script( 'pp-chat-listados', 'ppv2ChatListado', array(
		'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
		'nonce'      => wp_create_nonce( 'ppv2_chat_listado' ),
		'loggedIn'   => is_user_logged_in(),
		'canUse'     => ppcl_user_can_use_chat(),
		'mode'       => $mode,
		'loginUrl'   => wp_login_url( get_permalink() ),
		'backUrl'    => $submit_page ? get_permalink( $submit_page ) : home_url( '/' ),
		'maxMb'      => max( 1, $max_mb ),
		'maxGallery' => 10,
	) );
}

/**
 * Sección "Agregar por chat" bajo el componente "Elige el tipo de listado".
 * Se añade por filtro the_content en la página Agregar Listado (solo en la
 * pantalla de tipos): arriba queda el componente nativo y debajo, en otro
 * contenedor, esta sección con el botón que abre el chat EMBEBIDO (misma
 * página: menú lateral y header intactos).
 */
function ppcl_append_chat_section( $content ) {
	if ( ! in_the_loop() || ! is_main_query() || ! ppcl_is_type_screen() || ! ppcl_user_can_use_chat() ) {
		return $content;
	}
	$section  = '<div class="ppcl-chat-section" id="ppcl-chat-section">';
	$section .= '<div class="ppcl-chat-section-head"><h3>' . esc_html__( 'Agregar por chat', 'pp-chat-listados' ) . '</h3>';
	$section .= '<p>' . esc_html__( 'Prefieres que te guiemos? Crea tu listado conversando: te preguntamos todo paso a paso, incluidas las fotos.', 'pp-chat-listados' ) . '</p></div>';
	$section .= '<button type="button" class="ppcl-open-chat" id="ppcl-open-chat">💬 ' . esc_html__( 'Crear con el chat', 'pp-chat-listados' ) . '</button>';
	$section .= '</div>';
	$section .= '<div id="ppv2-chat-listado" class="ppv2-chat ppcl-embedded" hidden aria-live="polite"></div>';
	return $content . $section;
}
add_filter( 'the_content', 'ppcl_append_chat_section', 20 );

/** Shortcode [ppv2_chat_listado] — página propia (modo "page"). */
function ppcl_shortcode() {
	if ( ! wp_script_is( 'pp-chat-listados', 'enqueued' ) ) {
		ppcl_enqueue_assets( 'page' );
	}
	return '<div id="ppv2-chat-listado" class="ppv2-chat" aria-live="polite"></div>';
}
add_shortcode( 'ppv2_chat_listado', 'ppcl_shortcode' );

/* =========================================================================
 * 4. AJAX
 * ======================================================================= */

/** Guardas comunes de los endpoints. Devuelve el rol o corta con error. */
function ppcl_ajax_guard() {
	check_ajax_referer( 'ppv2_chat_listado', 'nonce' );
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'code' => 'login' ) );
	}
	if ( ! ppcl_user_can_use_chat() ) {
		wp_send_json_error( array(
			'code'    => 'role',
			'message' => __( 'Tu tipo de cuenta no puede publicar listados. Escríbenos si crees que es un error.', 'pp-chat-listados' ),
		) );
	}
}

/** 4a. Tipos permitidos para el usuario (arranque del chat), con su cupo
 *  restante si el módulo Listados tiene topes configurados. */
function ppcl_ajax_bootstrap() {
	ppcl_ajax_guard();
	$user_id = get_current_user_id();
	$types   = array();
	foreach ( ppcl_allowed_types_for_user() as $t ) {
		$entry = array( 'slug' => $t->slug, 'name' => $t->name );
		if ( function_exists( 'pp_listados_restantes' ) ) {
			$restantes = pp_listados_restantes( $user_id, $t->slug );
			if ( null !== $restantes ) {
				$entry['remaining'] = $restantes;
			}
		}
		$types[] = $entry;
	}
	wp_send_json_success( array( 'types' => $types ) );
}
add_action( 'wp_ajax_ppcl_bootstrap', 'ppcl_ajax_bootstrap' );

/** 4b. Esquema de campos del tipo elegido (siempre fresco desde el admin). */
function ppcl_ajax_fields() {
	ppcl_ajax_guard();
	$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
	if ( ! $type || ! ppcl_type_allowed( $type ) ) {
		wp_send_json_error( array( 'code' => 'type', 'message' => __( 'Ese tipo de listado no está disponible para tu cuenta.', 'pp-chat-listados' ) ) );
	}
	$schema = ppcl_field_schema( $type );
	if ( is_wp_error( $schema ) ) {
		wp_send_json_error( array( 'code' => 'error', 'message' => $schema->get_error_message() ) );
	}
	wp_send_json_success( array(
		'schema' => $schema,
		'hours'  => ppcl_type_supports_hours( $type ),
	) );
}
add_action( 'wp_ajax_ppcl_fields', 'ppcl_ajax_fields' );

/** 4c. Subida de UNA imagen (el chat sube de a una, con vista previa). */
function ppcl_ajax_upload() {
	ppcl_ajax_guard();

	// Anti-abuso: máximo 30 subidas por hora por usuario.
	$user_id  = get_current_user_id();
	$lock_key = 'ppcl_uploads_' . $user_id;
	$subidas  = (int) get_transient( $lock_key );
	if ( $subidas >= 30 ) {
		wp_send_json_error( array( 'code' => 'rate', 'message' => __( 'Has subido muchas imágenes seguidas. Espera un rato e inténtalo de nuevo.', 'pp-chat-listados' ) ) );
	}

	if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
		wp_send_json_error( array( 'code' => 'nofile', 'message' => __( 'No llegó ningún archivo.', 'pp-chat-listados' ) ) );
	}

	$max_mb = (int) get_option( 'listeo_max_filesize', 10 );
	if ( ! empty( $_FILES['file']['size'] ) && $_FILES['file']['size'] > $max_mb * MB_IN_BYTES ) {
		wp_send_json_error( array(
			'code'    => 'size',
			'message' => sprintf( __( 'La imagen pesa demasiado (máximo %d MB).', 'pp-chat-listados' ), $max_mb ),
		) );
	}

	// Solo imágenes.
	$check = wp_check_filetype_and_ext( $_FILES['file']['tmp_name'], $_FILES['file']['name'] );
	$ok_types = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );
	if ( empty( $check['type'] ) || ! in_array( $check['type'], $ok_types, true ) ) {
		wp_send_json_error( array( 'code' => 'mime', 'message' => __( 'Solo se permiten imágenes (JPG, PNG, WebP o GIF).', 'pp-chat-listados' ) ) );
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$attachment_id = media_handle_upload( 'file', 0 );
	if ( is_wp_error( $attachment_id ) ) {
		wp_send_json_error( array( 'code' => 'upload', 'message' => $attachment_id->get_error_message() ) );
	}
	// La imagen queda a nombre del usuario (para poder validarla al crear) y
	// marcada como subida del chat (para la limpieza de huérfanas).
	wp_update_post( array( 'ID' => $attachment_id, 'post_author' => get_current_user_id() ) );
	update_post_meta( $attachment_id, '_ppcl_chat_upload', time() );
	set_transient( $lock_key, $subidas + 1, HOUR_IN_SECONDS );

	wp_send_json_success( array(
		'id'    => $attachment_id,
		'url'   => wp_get_attachment_url( $attachment_id ),
		'thumb' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
	) );
}
add_action( 'wp_ajax_ppcl_upload', 'ppcl_ajax_upload' );

/** ¿Este adjunto es una imagen del usuario actual? (para _gallery/logo). */
function ppcl_own_image( $attachment_id ) {
	$att = get_post( $attachment_id );
	return $att
		&& 'attachment' === $att->post_type
		&& (int) $att->post_author === get_current_user_id()
		&& wp_attachment_is_image( $att );
}

/** 4d. Crear el listado borrador con las respuestas del chat. */
function ppcl_ajax_create() {
	ppcl_ajax_guard();

	$user_id = get_current_user_id();

	// Anti-doble-clic / spam: máximo un listado por minuto por usuario.
	if ( get_transient( 'ppv2_chat_listado_lock_' . $user_id ) ) {
		wp_send_json_error( array(
			'code'    => 'rate',
			'message' => __( 'Acabas de crear un listado. Espera un minuto e inténtalo de nuevo.', 'pp-chat-listados' ),
		) );
	}

	$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
	if ( ! $type || ! ppcl_type_allowed( $type ) ) {
		wp_send_json_error( array( 'code' => 'type', 'message' => __( 'Ese tipo de listado no está disponible para tu cuenta.', 'pp-chat-listados' ) ) );
	}

	// Tope de publicaciones por cuenta (módulo Listados), si está configurado.
	if ( function_exists( 'pp_listados_restantes' ) ) {
		$restantes = pp_listados_restantes( $user_id, $type );
		if ( null !== $restantes && $restantes <= 0 ) {
			wp_send_json_error( array(
				'code'    => 'limit',
				'message' => __( 'Ya alcanzaste el número máximo de publicaciones de este tipo para tu cuenta. Puedes gestionar las que ya tienes desde "Mis publicaciones".', 'pp-chat-listados' ),
			) );
		}
	}

	$raw = isset( $_POST['fields'] ) ? json_decode( wp_unslash( $_POST['fields'] ), true ) : null;
	if ( ! is_array( $raw ) ) {
		wp_send_json_error( array( 'code' => 'invalid', 'message' => __( 'No llegaron las respuestas del chat.', 'pp-chat-listados' ) ) );
	}

	// El esquema se re-resuelve EN EL SERVIDOR: el cliente no dicta qué
	// campos existen ni cuáles son obligatorios.
	$schema = ppcl_field_schema( $type );
	if ( is_wp_error( $schema ) ) {
		wp_send_json_error( array( 'code' => 'error', 'message' => $schema->get_error_message() ) );
	}

	$title       = '';
	$description = '';
	$metas       = array();   // key => valor saneado
	$taxonomies  = array();   // taxonomy => [term_ids]
	$galleries   = array();   // key => [attachment_ids]
	$images      = array();   // key => attachment_id
	$faltantes   = array();

	foreach ( $schema as $field ) {
		$key   = $field['key'];
		$value = array_key_exists( $key, $raw ) ? $raw[ $key ] : '';

		// Requeridos (la galería/las imágenes también cuentan si el admin las marcó).
		$empty = ( '' === $value || null === $value || array() === $value );
		if ( ! empty( $field['required'] ) && $empty ) {
			$faltantes[] = $field['label'];
			continue;
		}
		if ( $empty ) {
			continue;
		}

		switch ( $field['kind'] ) {
			case 'text':
				$metas[ $key ] = sanitize_text_field( (string) $value );
				break;
			case 'number':
				if ( is_numeric( $value ) ) {
					$metas[ $key ] = (string) ( 0 + $value );
				}
				break;
			case 'textarea':
				$metas[ $key ] = sanitize_textarea_field( (string) $value );
				break;
			case 'options':
				if ( isset( $field['options'][ $value ] ) ) {
					$metas[ $key ] = (string) $value;
				}
				break;
			case 'multioptions':
				$vals = array_values( array_intersect( array_keys( (array) $field['options'] ), (array) $value ) );
				if ( $vals ) {
					$metas[ $key ] = $vals;
				}
				break;
			case 'boolean':
				if ( $value ) {
					$metas[ $key ] = 'on';
				}
				break;
			case 'terms':
				$term = get_term( absint( $value ) );
				if ( $term instanceof WP_Term && ! empty( $field['taxonomy'] ) && $term->taxonomy === $field['taxonomy'] ) {
					$taxonomies[ $term->taxonomy ][] = $term->term_id;
				}
				break;
			case 'image':
				$att = absint( $value );
				if ( $att && ppcl_own_image( $att ) ) {
					$images[ $key ] = $att;
				} elseif ( ! empty( $field['required'] ) ) {
					$faltantes[] = $field['label'];
				}
				break;
			case 'images':
				$ids = array_filter( array_map( 'absint', (array) $value ) );
				$ids = array_values( array_filter( $ids, 'ppcl_own_image' ) );
				$ids = array_slice( $ids, 0, 10 );
				if ( $ids ) {
					$galleries[ $key ] = $ids;
				} elseif ( ! empty( $field['required'] ) ) {
					$faltantes[] = $field['label'];
				}
				break;
		}

		// Título y descripción van al post, no a metas.
		if ( 'listing_title' === $key && isset( $metas[ $key ] ) ) {
			$title = $metas[ $key ];
			unset( $metas[ $key ] );
		}
		if ( 'listing_description' === $key && isset( $metas[ $key ] ) ) {
			$description = $metas[ $key ];
			unset( $metas[ $key ] );
		}
	}

	if ( '' === $title ) {
		$faltantes[] = __( 'Título', 'pp-chat-listados' );
	}
	if ( $faltantes ) {
		wp_send_json_error( array(
			'code'    => 'invalid',
			'message' => sprintf( __( 'Faltan datos obligatorios: %s.', 'pp-chat-listados' ), implode( ', ', array_unique( $faltantes ) ) ),
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

	update_post_meta( $listing_id, '_listing_type', $type );
	update_post_meta( $listing_id, '_ppcl_via_chat', time() ); // medición: creado por chat

	foreach ( $metas as $key => $value ) {
		update_post_meta( $listing_id, $key, $value );
	}

	// Horarios de atención (paso propio del chat, formato nativo por día:
	// el formulario los prellena y reconstruye el resumen al enviarse).
	$hours_raw = isset( $_POST['hours'] ) ? json_decode( wp_unslash( $_POST['hours'] ), true ) : null;
	if ( is_array( $hours_raw ) && ppcl_type_supports_hours( $type ) ) {
		ppcl_save_hours( $listing_id, $hours_raw );
	}

	// Geocodificación de la dirección (para que el listado salga con pin en
	// el mapa sin que el dueño tenga que buscarla de nuevo en el formulario).
	if ( isset( $metas['_address'] ) && '' !== $metas['_address'] ) {
		$region_name = '';
		if ( ! empty( $taxonomies['region'] ) ) {
			$region_term = get_term( $taxonomies['region'][0], 'region' );
			if ( $region_term instanceof WP_Term ) {
				$region_name = $region_term->name;
			}
		}
		ppcl_geocode_listing( $listing_id, $metas['_address'], $region_name );
	}
	foreach ( $taxonomies as $taxonomy => $term_ids ) {
		wp_set_object_terms( $listing_id, array_map( 'intval', $term_ids ), $taxonomy );
	}

	// Imágenes sueltas (p. ej. _listing_logo): Listeo guarda la URL.
	foreach ( $images as $key => $att ) {
		update_post_meta( $listing_id, $key, wp_get_attachment_url( $att ) );
		wp_update_post( array( 'ID' => $att, 'post_parent' => $listing_id ) );
	}

	// Galerías (p. ej. _gallery): formato nativo array(id => url) + parent.
	$primera_foto = 0;
	foreach ( $galleries as $key => $ids ) {
		$valor = array();
		foreach ( $ids as $att ) {
			$valor[ $att ] = wp_get_attachment_url( $att );
			wp_update_post( array( 'ID' => $att, 'post_parent' => $listing_id ) );
			if ( ! $primera_foto ) {
				$primera_foto = $att;
			}
		}
		update_post_meta( $listing_id, $key, $valor );
	}
	// Imagen destacada: primera foto de la galería (para que las tarjetas
	// del sitio muestren foto desde el principio).
	if ( $primera_foto && ! has_post_thumbnail( $listing_id ) ) {
		set_post_thumbnail( $listing_id, $primera_foto );
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
add_action( 'wp_ajax_ppcl_create', 'ppcl_ajax_create' );

// Compatibilidad con la acción de la v1 (por si hay HTML cacheado).
add_action( 'wp_ajax_ppv2_chat_listado_create', 'ppcl_ajax_create' );

/* =========================================================================
 * 5. HORARIOS Y GEOCODIFICACIÓN
 * ======================================================================= */

/**
 * Guarda los horarios del chat en el formato por-día del formulario nativo:
 * meta `_{dia}_opening_hour` / `_{dia}_closing_hour` = array( 'hh:mm am' ).
 * Entrada: { days: ['monday',...], open: 'HH:MM', close: 'HH:MM' } (24 h).
 */
function ppcl_save_hours( $listing_id, $hours ) {
	$valid_days = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
	$days  = isset( $hours['days'] ) ? array_values( array_intersect( $valid_days, (array) $hours['days'] ) ) : array();
	$open  = isset( $hours['open'] ) ? ppcl_format_hour( $hours['open'] ) : '';
	$close = isset( $hours['close'] ) ? ppcl_format_hour( $hours['close'] ) : '';
	if ( ! $days || ! $open || ! $close ) {
		return;
	}
	foreach ( $days as $day ) {
		update_post_meta( $listing_id, '_' . $day . '_opening_hour', array( $open ) );
		update_post_meta( $listing_id, '_' . $day . '_closing_hour', array( $close ) );
	}
}

/** 'HH:MM' (24 h) → formato del reloj del sitio ('8:00 am' o '08:00'). */
function ppcl_format_hour( $value ) {
	if ( ! preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', trim( (string) $value ) ) ) {
		return '';
	}
	$ts = strtotime( '1970-01-01 ' . trim( $value ) );
	return '24' === (string) get_option( 'listeo_clock_format', '12' )
		? date( 'H:i', $ts )
		: date( 'g:i a', $ts );
}

/**
 * Geocodifica la dirección con la clave de SERVIDOR de Google Maps del sitio
 * (opción listeo_maps_api_server) y guarda lat/long. Silencioso si falla:
 * el dueño siempre puede ubicar el pin en el formulario nativo.
 */
function ppcl_geocode_listing( $listing_id, $address, $region_name = '' ) {
	$key = get_option( 'listeo_maps_api_server' );
	if ( ! $key || 'google' !== get_option( 'listeo_map_provider', 'google' ) ) {
		return;
	}
	$full = $address . ( $region_name ? ', ' . $region_name : '' ) . ', Colombia';
	$url  = add_query_arg( array(
		'address' => rawurlencode( $full ),
		'region'  => 'co',
		'key'     => $key,
	), 'https://maps.googleapis.com/maps/api/geocode/json' );

	$res = wp_remote_get( $url, array( 'timeout' => 6 ) );
	if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
		return;
	}
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $data['status'] ) || 'OK' !== $data['status'] || empty( $data['results'][0]['geometry']['location'] ) ) {
		return;
	}
	$loc = $data['results'][0]['geometry']['location'];
	update_post_meta( $listing_id, '_geolocation_lat', (string) $loc['lat'] );
	update_post_meta( $listing_id, '_geolocation_long', (string) $loc['lng'] );
}

/* =========================================================================
 * 6. LIMPIEZA PROGRAMADA (fotos huérfanas y borradores abandonados)
 * ======================================================================= */

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'ppcl_daily_cleanup' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ppcl_daily_cleanup' );
	}
} );
register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'ppcl_daily_cleanup' );
} );

add_action( 'ppcl_daily_cleanup', 'ppcl_run_cleanup' );
function ppcl_run_cleanup() {
	// (a) Fotos subidas por el chat que nunca quedaron en un listado (>7 días).
	$huerfanas = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'any',
		'post_parent'    => 0,
		'posts_per_page' => 50,
		'fields'         => 'ids',
		'meta_query'     => array( array(
			'key'     => '_ppcl_chat_upload',
			'value'   => time() - 7 * DAY_IN_SECONDS,
			'compare' => '<',
			'type'    => 'NUMERIC',
		) ),
	) );
	foreach ( $huerfanas as $att ) {
		wp_delete_attachment( $att, true );
	}

	// (b) Borradores del CHAT nunca enviados a revisión (>30 días en preview).
	$abandonados = get_posts( array(
		'post_type'      => 'listing',
		'post_status'    => 'preview',
		'posts_per_page' => 25,
		'fields'         => 'ids',
		'meta_query'     => array( array(
			'key'     => '_ppcl_via_chat',
			'value'   => time() - 30 * DAY_IN_SECONDS,
			'compare' => '<',
			'type'    => 'NUMERIC',
		) ),
	) );
	foreach ( $abandonados as $listing ) {
		// Sus fotos del chat (ya con parent) se van con él.
		$fotos = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'post_parent'    => $listing,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_ppcl_chat_upload',
		) );
		foreach ( $fotos as $att ) {
			wp_delete_attachment( $att, true );
		}
		wp_delete_post( $listing, true );
	}
	return array( 'fotos' => count( $huerfanas ), 'borradores' => count( $abandonados ) );
}

/* =========================================================================
 * 7. MEDICIÓN: columna "Origen" en wp-admin → Listings
 * ======================================================================= */

add_filter( 'manage_listing_posts_columns', function ( $columns ) {
	$columns['ppcl_origen'] = __( 'Origen', 'pp-chat-listados' );
	return $columns;
} );
add_action( 'manage_listing_posts_custom_column', function ( $column, $post_id ) {
	if ( 'ppcl_origen' === $column ) {
		echo get_post_meta( $post_id, '_ppcl_via_chat', true )
			? '💬 <span title="Creado con el chat">Chat</span>'
			: '<span style="opacity:.45">Formulario</span>';
	}
}, 10, 2 );

// Sin sesión: todos los endpoints responden "login" (el chat muestra el CTA).
function ppcl_ajax_needs_login() {
	wp_send_json_error( array( 'code' => 'login' ) );
}
foreach ( array( 'ppcl_bootstrap', 'ppcl_fields', 'ppcl_upload', 'ppcl_create', 'ppv2_chat_listado_create' ) as $ppcl_action ) {
	add_action( 'wp_ajax_nopriv_' . $ppcl_action, 'ppcl_ajax_needs_login' );
}
