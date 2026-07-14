<?php
/**
 * Filtros personalizados — página de administración.
 * Ajustes → Filtros personalizados: activar/desactivar y configurar el
 * filtro de disponibilidad (búsqueda por franjas).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'pp_franjas_menu_admin' );
function pp_franjas_menu_admin() {
	add_options_page(
		'Filtros personalizados',
		'Filtros personalizados',
		'manage_options',
		'pp-busqueda-franjas',
		'pp_franjas_pagina_admin'
	);
}

/** Guarda los ajustes (con nonce) y pinta la página. */
function pp_franjas_pagina_admin() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$guardado    = false;
	$reindexados = null;
	if ( isset( $_POST['pp_franjas_reindexar'] ) && check_admin_referer( 'pp_franjas_ajustes' ) ) {
		$reindexados = pp_franjas_reindexar_todo();
	} elseif ( isset( $_POST['pp_franjas_guardar'] ) && check_admin_referer( 'pp_franjas_ajustes' ) ) {
		update_option( 'pp_franjas_activo', empty( $_POST['pp_franjas_activo'] ) ? 0 : 1 );
		update_option( 'pp_franjas_incluir_date_range', empty( $_POST['pp_franjas_incluir_date_range'] ) ? 0 : 1 );
		update_option( 'pp_franjas_reemplazar_carrusel', empty( $_POST['pp_franjas_reemplazar_carrusel'] ) ? 0 : 1 );

		// Hora de inicio del selector (el final es siempre el fin del día).
		$hora_min = sanitize_text_field( wp_unslash( $_POST['pp_franjas_hora_min'] ?? '08:00' ) );
		if ( preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $hora_min ) ) {
			update_option( 'pp_franjas_hora_min', $hora_min );
		}

		$paso = (int) ( $_POST['pp_franjas_paso'] ?? 30 );
		if ( in_array( $paso, array( 15, 30, 60 ), true ) ) {
			update_option( 'pp_franjas_paso', $paso );
		}

		pp_franjas_invalidar_cache(); // por si cambió la config que afecta el cálculo
		$guardado = true;
	}

	$activo     = (bool) get_option( 'pp_franjas_activo', 0 );
	$incluir_dr = (bool) get_option( 'pp_franjas_incluir_date_range', 1 );
	$carrusel   = (bool) get_option( 'pp_franjas_reemplazar_carrusel', 1 );
	$hora_min   = (string) get_option( 'pp_franjas_hora_min', '08:00' );
	$paso       = (int) get_option( 'pp_franjas_paso', 30 );

	// Transparencia: qué tipos de listado participan hoy y cómo.
	$tipos_cita = function_exists( 'listeo_core_get_listing_type_slugs_by_booking_type' )
		? (array) listeo_core_get_listing_type_slugs_by_booking_type( 'single_day' )
		: array();
	$tipos_dr = function_exists( 'listeo_core_get_listing_type_slugs_by_booking_type' )
		? (array) listeo_core_get_listing_type_slugs_by_booking_type( 'date_range' )
		: array();
	?>
	<div class="wrap">
		<h1>🎛️ Filtros personalizados</h1>
		<p style="max-width:680px"><strong>Búsqueda por franjas</strong>: filtro de disponibilidad por <strong>fecha y hora</strong> en los resultados de listados. El visitante elige la franja y pulsa <em>"Buscar disponibilidad"</em>: solo entonces se filtra (nunca de forma automática, para no afectar el performance de la búsqueda normal). El panel vive en el cabezote de los resultados, en el lugar del carrusel de categorías.</p>

		<?php if ( $guardado ) : ?>
			<div class="notice notice-success is-dismissible"><p>Ajustes guardados.</p></div>
		<?php endif; ?>
		<?php if ( null !== $reindexados ) : ?>
			<div class="notice notice-success is-dismissible"><p>Índice de servicios regenerado: <?php echo esc_html( $reindexados ); ?> listados procesados.</p></div>
		<?php endif; ?>

		<form method="post" style="max-width:680px">
			<?php wp_nonce_field( 'pp_franjas_ajustes' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Estado</th>
					<td>
						<label>
							<input type="checkbox" name="pp_franjas_activo" value="1" <?php checked( $activo ); ?>>
							Activar la búsqueda por franjas en los resultados
						</label>
						<p class="description">Al desactivarla, el panel desaparece del sitio y las búsquedas ignoran cualquier parámetro de franja.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Carrusel de categorías</th>
					<td>
						<label>
							<input type="checkbox" name="pp_franjas_reemplazar_carrusel" value="1" <?php checked( $carrusel ); ?>>
							Reemplazar el carrusel de categorías del cabezote por el panel de Disponibilidad
						</label>
						<p class="description">Con esto activo, el carrusel (Bienestar, Comportamiento, Cuidado…) ya no se genera y en su lugar aparece el panel, junto al botón "Mostrar Filtros" (que no se toca). Al apagarlo, el carrusel vuelve y el panel se muestra encima de los resultados.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Horas del selector</th>
					<td>
						Desde
						<input type="time" name="pp_franjas_hora_min" value="<?php echo esc_attr( $hora_min ); ?>" step="1800" style="width:110px">
						hasta el final del día, cada
						<select name="pp_franjas_paso">
							<option value="15" <?php selected( $paso, 15 ); ?>>15 min</option>
							<option value="30" <?php selected( $paso, 30 ); ?>>30 min</option>
							<option value="60" <?php selected( $paso, 60 ); ?>>1 hora</option>
						</select>
						<p class="description">El selector arranca en la hora de inicio y llega hasta las 11:xx p.m. Las opciones se muestran en formato a.m./p.m.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Tipos con rango de fechas</th>
					<td>
						<label>
							<input type="checkbox" name="pp_franjas_incluir_date_range" value="1" <?php checked( $incluir_dr ); ?>>
							Incluir los tipos con reserva por rango (p. ej. Guardería) cuando estén libres ese día <strong>desde la hora elegida</strong>
						</label>
						<p class="description">Aplica cuando se busca con "Todos los servicios": estos listados no manejan citas por hora, así que se muestran si ninguna reserva los ocupa desde la hora elegida en adelante ese día (un checkout en la mañana no bloquea la tarde). Si la opción está apagada, quedan fuera de esa búsqueda general. Al buscar su servicio específico (p. ej. Cuidador), se usa el modo entrada–salida y esta opción no interviene.</p>
					</td>
				</tr>
			</table>
			<p><button type="submit" name="pp_franjas_guardar" value="1" class="button button-primary">Guardar cambios</button></p>
		</form>

		<h2 style="margin-top:28px">Servicios del panel y modo de cada uno</h2>
		<div style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:16px;max-width:680px">
			<p style="margin-top:0">El selector del panel sale del catálogo de <a href="<?php echo esc_url( admin_url( 'admin.php?page=pp-servicios' ) ); ?>">Tipos de Servicio</a> y su modo lo decide la matriz tipología × tipo de listado:</p>
			<ul style="margin:0;list-style:disc;padding-left:20px">
				<?php foreach ( pp_franjas_servicios_para_panel() as $slug => $s ) : ?>
					<li><strong><?php echo esc_html( $s['nombre'] ); ?></strong> — <?php echo 'rango' === $s['modo'] ? 'entrada–salida (estadía; con 2+ días se validan todas las noches)' : 'fecha + hora (cita)'; ?></li>
				<?php endforeach; ?>
			</ul>
			<p class="description" style="margin-bottom:0">Un listado aparece al buscar un servicio solo si lo declara: los de cita, en sus Menús de Servicios (con al menos un servicio reservable); los de estadía (<?php echo $tipos_dr ? esc_html( implode( ', ', $tipos_dr ) ) : 'guardería'; ?>) lo ofrecen implícitamente por su tipo. Tipos no reservables (adopción, mascotas perdidas…) quedan fuera mientras el filtro está aplicado.</p>
		</div>

		<h2 style="margin-top:28px">Índice de servicios</h2>
		<div style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:16px;max-width:680px">
			<p style="margin-top:0">Al guardar un listado, el plugin genera una marca liviana y consultable con los servicios que ofrece (así la búsqueda no tiene que abrir los menús de cada listado). Se mantiene solo; usa este botón únicamente si sospechas que quedó desactualizado (p. ej. tras importar listados por fuera del formulario).</p>
			<form method="post" style="margin:0">
				<?php wp_nonce_field( 'pp_franjas_ajustes' ); ?>
				<button type="submit" name="pp_franjas_reindexar" value="1" class="button">Regenerar índice ahora</button>
			</form>
		</div>

		<h2 style="margin-top:28px">Performance</h2>
		<div style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:16px;max-width:680px">
			<ul style="margin:0;list-style:disc;padding-left:20px">
				<li>El cálculo usa <strong>consultas agrupadas</strong> (~5 consultas fijas, sin importar cuántos listados haya) — nunca consulta listado por listado.</li>
				<li>El resultado se guarda en <strong>caché 10 minutos</strong> y se invalida solo al crear/cancelar/expirar reservas o editar un listado.</li>
				<li>La búsqueda normal (sin franja) <strong>no ejecuta nada</strong> de este plugin.</li>
			</ul>
		</div>
	</div>
	<?php
}
