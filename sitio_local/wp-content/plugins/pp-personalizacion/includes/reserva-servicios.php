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
		// SOLO tipologías del CATÁLOGO: las pestañas muestran el Tipo de
		// Servicio, jamás el nombre del menú (regla de Miguel 2026-07-07;
		// mismo criterio que el índice del buscador). Un menú sin tipología
		// válida (dato viejo) NO genera pestaña: sus servicios quedan fuera
		// del paso hasta que el listado se re-guarde con la tipología —
		// que hoy es obligatoria en el formulario.
		if ( '' === $slug || ! isset( $catalogo[ $slug ] ) ) {
			continue;
		}
		if ( ! isset( $out[ $slug ] ) ) {
			$out[ $slug ] = array(
				'slug'    => $slug,
				'nombre'  => $catalogo[ $slug ]['nombre'],
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

/**
 * Tabs para el PASO SERVICIOS del popup: UNA por menú, etiquetadas con el
 * NOMBRE que el prestador puso al menú (no la tipología). Es solo para la
 * ETIQUETA visible — el enrutamiento (profesional/agenda) sigue por la
 * tipología (`slug`), idéntico a pp_rs_tipologias_listado(). Así el popup
 * muestra "Peluquería", "Veterinario"… como la vitrina, sin cambiar la
 * lógica de agendas ni el buscador.
 *
 *   slug     → tipología (catálogo) para enrutamiento — se REPITE si dos
 *              menús comparten tipología (el JS distingue las tabs por índice)
 *   nombre   → título del menú del prestador (fallback: nombre de tipología)
 *   indices  → índices planos SOLO de ESE menú
 *   agenda   → agenda automática de la tipología (0 = agenda del listado)
 */
function pp_rs_menus_para_popup( $listing_id ) {
	$menu = get_post_meta( $listing_id, '_menu', true );
	$out  = array();
	if ( ! is_array( $menu ) ) {
		return $out;
	}
	$catalogo = function_exists( 'pp_serv_tipos' ) ? pp_serv_tipos() : array();
	$agendas  = pp_rs_agendas_del_listado( $listing_id ); // tipo => rid

	$ix = 0;
	foreach ( $menu as $g ) {
		if ( ! is_array( $g ) || empty( $g['menu_elements'] ) || ! is_array( $g['menu_elements'] ) ) {
			continue;
		}
		$slug    = sanitize_key( $g['pp_tipo_servicio'] ?? '' );
		$indices = array();
		foreach ( $g['menu_elements'] as $el ) {
			if ( ! is_array( $el ) || ! isset( $el['bookable'] ) ) {
				continue; // paridad con listeo_get_bookable_services()
			}
			$indices[] = $ix;
			$ix++;
		}
		if ( ! $indices ) {
			continue;
		}
		// Misma guarda que pp_rs_tipologias_listado: solo tipologías del
		// catálogo (un menú sin tipología válida no genera tab).
		if ( '' === $slug || ! isset( $catalogo[ $slug ] ) ) {
			continue;
		}
		$titulo = ( isset( $g['menu_title'] ) && '' !== trim( (string) $g['menu_title'] ) )
			? sanitize_text_field( $g['menu_title'] )
			: $catalogo[ $slug ]['nombre'];
		$out[] = array(
			'slug'    => $slug,
			'nombre'  => $titulo,
			'indices' => $indices,
			'agenda'  => isset( $agendas[ $slug ] ) ? (int) $agendas[ $slug ] : 0,
		);
	}
	return $out;
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

/**
 * Mapa profesional ACTIVO (NO auto-agenda) → tipología asignada ('' = todas).
 * Mismo criterio de "reservable" que lbp_get_active_resources(): publicado y
 * sin pausar — un profesional en borrador o pausado no atiende a nadie, así
 * que su tipo debe quedar cubierto por otra ruta (agenda general).
 */
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
		if ( 'publish' !== get_post_status( $rid ) || get_post_meta( $rid, '_lbp_resource_paused', true ) ) {
			continue;
		}
		$mapa[ $rid ] = sanitize_key( get_post_meta( $rid, '_pp_tipologia', true ) );
	}
	return $mapa;
}

/**
 * ID de la "Agenda general" ACTIVA del listado (0 si no hay).
 *
 * Es el recurso que atiende a los tipos SIN agenda propia ni profesional,
 * cuando el listado ya tiene alguna agenda separada. Existe por una razón
 * de fondo: para Booking Plus, en cuanto un listado tiene recursos, la
 * agenda del listado deja de ser reservable — pedir "recurso 0" significa
 * "que CUALQUIER recurso esté libre" (class-lbp-availability.php, rama
 * "Any resource"), lo que mezclaría la disponibilidad de agendas ajenas.
 * Con la Agenda general cada tipo tiene SIEMPRE su propia ruta y no hay
 * contaminación. Hereda horarios/slots del listado (sin overrides), así que
 * se comporta igual que la agenda del negocio de toda la vida.
 */
function pp_rs_agenda_general_del_listado( $listing_id ) {
	$ids = get_post_meta( (int) $listing_id, '_lbp_resources', true );
	if ( ! is_array( $ids ) ) {
		return 0;
	}
	foreach ( $ids as $rid ) {
		$rid = (int) $rid;
		if ( $rid <= 0 || ! get_post_meta( $rid, '_pp_auto_general', true ) ) {
			continue;
		}
		if ( 'publish' !== get_post_status( $rid ) || get_post_meta( $rid, '_lbp_resource_paused', true ) ) {
			continue;
		}
		return $rid;
	}
	return 0;
}

/** ¿Este tipo ya tiene ruta propia (profesional que lo atiende o agenda propia)? */
function pp_rs_tipo_cubierto( $tipo, $profesionales, $agendas ) {
	if ( isset( $agendas[ $tipo ] ) ) {
		return true;
	}
	foreach ( $profesionales as $rid => $tipologia ) {
		if ( '' === $tipologia || $tipo === $tipologia ) {
			return true; // '' = atiende todos los tipos
		}
	}
	return false;
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

	pp_rs_sincronizar_agenda_general( $listing_id );
}

/**
 * Crea/pausa la "Agenda general" según la cobertura del listado.
 *
 * Necesaria SOLO cuando el listado tiene alguna agenda separada (profesional
 * o agenda de tipo) Y queda algún tipo sin ruta propia: esos tipos necesitan
 * un recurso propio porque, con recursos presentes, Booking Plus ya no puede
 * reservar "la agenda del listado" (ver pp_rs_agenda_general_del_listado).
 * Si el listado no tiene recursos, NO se crea nada: sigue el flujo clásico.
 */
function pp_rs_sincronizar_agenda_general( $listing_id ) {
	if ( ! pp_rs_habilitado() ) {
		return;
	}

	$profesionales = pp_rs_profesionales_del_listado( $listing_id );
	$agendas       = pp_rs_agendas_del_listado( $listing_id );
	$tiene_rutas   = ( $profesionales || $agendas );

	// ¿Queda algún tipo del listado sin profesional ni agenda propia?
	$huerfanos = false;
	foreach ( pp_rs_tipologias_listado( $listing_id ) as $t ) {
		if ( ! pp_rs_tipo_cubierto( $t['slug'], $profesionales, $agendas ) ) {
			$huerfanos = true;
			break;
		}
	}
	$necesaria = ( $tiene_rutas && $huerfanos );

	// Buscar la general existente (activa o pausada).
	$ids     = get_post_meta( $listing_id, '_lbp_resources', true );
	$ids     = is_array( $ids ) ? $ids : array();
	$general = 0;
	foreach ( $ids as $rid ) {
		$rid = (int) $rid;
		if ( $rid > 0 && get_post_meta( $rid, '_pp_auto_general', true ) && get_post_status( $rid ) ) {
			$general = $rid;
			break;
		}
	}

	if ( ! $necesaria ) {
		// Sobra: pausar (no borrar — puede tener reservas históricas).
		if ( $general && ! get_post_meta( $general, '_lbp_resource_paused', true ) ) {
			update_post_meta( $general, '_lbp_resource_paused', '1' );
			update_post_meta( $general, '_pp_auto_pausado', '1' );
		}
		return;
	}

	if ( $general ) {
		if ( get_post_meta( $general, '_lbp_resource_paused', true ) ) {
			delete_post_meta( $general, '_lbp_resource_paused' );
			delete_post_meta( $general, '_pp_auto_pausado' );
		}
		return;
	}

	$rid = wp_insert_post( array(
		'post_type'   => 'lbp_resource',
		'post_title'  => 'Agenda general',
		'post_status' => 'publish',
		'post_author' => (int) get_post_field( 'post_author', $listing_id ) ?: get_current_user_id(),
	) );
	if ( ! $rid || is_wp_error( $rid ) ) {
		return;
	}
	// Sin overrides: hereda horarios y slots del listado → se comporta igual
	// que la agenda del negocio de siempre.
	update_post_meta( $rid, '_pp_auto_agenda', '1' );
	update_post_meta( $rid, '_pp_auto_general', '1' );
	update_post_meta( $rid, '_lbp_assigned_listing', $listing_id );
	update_post_meta( $rid, '_lbp_subtitle', 'Agenda del negocio' );
	$ids[] = $rid;
	update_post_meta( $listing_id, '_lbp_resources', array_values( $ids ) );
}

/**
 * Crear/editar un profesional cambia la cobertura de tipos del listado
 * (p. ej. el último profesional de un tipo deja de atenderlo) → resincronizar
 * su Agenda general. Prioridad 25: después de guardar `_pp_tipologia` (20).
 */
add_action( 'save_post_lbp_resource', 'pp_rs_resync_general_por_recurso', 25, 1 );
function pp_rs_resync_general_por_recurso( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( get_post_meta( $post_id, '_pp_auto_agenda', true ) ) {
		return; // evitar recursión: es una agenda creada por nosotros
	}
	$listing_id = (int) get_post_meta( $post_id, '_lbp_assigned_listing', true );
	if ( $listing_id > 0 ) {
		pp_rs_sincronizar_agenda_general( $listing_id );
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
	// Tabs del popup = UNA por menú, con el nombre del menú (no la tipología).
	$tipologias = pp_rs_menus_para_popup( $listing_id );
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
		// Recurso que atiende a los tipos sin ruta propia (0 = el listado no
		// tiene agendas separadas y se reserva con su calendario de siempre).
		'agendaGeneral' => pp_rs_agenda_general_del_listado( $listing_id ),
	) );
}

/* =========================================================================
 * Ocultar las AGENDAS internas de la sección pública "Profesionales"
 *
 * Booking Plus lista TODOS los recursos activos del listado en la ficha
 * (sección #listing-resources, con botón Reservar por tarjeta). Nuestras
 * agendas automáticas (por tipo y la general) son recursos por debajo, así
 * que se colaban como si fueran profesionales elegibles. No hay filtro
 * nativo para excluirlas → se ocultan con CSS por su data-resource-id
 * (a prueba de updates; si el CSS no corriera, lo peor es que se vean, no
 * se rompe nada). Si el listado SOLO tiene agendas internas (sin
 * profesionales reales), se oculta la sección y su pestaña completas.
 * ========================================================================= */

/** IDs de recursos internos (auto-agenda / general) ACTIVOS del listado. */
function pp_rs_agendas_internas_activas( $listing_id ) {
	$out = array();
	$ids = get_post_meta( (int) $listing_id, '_lbp_resources', true );
	if ( ! is_array( $ids ) ) {
		return $out;
	}
	foreach ( $ids as $rid ) {
		$rid = (int) $rid;
		if ( $rid <= 0 ) {
			continue;
		}
		$es_interna = get_post_meta( $rid, '_pp_auto_agenda', true ) || get_post_meta( $rid, '_pp_auto_general', true );
		if ( ! $es_interna ) {
			continue;
		}
		if ( 'publish' !== get_post_status( $rid ) || get_post_meta( $rid, '_lbp_resource_paused', true ) ) {
			continue;
		}
		$out[] = $rid;
	}
	return $out;
}

add_action( 'wp_head', 'pp_rs_ocultar_agendas_publicas', 99 );
function pp_rs_ocultar_agendas_publicas() {
	if ( is_admin() || ! is_singular( 'listing' ) || ! pp_rs_habilitado() ) {
		return;
	}
	$listing_id = get_queried_object_id();
	$internas   = pp_rs_agendas_internas_activas( $listing_id );
	if ( ! $internas ) {
		return; // no hay agendas internas → nada que ocultar
	}

	// ¿Quedan profesionales REALES para mostrar en la sección?
	$hay_profesionales = ! empty( pp_rs_profesionales_del_listado( $listing_id ) );

	echo "\n<style id=\"pp-rs-ocultar-agendas\">\n";
	if ( $hay_profesionales ) {
		// Ocultar solo las tarjetas de agendas internas. Los IDs son (int):
		// seguros para interpolar en CSS. NO usar esc_html (convertiría las
		// comillas del selector en &quot; y rompería la regla).
		$sel = array();
		foreach ( $internas as $rid ) {
			$sel[] = '#listing-resources .lbp-resource-listing-card[data-resource-id="' . (int) $rid . '"]';
		}
		echo implode( ",\n", $sel ) . " { display: none !important; }\n";
	} else {
		// Solo hay agendas internas → ocultar la sección y su pestaña.
		echo "#listing-resources { display: none !important; }\n";
		echo ".pp-rs-nav-oculto { display: none !important; }\n";
	}
	echo "</style>\n";

	// La pestaña del menú (<li><a href=\"#listing-resources\">) no tiene clase
	// propia: se marca por JS para ocultarla sin depender de :has(). El nav se
	// renderiza en el <body> (después de este <head>), así que se espera al DOM.
	if ( ! $hay_profesionales ) {
		echo "<script>(function(){function h(){var a=document.querySelector('a[href=\"#listing-resources\"]');if(a){var li=a.closest('li');if(li){li.className+=' pp-rs-nav-oculto';}}}if(document.readyState!=='loading'){h();}else{document.addEventListener('DOMContentLoaded',h);}})();</script>\n";
	}
}
