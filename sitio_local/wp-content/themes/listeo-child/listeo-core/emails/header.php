<?php
/**
 * Cabecera de los correos de Listeo — versión Parche Peludo
 * =============================================================================
 *
 * Sobreescribe listeo-core/templates/emails/header.php mediante el cargador de
 * plantillas del plugin (Gamajo Template Loader), que busca primero en
 * listeo-child/listeo-core/emails/. Así el diseño sobrevive a las
 * actualizaciones de Listeo Core.
 *
 * QUÉ CAMBIA RESPECTO AL ORIGINAL
 *   - Color de enlaces: #127DB3 (azul genérico del tema) → #79C8D0 (teal de la marca).
 *   - Fondo del correo: #f6f6f6 → #F7FAFB, un gris con matiz frío que combina
 *     con el teal sin competir con el contenido.
 *   - Logo: se fuerza formato PNG. El logo del sitio está en WebP, que Outlook
 *     y varios clientes de correo NO renderizan; con WebP el correo llegaría
 *     con la cabecera vacía. Se usa la versión horizontal PNG de 600 px (14 KB),
 *     que se ve nítida también en pantallas retina.
 *   - Altura del logo: 50 px → 38 px, proporción correcta para un logo
 *     horizontal (el original estaba pensado para uno cuadrado).
 *
 * LO QUE NO SE TOCA (a propósito)
 *   La estructura de tablas anidadas con atributos inline es fea de leer pero es
 *   LA CORRECTA para correo: es lo único que Outlook renderiza de forma fiable.
 *   No migrar a flexbox/grid ni a CSS externo.
 *
 * @package listeo-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logo en PNG para máxima compatibilidad con clientes de correo.
 * Se puede sustituir con el filtro `pp_email_logo` sin tocar este archivo.
 */
$pp_email_logo = apply_filters(
	'pp_email_logo',
	'https://parchepeludo.com/wp-content/uploads/2026/05/Logo-Parche-Peludo-horizontal-v2-600x86.png'
);
?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0;">
	<meta name="format-detection" content="telephone=no"/>

	<style>
/* Reset */
body { margin: 0; padding: 0; min-width: 100%; width: 100% !important; height: 100% !important;}
body, table, td, div, p, a { -webkit-font-smoothing: antialiased; text-size-adjust: 100%; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; line-height: 100%; }
table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse !important; border-spacing: 0; width: 100%; }
img { border: 0; line-height: 100%; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
#outlook a { padding: 0; }
.ReadMsgBody { width: 100%; } .ExternalClass { width: 100%; }
.ExternalClass, .ExternalClass p, .ExternalClass span, .ExternalClass font, .ExternalClass td, .ExternalClass div { line-height: 100%; }
.container { border-radius: 0px; box-shadow: 0 0 18px rgba(0,0,0,0); }
.paragraph { line-height: 26px !important; }

/* Esquinas redondeadas solo en clientes que las soportan */
@media all and (min-width: 560px) {
	.container { border-radius: 8px; }
	table, td { width: 560px; }
	.container { box-shadow: 0 0 18px rgba(0,0,0,0.06); }
}

/* Enlaces con el teal de la marca */
a, a:hover {
	color: #4FA3AC;
}
.footer a, .footer a:hover {
	color: #999999;
}
	</style>
</head>

<body topmargin="0" rightmargin="0" bottommargin="0" leftmargin="0" marginwidth="0" marginheight="0" width="100%" style="border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; width: 100%; height: 100%; -webkit-font-smoothing: antialiased; text-size-adjust: 100%; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; line-height: 100%;
	background-color: #F7FAFB;
	color: #666666;"
	bgcolor="#F7FAFB"
	text="#000000">

<!-- SECCIÓN / FONDO -->
<table width="100%" align="center" border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; width: 100%;" class="background"><tr><td align="center" valign="top" style="border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0;"
	bgcolor="#F7FAFB">
<tr><td>

<!-- CABECERA CON LOGO -->
<table border="0" cellpadding="0" cellspacing="0" align="center"
	width="560" style="border-collapse: collapse; border-spacing: 0; padding: 0; width: inherit;
	max-width: 560px;" class="wrapper">

	<tr>
		<td align="center" valign="top" style="border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; padding-left: 6.25%; padding-right: 6.25%; width: 87.5%;
			padding-top: 24px;
			padding-bottom: 20px;">

		<?php if ( $pp_email_logo ) { ?>
			<a style="border: none;" href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home"><img border="0" vspace="0" hspace="0"
				src="<?php echo esc_url( $pp_email_logo ); ?>"
				width="200"
				alt="<?php bloginfo( 'name' ); ?>" title="<?php bloginfo( 'name' ); ?>" style="
				color: #333;
				width: 200px; max-width: 200px; height: auto;
				font-size: 12px; margin: 0; padding: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; border: none; display: block;" /></a>
		<?php } else {
			echo '<h3 style="color:#4FA3AC;font-family:sans-serif;margin:10px 0;">'; bloginfo( 'name' ); echo '</h3>';
		} ?>
		</td>
	</tr>
</table>

<!-- CONTENEDOR DEL CONTENIDO -->
<table border="0" cellpadding="0" cellspacing="0" align="center"
	bgcolor="#FFFFFF"
	width="560" style="border-collapse: collapse; border-spacing: 0; padding: 0; width: inherit;
	max-width: 560px;" class="container">
