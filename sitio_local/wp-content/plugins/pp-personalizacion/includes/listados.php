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

/** Slugs que puede publicar un rol dado (según la config). Auxiliar propia
 *  del plugin (no existe en el tema): se define en la carga normal. */
function pp_listados_slugs_de_rol( $role ) {
	$permisos = pp_listados_permisos();
	return isset( $permisos[ $role ] ) ? (array) $permisos[ $role ] : array();
}

/* -------------------------------------------------------------------------
 * 1 + 2. Funciones PÚBLICAS de permisos y candado del guardado.
 *
 * ⚠️ Estas mismas funciones existían antes en el tema hijo (functions.php).
 * Tras la migración quedaron aquí, pero un sitio cuyo tema hijo AÚN tenga la
 * versión vieja las definiría dos veces → "Cannot redeclare function" (error
 * fatal al activar el plugin). Para blindarlo:
 *   - Se definen en `after_setup_theme` (corre DESPUÉS de cargar el tema), y
 *   - Se envuelven en `function_exists()`.
 * Así, si el tema ya las tiene, el plugin no las redefine (usa las del tema);
 * si el tema está limpio, las define el plugin (versión configurable).
 * pp-chat-listados las consume vía function_exists — NO renombrar.
 * ---------------------------------------------------------------------- */

add_action( 'after_setup_theme', 'pp_listados_definir_funciones_publicas', 1 );
function pp_listados_definir_funciones_publicas() {

	if ( ! function_exists( 'pp_tipos_listado_guest' ) ) {
		/** Tipos que puede publicar un guest. */
		function pp_tipos_listado_guest() {
			return pp_listados_slugs_de_rol( 'guest' );
		}
	}

	if ( ! function_exists( 'pp_tipo_listado_permitido_para_rol' ) ) {
		/** ¿Puede este rol publicar este tipo de listado? */
		function pp_tipo_listado_permitido_para_rol( $slug, $role ) {
			// Solo controlamos guest y owner; los demás roles ven todo.
			if ( ! array_key_exists( $role, pp_listados_roles_configurables() ) ) {
				return true;
			}
			return in_array( $slug, pp_listados_slugs_de_rol( $role ), true );
		}
	}

	if ( ! function_exists( 'pp_filtrar_tipos_listado_por_rol' ) ) {
		/** Filtra la lista de tipos que se muestran en "Elige tipo de listado". */
		function pp_filtrar_tipos_listado_por_rol( $types, $role ) {
			if ( ! array_key_exists( $role, pp_listados_roles_configurables() ) || ! is_array( $types ) ) {
				return $types; // roles no controlados: sin filtrar
			}
			$permitidos = pp_listados_slugs_de_rol( $role );
			$filtrados  = array();
			foreach ( $types as $type ) {
				if ( isset( $type->slug ) && in_array( $type->slug, $permitidos, true ) ) {
					$filtrados[] = $type;
				}
			}
			return $filtrados;
		}
	}

	if ( ! function_exists( 'pp_primer_tipo_listado_para_rol' ) ) {
		/** Primer tipo permitido para un rol (fallback cuando llega sin elegir tipo). */
		function pp_primer_tipo_listado_para_rol( $role ) {
			$permitidos = pp_listados_slugs_de_rol( $role );
			return ! empty( $permitidos ) ? $permitidos[0] : 'directorio';
		}
	}

	if ( ! function_exists( 'pp_validar_tipo_listado_por_rol' ) ) {
		/** Candado del guardado: rechaza un tipo no permitido para el rol. */
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

			if ( $tipo && function_exists( 'pp_tipo_listado_permitido_para_rol' )
				&& ! pp_tipo_listado_permitido_para_rol( $tipo, $role ) ) {
				return new WP_Error(
					'pp_tipo_no_permitido',
					'Tu tipo de cuenta no puede publicar este tipo de listado.'
				);
			}
			return $valido;
		}
	}

	// Registrar el candado (has_filter evita duplicarlo si el tema ya lo puso).
	if ( false === has_filter( 'submit_listing_form_validate_fields', 'pp_validar_tipo_listado_por_rol' ) ) {
		add_filter( 'submit_listing_form_validate_fields', 'pp_validar_tipo_listado_por_rol', 10, 3 );
	}
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

	// Toggle "Separar resultados" por tipo (el Directorio nunca se separa).
	$sep_marcados = isset( $_POST['pp_separar'] ) && is_array( $_POST['pp_separar'] )
		? array_map( 'sanitize_text_field', $_POST['pp_separar'] )
		: array();
	$sep_nuevo = array_values( array_diff( array_intersect( $activos, $sep_marcados ), array( 'directorio' ) ) );
	update_option( 'pp_listados_separar', $sep_nuevo );
	wp_safe_redirect( add_query_arg( 'pp_ok', 'guardado', admin_url( 'admin.php?page=pp-listados' ) ) );
	exit;
}

function pp_listados_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$roles     = pp_listados_roles_configurables();
	$tipos     = pp_listados_tipos_activos();
	$permisos  = pp_listados_permisos();
	$separados = pp_listados_tipos_separados();
	?>
	<div class="wrap">
		<h1>📋 Listados</h1>
		<p style="max-width:760px">Controla qué <strong>tipos de listado</strong> puede publicar cada tipo de cuenta, y qué tipos se muestran <strong>separados</strong> en el buscador. Con <strong>"Separar resultados"</strong> activo, ese tipo NO se mezcla con el Directorio: sus resultados (lista y mapa) solo aparecen al buscar por ese tipo (chip "+" del Directorio o sus páginas de categoría). Los administradores no se ven afectados en permisos (siguen viendo todo).</p>

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
							<th style="width:150px;text-align:center">Separar resultados</th>
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
								<td style="text-align:center">
									<?php if ( 'directorio' === $slug ) : ?>
										<span style="opacity:.5" title="El Directorio es la búsqueda base: los demás tipos se separan de él">—</span>
									<?php else :
										$sep_on = in_array( $slug, $separados, true ); ?>
										<label class="pp-switch" title="<?php echo esc_attr( $sep_on ? 'Separado del Directorio' : 'Mezclado con el Directorio' ); ?>">
											<input type="checkbox" name="pp_separar[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $sep_on ); ?>>
											<span class="pp-switch__track"><span class="pp-switch__thumb"></span></span>
										</label>
									<?php endif; ?>
								</td>
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

/* -------------------------------------------------------------------------
 * 6. SEPARAR RESULTADOS POR TIPO DE LISTADO (2026-07-05)
 *
 * Los tipos marcados con "Separar resultados" (opción `pp_listados_separar`,
 * por defecto adopción + mascotas perdidas) dejan de mezclarse con el
 * Directorio: buscar en el Directorio los excluye, y en el contexto de un
 * tipo separado SOLO se muestran los de ese tipo. El candado va en
 * `pre_get_posts` (prio 999) sobre la query principal del archivo/taxonomías
 * de listing Y sobre las queries del AJAX `listeo_get_listings` → la lista
 * y el mapa salen de la MISMA consulta (consistencia garantizada).
 *
 * Contexto de tipo:
 *  - parámetro `_listing_type` (lo envía el chip "+" del archivo, y el
 *    propio slider nativo de Listeo cuando trabaja por tipos), o
 *  - página de taxonomía `{tipo}_category` de un tipo separado, o
 *  - petición AJAX que llegue con `tax-{tipo}_category`.
 *
 * El front (js/pp-listados-separar.js) añade el chip "+" junto al botón de
 * filtros del archivo y cambia el carrusel superior a las categorías del
 * tipo elegido (en páginas de categoría de un tipo separado, el carrusel se
 * cambia sin chip: el contexto es fijo, decisión de Miguel 2026-07-05).
 * ---------------------------------------------------------------------- */

/** Tipos con "Separar resultados" activado (solo tipos activos; el
 *  Directorio es la base y nunca se separa). */
function pp_listados_tipos_separados() {
	$guardado = get_option( 'pp_listados_separar', null );
	if ( ! is_array( $guardado ) ) {
		$guardado = array( 'adopcion', 'mascotas-perdidas' ); // valor de fábrica
	}
	$guardado = array_diff( array_map( 'sanitize_key', $guardado ), array( 'directorio' ) );
	$activos  = pp_listados_tipos_activos_slugs();
	return $activos ? array_values( array_intersect( $activos, $guardado ) ) : array_values( $guardado );
}

/** ¿La taxonomía consultada pertenece al CPT listing? */
function pp_listados_query_es_tax_listing( $query ) {
	$qo = $query->get_queried_object();
	if ( ! $qo || empty( $qo->taxonomy ) ) {
		return false;
	}
	return in_array( $qo->taxonomy, get_object_taxonomies( 'listing' ), true );
}

/** Contexto de tipo separado de la petición actual ('' = base/Directorio). */
function pp_listados_contexto_tipo() {
	$sep = pp_listados_tipos_separados();
	if ( ! $sep ) {
		return '';
	}
	// 1) Parámetro explícito (chip "+", slider nativo o URL).
	if ( isset( $_REQUEST['_listing_type'] ) && ! is_array( $_REQUEST['_listing_type'] ) ) {
		$param = sanitize_key( wp_unslash( $_REQUEST['_listing_type'] ) );
		if ( in_array( $param, $sep, true ) ) {
			return $param;
		}
	}
	// 1b) Query var (rutas bonitas /adopcion/, /mascotas-perdidas/ → rewrite).
	if ( ! wp_doing_ajax() && function_exists( 'get_query_var' ) ) {
		$qv = sanitize_key( (string) get_query_var( '_listing_type' ) );
		if ( $qv && in_array( $qv, $sep, true ) ) {
			return $qv;
		}
	}
	// 2) AJAX con la taxonomía de un tipo separado.
	if ( wp_doing_ajax() ) {
		foreach ( $sep as $tipo ) {
			if ( ! empty( $_REQUEST[ 'tax-' . $tipo . '_category' ] ) ) {
				return $tipo;
			}
		}
		return '';
	}
	// 3) Página de taxonomía de un tipo separado ({tipo}_category).
	if ( is_tax() ) {
		$qo  = get_queried_object();
		$tax = ( $qo && ! empty( $qo->taxonomy ) ) ? $qo->taxonomy : '';
		foreach ( $sep as $tipo ) {
			if ( $tax === $tipo . '_category' ) {
				return $tipo;
			}
		}
	}
	return '';
}

/** Candado: filtra la query según el contexto (lista y mapa juntos). */
add_action( 'pre_get_posts', 'pp_listados_separar_query', 999 );
function pp_listados_separar_query( $query ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return;
	}
	$sep = pp_listados_tipos_separados();
	if ( ! $sep ) {
		return;
	}

	if ( wp_doing_ajax() ) {
		// Solo las queries de listing del buscador AJAX de Listeo.
		if ( ! isset( $_REQUEST['action'] ) || 'listeo_get_listings' !== $_REQUEST['action'] ) {
			return;
		}
		if ( 'listing' !== $query->get( 'post_type' ) ) {
			return;
		}
	} else {
		// Solo la query principal del archivo de listados o de sus taxonomías.
		if ( ! $query->is_main_query() ) {
			return;
		}
		$es_archivo = $query->is_post_type_archive( 'listing' );
		$es_tax     = $query->is_tax() && pp_listados_query_es_tax_listing( $query );
		if ( ! $es_archivo && ! $es_tax ) {
			return;
		}
	}

	$contexto = pp_listados_contexto_tipo();
	$meta     = $query->get( 'meta_query' );
	$meta     = is_array( $meta ) ? $meta : array();
	if ( $contexto ) {
		// Contexto de un tipo separado: SOLO ese tipo.
		$meta[] = array(
			'key'     => '_listing_type',
			'value'   => $contexto,
			'compare' => '=',
		);
	} else {
		// Contexto base (Directorio): excluir los tipos separados.
		$meta[] = array(
			'relation' => 'OR',
			array(
				'key'     => '_listing_type',
				'value'   => $sep,
				'compare' => 'NOT IN',
			),
			array(
				'key'     => '_listing_type',
				'compare' => 'NOT EXISTS',
			),
		);
	}
	$query->set( 'meta_query', $meta );
}

/** Ítems del carrusel (términos raíz) para una taxonomía de categoría,
 *  replicando las metas de icono que usa el carrusel nativo de Listeo. */
function pp_listados_items_carrusel( $taxonomy ) {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}
	$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'parent' => 0 ) );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return array();
	}
	$items = array();
	foreach ( $terms as $t ) {
		$icon = '';
		$svg  = get_term_meta( $t->term_id, '_icon_svg', true );
		if ( $svg && function_exists( 'listeo_smart_svg_render' ) ) {
			$out = listeo_smart_svg_render( $svg );
			if ( $out ) {
				$icon = '<i class="listeo-svg-icon-box-grid">' . $out . '</i>';
			}
		}
		if ( ! $icon ) {
			$fa = get_term_meta( $t->term_id, 'icon', true );
			if ( $fa && 'emtpy' !== $fa ) {
				$icon = ( 0 === strpos( $fa, 'im ' ) )
					? '<i class="' . esc_attr( $fa ) . '"></i>'
					: '<i class="fa ' . esc_attr( $fa ) . '"></i>';
			}
		}
		if ( ! $icon ) {
			$icon = '<i class="fa fa-paw"></i>';
		}
		$link    = get_term_link( $t );
		$items[] = array(
			'nombre' => $t->name,
			'slug'   => $t->slug,
			'icono'  => $icon,
			'url'    => is_wp_error( $link ) ? '' : $link,
		);
	}
	return $items;
}

/* ---- Rutas bonitas por tipo separado: /adopcion/, /mascotas-perdidas/ ----
 * Una regla de reescritura por cada tipo con "Separar resultados" activo:
 * /{tipo}/ (y su paginación) carga el archivo de listados YA filtrado por
 * ese tipo (query var `_listing_type` → el candado y el chip la reconocen).
 * Anti-choque: si existe una página/entrada con ese slug, NO se crea la
 * regla (el contenido real gana). El flush de reglas corre solo cuando
 * cambia la configuración (firma en `pp_listados_rutas_firma`). */
add_filter( 'query_vars', 'pp_listados_query_var_tipo' );
function pp_listados_query_var_tipo( $vars ) {
	if ( ! in_array( '_listing_type', $vars, true ) ) {
		$vars[] = '_listing_type';
	}
	return $vars;
}

add_action( 'init', 'pp_listados_rutas_por_tipo', 20 );
function pp_listados_rutas_por_tipo() {
	$sep    = pp_listados_tipos_separados();
	$reglas = array();
	foreach ( $sep as $tipo ) {
		// El contenido real con ese slug gana: no crear la regla si choca.
		if ( get_page_by_path( $tipo ) ) {
			continue;
		}
		add_rewrite_rule(
			'^' . $tipo . '/?$',
			'index.php?post_type=listing&_listing_type=' . $tipo,
			'top'
		);
		add_rewrite_rule(
			'^' . $tipo . '/page/([0-9]{1,})/?$',
			'index.php?post_type=listing&_listing_type=' . $tipo . '&paged=$matches[1]',
			'top'
		);
		$reglas[] = $tipo;
	}
	// Regenerar las reglas SOLO cuando cambie la configuración.
	$firma = md5( wp_json_encode( $reglas ) );
	if ( get_option( 'pp_listados_rutas_firma' ) !== $firma ) {
		flush_rewrite_rules( false );
		update_option( 'pp_listados_rutas_firma', $firma );
	}
}

/* Título del archivo según contexto: /adopcion/ muestra "Adopción" (no
 * "Directorio"). Prio 20: corre después del filtro del tema hijo que fija
 * "Directorio" como título del archivo de listados. */
add_filter( 'pre_option_listeo_listings_archive_title', 'pp_listados_titulo_contexto', 20 );
function pp_listados_titulo_contexto( $valor ) {
	if ( is_admin() ) {
		return $valor;
	}
	$contexto = pp_listados_contexto_tipo();
	if ( ! $contexto ) {
		return $valor;
	}
	foreach ( pp_listados_tipos_activos() as $t ) {
		if ( $t->slug === $contexto && ! empty( $t->name ) ) {
			return $t->name;
		}
	}
	return $valor;
}

/** Assets del front: chip "+" en el BUSCADOR PRINCIPAL del header (todo el
 *  sitio — la selección modifica la búsqueda general) + carrusel contextual
 *  en el archivo y en las páginas de categoría de tipos separados. */
add_action( 'wp_enqueue_scripts', 'pp_listados_separar_assets', 140 );
function pp_listados_separar_assets() {
	if ( is_admin() ) {
		return;
	}
	$sep = pp_listados_tipos_separados();
	if ( ! $sep ) {
		return;
	}

	$js = PP_PERS_DIR . 'js/pp-listados-separar.js';
	if ( ! file_exists( $js ) ) {
		return;
	}

	$es_archivo  = is_post_type_archive( 'listing' );
	$es_tax_tipo = false; // ¿página de categoría de un TIPO SEPARADO?
	$term_slug   = '';
	if ( is_tax() ) {
		$qo = get_queried_object();
		if ( $qo && ! empty( $qo->taxonomy ) ) {
			$term_slug = $qo->slug;
			foreach ( $sep as $tipo ) {
				if ( $qo->taxonomy === $tipo . '_category' ) {
					$es_tax_tipo = true;
					break;
				}
			}
		}
	}

	// Nombres visibles de los tipos separados.
	$nombres = array();
	foreach ( pp_listados_tipos_activos() as $t ) {
		if ( in_array( $t->slug, $sep, true ) ) {
			$nombres[ $t->slug ] = $t->name;
		}
	}

	// Carrusel por tipo separado (términos raíz de {tipo}_category).
	$carruseles = array();
	foreach ( $sep as $tipo ) {
		$carruseles[ $tipo ] = pp_listados_items_carrusel( $tipo . '_category' );
	}

	// URL destino de la búsqueda de listados (por si el form del header
	// necesita crearse el destino al vuelo — normalmente ya apunta ahí).
	$archivo_url = get_post_type_archive_link( 'listing' );

	wp_enqueue_script( 'pp-listados-separar', PP_PERS_URL . 'js/pp-listados-separar.js', array( 'jquery' ), filemtime( $js ), true );
	$css = PP_PERS_DIR . 'css/pp-listados-separar.css';
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'pp-listados-separar', PP_PERS_URL . 'css/pp-listados-separar.css', array(), filemtime( $css ) );
	}
	wp_localize_script( 'pp-listados-separar', 'PP_LISTADOS_SEP', array(
		'contexto'   => pp_listados_contexto_tipo(),
		'esArchivo'  => $es_archivo ? 1 : 0,
		'esTaxTipo'  => $es_tax_tipo ? 1 : 0,
		'tipos'      => $nombres ? $nombres : new stdClass(),
		'carruseles' => $carruseles ? $carruseles : new stdClass(),
		'termActual' => $term_slug,
		'archivoUrl' => $archivo_url ? $archivo_url : '',
	) );
}
