<?php
/**
 * Pie de los correos de Listeo — versión Parche Peludo
 * =============================================================================
 *
 * Sobreescribe listeo-core/templates/emails/footer.php. Ver la nota completa en
 * el header.php de esta misma carpeta.
 *
 * QUÉ CAMBIA RESPECTO AL ORIGINAL
 *   - El original imprime «Have a question?» en inglés vía esc_html_e() con el
 *     dominio listeo_core; dependía de que la traducción estuviera cargada. Aquí
 *     el texto va directo en español, sin depender del catálogo .mo.
 *   - Color del enlace: #127DB3 → #4FA3AC (teal de marca, oscurecido lo justo
 *     para mantener contraste legible sobre fondo claro).
 *   - Se añade la firma de marca y el enlace al sitio: da contexto al receptor y
 *     ayuda a que el correo no parezca automático/sospechoso, lo que cuenta a
 *     favor en los filtros anti-SPAM.
 *
 * NOTA ANTI-SPAM
 *   Estos son correos TRANSACCIONALES (confirmaciones, avisos de reservas), no
 *   marketing: no llevan enlace de baja porque el usuario no puede darse de baja
 *   de la confirmación de su propia reserva. Si algún día se envían boletines o
 *   promociones, ESOS sí necesitan enlace de baja visible por ley y por
 *   requisito de Gmail/Yahoo.
 *
 * @package listeo-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pp_contacto = get_option( 'listeo_emails_from_email', get_bloginfo( 'admin_email' ) );

/**
 * Celular / WhatsApp de atención. Se pone aquí, en el pie, para que aparezca en
 * TODOS los correos sin tener que repetirlo en cada plantilla del panel: si el
 * número cambia, se toca un solo sitio.
 *
 * `pp_wa` es el número en formato internacional sin signos (lo que exige wa.me);
 * `pp_wa_visible` es cómo se muestra al lector.
 */
$pp_wa         = apply_filters( 'pp_email_whatsapp', '573012773594' );
$pp_wa_visible = apply_filters( 'pp_email_whatsapp_visible', '301 277 3594' );
?>

<!-- Fin del CONTENEDOR -->
</table>

<!-- PIE -->
<table border="0" cellpadding="0" cellspacing="0" align="center"
	width="560" style="border-collapse: collapse; border-spacing: 0; padding: 0; width: inherit;
	max-width: 560px;" class="wrapper">

	<tr>
		<td align="center" valign="top" style="border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; padding-left: 6.25%; padding-right: 6.25%; width: 87.5%; font-size: 15px; font-weight: 400; line-height: 160%;
			padding-top: 22px;
			padding-bottom: 8px;
			color: #777777;
			font-family: sans-serif;" class="paragraph">
				¿Tienes alguna duda? Estamos para ayudarte:<br />
				📧 <a href="mailto:<?php echo esc_attr( $pp_contacto ); ?>" target="_blank" style="color: #4FA3AC; font-family: sans-serif; font-size: 15px; font-weight: 400; line-height: 160%; text-decoration: underline;"><?php echo esc_html( $pp_contacto ); ?></a><br />
				📱 WhatsApp: <a href="https://wa.me/<?php echo esc_attr( $pp_wa ); ?>" target="_blank" style="color: #4FA3AC; font-family: sans-serif; font-size: 15px; font-weight: 400; line-height: 160%; text-decoration: underline;"><?php echo esc_html( $pp_wa_visible ); ?></a>
		</td>
	</tr>

	<tr>
		<td align="center" valign="top" style="border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; padding-left: 6.25%; padding-right: 6.25%; width: 87.5%; font-size: 13px; font-weight: 400; line-height: 150%;
			padding-top: 4px;
			padding-bottom: 26px;
			color: #9AA5A8;
			font-family: sans-serif;" class="footer">
				<strong style="color: #7C8A8D;">Parche Peludo</strong> — cuidado profesional para tu mascota<br />
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" style="color: #9AA5A8; font-family: sans-serif; font-size: 13px; text-decoration: underline;">parchepeludo.com</a>
		</td>
	</tr>
</table>

<!-- Fin de SECCIÓN / FONDO -->
</td></tr></table>

</body>
</html>
