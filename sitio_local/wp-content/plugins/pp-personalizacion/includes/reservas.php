<?php
/**
 * Módulo RESERVAS — Personalización Parche
 *
 * Primera funcionalidad real del módulo: la DIRECCIÓN en el flujo de reserva.
 *
 * 1) La modal de reserva (Listeo Booking Plus) muestra los campos de
 *    dirección en el orden estándar: Departamento → Ciudad → Dirección.
 *    Se logra sirviendo nuestra copia de la plantilla modal-step-confirm.php
 *    a través del filtro `lbp_template_paths` del loader del plugin (el tema
 *    hijo, si algún día quiere, aún puede sobreescribirnos: su carpeta tiene
 *    prioridad 1 y la nuestra 50).
 * 2) Ciudad es un desplegable dependiente del departamento (mejora
 *    progresiva en JS: si el script no corre, queda input de texto y la
 *    reserva funciona igual). Dataset: js/pp-ciudades-co.js (1.123
 *    municipios, indexado por nombre de departamento normalizado).
 * 3) Si el usuario ya tiene dirección completa, la modal muestra un RESUMEN
 *    (departamento · ciudad · dirección) con botón Editar; los campos quedan
 *    ocultos pero con valores (la validación del plugin valida valores).
 * 4) Al enviar la reserva, la dirección se guarda en el perfil del usuario
 *    (user meta billing_*), y aparece/edita en Panel de Control → Mi Perfil
 *    (sección propia con su formulario, hook listeo_core_my_account_sections).
 *
 * Performance: los dos JS (≈17 KB, ≈6 KB gzip) y el CSS solo se encolan en
 * fichas de listado y en la página Mi Perfil. Nada global.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * 1) Plantilla de la modal: registrar nuestra carpeta en el loader de LBP
 * ---------------------------------------------------------------------- */
add_filter( 'lbp_template_paths', 'pp_reservas_template_path' );
function pp_reservas_template_path( $paths ) {
	// 1 = tema hijo, 10 = tema padre, 100 = plugin LBP. 50 = nosotros:
	// pisamos el plugin, pero un override del tema seguiría mandando.
	$paths[50] = PP_PERS_DIR . 'templates/listeo-booking-plus/';
	return $paths;
}

/* -------------------------------------------------------------------------
 * 2) Assets (ficha de listado + Mi Perfil)
 * ---------------------------------------------------------------------- */
function pp_reservas_es_pagina_perfil() {
	$perfil = (int) get_option( 'listeo_profile_page' );
	return $perfil && is_page( $perfil );
}

add_action( 'wp_enqueue_scripts', 'pp_reservas_direccion_assets', 100 );
function pp_reservas_direccion_assets() {
	if ( ! is_singular( 'listing' ) && ! pp_reservas_es_pagina_perfil() ) {
		return;
	}
	$css  = PP_PERS_DIR . 'css/pp-reservas-direccion.css';
	$data = PP_PERS_DIR . 'js/pp-ciudades-co.js';
	$js   = PP_PERS_DIR . 'js/pp-reservas-direccion.js';
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'pp-reservas-direccion', PP_PERS_URL . 'css/pp-reservas-direccion.css', array(), filemtime( $css ) );
	}
	if ( file_exists( $data ) && file_exists( $js ) ) {
		// Nota: el checkout de WooCommerce carga su propia copia del dataset
		// desde el tema hijo (handle ppv2-ciudades-co). No coinciden nunca en
		// la misma página, así que no hay doble carga.
		wp_enqueue_script( 'pp-ciudades-co', PP_PERS_URL . 'js/pp-ciudades-co.js', array(), filemtime( $data ), true );
		wp_enqueue_script( 'pp-reservas-direccion', PP_PERS_URL . 'js/pp-reservas-direccion.js', array( 'pp-ciudades-co' ), filemtime( $js ), true );
	}
}

/* -------------------------------------------------------------------------
 * 3) Guardar la dirección en el PERFIL al enviar la reserva
 *
 * Corre en prioridad 0 sobre la MISMA acción AJAX del plugin (su handler va
 * en 10 y termina con wp_die). Solo usuarios logueados: los invitados no
 * tienen perfil que actualizar. El nonce se verifica en modo no fatal: si no
 * valida, simplemente no guardamos y dejamos que el handler del plugin
 * decida qué hacer con la petición.
 * ---------------------------------------------------------------------- */
add_action( 'wp_ajax_lbp_submit_booking', 'pp_reservas_guardar_direccion_usuario', 0 );
function pp_reservas_guardar_direccion_usuario() {
	if ( ! is_user_logged_in() ) {
		return;
	}
	if ( ! check_ajax_referer( 'lbp_booking_nonce', 'nonce', false ) ) {
		return;
	}
	$user_id = get_current_user_id();
	$campos  = array( 'billing_state', 'billing_city', 'billing_address_1', 'billing_address_2', 'billing_country' );
	foreach ( $campos as $campo ) {
		if ( ! isset( $_POST[ $campo ] ) ) {
			continue;
		}
		$valor = sanitize_text_field( wp_unslash( $_POST[ $campo ] ) );
		// address_2 vacío SÍ se guarda (el usuario pudo borrarlo); el resto
		// solo si trae valor, para no vaciar el perfil por un envío parcial.
		if ( '' !== $valor || 'billing_address_2' === $campo ) {
			update_user_meta( $user_id, $campo, $valor );
		}
	}
}

/* -------------------------------------------------------------------------
 * 4) Sección "Dirección" en Panel de Control → Mi Perfil
 *
 * El hook del tema cae FUERA del <form> principal del perfil, así que esta
 * sección lleva su propio formulario y su propio guardado (init).
 * ---------------------------------------------------------------------- */
add_action( 'listeo_core_my_account_sections', 'pp_reservas_seccion_direccion_perfil' );
function pp_reservas_seccion_direccion_perfil( $current_user ) {
	$estado    = get_user_meta( $current_user->ID, 'billing_state', true );
	$ciudad    = get_user_meta( $current_user->ID, 'billing_city', true );
	$dir1      = get_user_meta( $current_user->ID, 'billing_address_1', true );
	$dir2      = get_user_meta( $current_user->ID, 'billing_address_2', true );
	$pais      = get_user_meta( $current_user->ID, 'billing_country', true );
	$pais      = $pais ? $pais : 'CO';
	$estados   = ( function_exists( 'WC' ) && WC() && WC()->countries ) ? WC()->countries->get_states( $pais ) : array();
	$guardado  = isset( $_GET['pp_direccion'] ) && 'ok' === $_GET['pp_direccion'];
	?>
	<div class="col-lg-12 pp-perfil-direccion">
		<div class="dashboard-list-box margin-top-40">
			<h4 class="gray"><?php esc_html_e( 'Dirección', 'pp-personalizacion' ); ?></h4>
			<div class="dashboard-list-box-static">
				<?php if ( $guardado ) : ?>
					<div class="notification success"><p><?php esc_html_e( 'Dirección guardada.', 'pp-personalizacion' ); ?></p></div>
				<?php endif; ?>
				<p class="pp-perfil-direccion-desc"><?php esc_html_e( 'Se usa para tus reservas y compras. La puedes cambiar aquí o al momento de reservar.', 'pp-personalizacion' ); ?></p>
				<form method="post" class="pp-perfil-direccion-form">
					<?php wp_nonce_field( 'pp_direccion_perfil', 'pp_direccion_perfil_nonce' ); ?>
					<div class="pp-perfil-dir-fila">
						<div class="pp-perfil-dir-campo">
							<label for="ppv2-perfil-state"><?php esc_html_e( 'Departamento', 'pp-personalizacion' ); ?></label>
							<?php if ( ! empty( $estados ) ) : ?>
								<select id="ppv2-perfil-state" name="pp_billing_state">
									<option value=""><?php esc_html_e( 'Selecciona tu departamento…', 'pp-personalizacion' ); ?></option>
									<?php foreach ( $estados as $codigo => $nombre ) : ?>
										<option value="<?php echo esc_attr( $codigo ); ?>" <?php selected( $estado, $codigo ); ?>><?php echo esc_html( $nombre ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php else : ?>
								<input type="text" id="ppv2-perfil-state" name="pp_billing_state" value="<?php echo esc_attr( $estado ); ?>">
							<?php endif; ?>
						</div>
						<div class="pp-perfil-dir-campo">
							<label for="ppv2-perfil-city"><?php esc_html_e( 'Ciudad / Municipio', 'pp-personalizacion' ); ?></label>
							<input type="text" id="ppv2-perfil-city" name="pp_billing_city" value="<?php echo esc_attr( $ciudad ); ?>">
						</div>
					</div>
					<div class="pp-perfil-dir-fila">
						<div class="pp-perfil-dir-campo">
							<label for="ppv2-perfil-dir1"><?php esc_html_e( 'Dirección', 'pp-personalizacion' ); ?></label>
							<input type="text" id="ppv2-perfil-dir1" name="pp_billing_address_1" value="<?php echo esc_attr( $dir1 ); ?>">
						</div>
						<div class="pp-perfil-dir-campo">
							<label for="ppv2-perfil-dir2"><?php esc_html_e( 'Apartamento, habitación, etc. (opcional)', 'pp-personalizacion' ); ?></label>
							<input type="text" id="ppv2-perfil-dir2" name="pp_billing_address_2" value="<?php echo esc_attr( $dir2 ); ?>">
						</div>
					</div>
					<button type="submit" class="button pp-perfil-dir-guardar"><?php esc_html_e( 'Guardar dirección', 'pp-personalizacion' ); ?></button>
				</form>
			</div>
		</div>
	</div>
	<?php
}

add_action( 'init', 'pp_reservas_guardar_direccion_perfil', 11 );
function pp_reservas_guardar_direccion_perfil() {
	if ( ! is_user_logged_in() || ! isset( $_POST['pp_direccion_perfil_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pp_direccion_perfil_nonce'] ) ), 'pp_direccion_perfil' ) ) {
		return;
	}
	$user_id = get_current_user_id();
	$mapa = array(
		'pp_billing_state'     => 'billing_state',
		'pp_billing_city'      => 'billing_city',
		'pp_billing_address_1' => 'billing_address_1',
		'pp_billing_address_2' => 'billing_address_2',
	);
	foreach ( $mapa as $campo_post => $meta ) {
		if ( isset( $_POST[ $campo_post ] ) ) {
			update_user_meta( $user_id, $meta, sanitize_text_field( wp_unslash( $_POST[ $campo_post ] ) ) );
		}
	}
	// País consistente para el resto del ecosistema (WooCommerce, LBP).
	if ( ! get_user_meta( $user_id, 'billing_country', true ) ) {
		update_user_meta( $user_id, 'billing_country', 'CO' );
	}
	// Recargar sin re-POST (patrón POST/redirect/GET) + aviso de guardado.
	// OJO: wp_get_referer() devuelve false cuando el form envía a la MISMA
	// página (caso normal aquí) → se redirige explícitamente a Mi Perfil.
	$perfil  = (int) get_option( 'listeo_profile_page' );
	$destino = $perfil ? get_permalink( $perfil ) : home_url();
	wp_safe_redirect( add_query_arg( 'pp_direccion', 'ok', $destino ) );
	exit;
}
