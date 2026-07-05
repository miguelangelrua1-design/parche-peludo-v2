<?php
/**
 * Módulo LISTADOS del plugin Personalización Parche.
 *
 * "Listados por rol" (migrado del tema hijo el 2026-07-05; implementado
 * originalmente el 2026-07-03): los "Usuarios" (rol WP `guest`) pueden crear
 * y gestionar listados de comunidad — **Adopción** y **Mascotas perdidas** —
 * mientras que el tipo **Directorio** queda reservado a Prestadores (rol
 * `owner`). Listeo no trae esta opción: sus plantillas bloquean por rol de
 * forma fija y el guardado solo valida el login.
 *
 * Piezas:
 *  1. Helpers de permisos por rol (pp_tipos_listado_guest, …). El plugin
 *     pp-chat-listados también los usa (via function_exists): NO renombrar.
 *  2. Candado del lado servidor en el guardado del formulario.
 *  3. Ítems "Agregar listado" / "Mis listados" en el menú del dashboard
 *     para guests (reemplaza la antigua copia completa de
 *     template-dashboard.php en el tema hijo: mismo efecto vía hook).
 *  4. Plantillas sobrescritas en templates/ del plugin (las sirve el filtro
 *     listeo_core_template_paths registrado en el módulo Mascotas):
 *     listing-submit.php, listing-submit-type.php, account/my_listings.php
 *     — añaden `guest` a los roles y filtran los tipos visibles.
 *
 * NOTA producción: el TIPO "mascotas-perdidas" es un dato de la tabla
 * wp_listeo_listing_types (se crea en el panel de Listeo), y el registro de
 * usuarios con rol guest es configuración — ninguna de las dos viaja con
 * este plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * 1. Permisos por rol
 * ---------------------------------------------------------------------- */

/** Tipos de listado que puede publicar un guest (slugs de wp_listeo_listing_types). */
function pp_tipos_listado_guest() {
	return array( 'adopcion', 'mascotas-perdidas' );
}

/** ¿Puede este rol publicar este tipo de listado? */
function pp_tipo_listado_permitido_para_rol( $slug, $role ) {
	if ( 'guest' !== $role ) {
		return true;
	}
	return in_array( $slug, pp_tipos_listado_guest(), true );
}

/** Filtra la lista de tipos que se muestran en "Elige tipo de listado". */
function pp_filtrar_tipos_listado_por_rol( $types, $role ) {
	if ( 'guest' !== $role || ! is_array( $types ) ) {
		return $types;
	}
	$permitidos = pp_tipos_listado_guest();
	$filtrados  = array();
	foreach ( $types as $type ) {
		if ( isset( $type->slug ) && in_array( $type->slug, $permitidos, true ) ) {
			$filtrados[] = $type;
		}
	}
	return $filtrados;
}

/** Primer tipo permitido para un rol (fallback cuando llega sin elegir tipo). */
function pp_primer_tipo_listado_para_rol( $role ) {
	if ( 'guest' === $role ) {
		$permitidos = pp_tipos_listado_guest();
		return $permitidos[0];
	}
	return 'directorio';
}

/* -------------------------------------------------------------------------
 * 2. Candado del lado servidor: aunque alguien manipule el formulario, un
 *    guest no puede GUARDAR un listado de un tipo que no le corresponde.
 * ---------------------------------------------------------------------- */

add_filter( 'submit_listing_form_validate_fields', 'pp_validar_tipo_listado_por_rol', 10, 3 );
function pp_validar_tipo_listado_por_rol( $valido, $fields, $values ) {
	if ( is_wp_error( $valido ) || ! is_user_logged_in() ) {
		return $valido;
	}
	$user  = wp_get_current_user();
	$roles = (array) $user->roles;
	$role  = array_shift( $roles );

	// Tipo que se intenta guardar: viene en el POST o ya está en el borrador
	$tipo = '';
	if ( isset( $_POST['_listing_type'] ) ) {
		$tipo = sanitize_text_field( wp_unslash( $_POST['_listing_type'] ) );
	} elseif ( isset( $_POST['listing_id'] ) && absint( $_POST['listing_id'] ) ) {
		$tipo = get_post_meta( absint( $_POST['listing_id'] ), '_listing_type', true );
	}

	if ( $tipo && ! pp_tipo_listado_permitido_para_rol( $tipo, $role ) ) {
		return new WP_Error(
			'pp_tipo_no_permitido',
			'Este tipo de listado solo está disponible para cuentas de prestador de servicios.'
		);
	}
	return $valido;
}

/* -------------------------------------------------------------------------
 * 3. Menú del dashboard para guests: "Agregar listado" y "Mis listados".
 *    El template-dashboard.php de Listeo oculta su sección Listings a los
 *    guests; en vez de copiar esa plantilla de 27 KB para tocar una línea,
 *    inyectamos los dos ítems con el hook oficial del tema. Prioridad 20:
 *    después de la sección Mascotas (prioridad por defecto).
 * ---------------------------------------------------------------------- */

add_action( 'listeo/dashboard-menu/start', 'pp_listados_menu_dashboard_guest', 20 );
function pp_listados_menu_dashboard_guest() {
	$user  = wp_get_current_user();
	$roles = (array) $user->roles;
	$role  = array_shift( $roles );
	if ( 'guest' !== $role ) {
		return; // los demás roles ya tienen la sección Listings nativa
	}

	$submit_page   = (int) get_option( 'listeo_submit_page' );
	$listings_page = (int) get_option( 'listeo_listings_page' );
	if ( ! $submit_page && ! $listings_page ) {
		return;
	}

	$actual = get_queried_object_id();
	echo '<ul data-submenu-title="' . esc_attr__( 'Listings', 'listeo' ) . '">';
	if ( $submit_page ) {
		echo '<li' . ( $actual === $submit_page ? ' class="active"' : '' ) . '><a href="' . esc_url( get_permalink( $submit_page ) ) . '"><i class="sl sl-icon-plus"></i> ' . esc_html__( 'Add Listing', 'listeo' ) . '</a></li>';
	}
	if ( $listings_page ) {
		echo '<li' . ( $actual === $listings_page ? ' class="active"' : '' ) . '><a href="' . esc_url( get_permalink( $listings_page ) ) . '"><i class="sl sl-icon-layers"></i> ' . esc_html__( 'My Listings', 'listeo' ) . '</a></li>';
	}
	echo '</ul>';
}
