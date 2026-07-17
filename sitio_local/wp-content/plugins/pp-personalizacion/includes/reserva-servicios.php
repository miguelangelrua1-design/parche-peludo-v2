<?php
/**
 * Módulo: Reserva por Servicios (flujo servicio → agenda).
 *
 * Reordena la experiencia de reserva: tras elegir la mascota, el cliente
 * elige el TIPO de servicio (menú), el profesional si aplica y los
 * servicios; solo después ve la agenda. Reutiliza los Recursos de Listeo
 * Booking Plus como motor de agendas:
 *
 *   - Sin recursos            → una sola agenda (flujo actual).
 *   - Recurso auto-agenda     → agenda propia POR TIPO de servicio
 *                               (checkbox "agenda diferente" en el menú).
 *   - Recursos profesionales  → agenda por profesional, cada uno asignado
 *                               a un tipo de servicio (meta _pp_tipologia).
 *
 * TODO el módulo cuelga del kill-switch `pp_serv_flujo_servicios` (pestaña
 * Personalizaciones de Servicios). Apagado: ni un asset ni un hook de
 * frontend actúan y el flujo nativo de Booking Plus queda intacto. El JS
 * está construido para degradar: si algún selector nativo falta (p. ej.
 * tras una actualización del plugin), el paso no se monta y la reserva
 * sigue por el camino nativo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * Kill-switch
 * ========================================================================= */

/** ¿El flujo "Reserva por Servicios" está activo (opción + Booking Plus presente)? */
function pp_rs_habilitado() {
	return 'on' === get_option( 'pp_serv_flujo_servicios', '' )
		&& function_exists( 'lbp_get_active_resources' );
}

/** Sección de la pestaña Personalizaciones: activar/desactivar el flujo. */
function pp_rs_admin_seccion() {
	$activo = 'on' === get_option( 'pp_serv_flujo_servicios', '' );
	$lbp_ok = function_exists( 'lbp_get_active_resources' );
	?>
	<div style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px;max-width:720px;margin-top:14px">
		<h3 style="margin-top:0">Flujo de reserva por servicios <?php echo $activo ? '<span style="color:#00a32a;font-size:12px">● Activo</span>' : '<span style="color:#787c82;font-size:12px">○ Inactivo</span>'; ?></h3>
		<p>Cambia el orden del popup de reserva: <strong>Mascota → Servicios → Fecha y hora → Confirmar</strong>. En el paso Servicios el cliente elige el tipo de servicio, el profesional (si el listado tiene) y los servicios; la agenda que ve después es la que corresponde a esa elección.</p>
		<ul style="list-style:disc;padding-left:20px">
			<li>Los <strong>profesionales</strong> son Recursos de Booking Plus asignados a un tipo de servicio (campo "Tipo de servicio que atiende" al crear/editar el profesional).</li>
			<li>Un menú puede tener <strong>agenda propia</strong> (checkbox al crear el listado): se crea una agenda administrable en la página "Gestionar recursos".</li>
			<li>Si un listado no tiene profesionales ni agendas propias, todo funciona con su única agenda, como hoy.</li>
		</ul>
		<?php if ( ! $lbp_ok ) : ?>
			<p style="color:#b32d2e"><strong>Booking Plus no está activo (o sin licencia premium):</strong> el flujo no puede activarse.</p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'pp_rs_toggle' ); ?>
			<input type="hidden" name="action" value="pp_rs_toggle">
			<input type="hidden" name="pp_rs_estado" value="<?php echo $activo ? 'off' : 'on'; ?>">
			<button type="submit" class="button <?php echo $activo ? '' : 'button-primary'; ?>" <?php disabled( ! $lbp_ok && ! $activo ); ?>>
				<?php echo $activo ? 'Desactivar flujo por servicios' : 'Activar flujo por servicios'; ?>
			</button>
			<p class="description" style="margin-top:8px">Al desactivar, las agendas automáticas por tipo de servicio se pausan (el paso nativo de Booking Plus no mostrará tarjetas de agenda) y el popup vuelve al flujo nativo. Al reactivar, se reanudan.</p>
		</form>
	</div>
	<?php
}

add_action( 'admin_post_pp_rs_toggle', 'pp_rs_toggle_handler' );
function pp_rs_toggle_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'No autorizado' );
	}
	check_admin_referer( 'pp_rs_toggle' );

	$nuevo = ( 'on' === ( $_POST['pp_rs_estado'] ?? '' ) ) ? 'on' : '';
	update_option( 'pp_serv_flujo_servicios', $nuevo );

	// Pausar/reanudar las agendas automáticas para que el paso nativo de
	// recursos no muestre tarjetas "Agenda · X" con el flujo apagado.
	pp_rs_pausar_agendas_auto( '' === $nuevo );

	wp_safe_redirect( admin_url( 'admin.php?page=pp-servicios&tab=extras&pp_msg=' . ( $nuevo ? 'rs_on' : 'rs_off' ) ) );
	exit;
}

/**
 * Pausa (o reanuda) TODOS los recursos auto-agenda creados por este módulo.
 * Solo toca recursos marcados con _pp_auto_agenda; jamás profesionales.
 */
function pp_rs_pausar_agendas_auto( $pausar ) {
	$agendas = get_posts( array(
		'post_type'      => 'lbp_resource',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
		'meta_key'       => '_pp_auto_agenda',
		'meta_value'     => '1',
	) );
	foreach ( $agendas as $rid ) {
		if ( $pausar ) {
			update_post_meta( $rid, '_lbp_resource_paused', '1' );
			update_post_meta( $rid, '_pp_auto_pausado', '1' );
		} elseif ( get_post_meta( $rid, '_pp_auto_pausado', true ) ) {
			delete_post_meta( $rid, '_lbp_resource_paused' );
			delete_post_meta( $rid, '_pp_auto_pausado' );
		}
	}
}

/* =========================================================================
 * Datos: tipologías del listado (menús + índices + agendas + profesionales)
 * ========================================================================= */

/**
 * Tipologías del listado para el paso Servicios del popup.
 *
 * Devuelve array de:
 *   slug     → slug de la tipología (catálogo pp_serv_tipos)
 *   nombre   → nombre visible
 *   indices  → índices planos de sus servicios reservables (paridad EXACTA
 *              con listeo_get_bookable_services(): isset($el['bookable']))
 *   agenda   → ID del recurso auto-agenda del tipo (0 = usa la del listado)
 *
 * Varios menús con la MISMA tipología se fusionan en una entrada.
 */
function pp_rs_tipologias_listado( $listing_id ) {
	$menu = get_post_meta( $listing_id, '_menu', true );
	$out  = array();
	if ( ! is_array( $menu ) ) {
		return $out;
	}
	$catalogo = function_exists( 'pp_serv_tipos' ) ? pp_serv_tipos() : array();

	$ix = 0;
	foreach ( $menu as $g ) {
		if ( ! is_array( $g ) || empty( $g['menu_elements'] ) || ! is_array( $g['menu_elements'] ) ) {
			continue;
		}
		$slug    = sanitize_key( $g['pp_tipo_servicio'] ?? '' );
		$indices = array();
		foreach ( $g['menu_elements'] as $el ) {
			if ( ! is_array( $el ) || ! isset( $el['bookable'] ) ) {
				continue; // misma condición que listeo_get_bookable_services()
			}
			$indices[] = $ix;
			$ix++;
		}
		if ( ! $indices ) {
			continue; // menú sin servicios reservables → no es un "tipo" reservable
		}
		if ( '' === $slug ) {
			// Menú sin tipología (dato viejo): agrúpalo bajo el título del menú.
			$slug = sanitize_key( sanitize_title( $g['menu_title'] ?? '' ) ) ?: 'otros';
		}
		if ( ! isset( $out[ $slug ] ) ) {
			$out[ $slug ] = array(
				'slug'    => $slug,
				'nombre'  => isset( $catalogo[ $slug ]['nombre'] )
					? $catalogo[ $slug ]['nombre']
					: ( sanitize_text_field( $g['menu_title'] ?? '' ) ?: $slug ),
				'indices' => array(),
				'agenda'  => 0,
			);
		}
		$out[ $slug ]['indices'] = array_merge( $out[ $slug ]['indices'], $indices );
	}

	// Vincular agendas automáticas existentes (recurso _pp_auto_agenda por tipo).
	if ( $out ) {
		foreach ( pp_rs_agendas_del_listado( $listing_id ) as $tipo => $rid ) {
			if ( isset( $out[ $tipo ] ) ) {
				$out[ $tipo ]['agenda'] = (int) $rid;
			}
		}
	}

	return array_values( $out );
}

/** Mapa tipología → ID de recurso auto-agenda ACTIVO del listado. */
function pp_rs_agendas_del_listado( $listing_id ) {
	$mapa = array();
	$ids  = get_post_meta( (int) $listing_id, '_lbp_resources', true );
	if ( ! is_array( $ids ) ) {
		return $mapa;
	}
	foreach ( $ids as $rid ) {
		$rid = (int) $rid;
		if ( $rid <= 0 || ! get_post_meta( $rid, '_pp_auto_agenda', true ) ) {
			continue;
		}
		if ( 'publish' !== get_post_status( $rid ) || get_post_meta( $rid, '_lbp_resource_paused', true ) ) {
			continue;
		}
		$tipo = sanitize_key( get_post_meta( $rid, '_pp_tipologia', true ) );
		if ( $tipo ) {
			$mapa[ $tipo ] = $rid;
		}
	}
	return $mapa;
}

/** Mapa recurso profesional (NO auto-agenda) → tipología asignada ('' = todas). */
function pp_rs_profesionales_del_listado( $listing_id ) {
	$mapa = array();
	$ids  = get_post_meta( (int) $listing_id, '_lbp_resources', true );
	if ( ! is_array( $ids ) ) {
		return $mapa;
	}
	foreach ( $ids as $rid ) {
		$rid = (int) $rid;
		if ( $rid <= 0 || get_post_meta( $rid, '_pp_auto_agenda', true ) ) {
			continue;
		}
		$mapa[ $rid ] = sanitize_key( get_post_meta( $rid, '_pp_tipologia', true ) );
	}
	return $mapa;
}

/* =========================================================================
 * Campo "Tipo de servicio que atiende" en el RECURSO (profesional)
 * ========================================================================= */

/** Opciones del select: tipologías del catálogo ('' = atiende todos). */
function pp_rs_opciones_tipologia() {
	$ops = array( '' => 'Todos los tipos de servicio' );
	if ( function_exists( 'pp_serv_tipos' ) ) {
		foreach ( pp_serv_tipos() as $slug => $t ) {
			$ops[ $slug ] = $t['nombre'];
		}
	}
	return $ops;
}

/* Form FRONTEND de recursos (página "Gestionar recursos" del owner). */
add_filter( 'lbp_resource_form_fields', 'pp_rs_campo_form_recurso', 20, 2 );
function pp_rs_campo_form_recurso( $fields, $listing_id ) {
	if ( ! pp_rs_habilitado() || ! is_array( $fields ) ) {
		return $fields;
	}
	$seccion = array(
		'title'  => 'Tipo de servicio',
		'icon'   => 'fa fa-tag',
		'fields' => array(
			'_pp_tipologia' => array(
				'label'          => 'Tipo de servicio que atiende',
				'type'           => 'select',
				'name'           => '_pp_tipologia',
				'options'        => pp_rs_opciones_tipologia(),
				'required'       => false,
				'render_row_col' => 12,
				'tooltip'        => 'En el popup de reserva, este profesional aparecerá solo cuando el cliente elija este tipo de servicio. "Todos" = aparece en cualquier tipo.',
			),
		),
	);
	// Insertarla tras la sección básica (primera) para que quede visible arriba.
	$pos = 1;
	$out = array_slice( $fields, 0, $pos, true )
		+ array( 'pp_tipologia' => $seccion )
		+ array_slice( $fields, $pos, null, true );
	return $out;
}

/* Guardado del campo (form frontend Y admin: cualquier save del CPT). */
add_action( 'save_post_lbp_resource', 'pp_rs_guardar_tipologia_recurso', 20, 1 );
function pp_rs_guardar_tipologia_recurso( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['_pp_tipologia'] ) ) {
		return; // el form no traía el campo (p. ej. flujo apagado) → no tocar
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$tipo = sanitize_key( wp_unslash( $_POST['_pp_tipologia'] ) );
	if ( $tipo && function_exists( 'pp_serv_tipos' ) && ! isset( pp_serv_tipos()[ $tipo ] ) ) {
		$tipo = '';
	}
	if ( $tipo ) {
		update_post_meta( $post_id, '_pp_tipologia', $tipo );
	} else {
		delete_post_meta( $post_id, '_pp_tipologia' );
	}
}

/* Metabox ADMIN (CMB2, mismo stack que el resto del plugin). */
add_action( 'cmb2_admin_init', 'pp_rs_metabox_recurso' );
function pp_rs_metabox_recurso() {
	// Con el flujo apagado la tipología no se usa en ninguna parte: no
	// mostrar un campo que no hace nada (mismo criterio que el form del owner).
	if ( ! function_exists( 'new_cmb2_box' ) || ! pp_rs_habilitado() ) {
		return;
	}
	$cmb = new_cmb2_box( array(
		'id'           => 'pp_rs_recurso_tipologia',
		'title'        => 'Personalización Parche — Tipo de servicio',
		'object_types' => array( 'lbp_resource' ),
		'context'      => 'side',
		'priority'     => 'default',
	) );
	$cmb->add_field( array(
		'name'       => 'Tipo de servicio que atiende',
		'id'         => '_pp_tipologia',
		'type'       => 'select',
		'options_cb' => 'pp_rs_opciones_tipologia',
		'desc'       => 'El profesional aparece en el paso Servicios solo para este tipo. "Todos" = cualquier tipo.',
	) );
}

/* =========================================================================
 * Agendas automáticas por tipo de servicio (checkbox del menú)
 * ========================================================================= */

/**
 * Sincroniza las agendas automáticas al guardar un listado.
 * Lee `_menu[i][pp_agenda_propia]` (guardado por Listeo como parte de _menu):
 * cada menú marcado y con tipología crea/reanuda un recurso auto-agenda;
 * los tipos que dejan de estar marcados se PAUSAN (no se borran: pueden
 * tener reservas históricas).
 */
add_action( 'save_post_listing', 'pp_rs_sincronizar_agendas', 99, 1 );
function pp_rs_sincronizar_agendas( $listing_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $listing_id ) || ! pp_rs_habilitado() ) {
		return;
	}

	$menu = get_post_meta( $listing_id, '_menu', true );
	if ( ! is_array( $menu ) ) {
		return;
	}

	// Tipos que DEBEN tener agenda propia según el formulario recién guardado.
	$deseados = array();
	foreach ( $menu as $g ) {
		if ( ! is_array( $g ) || empty( $g['pp_agenda_propia'] ) ) {
			continue;
		}
		$tipo = sanitize_key( $g['pp_tipo_servicio'] ?? '' );
		if ( $tipo ) {
			$deseados[ $tipo ] = true;
		}
	}

	// Agendas auto existentes del listado (activas O pausadas).
	$existentes = array(); // tipo => rid
	$ids        = get_post_meta( $listing_id, '_lbp_resources', true );
	$ids        = is_array( $ids ) ? $ids : array();
	foreach ( $ids as $rid ) {
		$rid = (int) $rid;
		if ( $rid > 0 && get_post_meta( $rid, '_pp_auto_agenda', true ) && get_post_status( $rid ) ) {
			$tipo = sanitize_key( get_post_meta( $rid, '_pp_tipologia', true ) );
			if ( $tipo ) {
				$existentes[ $tipo ] = $rid;
			}
		}
	}

	$catalogo = function_exists( 'pp_serv_tipos' ) ? pp_serv_tipos() : array();
	$cambio   = false;

	// Crear o reanudar las deseadas.
	foreach ( array_keys( $deseados ) as $tipo ) {
		if ( isset( $existentes[ $tipo ] ) ) {
			$rid = $existentes[ $tipo ];
			if ( get_post_meta( $rid, '_lbp_resource_paused', true ) ) {
				delete_post_meta( $rid, '_lbp_resource_paused' );
				delete_post_meta( $rid, '_pp_auto_pausado' );
			}
			continue;
		}
		$nombre = isset( $catalogo[ $tipo ]['nombre'] ) ? $catalogo[ $tipo ]['nombre'] : $tipo;
		$rid    = wp_insert_post( array(
			'post_type'   => 'lbp_resource',
			'post_title'  => 'Agenda · ' . $nombre,
			'post_status' => 'publish',
			'post_author' => (int) get_post_field( 'post_author', $listing_id ) ?: get_current_user_id(),
		) );
		if ( $rid && ! is_wp_error( $rid ) ) {
			update_post_meta( $rid, '_pp_auto_agenda', '1' );
			update_post_meta( $rid, '_pp_tipologia', $tipo );
			update_post_meta( $rid, '_lbp_assigned_listing', $listing_id );
			update_post_meta( $rid, '_lbp_subtitle', 'Agenda del tipo de servicio' );
			if ( ! in_array( $rid, $ids, false ) ) {
				$ids[]  = $rid;
				$cambio = true;
			}
		}
	}

	// Pausar las que sobran (marcadas antes, desmarcadas ahora).
	foreach ( $existentes as $tipo => $rid ) {
		if ( ! isset( $deseados[ $tipo ] ) && ! get_post_meta( $rid, '_lbp_resource_paused', true ) ) {
			update_post_meta( $rid, '_lbp_resource_paused', '1' );
			update_post_meta( $rid, '_pp_auto_pausado', '1' );
		}
	}

	if ( $cambio ) {
		update_post_meta( $listing_id, '_lbp_resources', array_values( $ids ) );
	}
}

/* =========================================================================
 * Assets + datos del popup (solo página de listado, solo con el flujo activo)
 * ========================================================================= */

add_action( 'wp_enqueue_scripts', 'pp_rs_assets', 130 );
function pp_rs_assets() {
	if ( is_admin() || ! is_singular( 'listing' ) || ! pp_rs_habilitado() ) {
		return;
	}

	$listing_id = get_queried_object_id();
	$tipologias = pp_rs_tipologias_listado( $listing_id );
	if ( ! $tipologias ) {
		return; // sin servicios reservables → nada que orquestar
	}

	$css = PP_PERS_DIR . 'css/pp-reserva-servicios.css';
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'pp-reserva-servicios', PP_PERS_URL . 'css/pp-reserva-servicios.css', array(), filemtime( $css ) );
	}

	$js = PP_PERS_DIR . 'js/pp-reserva-servicios.js';
	if ( ! file_exists( $js ) ) {
		return;
	}
	wp_enqueue_script( 'pp-reserva-servicios', PP_PERS_URL . 'js/pp-reserva-servicios.js', array( 'jquery' ), filemtime( $js ), true );

	$profesionales = pp_rs_profesionales_del_listado( $listing_id );

	wp_localize_script( 'pp-reserva-servicios', 'PP_RS', array(
		'activo'        => 1,
		'tipologias'    => array_values( $tipologias ),
		// { resource_id: tipologia } — SOLO profesionales (las auto-agendas
		// viajan dentro de cada tipología como 'agenda').
		'profesionales' => $profesionales ? $profesionales : new stdClass(),
	) );
}
