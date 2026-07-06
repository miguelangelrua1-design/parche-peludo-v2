<?php
/**
 * Plugin Name: Personalización Parche
 * Plugin URI:  https://parchepeludo.com
 * Description: Personalizaciones de Parche Peludo sobre Listeo, organizadas por módulos: Mascotas (perfiles de los peludos de cada usuario e integración con las reservas), Servicios y Reservas (próximamente).
 * Version:     2.6.1
 * Author:      Parche Peludo
 * Text Domain: pp-personalizacion
 *
 * Historia: nació como el plugin "PP Mascotas" (v1.x). En v2.0.0 se renombró
 * a "Personalización Parche" y se organizó por módulos. TODOS los datos se
 * conservan (CPT pp_mascota, opciones pp_mascotas_*, metas de reservas):
 * solo cambió la envoltura y la ubicación de los menús en wp-admin.
 *
 * Requiere el tema Listeo + plugin listeo-core (+ Listeo Booking Plus para
 * la integración de mascotas en el popup de reservas).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PP_PERS_DIR', plugin_dir_path( __FILE__ ) );
define( 'PP_PERS_URL', plugin_dir_url( __FILE__ ) );

/* -------------------------------------------------------------------------
 * Menú principal "Personalización Parche" en wp-admin
 *
 * Estructura (los submenús de Mascotas los aporta el módulo):
 *   Personalización Parche
 *   ├── Inicio       (resumen de módulos)
 *   ├── Mascotas     (listado del CPT pp_mascota)
 *   ├── Razas        (catálogo de razas por especie)
 *   ├── Ajustes      (mascota en las reservas / precio según mascota)
 *   ├── Servicios    (próximamente)
 *   └── Reservas     (próximamente)
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', 'pp_pers_menu_principal', 9 );
function pp_pers_menu_principal() {
	add_menu_page(
		'Personalización Parche',
		'Personalización Parche',
		'manage_options',
		'pp-personalizacion',
		'pp_pers_pagina_inicio',
		'dashicons-pets',
		58
	);
	// Renombra el primer ítem (que por defecto repite el nombre del menú).
	add_submenu_page( 'pp-personalizacion', 'Personalización Parche', 'Inicio', 'manage_options', 'pp-personalizacion', 'pp_pers_pagina_inicio', 0 );
}

// Submenús: Servicios, Listados, Reservas. (Mascotas lo aporta el CPT;
// Razas y Ajustes ya NO son submenús: son tabs dentro de Mascotas y Reservas.)
add_action( 'admin_menu', 'pp_pers_menus_futuros', 30 );
function pp_pers_menus_futuros() {
	add_submenu_page( 'pp-personalizacion', 'Servicios', 'Servicios', 'manage_options', 'pp-servicios', 'pp_pers_pagina_servicios' );
	add_submenu_page( 'pp-personalizacion', 'Listados', 'Listados', 'manage_options', 'pp-listados', 'pp_listados_admin_page' );
	add_submenu_page( 'pp-personalizacion', 'Reservas', 'Reservas', 'manage_options', 'pp-reservas', 'pp_pers_pagina_reservas' );
}

// Orden final de los submenús (WordPress los coloca según el momento de
// registro; el del CPT llega aparte). Los reordenamos explícitamente:
// Inicio · Mascotas · Servicios · Listados · Reservas.
add_action( 'admin_menu', 'pp_pers_ordenar_submenu', 999 );
function pp_pers_ordenar_submenu() {
	global $submenu;
	if ( empty( $submenu['pp-personalizacion'] ) ) {
		return;
	}
	$orden = array(
		'pp-personalizacion',              // Inicio
		'edit.php?post_type=pp_mascota',   // Mascotas (CPT)
		'pp-servicios',                    // Servicios
		'pp-listados',                     // Listados
		'pp-reservas',                     // Reservas
	);
	$pos = array_flip( $orden );
	usort( $submenu['pp-personalizacion'], function ( $a, $b ) use ( $pos ) {
		$pa = $pos[ $a[2] ] ?? 99;
		$pb = $pos[ $b[2] ] ?? 99;
		return $pa <=> $pb;
	} );
}

/** Página Inicio: resumen del estado de los módulos. */
function pp_pers_pagina_inicio() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$conteo     = wp_count_posts( 'pp_mascota' );
	$n_mascotas = $conteo ? (int) $conteo->publish : 0;
	$razas      = function_exists( 'pp_mascotas_get_razas_editables' ) ? pp_mascotas_get_razas_editables() : array( 'perro' => array(), 'gato' => array() );
	?>
	<div class="wrap">
		<h1>🐾 Personalización Parche</h1>
		<p style="max-width:680px">Personalizaciones propias de Parche Peludo sobre Listeo, organizadas por módulos. Desde aquí administras lo que el tema y sus plugins no traen de fábrica.</p>

		<div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:18px">

			<div style="flex:1;min-width:260px;max-width:360px;background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:20px">
				<h2 style="margin:0 0 4px">🐾 Mascotas</h2>
				<p style="margin:0 0 10px"><span style="background:#e6f6f0;color:#1b5e40;border-radius:999px;padding:2px 10px;font-size:12px;font-weight:600">Activo</span></p>
				<p style="color:#50575e">Perfiles de mascotas de los usuarios (sección "Mis Mascotas" del dashboard) y su integración con el popup de reservas.</p>
				<ul style="margin:0;color:#50575e">
					<li>Mascotas registradas: <strong><?php echo esc_html( $n_mascotas ); ?></strong></li>
					<li>Razas en catálogo: <strong><?php echo esc_html( count( $razas['perro'] ) + count( $razas['gato'] ) ); ?></strong> (+ "Otro")</li>
				</ul>
				<p style="margin-top:12px">
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=pp_mascota' ) ); ?>">Ver mascotas</a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=pp-razas' ) ); ?>">Razas</a>
				</p>
			</div>

			<div style="flex:1;min-width:260px;max-width:360px;background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:20px">
				<h2 style="margin:0 0 4px">🧰 Servicios</h2>
				<p style="margin:0 0 10px"><span style="background:#e6f6f0;color:#1b5e40;border-radius:999px;padding:2px 10px;font-size:12px;font-weight:600">Activo</span></p>
				<p style="color:#50575e">Personalizaciones sobre los servicios del directorio.</p>
				<ul style="margin:0;color:#50575e">
					<li>✔ "Elige tu servicio" en la reserva (listados con servicios individuales)</li>
					<li>✔ Tabs por tipo de servicio (listados con varios menús)</li>
				</ul>
				<p style="margin-top:12px"><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=pp-servicios' ) ); ?>">Ver detalle</a></p>
			</div>

			<div style="flex:1;min-width:260px;max-width:360px;background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:20px">
				<h2 style="margin:0 0 4px">📋 Listados</h2>
				<p style="margin:0 0 10px"><span style="background:#e6f6f0;color:#1b5e40;border-radius:999px;padding:2px 10px;font-size:12px;font-weight:600">Activo</span></p>
				<p style="color:#50575e">Reglas de publicación por rol.</p>
				<ul style="margin:0;color:#50575e">
					<li>✔ Usuarios (guest) publican Adopción y Mascotas perdidas</li>
					<li>✔ Directorio reservado a Prestadores</li>
				</ul>
				<p style="margin-top:12px"><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=pp-listados' ) ); ?>">Ver detalle</a></p>
			</div>

			<div style="flex:1;min-width:260px;max-width:360px;background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:20px">
				<h2 style="margin:0 0 4px">📅 Reservas</h2>
				<p style="margin:0 0 10px"><span style="background:#e6f6f0;color:#1b5e40;border-radius:999px;padding:2px 10px;font-size:12px;font-weight:600">Activo</span></p>
				<p style="color:#50575e">Ajustes del flujo de reservas.</p>
				<ul style="margin:0;color:#50575e">
					<li>Pedir la mascota al reservar</li>
					<li>Precio según la mascota (preparación)</li>
				</ul>
				<p style="margin-top:12px"><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=pp-reservas' ) ); ?>">Ver ajustes</a></p>
			</div>

		</div>
	</div>
	<?php
}

/** Página Servicios — organizada por TABS: Tipos de Servicio | Personalizaciones. */
function pp_pers_pagina_servicios() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$tabs = array(
		'tipos'  => 'Tipos de Servicio',
		'extras' => 'Personalizaciones',
	);
	$activo = sanitize_key( $_GET['tab'] ?? 'tipos' );
	if ( ! isset( $tabs[ $activo ] ) ) {
		$activo = 'tipos';
	}
	?>
	<div class="wrap">
		<h1>🧰 Servicios</h1>
		<h2 class="nav-tab-wrapper" style="margin-bottom:16px">
			<?php foreach ( $tabs as $clave => $etiqueta ) :
				$url   = admin_url( 'admin.php?page=pp-servicios&tab=' . $clave );
				$clase = 'nav-tab' . ( $clave === $activo ? ' nav-tab-active' : '' ); ?>
				<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $clase ); ?>"><?php echo esc_html( $etiqueta ); ?></a>
			<?php endforeach; ?>
		</h2>

		<?php
		if ( 'tipos' === $activo && function_exists( 'pp_serv_tab_tipos' ) ) {
			pp_serv_tab_tipos();
			echo '</div>';
			return;
		}
		?>
		<h2>Personalizaciones activas</h2>
		<div style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px;max-width:720px">
			<h3 style="margin-top:0">"Elige tu servicio" en la reserva</h3>
			<p>En todo listado que ofrezca <strong>servicios reservables</strong>, el acordeón del popup de reserva ya no dice "Servicios adicionales":</p>
			<ul style="list-style:disc;padding-left:20px">
				<li>La etiqueta base pasa a ser <strong>"Elige tu servicio"</strong> (tenga o no servicios individuales).</li>
				<li>Si hay servicios individuales, al seleccionar uno la etiqueta muestra <strong>el nombre del servicio elegido</strong>.</li>
				<li>Los listados sin servicios reservables conservan "Servicios adicionales".</li>
			</ul>
			<p class="description">Cómo activar servicios individuales en un listado: Listeo Core → pestaña Booking → "Enable Individual Services" (global, ya activado) + marcar "Individual service" en cada servicio del menú del listado + opcionalmente "Require service selection" en el listado para que elegir sea obligatorio.</p>
		</div>

		<div style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px;max-width:720px;margin-top:14px">
			<h3 style="margin-top:0">Tabs por tipo de servicio</h3>
			<p>Cuando un listado tiene <strong>varios menús</strong> con servicios reservables (p. ej. "Adiestramiento" y "Fotografía y Video"), el popup de reserva muestra <strong>pestañas con los tipos</strong>; al elegir uno se habilita la lista de servicios de ese tipo (con contador de seleccionados por pestaña).</p>
			<ul style="list-style:disc;padding-left:20px">
				<li>Con un solo menú, no aparecen pestañas (comportamiento normal).</li>
				<li>Regla vigente: <strong>un solo servicio principal (individual) por reserva</strong>, sin importar el tipo; los extras se acumulan libremente.</li>
			</ul>
		</div>
		<p style="margin-top:16px" class="description">Más personalizaciones de este módulo se definirán próximamente.</p>
	</div>
	<?php
}

/**
 * Página Reservas — organizada por TABS. Hoy: "Ajustes" (mascota en la
 * reserva / precio según la mascota). A futuro se añadirán más pestañas.
 */
function pp_pers_pagina_reservas() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$tabs = array(
		'ajustes' => 'Ajustes',
	);
	$activo = sanitize_key( $_GET['tab'] ?? 'ajustes' );
	if ( ! isset( $tabs[ $activo ] ) ) {
		$activo = 'ajustes';
	}
	?>
	<div class="wrap">
		<h1>📅 Reservas</h1>
		<h2 class="nav-tab-wrapper" style="margin-bottom:16px">
			<?php foreach ( $tabs as $clave => $etiqueta ) :
				$url   = admin_url( 'admin.php?page=pp-reservas&tab=' . $clave );
				$clase = 'nav-tab' . ( $clave === $activo ? ' nav-tab-active' : '' ); ?>
				<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $clase ); ?>"><?php echo esc_html( $etiqueta ); ?></a>
			<?php endforeach; ?>
		</h2>

		<?php
		if ( 'ajustes' === $activo && function_exists( 'pp_mascotas_ajustes_form' ) ) {
			pp_mascotas_ajustes_form();
		}
		?>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Módulos
 * ---------------------------------------------------------------------- */

require_once PP_PERS_DIR . 'includes/mascotas.php';
require_once PP_PERS_DIR . 'includes/servicios.php';
require_once PP_PERS_DIR . 'includes/listados.php';
