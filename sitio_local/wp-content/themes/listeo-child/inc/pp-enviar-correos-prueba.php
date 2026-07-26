<?php
/**
 * HERRAMIENTA TEMPORAL — Enviar todos los correos del sitio para revisarlos
 * =============================================================================
 *
 * PARA QUÉ SIRVE
 * Añade una pantalla en Herramientas → «Correos de prueba» que envía TODOS los
 * correos de Listeo, ya renderizados con la plantilla real (cabecera con logo,
 * cuerpo, pie de marca) y con datos de ejemplo en las variables, a las
 * direcciones que se indiquen. Sirve para revisar en Gmail y en Outlook, que
 * renderizan HTML de forma muy distinta.
 *
 * ⚠️ ES TEMPORAL. Cuando termine la revisión, BORRAR este archivo y su línea
 * `require_once` en functions.php. No debe quedarse en producción: cualquier
 * administrador podría lanzar decenas de envíos con un clic.
 *
 * CÓMO FUNCIONA
 * Reutiliza Listeo_Core_Emails::send(), el mismo método que usa el plugin en
 * producción, así que lo que se recibe es EXACTAMENTE lo que recibirá un
 * cliente real: mismo motor de plantillas, mismas cabeceras, mismo remitente.
 * No se reimplementa nada, para que la prueba no mienta.
 *
 * SOBRE EL PREFIJO [PRUEBA]
 * Los asuntos se envían con el prefijo «[PRUEBA] » para que no se confundan con
 * correos reales en la bandeja. El resto del asunto y todo el cuerpo van tal
 * cual, que es lo que se quiere revisar.
 *
 * SOBRE EL RITMO DE ENVÍO
 * Se envían de a uno con una pausa breve. El buzón de GoDaddy admite unos
 * 250-500 correos al día, pero una ráfaga de decenas en un segundo puede
 * hacer que el servidor corte la conexión por sospecha de abuso.
 *
 * @package listeo-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Correos cuyo TEXTO cambió en la revisión del 2026-07-25.
 *
 * Sirven para el botón «Solo los modificados» y para marcarlos con ✏ en la
 * lista. Ojo: el pie de página cambió para TODOS los correos (se le añadió el
 * WhatsApp), así que si lo que se quiere revisar es el pie, hay que mirar
 * cualquiera de ellos, no solo estos.
 */
function pp_correos_prueba_modificados() {
	return array(
		'listing_expiring_soon',                 // errata «inform» + datos de contacto
		'listing_expired',                       // errata «pubicación» + datos de contacto
		'listing_new',                           // datos de contacto
		'listing_published',                     // datos de contacto
		'instant_booking_user_waiting_approval', // preparación válida también a domicilio
		'free_booking_confirmation',             // idem
		'mail_to_user_pay_cash_confirmed',       // idem
		'user_booking_reminder',                 // idem
		'listing_remind_review',                 // enlace directo a #add-review
		'saved_search',                          // variables que antes salían literales
		'pay_booking_confirmation',              // URL de pago de ejemplo corregida
	);
}

/**
 * Catálogo de correos a enviar.
 *
 * Cada entrada: clave de opción (sin el prefijo `listeo_` ni el sufijo
 * `_email_subject`/`_email_content`) => etiqueta legible.
 *
 * Se omiten a propósito:
 *   - Los de Zoom: están desactivados y siguen en inglés.
 *   - Los de equipo (team_*): usan un mecanismo de reemplazo aparte.
 */
function pp_correos_prueba_catalogo() {
	return array(
		// --- Cuenta ---
		'listing_welcome'                       => 'Bienvenida',
		'otp'                                   => 'Código de acceso (OTP)',

		// --- Publicaciones ---
		'listing_new'                           => 'Publicación recibida',
		'listing_published'                     => 'Publicación aprobada',
		'listing_rejected'                      => 'Publicación rechazada',
		'listing_expiring_soon'                 => 'Publicación por expirar',
		'listing_expired'                       => 'Publicación expirada',

		// --- Reservas: usuario ---
		'booking_user_waiting_approval'         => 'Reserva solicitada (usuario)',
		'instant_booking_user_waiting_approval' => 'Reserva instantánea (usuario)',
		'free_booking_confirmation'             => 'Reserva aprobada, gratis',
		'pay_booking_confirmation'              => 'Reserva aprobada, falta pago',
		'mail_to_user_pay_cash_confirmed'       => 'Reserva aprobada, pago en sitio',
		'user_paid_booking_confirmation'        => 'Pago recibido (usuario)',
		'booking_user_cancellation'             => 'Reserva cancelada (usuario)',
		'user_booking_reminder'                 => 'Recordatorio de cita',

		// --- Reservas: prestador ---
		'booking_owner_new_booking'             => 'Nueva solicitud (prestador)',
		'booking_instant_owner_new_booking'     => 'Nueva reserva instantánea (prestador)',
		'paid_booking_confirmation'             => 'Pago recibido (prestador)',
		'booking_owner_cancellation'            => 'Reserva cancelada (prestador)',

		// --- Reseñas y mensajes ---
		'listing_new_review'                    => 'Nueva reseña (prestador)',
		'listing_remind_review'                 => 'Recordatorio de reseña',
		'new_conversation_notification'         => 'Nueva conversación',
		'new_message_notification'              => 'Nuevo mensaje',
		'saved_search'                          => 'Búsquedas guardadas',

		// --- Reclamaciones ---
		'claim_request_notification'            => 'Reclamación solicitada',
		'claim_pending_notification'            => 'Reclamación en proceso',
		'claim_approved_notification'           => 'Reclamación aprobada',
		'claim_rejected_notification'           => 'Reclamación rechazada',
		'claim_completed_notification'          => 'Reclamación completada',
	);
}

/**
 * Datos de ejemplo para las variables de las plantillas.
 *
 * Se usan valores realistas y en español para que la revisión se parezca lo más
 * posible a un correo de verdad; con «Lorem ipsum» no se nota si una frase
 * queda mal construida.
 */
function pp_correos_prueba_datos() {
	return array(
		'user_name'             => 'María Fernanda',
		'user_mail'             => 'maria@ejemplo.com',
		'first_name'            => 'María',
		'last_name'             => 'Fernanda',
		'login'                 => 'mariaf',
		'password'              => '(no se envía)',
		'listing_name'          => 'Clínica Veterinaria Los Rosales 24h',
		'listing_url'           => home_url( '/publicacion/clinica-veterinaria-los-rosales-24h/' ),
		'listing_address'       => 'Cra. 70 #45-12, Medellín',
		'listing_phone'         => '+57 300 123 4567',
		'listing_email'         => 'contacto@ejemplo.com',
		'listing_dashboard_url' => home_url( '/mis-publicaciones/' ),
		'dates'                 => 'Martes 12 de agosto, 10:00 a.m.',
		'booking_date'          => '12 de agosto de 2026',
		'service'               => 'Consulta veterinaria general',
		'price'                 => '$ 85.000',
		// En producción esto es $order->get_checkout_payment_url() de WooCommerce
		// (la página «pagar pedido», no el carrito). Se imita el formato real.
		'payment_url'           => home_url( '/finalizar-compra/order-pay/1042/?pay_for_order=true&key=wc_order_ejemplo' ),
		'expiration'            => '10 de agosto de 2026',
		'rejection_reason'      => 'Faltan fotos del establecimiento y el horario de atención.',
		'site_name'             => get_bloginfo( 'name' ),
		'site_url'              => home_url( '/' ),
		'login_url'             => home_url( '/mi-cuenta/' ),
		// No existe una página pública de reclamaciones: en el plugin, {claim_url}
		// está comentado y llega vacío. Se apunta al panel, que es donde el usuario
		// consulta el estado de su solicitud.
		'claim_url'             => home_url( '/panel-control/' ),
		'conversation_url'      => home_url( '/panel-control/' ),
		'manage_url'            => home_url( '/panel-control/' ),
		'match_count'           => '3',
		'listings'              => 'Guardería Patitas Felices · Spa Canino Medellín · Vet Móvil 24h',
		'otp'                   => '482913',
		'expiring'              => '5',
		'order_id'              => '1042',
		'booking_id'            => '318',
		'sender'                => 'Andrés Gómez',
		'email_message'         => 'Hola, quería consultar disponibilidad para el próximo sábado.',
		'user_message'          => 'Mi perro es un labrador de 4 años, muy tranquilo.',
	);
}

/**
 * Envía las plantillas de WooCommerce a las direcciones indicadas.
 *
 * POR QUÉ NO SE USA EL BOTÓN DE WOOCOMMERCE
 * WooCommerce trae su propio «Enviar un correo electrónico de prueba» en
 * Ajustes → Correos, pero al pulsarlo no llegó a enviar nada (sin registro en
 * FluentSMTP, dos intentos). Aquí se rinde la plantilla y se envía por nuestra
 * cuenta, que además permite elegir destinatarios.
 *
 * POR QUÉ SE RINDE Y NO SE «DISPARA»
 * Cada WC_Email tiene trigger( $order_id ), pero ese método toma el
 * destinatario del pedido: dispararlo enviaría el correo AL CLIENTE REAL de ese
 * pedido. Aquí se hace lo seguro: se pide a la plantilla su HTML
 * (get_content + style_inline, que es exactamente lo que Woo enviaría) y se
 * manda con wp_mail a las direcciones de prueba. No se ejecuta ningún gancho de
 * WooCommerce, así que no hay riesgo de tocar pedidos ni de escribirle a nadie.
 *
 * EL PEDIDO DE EJEMPLO
 * Se usa el más reciente de la tienda, SOLO PARA LEER. Si no hay pedidos, las
 * plantillas que dependen de uno se omiten con un aviso, porque sin datos
 * saldrían vacías y no servirían para revisar nada.
 *
 * @param array $destinos Correos a los que enviar.
 * @return array {ok: etiquetas enviadas, no: etiquetas omitidas}
 */
function pp_correos_prueba_woo( $destinos ) {

	$ok = array();
	$no = array();

	if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
		return array( 'ok' => $ok, 'no' => array( 'WooCommerce no está activo' ) );
	}

	$pedidos = wc_get_orders( array( 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC' ) );
	$pedido  = ! empty( $pedidos ) ? $pedidos[0] : null;

	if ( ! $pedido ) {
		return array( 'ok' => $ok, 'no' => array( 'WooCommerce: no hay pedidos en la tienda para usar de ejemplo' ) );
	}

	$correos = WC()->mailer()->get_emails();

	foreach ( $correos as $email ) {

		// Los de TPV no se usan en este sitio y los de admin ya se revisan en
		// la bandeja del propio administrador.
		if ( strpos( $email->id, 'pos_' ) !== false ) {
			continue;
		}

		// Se le da el pedido para que las variables se resuelvan.
		$email->object = $pedido;
		if ( method_exists( $email, 'set_object' ) ) {
			$email->set_object( $pedido );
		}
		$email->placeholders = array_merge(
			$email->placeholders,
			array(
				'{order_date}'   => wc_format_datetime( $pedido->get_date_created() ),
				'{order_number}' => $pedido->get_order_number(),
			)
		);

		$asunto = $email->format_string( $email->get_subject() );
		$cuerpo = $email->style_inline( $email->get_content() );

		if ( empty( $cuerpo ) ) {
			$no[] = 'Woo: ' . $email->get_title() . ' (sin contenido)';
			continue;
		}

		foreach ( $destinos as $destino ) {
			wp_mail(
				$destino,
				'[PRUEBA] ' . $asunto,
				$cuerpo,
				array( 'Content-Type: text/html; charset=UTF-8' )
			);
			usleep( 400000 );
		}

		$ok[] = 'Woo: ' . $email->get_title();
	}

	return array( 'ok' => $ok, 'no' => $no );
}

/**
 * Registra la pantalla en Herramientas.
 */
add_action(
	'admin_menu',
	function () {
		add_management_page(
			'Correos de prueba',
			'Correos de prueba',
			'manage_options',
			'pp-correos-prueba',
			'pp_correos_prueba_pantalla'
		);
	}
);

/**
 * Pantalla y procesamiento del envío.
 */
function pp_correos_prueba_pantalla() {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permisos.' );
	}

	$catalogo = pp_correos_prueba_catalogo();
	$enviados = array();
	$fallidos = array();

	if ( isset( $_POST['pp_enviar'] ) && check_admin_referer( 'pp_correos_prueba' ) ) {

		$destinos = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', wp_unslash( $_POST['pp_destinos'] ) ) ) ) );
		$datos    = pp_correos_prueba_datos();

		/*
		 * Solo se envían los marcados. Si no llega ninguno (el usuario los
		 * desmarcó todos), se corta aquí en vez de enviar los 29 por defecto:
		 * enviar de más es peor que no enviar nada.
		 */
		$elegidos = isset( $_POST['pp_correos'] ) ? array_map( 'sanitize_text_field', (array) $_POST['pp_correos'] ) : array();
		$catalogo = array_intersect_key( $catalogo, array_flip( $elegidos ) );

		/*
		 * Se usa el SINGLETON, no `new Listeo_Core_Emails()`.
		 *
		 * El constructor de esa clase registra con add_action() los ~40 ganchos
		 * de envío. Crear una segunda instancia los registraría por duplicado
		 * (WordPress identifica los callbacks de objeto por instancia, así que
		 * no los deduplica) y a partir de ahí CADA correo real del sitio se
		 * enviaría dos veces al cliente. Con instance() se reutiliza el objeto
		 * que el plugin ya creó y no se toca nada.
		 */
		$emails = Listeo_Core_Emails::instance();

		foreach ( $catalogo as $clave => $etiqueta ) {

			$asunto = get_option( 'listeo_' . $clave . '_email_subject' );
			$cuerpo = get_option( 'listeo_' . $clave . '_email_content' );

			if ( empty( $asunto ) || empty( $cuerpo ) ) {
				$fallidos[] = $etiqueta . ' — sin contenido configurado';
				continue;
			}

			$asunto = $emails->replace_shortcode( $datos, $asunto, $clave );
			$cuerpo = $emails->replace_shortcode( $datos, $cuerpo, $clave );

			/*
			 * Variables que replace_shortcode() NO conoce.
			 *
			 * El correo de búsquedas guardadas no usa el reemplazador general:
			 * su propia clase (class-listeo-core-saved-searches.php, ~línea 1470)
			 * sustituye {match_count}, {listings} y {manage_url} antes de enviar.
			 * En el correo real llegan bien; aquí hay que suplirlas a mano o se
			 * verían literales y parecería un error que no existe.
			 */
			$extra  = array(
				'{match_count}' => $datos['match_count'],
				'{listings}'    => $datos['listings'],
				'{manage_url}'  => $datos['manage_url'],
			);
			$asunto = strtr( $asunto, $extra );
			$cuerpo = strtr( $cuerpo, $extra );

			foreach ( $destinos as $destino ) {
				// El cuarto parámetro en null evita la copia al admin: aquí ya
				// estamos enviando a propósito a las direcciones indicadas.
				Listeo_Core_Emails::send( $destino, '[PRUEBA] ' . $asunto, $cuerpo, null, $clave );
				usleep( 400000 ); // 0,4 s entre envíos, para no disparar el antiabuso del SMTP.
			}

			$enviados[] = $etiqueta;
		}

		// --- WooCommerce ------------------------------------------------------
		if ( ! empty( $_POST['pp_woo'] ) ) {
			$res_woo = pp_correos_prueba_woo( $destinos );
			$enviados = array_merge( $enviados, $res_woo['ok'] );
			$fallidos = array_merge( $fallidos, $res_woo['no'] );
		}
	}
	?>
	<div class="wrap">
		<h1>Correos de prueba</h1>

		<?php if ( $enviados ) : ?>
			<div class="notice notice-success">
				<p><strong><?php echo count( $enviados ); ?> correos enviados</strong> a cada destinatario.</p>
				<p style="font-size:12px;color:#555;"><?php echo esc_html( implode( ' · ', $enviados ) ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $fallidos ) : ?>
			<div class="notice notice-warning">
				<p><strong>Omitidos:</strong></p>
				<p style="font-size:12px;"><?php echo esc_html( implode( ' · ', $fallidos ) ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'pp_correos_prueba' ); ?>

			<p>
				<label><strong>Destinatarios</strong> (separados por coma)<br>
				<input type="text" name="pp_destinos" size="70"
					value="miguelangel.rua1@gmail.com, miguelangel.rua@hotmail.com"></label>
			</p>

			<h2>¿Cuáles enviar?</h2>
			<p>
				<button type="button" class="button" onclick="ppMarcar('todos')">Marcar todos</button>
				<button type="button" class="button button-secondary" onclick="ppMarcar('modificados')">Solo los modificados ✏</button>
				<button type="button" class="button" onclick="ppMarcar('ninguno')">Desmarcar todos</button>
				<span id="pp-cuenta" style="margin-left:12px;font-weight:600;"></span>
			</p>

			<div style="columns:2;column-gap:40px;max-width:900px;background:#fff;border:1px solid #dcdcde;padding:14px 18px;">
			<?php
			$modificados = pp_correos_prueba_modificados();
			foreach ( pp_correos_prueba_catalogo() as $clave => $etiqueta ) :
				$es_mod = in_array( $clave, $modificados, true );
				?>
				<label style="display:block;padding:3px 0;break-inside:avoid;">
					<input type="checkbox" name="pp_correos[]" value="<?php echo esc_attr( $clave ); ?>"
						data-mod="<?php echo $es_mod ? '1' : '0'; ?>" <?php checked( $es_mod ); ?>>
					<?php echo esc_html( $etiqueta ); ?>
					<?php if ( $es_mod ) : ?>
						<span title="Modificado en la última revisión" style="color:#79C8D0;font-weight:700;">✏</span>
					<?php endif; ?>
				</label>
			<?php endforeach; ?>
			</div>

			<h2>WooCommerce (Tienda)</h2>
			<p style="background:#fff;border:1px solid #dcdcde;padding:12px 16px;max-width:900px;">
				<label>
					<input type="checkbox" name="pp_woo" value="1">
					<strong>Enviar también las plantillas de WooCommerce</strong>
				</label><br>
				<span style="font-size:12px;color:#666;">
					Se rinden con el pedido más reciente de la tienda (solo lectura, no se toca) y se envían
					a las mismas direcciones. Son ~13 plantillas más por destinatario.
				</span>
			</p>

			<p style="margin-top:16px;">
				<button type="submit" name="pp_enviar" class="button button-primary button-large">Enviar los seleccionados</button>
			</p>
			<p style="color:#a00;font-size:12px;">
				Se envía uno cada 0,4 s. No cierres la pestaña hasta que aparezca el aviso verde.<br>
				Recuerda que el <strong>pie con el WhatsApp cambió en TODOS</strong> los correos: para revisarlo basta abrir cualquiera.
			</p>
		</form>

		<script>
		function ppMarcar( modo ) {
			document.querySelectorAll( 'input[name="pp_correos[]"]' ).forEach( function ( c ) {
				if ( 'todos' === modo )            { c.checked = true; }
				else if ( 'ninguno' === modo )     { c.checked = false; }
				else if ( 'modificados' === modo ) { c.checked = ( '1' === c.dataset.mod ); }
			} );
			ppContar();
		}
		function ppContar() {
			var n = document.querySelectorAll( 'input[name="pp_correos[]"]:checked' ).length;
			document.getElementById( 'pp-cuenta' ).textContent = n + ' seleccionados';
		}
		document.addEventListener( 'change', function ( e ) {
			if ( e.target.name === 'pp_correos[]' ) { ppContar(); }
		} );
		ppContar();
		</script>
	</div>
	<?php
}
