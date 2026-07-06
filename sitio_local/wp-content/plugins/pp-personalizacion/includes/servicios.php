<?php
/**
 * Módulo SERVICIOS del plugin Personalización Parche.
 *
 * Personalización 1 (2026-07-04) — "Elige tu servicio":
 * En listados con servicios INDIVIDUALES (Service Constraints de Booking
 * Plus), el acordeón de servicios del popup cambia su etiqueta base a
 * "Elige tu servicio" y, al seleccionar un individual, muestra el NOMBRE
 * del servicio elegido (Booking Plus solo lo hace en el widget clásico,
 * no en su popup).
 *
 * Personalización 2 (2026-07-04) — Tabs por tipo de servicio:
 * Cuando el listado tiene VARIOS menús con servicios reservables (p. ej.
 * "Adiestramiento" y "Fotografía y Video"), el popup muestra pestañas con
 * los títulos de los menús; al elegir una se habilita la lista de
 * servicios de ese tipo. Con un solo menú no aparecen pestañas.
 * Decisión de producto (Miguel 2026-07-04): se mantiene UN solo servicio
 * principal (individual) por reserva, sin importar el tipo; los extras
 * se acumulan libremente.
 *
 * Personalización 3 (2026-07-05) — TIPOS DE SERVICIO (v2.5.0):
 * Catálogo propio de tipos de servicio (CRUD en el admin del plugin,
 * opción `pp_serv_tipos`). Cada MENÚ del listado debe declarar su tipo
 * (campo obligatorio `_menu[i][pp_tipo_servicio]`, inyectado por JS en el
 * formulario de publicar/editar listado y validado en el servidor).
 * Los tipos con la marca "mascota" (p. ej. Peluquería y baño) habilitan
 * en cada servicio el bloque "Depende de mascota": especie, tipo de
 * pelaje y rangos de peso (`pp_dm*` dentro del elemento del menú).
 * En el popup de reserva, al elegir la mascota (paso 1 del gate de
 * pp-mascotas-reserva.js) se OCULTAN los servicios que no aplican.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** ¿La página actual es un listado con servicios individuales configurados? */
function pp_serv_listado_con_individuales() {
	if ( is_admin() || ! is_singular( 'listing' ) ) {
		return false;
	}
	if ( ! class_exists( 'LBP_Service_Constraints' ) || ! method_exists( 'LBP_Service_Constraints', 'listing_has_individuals' ) ) {
		return false;
	}
	$id = get_queried_object_id();
	return $id && LBP_Service_Constraints::listing_has_individuals( $id );
}

/**
 * ¿La página actual es un listado con AL MENOS un servicio reservable
 * (haya o no servicios individuales)? En Parche Peludo el cliente viene a
 * contratar un servicio, así que el acordeón se rotula "Elige tu servicio"
 * siempre que haya servicios, no solo cuando hay individuales.
 */
function pp_serv_listado_con_servicios() {
	if ( is_admin() || ! is_singular( 'listing' ) ) {
		return false;
	}
	$id = get_queried_object_id();
	return $id && count( pp_serv_menus_reservables( $id ) ) > 0;
}

/**
 * Menús del listado que tienen servicios reservables, como
 * [ { titulo, slugs: [slug-de-servicio, …] }, … ].
 * El slug replica el de Booking Plus: sanitize_title(nombre del servicio).
 */
function pp_serv_menus_reservables( $listing_id ) {
	$menu   = get_post_meta( $listing_id, '_menu', true );
	$grupos = array();
	if ( ! is_array( $menu ) ) {
		return $grupos;
	}
	foreach ( $menu as $g ) {
		$slugs = array();
		foreach ( (array) ( $g['menu_elements'] ?? array() ) as $el ) {
			if ( ! empty( $el['bookable'] ) && 'on' === $el['bookable'] && ! empty( $el['name'] ) ) {
				$slugs[] = sanitize_title( (string) $el['name'] );
			}
		}
		if ( $slugs ) {
			$grupos[] = array(
				'titulo' => sanitize_text_field( $g['menu_title'] ?? '' ) ?: 'Servicios',
				'slugs'  => $slugs,
			);
		}
	}
	return $grupos;
}

/* ---- Personalización 1a: etiqueta base del acordeón ---- */
add_filter( 'gettext_listeo-booking-plus', 'pp_serv_etiqueta_servicios', 20, 2 );
function pp_serv_etiqueta_servicios( $traduccion, $texto ) {
	// "Elige tu servicio" siempre que el listado ofrezca servicios
	// reservables, tenga o no servicios individuales marcados.
	if ( 'Extra Services' === $texto && pp_serv_listado_con_servicios() ) {
		return 'Elige tu servicio';
	}
	return $traduccion;
}

/* ---- Assets del módulo (etiqueta dinámica + tabs) ---- */
add_action( 'wp_enqueue_scripts', 'pp_serv_assets', 120 );
function pp_serv_assets() {
	if ( is_admin() || ! is_singular( 'listing' ) ) {
		return;
	}

	$listing_id    = get_queried_object_id();
	$menus         = pp_serv_menus_reservables( $listing_id );
	$individuales  = pp_serv_listado_con_individuales();
	$restricciones = pp_serv_restricciones_mascota( $listing_id );

	// Solo cargar si hay algo que hacer: etiqueta dinámica, tabs o
	// filtrado de servicios según la mascota elegida.
	if ( ! $individuales && count( $menus ) < 2 && empty( $restricciones ) ) {
		return;
	}

	$css = PP_PERS_DIR . 'css/pp-servicios-reserva.css';
	if ( ( count( $menus ) > 1 || ! empty( $restricciones ) ) && file_exists( $css ) ) {
		wp_enqueue_style( 'pp-servicios-reserva', PP_PERS_URL . 'css/pp-servicios-reserva.css', array(), filemtime( $css ) );
	}

	// Regla del filtrado por mascota: va como CSS propio (clase con
	// !important) para que sobreviva a los .toggle()/.show() de los tabs.
	wp_register_style( 'pp-serv-filtro-mascota', false, array(), '1.0' );
	wp_enqueue_style( 'pp-serv-filtro-mascota' );
	wp_add_inline_style( 'pp-serv-filtro-mascota', '.lbp-single-service.pp-oculta-mascota{display:none !important;}' );

	$js = PP_PERS_DIR . 'js/pp-servicios-reserva.js';
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'pp-servicios-reserva', PP_PERS_URL . 'js/pp-servicios-reserva.js', array( 'jquery' ), filemtime( $js ), true );
		wp_localize_script( 'pp-servicios-reserva', 'PP_SERV', array(
			'menus'         => $menus,
			'restricciones' => $restricciones ? $restricciones : new stdClass(),
		) );
	}
}

/* =========================================================================
 * TIPOS DE SERVICIO (v2.5.0)
 *
 * Catálogo configurable (opción `pp_serv_tipos`): slug => { nombre, mascota }.
 * "mascota" = 1 significa que los servicios de un menú de ese tipo pueden
 * declarar el bloque "Depende de mascota" (especie / pelaje / rangos de peso).
 * ====================================================================== */

/** Tipos iniciales (semilla; solo se usa si la opción aún no existe). */
function pp_serv_tipos_semilla() {
	return array(
		'peluqueria-y-bano'  => array( 'nombre' => 'Peluquería y baño',  'mascota' => 1 ),
		'adiestramiento'     => array( 'nombre' => 'Adiestramiento',     'mascota' => 0 ),
		'cuidador'           => array( 'nombre' => 'Cuidador',           'mascota' => 0 ),
		'fotografia-y-video' => array( 'nombre' => 'Fotografía y video', 'mascota' => 0 ),
		'reposteria'         => array( 'nombre' => 'Repostería',         'mascota' => 0 ),
		'fiestas-caninas'    => array( 'nombre' => 'Fiestas Caninas',    'mascota' => 0 ),
	);
}

/** Catálogo vigente de tipos de servicio (con siembra automática). */
function pp_serv_tipos() {
	$tipos = get_option( 'pp_serv_tipos', null );
	if ( ! is_array( $tipos ) || empty( $tipos ) ) {
		$tipos = pp_serv_tipos_semilla();
		update_option( 'pp_serv_tipos', $tipos );
	}
	$out = array();
	foreach ( $tipos as $slug => $t ) {
		$slug = sanitize_key( $slug );
		if ( '' === $slug ) {
			continue;
		}
		if ( ! is_array( $t ) ) {
			$t = array( 'nombre' => (string) $t );
		}
		// aviso = antelación mínima de reserva (minutos) propia del tipo.
		// '' = no define (hereda listado/global) · 0 = permite último minuto · N = minutos.
		$aviso = $t['aviso'] ?? '';
		$out[ $slug ] = array(
			'nombre'  => sanitize_text_field( $t['nombre'] ?? $slug ),
			'mascota' => empty( $t['mascota'] ) ? 0 : 1,
			'aviso'   => ( '' !== $aviso && is_numeric( $aviso ) ) ? max( 0, (int) $aviso ) : '',
		);
	}
	return $out;
}

/** ¿El tipo (slug) permite servicios que dependen de la mascota? */
function pp_serv_tipo_permite_mascota( $slug ) {
	$tipos = pp_serv_tipos();
	return ! empty( $tipos[ $slug ]['mascota'] );
}

/* ---- CRUD del catálogo (admin) ---- */
add_action( 'admin_post_pp_serv_tipos', 'pp_serv_tipos_handler' );
function pp_serv_tipos_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'No autorizado' );
	}
	check_admin_referer( 'pp_serv_tipos' );

	$accion = sanitize_key( $_POST['pp_accion'] ?? '' );
	$tipos  = pp_serv_tipos();
	$msg    = 'ok';

	// Antelación mínima propia del tipo: '' = hereda, 0 = último minuto, N = minutos.
	$aviso_raw = trim( (string) wp_unslash( $_POST['pp_aviso'] ?? '' ) );
	$aviso     = ( '' !== $aviso_raw && is_numeric( $aviso_raw ) ) ? max( 0, (int) $aviso_raw ) : '';

	if ( 'crear' === $accion ) {
		$nombre  = sanitize_text_field( wp_unslash( $_POST['pp_nombre'] ?? '' ) );
		$mascota = empty( $_POST['pp_mascota'] ) ? 0 : 1;
		$slug    = sanitize_title( $nombre );
		if ( '' === $nombre || '' === $slug ) {
			$msg = 'vacio';
		} elseif ( isset( $tipos[ $slug ] ) ) {
			$msg = 'duplicado';
		} else {
			$tipos[ $slug ] = array( 'nombre' => $nombre, 'mascota' => $mascota, 'aviso' => $aviso );
			update_option( 'pp_serv_tipos', $tipos );
			$msg = 'creado';
		}
	} elseif ( 'editar' === $accion ) {
		$slug    = sanitize_key( $_POST['pp_slug'] ?? '' );
		$nombre  = sanitize_text_field( wp_unslash( $_POST['pp_nombre'] ?? '' ) );
		$mascota = empty( $_POST['pp_mascota'] ) ? 0 : 1;
		if ( '' === $nombre || ! isset( $tipos[ $slug ] ) ) {
			$msg = 'vacio';
		} else {
			// El slug NO cambia al renombrar: los menús guardados lo referencian.
			$tipos[ $slug ] = array( 'nombre' => $nombre, 'mascota' => $mascota, 'aviso' => $aviso );
			update_option( 'pp_serv_tipos', $tipos );
			$msg = 'editado';
		}
	} elseif ( 'eliminar' === $accion ) {
		$slug = sanitize_key( $_POST['pp_slug'] ?? '' );
		if ( isset( $tipos[ $slug ] ) ) {
			unset( $tipos[ $slug ] );
			update_option( 'pp_serv_tipos', $tipos );
			$msg = 'eliminado';
		}
	}

	wp_safe_redirect( admin_url( 'admin.php?page=pp-servicios&tab=tipos&pp_msg=' . $msg ) );
	exit;
}

/** UI de la pestaña "Tipos de Servicio" (embebible en la página Servicios). */
function pp_serv_tab_tipos() {
	$tipos   = pp_serv_tipos();
	$editar  = sanitize_key( $_GET['editar'] ?? '' );
	$en_edicion = ( $editar && isset( $tipos[ $editar ] ) ) ? $tipos[ $editar ] : null;
	$msg     = sanitize_key( $_GET['pp_msg'] ?? '' );
	$avisos  = array(
		'creado'    => array( 'success', 'Tipo de servicio creado.' ),
		'editado'   => array( 'success', 'Tipo de servicio actualizado.' ),
		'eliminado' => array( 'success', 'Tipo de servicio eliminado.' ),
		'duplicado' => array( 'error', 'Ya existe un tipo con ese nombre.' ),
		'vacio'     => array( 'error', 'El nombre no puede estar vacío.' ),
	);
	if ( isset( $avisos[ $msg ] ) ) {
		printf(
			'<div class="notice notice-%s is-dismissible" style="margin-left:0"><p>%s</p></div>',
			esc_attr( $avisos[ $msg ][0] ),
			esc_html( $avisos[ $msg ][1] )
		);
	}
	?>
	<div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start">

		<div style="flex:2;min-width:340px;max-width:620px;background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px">
			<h3 style="margin-top:0">Tipos configurados</h3>
			<p class="description" style="margin-bottom:12px">Cada <strong>menú de servicios</strong> de un listado debe declarar su tipo (campo obligatorio al crear/editar el listado). Los tipos con <strong>"Según mascota"</strong> permiten que sus servicios dependan de la especie, el pelaje y el peso de la mascota.</p>
			<table class="widefat striped" style="max-width:600px">
				<thead>
					<tr>
						<th>Nombre</th>
						<th style="width:110px">Según mascota</th>
						<th style="width:120px">Antelación mín.</th>
						<th style="width:150px">Acciones</th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $tipos ) ) : ?>
					<tr><td colspan="4">No hay tipos configurados.</td></tr>
				<?php else : foreach ( $tipos as $slug => $t ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $t['nombre'] ); ?></strong><br><span class="description" style="font-size:11px"><?php echo esc_html( $slug ); ?></span></td>
						<td><?php echo $t['mascota'] ? '<span style="color:#1b5e40;font-weight:600">Sí 🐾</span>' : '—'; ?></td>
						<td><?php
							if ( '' === $t['aviso'] ) {
								echo '<span style="opacity:.6" title="Hereda la configuración del listado o la global">Hereda</span>';
							} elseif ( 0 === (int) $t['aviso'] ) {
								echo '<span title="Permite reservas de último minuto">Sin antelación</span>';
							} else {
								echo '<strong>' . esc_html( $t['aviso'] ) . '</strong> min';
							}
						?></td>
						<td>
							<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=pp-servicios&tab=tipos&editar=' . $slug ) ); ?>">Editar</a>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('¿Eliminar el tipo «<?php echo esc_js( $t['nombre'] ); ?>»? Los menús que lo usan quedarán sin tipo hasta que se editen.');">
								<?php wp_nonce_field( 'pp_serv_tipos' ); ?>
								<input type="hidden" name="action" value="pp_serv_tipos">
								<input type="hidden" name="pp_accion" value="eliminar">
								<input type="hidden" name="pp_slug" value="<?php echo esc_attr( $slug ); ?>">
								<button type="submit" class="button button-small button-link-delete">Eliminar</button>
							</form>
						</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>

		<div style="flex:1;min-width:280px;max-width:380px;background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px">
			<h3 style="margin-top:0"><?php echo $en_edicion ? 'Editar tipo' : 'Nuevo tipo'; ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'pp_serv_tipos' ); ?>
				<input type="hidden" name="action" value="pp_serv_tipos">
				<input type="hidden" name="pp_accion" value="<?php echo $en_edicion ? 'editar' : 'crear'; ?>">
				<?php if ( $en_edicion ) : ?>
					<input type="hidden" name="pp_slug" value="<?php echo esc_attr( $editar ); ?>">
				<?php endif; ?>
				<p>
					<label for="pp-tipo-nombre"><strong>Nombre</strong></label><br>
					<input type="text" id="pp-tipo-nombre" name="pp_nombre" class="regular-text" style="width:100%" required
						value="<?php echo $en_edicion ? esc_attr( $en_edicion['nombre'] ) : ''; ?>" placeholder="Ej: Paseo de mascotas">
				</p>
				<p>
					<label>
						<input type="checkbox" name="pp_mascota" value="1" <?php checked( $en_edicion && $en_edicion['mascota'] ); ?>>
						<strong>Según mascota</strong> — sus servicios podrán depender de especie, pelaje y peso
					</label>
				</p>
				<p>
					<label for="pp-tipo-aviso"><strong>Antelación mínima de reserva (minutos)</strong></label><br>
					<input type="number" id="pp-tipo-aviso" name="pp_aviso" min="0" step="1" style="width:140px"
						value="<?php echo $en_edicion ? esc_attr( $en_edicion['aviso'] ) : ''; ?>" placeholder="Hereda">
					<br><span class="description">Vacío = hereda la configuración del listado/global · <code>0</code> = permite último minuto · Ej: <code>1440</code> = 1 día, <code>4320</code> = 3 días. Manda sobre el listado cuando se reserva un servicio de este tipo.</span>
				</p>
				<p>
					<button type="submit" class="button button-primary"><?php echo $en_edicion ? 'Guardar cambios' : 'Crear tipo'; ?></button>
					<?php if ( $en_edicion ) : ?>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=pp-servicios&tab=tipos' ) ); ?>">Cancelar</a>
					<?php endif; ?>
				</p>
			</form>
			<p class="description">Al renombrar un tipo, los menús existentes lo conservan (el identificador interno no cambia). Al eliminarlo, los menús que lo usaban deberán elegir otro tipo la próxima vez que se editen.</p>
		</div>
	</div>
	<?php
}

/* =========================================================================
 * Campo "Tipo de servicio" + bloque "Depende de mascota" en el formulario
 * de publicar/editar listado (frontend).
 *
 * - El select por MENÚ lo inyecta js/pp-serv-menu.js en cada fila de
 *   categoría (Core no ofrece hook a nivel de grupo). Nombre del campo:
 *   `_menu[i][pp_tipo_servicio]` — el guardado de Core lo conserva.
 * - El bloque por SERVICIO se pinta aquí (hook oficial por elemento,
 *   mismo mecanismo que usa Booking Plus) y el JS lo replica en las
 *   filas nuevas añadidas con "Add Item".
 * ====================================================================== */

add_action( 'listeo_menu_element_extra_fields', 'pp_serv_campos_mascota_fila', 30, 4 );
function pp_serv_campos_mascota_fila( $menu_el, $key, $i, $z ) {
	echo pp_serv_campos_mascota_html( is_array( $menu_el ) ? $menu_el : array(), (string) $key, (int) $i, (int) $z ); // phpcs:ignore WordPress.Security.EscapeOutput
}

/** HTML del bloque "Depende de mascota" de un servicio (fila del menú). */
function pp_serv_campos_mascota_html( $el, $key, $i, $z ) {
	$base    = sprintf( '%s[%d][menu_elements][%d]', $key, $i, $z );
	$id      = sprintf( '%s_%d_%d_pp_dm', sanitize_html_class( $key ), $i, $z );
	$on      = ! empty( $el['pp_dm'] );
	$especie = sanitize_key( $el['pp_dm_especie'] ?? '' );
	$pelaje  = sanitize_key( $el['pp_dm_pelaje'] ?? '' );
	$pesos   = ( isset( $el['pp_dm_pesos'] ) && is_array( $el['pp_dm_pesos'] ) ) ? array_values( $el['pp_dm_pesos'] ) : array();

	$html  = '<div class="fm-input pp-dm-wrap" data-base="' . esc_attr( $base ) . '" style="display:none">';
	$html .= '<div class="checkboxes in-row pp-dm-check-fila">';
	$html .= '<input type="checkbox" class="input-checkbox pp-dm-check" id="' . esc_attr( $id ) . '" name="' . esc_attr( $base ) . '[pp_dm]" value="on"' . checked( $on, true, false ) . '>';
	$html .= '<label for="' . esc_attr( $id ) . '">Depende de mascota</label>';
	$html .= '</div>';

	$html .= '<div class="pp-dm-campos"' . ( $on ? '' : ' style="display:none"' ) . '>';

	// Especie y pelaje.
	$html .= '<div class="pp-dm-grid">';
	$html .= '<div class="pp-dm-campo"><label class="fm-input-label">Especie</label><select name="' . esc_attr( $base ) . '[pp_dm_especie]">';
	foreach ( array( '' => 'Cualquiera', 'perro' => 'Perro', 'gato' => 'Gato' ) as $v => $lbl ) {
		$html .= '<option value="' . esc_attr( $v ) . '"' . selected( $especie, $v, false ) . '>' . esc_html( $lbl ) . '</option>';
	}
	$html .= '</select></div>';
	$html .= '<div class="pp-dm-campo"><label class="fm-input-label">Tipo de pelaje</label><select name="' . esc_attr( $base ) . '[pp_dm_pelaje]">';
	foreach ( array( '' => 'Cualquiera', 'corto' => 'Corto', 'largo' => 'Largo' ) as $v => $lbl ) {
		$html .= '<option value="' . esc_attr( $v ) . '"' . selected( $pelaje, $v, false ) . '>' . esc_html( $lbl ) . '</option>';
	}
	$html .= '</select></div>';
	$html .= '</div>';

	// Rangos de peso.
	$html .= '<div class="pp-dm-pesos">';
	$html .= '<label class="fm-input-label">Rangos de peso (kg)</label>';
	$html .= '<div class="pp-dm-pesos-lista">';
	$n = 0;
	foreach ( $pesos as $r ) {
		$min = isset( $r['min'] ) ? (string) $r['min'] : '';
		$max = isset( $r['max'] ) ? (string) $r['max'] : '';
		if ( '' === $min && '' === $max ) {
			continue;
		}
		$html .= '<div class="pp-dm-rango">';
		$html .= '<input type="number" step="0.1" min="0" name="' . esc_attr( $base ) . '[pp_dm_pesos][' . $n . '][min]" value="' . esc_attr( $min ) . '" placeholder="Mín">';
		$html .= '<span class="pp-dm-rango-sep">–</span>';
		$html .= '<input type="number" step="0.1" min="0" name="' . esc_attr( $base ) . '[pp_dm_pesos][' . $n . '][max]" value="' . esc_attr( $max ) . '" placeholder="Máx">';
		$html .= '<button type="button" class="pp-dm-rango-quitar" title="Eliminar rango">&times;</button>';
		$html .= '</div>';
		$n++;
	}
	$html .= '</div>';
	$html .= '<button type="button" class="button pp-dm-rango-anadir">+ Añadir rango</button>';
	$html .= '<p class="pricing-field-desc">Sin rangos = aplica a cualquier peso. La mascota aplica si su peso cae en alguno de los rangos.</p>';
	$html .= '</div>';

	$html .= '</div></div>';
	return $html;
}

/* ---- wp-admin: "Tipo de servicio" y "Depende de mascota" en el metabox ----
 * El metabox "Menu (Pricing)" de Listeo es CMB2 (caja `_menu_metabox`,
 * grupo `_menu`). El select del tipo se añade como SUBCAMPO NATIVO del
 * grupo (CMB2 lo pinta y lo guarda solo, también en menús nuevos).
 * El bloque por servicio usa el hook oficial por elemento
 * `listeo_menu_element_extra_fields_admin`, cuyo guardado preserva las
 * claves desconocidas (lo documenta el propio Core). */
add_action( 'cmb2_admin_init', 'pp_serv_admin_metabox_tipo', 99 );
function pp_serv_admin_metabox_tipo() {
	if ( ! function_exists( 'cmb2_get_metabox' ) ) {
		return;
	}
	$cmb = cmb2_get_metabox( '_menu_metabox' );
	if ( ! $cmb ) {
		return;
	}
	$opciones = array();
	foreach ( pp_serv_tipos() as $slug => $t ) {
		$opciones[ $slug ] = $t['nombre'];
	}
	$cmb->add_group_field( '_menu', array(
		'name'             => 'Tipo de servicio',
		'desc'             => 'Obligatorio. Los tipos con "Según mascota" (p. ej. Peluquería y baño) habilitan "Depende de mascota" en sus servicios. Se administran en Personalización Parche → Servicios → Tipos de Servicio.',
		'id'               => 'pp_tipo_servicio',
		'type'             => 'select',
		'show_option_none' => '— Elige el tipo —',
		'options'          => $opciones,
	), 2 ); // posición 2: después de "Menu Title", antes de los elementos
}

add_action( 'listeo_menu_element_extra_fields_admin', 'pp_serv_campos_mascota_fila_admin', 30, 5 );
function pp_serv_campos_mascota_fila_admin( $field, $value, $object_id, $object_type, $field_type ) {
	$value   = is_array( $value ) ? $value : array();
	$base    = $field_type->_name( '' ); // p. ej. _menu[0][menu_elements][1]
	$on      = ! empty( $value['pp_dm'] );
	$especie = sanitize_key( $value['pp_dm_especie'] ?? '' );
	$pelaje  = sanitize_key( $value['pp_dm_pelaje'] ?? '' );
	$pesos   = ( isset( $value['pp_dm_pesos'] ) && is_array( $value['pp_dm_pesos'] ) ) ? array_values( $value['pp_dm_pesos'] ) : array();
	$id      = sanitize_html_class( $base ) . '_pp_dm';
	?>
	<br class="clear">
	<div class="pp-dm-admin" style="display:none;margin:8px 0;padding:10px 12px;background:#f4fbfc;border:1px solid #d6eef1;border-radius:6px;max-width:640px">
		<label style="font-weight:600">
			<input type="checkbox" class="pp-dm-check" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $base ); ?>[pp_dm]" value="on" <?php checked( $on ); ?>>
			Depende de mascota
		</label>
		<div class="pp-dm-campos" <?php if ( ! $on ) echo 'style="display:none"'; ?>>
			<div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:8px">
				<label>Especie<br>
					<select name="<?php echo esc_attr( $base ); ?>[pp_dm_especie]">
						<?php foreach ( array( '' => 'Cualquiera', 'perro' => 'Perro', 'gato' => 'Gato' ) as $v => $lbl ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $especie, $v ); ?>><?php echo esc_html( $lbl ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Tipo de pelaje<br>
					<select name="<?php echo esc_attr( $base ); ?>[pp_dm_pelaje]">
						<?php foreach ( array( '' => 'Cualquiera', 'corto' => 'Corto', 'largo' => 'Largo' ) as $v => $lbl ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $pelaje, $v ); ?>><?php echo esc_html( $lbl ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
			<div class="pp-dm-pesos" style="margin-top:10px">
				<strong>Rangos de peso (kg)</strong>
				<div class="pp-dm-pesos-lista">
					<?php
					$n = 0;
					foreach ( $pesos as $r ) {
						$min = isset( $r['min'] ) ? (string) $r['min'] : '';
						$max = isset( $r['max'] ) ? (string) $r['max'] : '';
						if ( '' === $min && '' === $max ) {
							continue;
						}
						?>
						<div class="pp-dm-rango" style="display:flex;align-items:center;gap:6px;margin-top:6px">
							<input type="number" step="0.1" min="0" style="width:90px" name="<?php echo esc_attr( $base ); ?>[pp_dm_pesos][<?php echo (int) $n; ?>][min]" value="<?php echo esc_attr( $min ); ?>" placeholder="Mín">
							<span>–</span>
							<input type="number" step="0.1" min="0" style="width:90px" name="<?php echo esc_attr( $base ); ?>[pp_dm_pesos][<?php echo (int) $n; ?>][max]" value="<?php echo esc_attr( $max ); ?>" placeholder="Máx">
							<button type="button" class="button-link pp-dm-rango-quitar" style="color:#c0392b" title="Eliminar rango">&times;</button>
						</div>
						<?php
						$n++;
					}
					?>
				</div>
				<p style="margin:8px 0 0"><button type="button" class="button button-small pp-dm-rango-anadir">+ Añadir rango</button></p>
				<p class="description" style="margin-top:6px">Sin rangos = aplica a cualquier peso. La mascota aplica si su peso cae en alguno de los rangos.</p>
			</div>
		</div>
	</div>
	<?php
}

/* JS del metabox admin (toggle por tipo + rangos). */
add_action( 'admin_enqueue_scripts', 'pp_serv_admin_assets' );
function pp_serv_admin_assets( $hook ) {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'listing' !== $screen->post_type ) {
		return;
	}
	$js = PP_PERS_DIR . 'js/pp-serv-menu-admin.js';
	if ( ! file_exists( $js ) ) {
		return;
	}
	wp_enqueue_script( 'pp-serv-menu-admin', PP_PERS_URL . 'js/pp-serv-menu-admin.js', array( 'jquery' ), filemtime( $js ), true );
	$mascota = array();
	foreach ( pp_serv_tipos() as $slug => $t ) {
		if ( $t['mascota'] ) {
			$mascota[ $slug ] = 1;
		}
	}
	wp_localize_script( 'pp-serv-menu-admin', 'PP_SERV_ADMIN', array(
		'mascota' => $mascota ? $mascota : new stdClass(),
	) );
}

/* ---- Validación en el guardado: cada menú con contenido exige su tipo ---- */
add_filter( 'submit_listing_form_validate_fields', 'pp_serv_validar_tipo_servicio_menu', 20, 3 );
function pp_serv_validar_tipo_servicio_menu( $valido, $fields, $values ) {
	if ( is_wp_error( $valido ) ) {
		return $valido;
	}
	if ( empty( $_POST['_menu'] ) || ! is_array( $_POST['_menu'] ) ) {
		return $valido;
	}
	// Si la sección de menús viene apagada, no exigir nada.
	if ( isset( $_POST['_menu_status'] ) && 'on' !== $_POST['_menu_status'] ) {
		return $valido;
	}
	$tipos = pp_serv_tipos();
	foreach ( wp_unslash( $_POST['_menu'] ) as $g ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! is_array( $g ) ) {
			continue;
		}
		$titulo       = trim( (string) ( $g['menu_title'] ?? '' ) );
		$tiene_elems  = false;
		foreach ( (array) ( $g['menu_elements'] ?? array() ) as $el ) {
			if ( is_array( $el ) && '' !== trim( (string) ( $el['name'] ?? '' ) ) ) {
				$tiene_elems = true;
				break;
			}
		}
		if ( '' === $titulo && ! $tiene_elems ) {
			continue; // grupo vacío: Core también lo ignora
		}
		$tipo = sanitize_key( $g['pp_tipo_servicio'] ?? '' );
		if ( '' === $tipo || ! isset( $tipos[ $tipo ] ) ) {
			return new WP_Error( 'pp_tipo_servicio', 'Selecciona el "Tipo de servicio" en cada menú de servicios.' );
		}
	}
	return $valido;
}

/* ---- Red de seguridad: ediciones desde wp-admin no borran nuestras claves ----
 * El metabox de Listeo en wp-admin reconstruye `_menu` desde su propio
 * formulario (que no incluye pp_tipo_servicio ni pp_dm*). Capturamos el
 * valor previo antes de guardar y reinyectamos las claves que falten.
 * Solo actúa en el guardado del editor clásico de wp-admin. */
add_action( 'pre_post_update', 'pp_serv_capturar_menu_previo', 10, 1 );
function pp_serv_capturar_menu_previo( $post_id ) {
	if ( 'listing' !== get_post_type( $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['action'] ) || 'editpost' !== $_POST['action'] ) {
		return;
	}
	$GLOBALS['pp_serv_menu_previo'][ $post_id ] = get_post_meta( $post_id, '_menu', true );
}
add_action( 'save_post_listing', 'pp_serv_reinyectar_claves_menu', 999, 1 );
function pp_serv_reinyectar_claves_menu( $post_id ) {
	if ( empty( $GLOBALS['pp_serv_menu_previo'][ $post_id ] ) ) {
		return;
	}
	$viejo = $GLOBALS['pp_serv_menu_previo'][ $post_id ];
	unset( $GLOBALS['pp_serv_menu_previo'][ $post_id ] );
	$nuevo = get_post_meta( $post_id, '_menu', true );
	if ( ! is_array( $viejo ) || ! is_array( $nuevo ) ) {
		return;
	}
	$cambio = false;
	foreach ( $nuevo as $i => $g ) {
		if ( ! is_array( $g ) ) {
			continue;
		}
		if ( ! array_key_exists( 'pp_tipo_servicio', $g ) && isset( $viejo[ $i ]['pp_tipo_servicio'] ) ) {
			$nuevo[ $i ]['pp_tipo_servicio'] = $viejo[ $i ]['pp_tipo_servicio'];
			$cambio = true;
		}
		foreach ( (array) ( $g['menu_elements'] ?? array() ) as $z => $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			foreach ( array( 'pp_dm', 'pp_dm_especie', 'pp_dm_pelaje', 'pp_dm_pesos' ) as $k ) {
				if ( ! array_key_exists( $k, $el ) && isset( $viejo[ $i ]['menu_elements'][ $z ][ $k ] ) ) {
					$nuevo[ $i ]['menu_elements'][ $z ][ $k ] = $viejo[ $i ]['menu_elements'][ $z ][ $k ];
					$cambio = true;
				}
			}
		}
	}
	if ( $cambio ) {
		update_post_meta( $post_id, '_menu', $nuevo );
	}
}

/* ---- Assets del formulario de publicar/editar listado ---- */
add_action( 'wp_enqueue_scripts', 'pp_serv_assets_submit', 130 );
function pp_serv_assets_submit() {
	// Solo usuarios logueados pueden publicar/editar; el JS además se
	// auto-desactiva si la página no tiene el builder de menús.
	if ( is_admin() || ! is_user_logged_in() ) {
		return;
	}
	$js = PP_PERS_DIR . 'js/pp-serv-menu.js';
	if ( ! file_exists( $js ) ) {
		return;
	}
	wp_enqueue_script( 'pp-serv-menu', PP_PERS_URL . 'js/pp-serv-menu.js', array( 'jquery' ), filemtime( $js ), true );

	$css = PP_PERS_DIR . 'css/pp-serv-menu.css';
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'pp-serv-menu', PP_PERS_URL . 'css/pp-serv-menu.css', array(), filemtime( $css ) );
	}

	// Tipos guardados por menú (para prellenar el select al EDITAR).
	$guardados = array();
	if ( isset( $_GET['action'], $_GET['listing_id'] ) && 'edit' === $_GET['action'] ) {
		$menu = get_post_meta( absint( $_GET['listing_id'] ), '_menu', true );
		if ( is_array( $menu ) ) {
			$ix = 0;
			foreach ( $menu as $g ) {
				if ( is_array( $g ) && isset( $g['pp_tipo_servicio'] ) ) {
					$guardados[ $ix ] = sanitize_key( $g['pp_tipo_servicio'] );
				}
				$ix++;
			}
		}
	}

	$tipos = array();
	foreach ( pp_serv_tipos() as $slug => $t ) {
		$tipos[] = array( 'slug' => $slug, 'nombre' => $t['nombre'], 'mascota' => $t['mascota'] );
	}

	wp_localize_script( 'pp-serv-menu', 'PP_SERV_MENU', array(
		'tipos'     => $tipos,
		'guardados' => $guardados ? $guardados : new stdClass(),
		'msgFalta'  => 'Selecciona el "Tipo de servicio" en cada menú de servicios.',
	) );
}

/* ---- Restricciones por mascota para el popup de reserva ----
 * Devuelve { índiceDeServicioReservable: {especie, pelaje, pesos[]} }.
 * El índice replica EXACTAMENTE el orden de listeo_get_bookable_services()
 * (recorrido plano de los elementos con `bookable`), que es el value que
 * llevan los checkboxes de servicios en el popup de Booking Plus. */
function pp_serv_restricciones_mascota( $listing_id ) {
	$menu = get_post_meta( $listing_id, '_menu', true );
	$out  = array();
	if ( ! is_array( $menu ) ) {
		return $out;
	}
	$ix = 0;
	foreach ( $menu as $g ) {
		if ( ! is_array( $g ) || empty( $g['menu_elements'] ) || ! is_array( $g['menu_elements'] ) ) {
			continue;
		}
		$grupo_mascota = pp_serv_tipo_permite_mascota( sanitize_key( $g['pp_tipo_servicio'] ?? '' ) );
		foreach ( $g['menu_elements'] as $el ) {
			if ( ! is_array( $el ) || ! isset( $el['bookable'] ) ) {
				continue; // misma condición que listeo_get_bookable_services()
			}
			if ( $grupo_mascota && ! empty( $el['pp_dm'] ) ) {
				$pesos = array();
				foreach ( (array) ( $el['pp_dm_pesos'] ?? array() ) as $r ) {
					if ( ! is_array( $r ) ) {
						continue;
					}
					$min = ( isset( $r['min'] ) && '' !== $r['min'] ) ? (float) $r['min'] : null;
					$max = ( isset( $r['max'] ) && '' !== $r['max'] ) ? (float) $r['max'] : null;
					if ( null !== $min || null !== $max ) {
						$pesos[] = array( 'min' => $min, 'max' => $max );
					}
				}
				$restriccion = array(
					'especie' => sanitize_key( $el['pp_dm_especie'] ?? '' ),
					'pelaje'  => sanitize_key( $el['pp_dm_pelaje'] ?? '' ),
					'pesos'   => $pesos,
				);
				// Solo registrar si de verdad restringe algo.
				if ( $restriccion['especie'] || $restriccion['pelaje'] || $pesos ) {
					$out[ $ix ] = $restriccion;
				}
			}
			$ix++;
		}
	}
	return $out;
}

/* =========================================================================
 * ANTELACIÓN MÍNIMA POR TIPO DE SERVICIO (v2.6.0)
 *
 * Cada tipo de servicio puede definir su propia antelación mínima de
 * reserva ('aviso', en minutos). Cuando aplica, ese valor MANDA sobre la
 * configuración del listado y la global de Booking Plus.
 *
 * Mecanismo (un solo punto, cubre todo): Booking Plus resuelve la
 * antelación en LBP_Booking_Cutoff::get_minutes(), que lee el meta del
 * listado `_lbp_min_advance_notice`. Interceptamos ESA lectura con el
 * filtro estándar `get_post_metadata`: así el valor del tipo alimenta a
 * la vez (a) los horarios visibles del popup (lbp_slot_lead_time_minutes),
 * (b) el candado del submit del popup (is_too_soon) y (c) el gate del
 * widget clásico (listeo_count_free_places) — sin duplicar su lógica y a
 * prueba de sus actualizaciones.
 *
 * Resolución del contexto:
 *  1. Servicios elegidos en la petición ($_REQUEST['services'] /
 *     'lbp_services', índices planos de los checkboxes del popup) →
 *     el aviso MÁS ESTRICTO (max) de sus tipos.
 *  2. Sin selección: si TODOS los menús reservables del listado son de un
 *     mismo tipo con aviso definido, aplica ese (cubre la vista de
 *     horarios de listados mono-tipo, p. ej. una repostería).
 *  3. Listado mixto sin selección → null (comportamiento actual intacto).
 *
 * wp-admin puro (reservas manuales del staff) queda intacto.
 * ====================================================================== */

/** Aviso (minutos) que aplica a la petición actual para un listado, o null. */
function pp_serv_aviso_para_peticion( $listing_id ) {
	// Caché por petición SOLO de resultados positivos. Un null NO se cachea:
	// puede venir de una lectura temprana (p. ej. la comparación interna de
	// update_post_meta durante un guardado, antes de que exista el _menu) y
	// recalcularlo es barato (el _menu ya está en la caché de metas de WP).
	static $cache = array();
	$listing_id = (int) $listing_id;
	if ( isset( $cache[ $listing_id ] ) ) {
		return $cache[ $listing_id ];
	}

	$out   = null;
	$tipos = pp_serv_tipos();

	// Mapa índice-plano-de-servicio → tipo del menú (mismo recorrido que
	// listeo_get_bookable_services / los checkboxes del popup).
	$menu              = get_post_meta( $listing_id, '_menu', true );
	$tipo_por_indice   = array();
	$tipos_del_listado = array();
	if ( is_array( $menu ) ) {
		$ix = 0;
		foreach ( $menu as $g ) {
			if ( ! is_array( $g ) || empty( $g['menu_elements'] ) || ! is_array( $g['menu_elements'] ) ) {
				continue;
			}
			$tipo_g = sanitize_key( $g['pp_tipo_servicio'] ?? '' );
			foreach ( $g['menu_elements'] as $el ) {
				if ( ! is_array( $el ) || ! isset( $el['bookable'] ) ) {
					continue;
				}
				$tipo_por_indice[ $ix ] = $tipo_g;
				if ( $tipo_g ) {
					$tipos_del_listado[ $tipo_g ] = 1;
				}
				$ix++;
			}
		}
	}

	// 1) Servicios elegidos en la petición (submit / disponibilidad del popup).
	$sel = array();
	if ( isset( $_REQUEST['services'] ) ) {
		$sel = (array) $_REQUEST['services'];
	} elseif ( isset( $_REQUEST['lbp_services'] ) ) {
		$sel = (array) $_REQUEST['lbp_services'];
	}
	$avisos = array();
	foreach ( $sel as $ix_sel ) {
		if ( ! is_scalar( $ix_sel ) ) {
			continue;
		}
		$tipo_slug = $tipo_por_indice[ (int) $ix_sel ] ?? '';
		if ( $tipo_slug && isset( $tipos[ $tipo_slug ] ) && '' !== $tipos[ $tipo_slug ]['aviso'] ) {
			$avisos[] = (int) $tipos[ $tipo_slug ]['aviso'];
		}
	}
	if ( $avisos ) {
		$out = max( $avisos ); // el más estricto de los servicios elegidos
	} elseif ( 1 === count( $tipos_del_listado ) ) {
		// 2) Listado mono-tipo: su aviso aplica también antes de elegir servicio.
		$unico = array_keys( $tipos_del_listado );
		$unico = $unico[0];
		if ( isset( $tipos[ $unico ] ) && '' !== $tipos[ $unico ]['aviso'] ) {
			$out = (int) $tipos[ $unico ]['aviso'];
		}
	}

	if ( null !== $out ) {
		$cache[ $listing_id ] = $out; // solo positivos (ver nota de arriba)
	}
	return $out;
}

add_filter( 'get_post_metadata', 'pp_serv_interceptar_aviso', 10, 4 );
function pp_serv_interceptar_aviso( $valor, $object_id, $meta_key, $single ) {
	// Salida ultra barata para el 99.99% de lecturas de meta.
	if ( '_lbp_min_advance_notice' !== $meta_key ) {
		return $valor;
	}
	// wp-admin puro (reservas manuales del staff): intacto.
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $valor;
	}
	if ( 'listing' !== get_post_type( $object_id ) ) {
		return $valor;
	}
	$aviso = pp_serv_aviso_para_peticion( (int) $object_id );
	if ( null === $aviso ) {
		return $valor; // sin contexto de tipo → meta real del listado
	}
	// Formato que espera get_metadata: array; con $single toma el [0].
	return array( (string) $aviso );
}
