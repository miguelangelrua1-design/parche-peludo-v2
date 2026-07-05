<?php
/**
 * Módulo LISTADOS del plugin Personalización Parche.
 *
 * "Listados por rol" (migrado del tema hijo el 2026-07-05): controla qué
 * tipos de listado puede publicar cada rol. Listeo no trae esta opción: sus
 * plantillas bloquean por rol de forma fija y el guardado solo valida login.
 *
 * v2.4.0 (2026-07-05): CONFIGURABLE desde wp-admin → Personalización Parche →
 * Listados. Una matriz Tipo de listado × Rol (Guest / Owner) con toggles;
 * se guarda en la opción `pp_listados_permisos`. Los roles administrativos
 * (admin, seller, etc.) no se tocan: siguen viendo todos los tipos.
 *
 * Piezas:
 *  1. Helpers de permisos por rol (leen la config; defaults sensatos).
 *  2. Candado del lado servidor en el guardado del formulario.
 *  3. Ítems "Agregar listado" / "Mis listados" en el menú del dashboard
 *     para guests (hook listeo/dashboard-menu/start).
 *  4. Plantillas sobrescritas en templates/ del plugin.
 *  5. Página de administración con los toggles.
 *
 * Los helpers conservan sus nombres: pp-chat-listados los usa vía
 * function_exists — NO renombrar.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * 0. Configuración: qué roles se administran y los tipos de listado activos
 * ---------------------------------------------------------------------- */

/** Roles cuya publicación se controla desde aquí (por ahora solo estos dos). */
function pp_listados_roles_configurables() {
	return array(
		'guest' => 'Usuario (Guest)',
		'owner' => 'Prestador (Owner)',
	);
}

/** Tipos de listado ACTIVOS del sitio, como objetos {slug, name}. */
function pp_listados_tipos_activos() {
	if ( function_exists( 'listeo_core_custom_listing_types' ) ) {
		$mgr = listeo_core_custom_listing_types();
		$tipos = $mgr->get_listing_types( true ); // solo activos
		return is_array( $tipos ) ? $tipos : array();
	}
	return array();
}

/** Slugs de todos los tipos de listado activos. */
function pp_listados_tipos_activos_slugs() {
	return array_values( array_filter( array_map( function ( $t ) {
		return isset( $t->slug ) ? $t->slug : null;
	}, pp_listados_tipos_activos() ) ) );
}

/**
 * Configuración de permisos por rol. Estructura:
 *   array( 'guest' => array(slugs…), 'owner' => array(slugs…) )
 * Si aún no se ha guardado, devuelve los valores por defecto:
 *   guest = adopción + mascotas perdidas · owner = todos los tipos activos.
 */
function pp_listados_permisos() {
	$guardado = get_option( 'pp_listados_permisos' );
	if ( is_array( $guardado ) && isset( $guardado['guest'] ) && isset( $guardado['owner'] ) ) {
		return array(
			'guest' => array_values( (array) $guardado['guest'] ),
			'owner' => array_values( (array) $guardado['owner'] ),
		);
	}
	// Defaults
	$activos = pp_listados_tipos_activos_slugs();
	$defaults_guest = array( 'adopcion', 'mascotas-perdidas' );
	return array(
		'guest' => array_values( array_intersect( $defaults_guest, $activos ?: $defaults_guest ) ) ?: $defaults_guest,
		'owner' => $activos, // todos los tipos activos
	);
}

/* -------------------------------------------------------------------------
 * 1. Permisos por rol (leen la configuración)
 * ---------------------------------------------------------------------- */

/** Slugs que puede publicar un rol dado (según la config). */
function pp_tipos_listado_permitidos_para_rol( $role ) {
	$permisos = pp_listados_permisos();
	return isset( $permisos[ $role ] ) ? (array) $permisos[ $role ] : array();
}

/** Tipos que puede publicar un guest (compat: pp-chat-listados lo usa). */
function pp_tipos_listado_guest() {
	return pp_tipos_listado_permitidos_para_rol( 'guest' );
}

/** ¿Puede este rol publicar este tipo de listado? */
function pp_tipo_listado_permitido_para_rol( $slug, $role ) {
	// Solo controlamos guest y owner; los demás roles ven todo.
	if ( ! array_key_exists( $role, pp_listados_roles_configurables() ) ) {
		return true;
	}
	return in_array( $slug, pp_tipos_listado_permitidos_para_rol( $role ), true );
}

/** Filtra la lista de tipos que se muestran en "Elige tipo de listado". */
function pp_filtrar_tipos_listado_por_rol( $types, $role ) {
	if ( ! array_key_exists( $role, pp_listados_roles_configurables() ) || ! is_array( $types ) ) {
		return $types; // roles no controlados: sin filtrar
	}
	$permitidos = pp_tipos_listado_permitidos_para_rol( $role );
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
	$permitidos = pp_tipos_listado_permitidos_para_rol( $role );
	if ( ! empty( $permitidos ) ) {
		return $permitidos[0];
	}
	return 'directorio';
}

/* -------------------------------------------------------------------------
 * 2. Candado del lado servidor: aunque alguien manipule el formulario, no
 *    puede GUARDAR un listado de un tipo que no le corresponde a su rol.
 * ---------------------------------------------------------------------- */

add_filter( 'submit_listing_form_validate_fields', 'pp_validar_tipo_listado_por_rol', 10, 3 );
function pp_validar_tipo_listado_por_rol( $valido, $fields, $values ) {
	if ( is_wp_error( $valido ) || ! is_user_logged_in() ) {
		return $valido;
	}
	$user  = wp_get_current_user();
	$roles = (array) $user->roles;
	$role  = array_shift( $roles );

	$tipo = '';
	if ( isset( $_POST['_listing_type'] ) ) {
		$tipo = sanitize_text_field( wp_unslash( $_POST['_listing_type'] ) );
	} elseif ( isset( $_POST['listing_id'] ) && absint( $_POST['listing_id'] ) ) {
		$tipo = get_post_meta( absint( $_POST['listing_id'] ), '_listing_type', true );
	}

	if ( $tipo && ! pp_tipo_listado_permitido_para_rol( $tipo, $role ) ) {
		return new WP_Error(
			'pp_tipo_no_permitido',
			'Tu tipo de cuenta no puede publicar este tipo de listado.'
		);
	}
	return $valido;
}

/* -------------------------------------------------------------------------
 * 3. Menú del dashboard para guests: "Agregar listado" y "Mis listados".
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

/* -------------------------------------------------------------------------
 * 5. Página de administración: Personalización Parche → Listados
 *    Matriz Tipo de listado × Rol (Guest / Owner) con toggles.
 * ---------------------------------------------------------------------- */

add_action( 'admin_init', 'pp_listados_admin_handler' );
function pp_listados_admin_handler() {
	if ( empty( $_POST['pp_listados_action'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'pp_listados_admin', 'pp_listados_nonce' );

	$activos = pp_listados_tipos_activos_slugs();
	$marcados = isset( $_POST['pp_permisos'] ) && is_array( $_POST['pp_permisos'] ) ? $_POST['pp_permisos'] : array();

	$nuevo = array();
	foreach ( array_keys( pp_listados_roles_configurables() ) as $role ) {
		$de_rol = isset( $marcados[ $role ] ) ? array_map( 'sanitize_text_field', (array) $marcados[ $role ] ) : array();
		// Solo slugs que existan y estén activos
		$nuevo[ $role ] = array_values( array_intersect( $activos, $de_rol ) );
	}

	update_option( 'pp_listados_permisos', $nuevo );
	wp_safe_redirect( add_query_arg( 'pp_ok', 'guardado', admin_url( 'admin.php?page=pp-listados' ) ) );
	exit;
}

function pp_listados_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$roles    = pp_listados_roles_configurables();
	$tipos    = pp_listados_tipos_activos();
	$permisos = pp_listados_permisos();
	?>
	<div class="wrap">
		<h1>📋 Listados</h1>
		<p style="max-width:760px">Controla qué <strong>tipos de listado</strong> puede publicar cada tipo de cuenta. Activa o desactiva con cada interruptor; el cambio afecta lo que el usuario ve en "Agregar listado" y lo que el servidor permite guardar. Los administradores no se ven afectados (siguen viendo todo).</p>

		<?php if ( 'guardado' === sanitize_key( $_GET['pp_ok'] ?? '' ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Permisos de publicación guardados.</p></div>
		<?php endif; ?>

		<?php if ( empty( $tipos ) ) : ?>
			<div class="notice notice-warning"><p>No hay tipos de listado activos en el sitio. Actívalos en el panel de Listeo (Listing Types) y vuelve aquí.</p></div>
		<?php else : ?>
			<form method="post" style="max-width:640px;margin-top:8px">
				<?php wp_nonce_field( 'pp_listados_admin', 'pp_listados_nonce' ); ?>
				<input type="hidden" name="pp_listados_action" value="guardar">

				<table class="widefat striped" style="max-width:640px">
					<thead>
						<tr>
							<th style="width:auto">Tipo de listado</th>
							<?php foreach ( $roles as $etiqueta ) : ?>
								<th style="width:150px;text-align:center"><?php echo esc_html( $etiqueta ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $tipos as $tipo ) :
							$slug = $tipo->slug;
							$nombre = isset( $tipo->name ) ? $tipo->name : $slug; ?>
							<tr>
								<td><strong><?php echo esc_html( $nombre ); ?></strong> <code style="opacity:.6"><?php echo esc_html( $slug ); ?></code></td>
								<?php foreach ( array_keys( $roles ) as $role ) :
									$on = in_array( $slug, (array) $permisos[ $role ], true ); ?>
									<td style="text-align:center">
										<label class="pp-switch" title="<?php echo esc_attr( $on ? 'Permitido' : 'Bloqueado' ); ?>">
											<input type="checkbox" name="pp_permisos[<?php echo esc_attr( $role ); ?>][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $on ); ?>>
											<span class="pp-switch__track"><span class="pp-switch__thumb"></span></span>
										</label>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p style="margin-top:18px"><button class="button button-primary">Guardar permisos</button></p>
			</form>

			<p class="description" style="max-width:640px">Recuerda: para que un usuario pueda publicar, además de activar aquí el tipo, ese tipo de listado debe existir y estar activo en el panel de Listeo, y el usuario debe tener el rol correspondiente.</p>
		<?php endif; ?>
	</div>

	<style>
		.pp-switch { display:inline-flex; cursor:pointer; }
		.pp-switch input { position:absolute; opacity:0; width:0; height:0; }
		.pp-switch__track { width:42px; height:24px; border-radius:999px; background:#c3c4c7; position:relative; transition:background .15s ease; }
		.pp-switch__thumb { position:absolute; top:3px; left:3px; width:18px; height:18px; border-radius:50%; background:#fff; transition:left .15s ease; box-shadow:0 1px 2px rgba(0,0,0,.25); }
		.pp-switch input:checked + .pp-switch__track { background:#2271b1; }
		.pp-switch input:checked + .pp-switch__track .pp-switch__thumb { left:21px; }
		.pp-switch input:focus-visible + .pp-switch__track { outline:2px solid #2271b1; outline-offset:2px; }
	</style>
	<?php
}
