<?php
/**
 * El embudo de reservas → Google Analytics 4
 * ============================================================================
 *
 * POR QUÉ HACE FALTA
 * Parche Peludo es, ante todo, un sitio de reservas; y hasta hoy el flujo de
 * reservas —el corazón del negocio— no se medía en ningún punto. Analytics
 * sabía cuánta gente entraba y qué compraba en la tienda, pero no cuántas
 * reservas se solicitan, cuántas aprueban los prestadores, cuántas se pagan ni
 * cuántas se caen por el camino.
 *
 * POR QUÉ NO SE MIDE DESDE EL NAVEGADOR
 * Los eventos de una reserva NO ocurren todos delante del cliente:
 *
 *   - La aprueba el PRESTADOR desde su panel, en otro navegador y otro día.
 *   - El pago lo confirma la pasarela por webhook: ahí no hay ningún navegador.
 *   - La caducidad la decide un cron, de madrugada, sin nadie conectado.
 *
 * Con `gtag` solo se podría medir el primer paso, y los cambios de estado se
 * atribuirían a quien tuviera el navegador abierto —normalmente el prestador—,
 * que es peor que no medirlos. Por eso se usa el **Measurement Protocol**: el
 * servidor habla directamente con GA4.
 *
 * CÓMO SE CONSERVA LA ATRIBUCIÓN
 * El Measurement Protocol exige decir de QUÉ usuario es cada evento
 * (`client_id`). Se resuelve así, por orden:
 *
 *   1. La cookie `_ga` de quien hace la petición, si la hay.
 *   2. El `client_id` que se guardó al crear ESA reserva. Es lo que hace que la
 *      aprobación y el pago se sumen al recorrido del CLIENTE que reservó, y no
 *      al del prestador que pulsó «aprobar» semanas después.
 *   3. Un identificador estable derivado del usuario, para no inventar un
 *      «usuario nuevo» distinto en cada evento de cron.
 *
 * QUÉ NO SE MIDE, A PROPÓSITO
 * Listeo usa la MISMA tabla y el mismo `type` para las reservas de cliente y
 * para los bloqueos de agenda del prestador («owner reservations»). Contar los
 * bloqueos como reservas inflaría el dato hasta hacerlo inútil, así que se
 * descartan por su `comment` / `status`.
 *
 * CONFIGURACIÓN
 * Hace falta un secreto de API de GA4 (Administrar → Flujos de datos → el flujo
 * web → Secretos de la API de Measurement Protocol). Se pega en
 * Ajustes → Medición GA4. **No se guarda en el código a propósito**: quien
 * tenga ese secreto puede inyectar datos falsos en la propiedad.
 *
 * KILL-SWITCH
 *     add_filter( 'pp_eventos_reservas_activo', '__return_false' );
 *
 * @package listeo-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Identificador de medición de GA4 (el mismo del sitio). */
function pp_ga4_measurement_id() {
	return apply_filters( 'pp_ga4_measurement_id', 'G-953JYYLBJ6' );
}

/**
 * Secreto de la API. Prioridad: constante en wp-config → opción del panel.
 *
 * La constante existe por si algún día se prefiere sacarlo de la base de datos;
 * la opción es lo que permite configurarlo sin tocar archivos.
 */
function pp_ga4_api_secret() {
	if ( defined( 'PP_GA4_MP_SECRET' ) && PP_GA4_MP_SECRET ) {
		return PP_GA4_MP_SECRET;
	}
	return (string) get_option( 'pp_ga4_mp_secret', '' );
}

/* -------------------------------------------------------------------------
   1. IDENTIDAD DEL USUARIO
   ------------------------------------------------------------------------- */

/**
 * Lee el `client_id` de la cookie `_ga`.
 *
 * El formato es `GA1.1.1234567890.1699999999`: los dos últimos campos, unidos
 * por un punto, son el identificador que GA4 espera. Se toman los DOS ÚLTIMOS y
 * no los de la posición 2 y 3 porque el prefijo cambia según el dominio.
 *
 * @return string Client ID, o cadena vacía si no hay cookie.
 */
function pp_ga4_client_id_de_cookie() {
	if ( empty( $_COOKIE['_ga'] ) ) {
		return '';
	}
	$partes = explode( '.', sanitize_text_field( wp_unslash( $_COOKIE['_ga'] ) ) );
	if ( count( $partes ) < 4 ) {
		return '';
	}
	$cid = $partes[ count( $partes ) - 2 ] . '.' . $partes[ count( $partes ) - 1 ];

	return preg_match( '/^\d+\.\d+$/', $cid ) ? $cid : '';
}

/**
 * Lee el identificador de sesión de la cookie `_ga_<ID>`.
 *
 * Sin él, GA4 abre una sesión nueva para cada evento del servidor y el informe
 * de sesiones deja de cuadrar. El valor tiene forma `GS1.1.s1699999999$o3$g1…`:
 * interesa el número que sigue a la `s`.
 *
 * @return string Session ID, o cadena vacía.
 */
function pp_ga4_session_id() {
	$nombre = '_ga_' . str_replace( 'G-', '', pp_ga4_measurement_id() );
	if ( empty( $_COOKIE[ $nombre ] ) ) {
		return '';
	}
	$valor = sanitize_text_field( wp_unslash( $_COOKIE[ $nombre ] ) );

	return preg_match( '/\bs(\d{10,})/', $valor, $m ) ? $m[1] : '';
}

/**
 * Identificador estable para cuando no hay navegador (cron, webhooks).
 *
 * Se deriva del usuario con un hash del `AUTH_SALT` del sitio, de modo que sea
 * siempre el mismo para la misma persona pero no revele su ID a Google. La
 * alternativa —un identificador aleatorio— crearía un «usuario nuevo» en cada
 * evento y falsearía el recuento de usuarios.
 *
 * @param int $user_id Usuario.
 * @return string
 */
function pp_ga4_client_id_derivado( $user_id ) {
	$semilla = defined( 'AUTH_SALT' ) ? AUTH_SALT : ABSPATH;
	$num     = hexdec( substr( md5( 'pp-ga4-' . $semilla . '-' . (int) $user_id ), 0, 8 ) );

	// Mismo formato que usa gtag: <aleatorio>.<marca de tiempo>.
	return $num . '.' . ( 1600000000 + ( (int) $user_id * 7 ) );
}

/* -------------------------------------------------------------------------
   2. ENVÍO
   ------------------------------------------------------------------------- */

/**
 * Cola de eventos pendientes de enviar en esta petición.
 *
 * Se acumulan y se mandan en `shutdown` en vez de en el momento del gancho:
 * hablar con Google en mitad de una reserva metería su latencia en el tiempo
 * de respuesta que ve el usuario.
 *
 * @param array|null $nuevo Evento a encolar, o null para leer la cola.
 * @return array
 */
function pp_ga4_cola( $nuevo = null ) {
	static $cola = array();
	if ( null !== $nuevo ) {
		$cola[] = $nuevo;
	}
	return $cola;
}

/**
 * Encola un evento para GA4.
 *
 * @param string $nombre    Nombre del evento (minúsculas, sin espacios).
 * @param array  $params    Parámetros del evento.
 * @param string $client_id Identificador del usuario al que atribuirlo.
 * @param int    $user_id   Usuario de WordPress, si se conoce.
 */
function pp_ga4_encolar( $nombre, $params, $client_id, $user_id = 0 ) {
	if ( ! $client_id ) {
		return;
	}

	$sesion = pp_ga4_session_id();
	if ( $sesion ) {
		$params['session_id'] = $sesion;
	}
	// GA4 descarta como «no interacción» los eventos sin tiempo de
	// interacción; con esto el evento cuenta como sesión activa.
	$params['engagement_time_msec'] = 1;

	pp_ga4_cola( array(
		'client_id' => $client_id,
		'user_id'   => $user_id ? (string) $user_id : '',
		'evento'    => array(
			'name'   => $nombre,
			'params' => $params,
		),
	) );
}

add_action( 'shutdown', 'pp_ga4_enviar_cola', 99 );
/**
 * Envía a GA4 todo lo acumulado en la petición.
 *
 * No bloqueante: el usuario no espera por Google. Como contrapartida no se
 * puede leer la respuesta, así que para depurar existe el modo de prueba, que
 * usa el endpoint de validación y escribe el resultado en el registro.
 */
function pp_ga4_enviar_cola() {
	$cola = pp_ga4_cola();
	if ( empty( $cola ) ) {
		return;
	}

	$secreto = pp_ga4_api_secret();
	if ( ! $secreto ) {
		// Sin secreto no hay nada que hacer; se avisa una vez al día para que
		// no pase inadvertido que la medición está apagada.
		if ( ! get_transient( 'pp_ga4_aviso_sin_secreto' ) ) {
			error_log( 'PP GA4: hay eventos de reserva que no se enviaron porque falta el secreto de la API (Ajustes → Medición GA4).' );
			set_transient( 'pp_ga4_aviso_sin_secreto', 1, DAY_IN_SECONDS );
		}
		return;
	}

	$depurar = (bool) get_option( 'pp_ga4_mp_depurar', false );
	$base    = $depurar
		? 'https://www.google-analytics.com/debug/mp/collect'
		: 'https://www.google-analytics.com/mp/collect';

	$url = add_query_arg(
		array(
			'measurement_id' => pp_ga4_measurement_id(),
			'api_secret'     => $secreto,
		),
		$base
	);

	foreach ( $cola as $item ) {
		$cuerpo = array(
			'client_id' => $item['client_id'],
			'events'    => array( $item['evento'] ),
		);
		if ( $item['user_id'] ) {
			$cuerpo['user_id'] = $item['user_id'];
		}

		$respuesta = wp_remote_post( $url, array(
			'timeout'  => $depurar ? 8 : 2,
			'blocking' => $depurar,
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'body'     => wp_json_encode( $cuerpo ),
		) );

		if ( $depurar ) {
			$salida = is_wp_error( $respuesta )
				? $respuesta->get_error_message()
				: wp_remote_retrieve_body( $respuesta );
			error_log( 'PP GA4 [' . $item['evento']['name'] . ']: ' . $salida );
		}
	}
}

/* -------------------------------------------------------------------------
   3. LOS EVENTOS DE RESERVA
   ------------------------------------------------------------------------- */

/**
 * ¿Es una reserva de cliente, o un bloqueo de agenda del prestador?
 *
 * Listeo guarda ambas cosas en la misma tabla y con el mismo `type`
 * ('reservation'). Los bloqueos que el prestador pinta en su calendario llevan
 * el comentario literal «owner reservations» y/o ese mismo estado; contarlos
 * como reservas multiplicaría el dato por varios órdenes de magnitud.
 *
 * @param array $datos Fila o argumentos de la reserva.
 * @return bool
 */
function pp_ga4_es_reserva_de_cliente( $datos ) {
	$comentario = isset( $datos['comment'] ) ? (string) $datos['comment'] : '';
	$estado     = isset( $datos['status'] ) ? (string) $datos['status'] : '';

	if ( 'owner reservations' === trim( $comentario ) || 'owner_reservations' === $estado ) {
		return false;
	}
	// Las reservas de cliente guardan el detalle como JSON; un comentario vacío
	// es siempre un bloqueo o una fila técnica.
	return '' !== trim( $comentario );
}

/**
 * Parámetros comunes a todos los eventos de una reserva.
 *
 * @param int   $listing_id Publicación reservada.
 * @param array $datos      Fila o argumentos de la reserva.
 * @return array
 */
function pp_ga4_params_reserva( $listing_id, $datos ) {
	$params = array(
		'pp_listado_id' => (string) $listing_id,
		'pp_listado'    => $listing_id ? get_the_title( $listing_id ) : '',
	);

	/*
	 * Tipo de reserva: es lo que distingue una cita de veterinario (single_day)
	 * de una guardería de varios días (date_range).
	 *
	 * Se pide a la función de Listeo y NO al meta `_booking_type`, que está
	 * vacío en las publicaciones reales: el tipo se deriva de la configuración
	 * del tipo de listado, no se guarda en cada publicación.
	 */
	if ( $listing_id && function_exists( 'listeo_get_booking_type' ) ) {
		$tipo = listeo_get_booking_type( $listing_id );
		if ( $tipo ) {
			$params['pp_tipo_reserva'] = (string) $tipo;
		}
	}

	// El precio va como `value`+`currency` porque son los nombres que GA4
	// entiende como dinero; con otro nombre no sumaría en los informes.
	if ( isset( $datos['price'] ) && (float) $datos['price'] > 0 ) {
		$params['value']    = round( (float) $datos['price'], 2 );
		$params['currency'] = 'COP';
	}

	return $params;
}

add_action( 'listeo_after_insert_booking', 'pp_ga4_reserva_solicitada', 20, 2 );
/**
 * Una reserva acaba de crearse: el cliente pulsó «reservar».
 *
 * Aquí es donde se guarda el `client_id` del cliente para que los eventos
 * posteriores de esta misma reserva —que ocurrirán en otro navegador o sin
 * ninguno— se le puedan atribuir a él.
 *
 * @param int   $booking_id Reserva creada.
 * @param array $args       Argumentos de insert_booking().
 */
function pp_ga4_reserva_solicitada( $booking_id, $args ) {
	if ( ! apply_filters( 'pp_eventos_reservas_activo', true ) || ! $booking_id ) {
		return;
	}
	if ( ! pp_ga4_es_reserva_de_cliente( $args ) ) {
		return;
	}

	$listing_id = isset( $args['listing_id'] ) ? absint( $args['listing_id'] ) : 0;
	$autor      = isset( $args['bookings_author'] ) ? absint( $args['bookings_author'] ) : get_current_user_id();

	$cid = pp_ga4_client_id_de_cookie();
	if ( ! $cid ) {
		$cid = pp_ga4_client_id_derivado( $autor );
	}

	/*
	 * Se guarda 60 días: el tiempo de sobra para que la reserva se apruebe, se
	 * pague o caduque. Es un transient y no una opción para que se limpie solo;
	 * la tabla de reservas de Listeo no tiene metadatos donde guardarlo.
	 */
	set_transient( 'pp_ga4_cid_' . $booking_id, $cid, 60 * DAY_IN_SECONDS );

	$params                = pp_ga4_params_reserva( $listing_id, $args );
	$params['pp_origen']   = isset( $args['source'] ) ? (string) $args['source'] : 'panel';

	pp_ga4_encolar( 'reserva_solicitada', $params, $cid, $autor );
}

add_action( 'listeo_booking_status_changed', 'pp_ga4_reserva_cambio_estado', 20, 4 );
/**
 * La reserva cambió de estado.
 *
 * El estado inicial («waiting») se ignora: lo dispara Listeo justo después de
 * crear la reserva, así que contarlo duplicaría cada solicitud.
 *
 * @param int    $booking_id   Reserva.
 * @param string $status       Estado nuevo.
 * @param string $old_status   Estado anterior.
 * @param array  $booking_data Fila tal como estaba antes del cambio.
 */
function pp_ga4_reserva_cambio_estado( $booking_id, $status, $old_status, $booking_data ) {
	if ( ! apply_filters( 'pp_eventos_reservas_activo', true ) || ! $booking_id ) {
		return;
	}
	if ( ! is_array( $booking_data ) || ! pp_ga4_es_reserva_de_cliente( $booking_data ) ) {
		return;
	}

	$eventos = array(
		'confirmed'      => 'reserva_aprobada',
		'pay_to_confirm' => 'reserva_aprobada',
		'paid'           => 'reserva_pagada',
		'cancelled'      => 'reserva_cancelada',
		'expired'        => 'reserva_expirada',
		'refund'         => 'reserva_reembolsada',
	);

	if ( ! isset( $eventos[ $status ] ) ) {
		return; // 'waiting' y cualquier estado interno futuro.
	}

	$listing_id = isset( $booking_data['listing_id'] ) ? absint( $booking_data['listing_id'] ) : 0;
	$autor      = isset( $booking_data['bookings_author'] ) ? absint( $booking_data['bookings_author'] ) : 0;

	/*
	 * El client_id del CLIENTE, no el de quien está pulsando el botón. Si el
	 * prestador aprueba desde su panel, su cookie `_ga` es la suya: usarla
	 * atribuiría la conversión a la persona equivocada.
	 */
	$cid = get_transient( 'pp_ga4_cid_' . $booking_id );
	if ( ! $cid ) {
		$cid = $autor ? pp_ga4_client_id_derivado( $autor ) : pp_ga4_client_id_de_cookie();
	}

	$params                       = pp_ga4_params_reserva( $listing_id, $booking_data );
	$params['pp_estado_anterior'] = (string) $old_status;

	/*
	 * Cuánto tardó el prestador en responder. Es el dato que convierte este
	 * evento en accionable: un prestador que tarda dos días pierde clientes, y
	 * hasta ahora eso no se veía en ninguna parte.
	 */
	if ( 'reserva_aprobada' === $eventos[ $status ] && ! empty( $booking_data['created'] ) ) {
		$creada = strtotime( $booking_data['created'] );
		if ( $creada ) {
			$horas = ( current_time( 'timestamp' ) - $creada ) / HOUR_IN_SECONDS;
			if ( $horas >= 0 ) {
				$params['pp_horas_respuesta'] = round( $horas, 1 );
			}
		}
	}

	pp_ga4_encolar( $eventos[ $status ], $params, $cid, $autor );
}

/* -------------------------------------------------------------------------
   4. AJUSTES
   ------------------------------------------------------------------------- */

add_action( 'admin_menu', 'pp_ga4_menu_ajustes' );
function pp_ga4_menu_ajustes() {
	add_options_page(
		'Medición GA4',
		'Medición GA4',
		'manage_options',
		'pp-medicion-ga4',
		'pp_ga4_pantalla_ajustes'
	);
}

add_action( 'admin_init', 'pp_ga4_registrar_ajustes' );
function pp_ga4_registrar_ajustes() {
	register_setting( 'pp_ga4_ajustes', 'pp_ga4_mp_secret', array(
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default'           => '',
	) );
	register_setting( 'pp_ga4_ajustes', 'pp_ga4_mp_depurar', array(
		'type'              => 'boolean',
		'sanitize_callback' => 'rest_sanitize_boolean',
		'default'           => false,
	) );
}

/**
 * Comprueba de extremo a extremo que la clave funciona.
 *
 * Guardar la clave no demuestra que sea correcta: un espacio de más al pegarla
 * la invalida y no se notaría hasta perder reservas reales. Esta prueba hace dos
 * cosas: valida el formato contra el endpoint de depuración de Google —que
 * responde si la clave no sirve— y, si pasa, manda un evento real
 * `pp_prueba_medicion` para poder verlo en Tiempo real. Se llama así, y no
 * `reserva_*`, para no ensuciar el embudo con datos inventados.
 *
 * @return array Mensajes a mostrar.
 */
function pp_ga4_probar_conexion() {
	$secreto = pp_ga4_api_secret();
	if ( ! $secreto ) {
		return array( 'error' => 'No hay ninguna clave guardada.' );
	}

	$cuerpo = wp_json_encode( array(
		'client_id' => '555000111.1700000000',
		'events'    => array( array(
			'name'   => 'pp_prueba_medicion',
			'params' => array(
				'pp_origen'            => 'boton-de-prueba',
				'engagement_time_msec' => 1,
			),
		) ),
	) );

	$args = array(
		'timeout' => 10,
		'headers' => array( 'Content-Type' => 'application/json' ),
		'body'    => $cuerpo,
	);

	$qs = array(
		'measurement_id' => pp_ga4_measurement_id(),
		'api_secret'     => $secreto,
	);

	// 1. Validación.
	$val = wp_remote_post( add_query_arg( $qs, 'https://www.google-analytics.com/debug/mp/collect' ), $args );

	if ( is_wp_error( $val ) ) {
		return array( 'error' => 'No se pudo contactar con Google: ' . $val->get_error_message() );
	}

	$codigo    = wp_remote_retrieve_response_code( $val );
	$respuesta = json_decode( wp_remote_retrieve_body( $val ), true );

	if ( 200 !== $codigo ) {
		return array( 'error' => 'Google respondió ' . $codigo . '. La clave o el identificador no son válidos.' );
	}
	if ( ! empty( $respuesta['validationMessages'] ) ) {
		$msg = array();
		foreach ( $respuesta['validationMessages'] as $v ) {
			$msg[] = isset( $v['description'] ) ? $v['description'] : wp_json_encode( $v );
		}
		return array( 'error' => 'Google rechazó el evento: ' . implode( ' | ', $msg ) );
	}

	// 2. Envío real, para poder verlo en Tiempo real.
	$env = wp_remote_post( add_query_arg( $qs, 'https://www.google-analytics.com/mp/collect' ), $args );
	if ( is_wp_error( $env ) ) {
		return array( 'error' => 'Validó bien, pero el envío falló: ' . $env->get_error_message() );
	}

	return array( 'ok' => 'La clave funciona. Se envió un evento <code>pp_prueba_medicion</code>: debería aparecer en GA4 → Informes → Tiempo real en menos de un minuto.' );
}

function pp_ga4_pantalla_ajustes() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$secreto  = pp_ga4_api_secret();
	$por_const = defined( 'PP_GA4_MP_SECRET' ) && PP_GA4_MP_SECRET;

	$resultado = array();
	if ( isset( $_POST['pp_ga4_probar'] )
		&& check_admin_referer( 'pp_ga4_probar' ) ) {
		$resultado = pp_ga4_probar_conexion();
	}
	?>
	<div class="wrap">
		<h1>Medición GA4 — eventos de reservas</h1>

		<p style="max-width:46em">
			Las reservas se miden desde el <strong>servidor</strong>, no desde el navegador,
			porque buena parte de lo que pasa con una reserva ocurre sin nadie delante:
			el prestador la aprueba desde su panel, la pasarela confirma el pago por
			webhook y el cron la deja caducar de madrugada. Para eso Google exige un
			<strong>secreto de API</strong>.
		</p>

		<p style="max-width:46em">
			Se obtiene en Google Analytics → <em>Administrar</em> → <em>Flujos de datos</em> →
			el flujo «Página Web» → <em>Secretos de la API de Measurement Protocol</em> → <em>Crear</em>.
		</p>

		<?php if ( $secreto ) : ?>
			<div class="notice notice-success inline"><p>
				Configurado<?php echo $por_const ? ' mediante constante en wp-config.php' : ''; ?>.
				Los eventos de reserva se están enviando.
			</p></div>
		<?php else : ?>
			<div class="notice notice-warning inline"><p>
				Sin secreto: <strong>los eventos de reserva no se envían</strong>. Todo lo demás
				del sitio sigue funcionando con normalidad.
			</p></div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'pp_ga4_ajustes' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="pp_ga4_mp_secret">Secreto de la API</label></th>
					<td>
						<input type="password" id="pp_ga4_mp_secret" name="pp_ga4_mp_secret"
							value="<?php echo esc_attr( get_option( 'pp_ga4_mp_secret', '' ) ); ?>"
							class="regular-text" autocomplete="off"
							<?php disabled( $por_const ); ?> />
						<p class="description">
							<?php if ( $por_const ) : ?>
								Definido en wp-config.php; este campo queda desactivado para no confundir.
							<?php else : ?>
								Se guarda solo en la base de datos, nunca en los archivos del tema:
								quien tenga este secreto puede inyectar datos falsos en la propiedad.
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Modo de prueba</th>
					<td>
						<label>
							<input type="checkbox" name="pp_ga4_mp_depurar" value="1"
								<?php checked( get_option( 'pp_ga4_mp_depurar' ) ); ?> />
							Validar los eventos en vez de enviarlos
						</label>
						<p class="description">
							Con esto activado los eventos <strong>no llegan a los informes</strong>:
							se mandan al validador de Google y su respuesta se escribe en el
							registro de errores de PHP. Sirve para comprobar que todo está bien
							formado. <strong>Acuérdate de desactivarlo.</strong>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Identificador de medición</th>
					<td><code><?php echo esc_html( pp_ga4_measurement_id() ); ?></code></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr />
		<h2>Comprobar que la clave funciona</h2>
		<p style="max-width:46em">
			Guardar la clave no demuestra que sea la correcta: basta un espacio de más al
			pegarla para que Google la rechace, y eso no se notaría hasta que se perdiera
			la primera reserva. Esta prueba lo confirma sin tocar los datos de reservas.
		</p>

		<?php if ( ! empty( $resultado['ok'] ) ) : ?>
			<div class="notice notice-success inline"><p><?php echo wp_kses_post( $resultado['ok'] ); ?></p></div>
		<?php elseif ( ! empty( $resultado['error'] ) ) : ?>
			<div class="notice notice-error inline"><p><?php echo esc_html( $resultado['error'] ); ?></p></div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'pp_ga4_probar' ); ?>
			<p>
				<button type="submit" name="pp_ga4_probar" value="1" class="button button-secondary"
					<?php disabled( ! $secreto ); ?>>
					Enviar evento de prueba
				</button>
			</p>
		</form>
	</div>
	<?php
}
