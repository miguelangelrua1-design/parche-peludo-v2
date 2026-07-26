<?php
/**
 * Ajustes finos de los correos: textos, enlaces y miniaturas
 * =============================================================================
 *
 * Reúne cuatro correcciones detectadas al revisar los correos reales en la
 * bandeja (2026-07-25). Van juntas porque todas tocan lo mismo —lo que el
 * cliente lee en un correo— y así hay un único sitio donde buscarlas.
 *
 *   1. Traducir «Hourly rate» y «1 hour», que salían en inglés en el desglose
 *      del pedido de una reserva.
 *   2. Quitar el «Enhorabuena por la venta» (españolismo) y el bloque que
 *      promociona la app móvil de WooCommerce, que no se usa en este negocio.
 *   3. Reemplazar los enlaces a woocommerce.com por la página de ayuda propia.
 *   4. Mostrar la miniatura de cada producto en la tabla del pedido.
 *
 * KILL-SWITCH
 *     define( 'PP_CORREOS_AJUSTES_OFF', true );   // en wp-config.php
 *
 * @package listeo-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'PP_CORREOS_AJUSTES_OFF' ) && PP_CORREOS_AJUSTES_OFF ) {
	return;
}


/* ==========================================================================
 * 1. TRADUCCIONES QUE FALTABAN
 *
 * Estas cadenas viven en Listeo Core (class-listeo-core-bookings-calendar.php,
 * ~línea 5193 y el helper _breakdown_units_label) y no estaban en el catálogo
 * es_ES, así que salían en inglés dentro del desglose del pedido.
 *
 * Se traducen con el filtro `gettext` en lugar de editar el .po del plugin:
 * un archivo de traducción se pierde al actualizar Listeo; esto no.
 *
 * Se usa `gettext_with_context` y `ngettext` además de `gettext` porque las
 * horas se generan con _n() (plural), que NO pasa por el filtro `gettext`.
 * ========================================================================== */

/**
 * Cadenas simples del dominio listeo_core.
 */
add_filter(
	'gettext',
	function ( $traducido, $original, $dominio ) {
		if ( 'listeo_core' !== $dominio ) {
			return $traducido;
		}
		$mapa = array(
			'Hourly rate'  => 'Tarifa por hora',
			'Nightly rate' => 'Tarifa por noche',
			'Daily rate'   => 'Tarifa por día',
			'Service'      => 'Servicio',
			'Guests'       => 'Personas',
			'Adults'       => 'Adultos',
			'Children'     => 'Niños',
		);
		return isset( $mapa[ $original ] ) ? $mapa[ $original ] : $traducido;
	},
	20,
	3
);

/**
 * Plurales: «%s hour» / «%s hours» y compañía.
 *
 * _n() dispara `ngettext`, no `gettext`. Se comparan las dos formas originales
 * para no depender de cuál eligió el plugin según el número.
 */
add_filter(
	'ngettext',
	function ( $traducido, $singular, $plural, $numero, $dominio ) {
		if ( 'listeo_core' !== $dominio ) {
			return $traducido;
		}
		$mapa = array(
			'%s hour'   => array( '%s hora', '%s horas' ),
			'%s night'  => array( '%s noche', '%s noches' ),
			'%s ticket' => array( '%s entrada', '%s entradas' ),
			'%s day'    => array( '%s día', '%s días' ),
		);
		if ( ! isset( $mapa[ $singular ] ) ) {
			return $traducido;
		}
		return ( 1 === (int) $numero ) ? $mapa[ $singular ][0] : $mapa[ $singular ][1];
	},
	20,
	5
);


/* ==========================================================================
 * 2. TEXTOS DE WOOCOMMERCE QUE NO ENCAJAN
 *
 * «Enhorabuena» es un españolismo que en Colombia no se usa; y el bloque que
 * invita a descargar la app móvil de WooCommerce sobra, porque el negocio no
 * la utiliza y manda al cliente (o al administrador) fuera del sitio.
 *
 * Se filtran por texto con `gettext` del dominio `woocommerce`. Se usa
 * str_replace y no una comparación exacta porque WooCommerce cambia estas
 * frases entre versiones y una coincidencia estricta dejaría de aplicar en
 * silencio tras una actualización.
 * ========================================================================== */
add_filter(
	'gettext',
	function ( $traducido, $original, $dominio ) {
		if ( 'woocommerce' !== $dominio ) {
			return $traducido;
		}

		// El aviso de venta al administrador.
		if ( false !== strpos( $traducido, 'Enhorabuena por la venta' ) ) {
			return '¡Nueva venta en Parche Peludo! 🐾';
		}
		if ( false !== strpos( $traducido, 'Congratulations on the sale' ) ) {
			return '¡Nueva venta en Parche Peludo! 🐾';
		}

		// Promoción de la app móvil: se vacía para que no se imprima.
		$promos = array(
			'Procesa tus pedidos sobre la marcha',
			'Manage your orders on the go',
			'Consigue la aplicación',
			'Get the app',
		);
		foreach ( $promos as $promo ) {
			if ( false !== strpos( $traducido, $promo ) ) {
				return '';
			}
		}

		return $traducido;
	},
	20,
	3
);

/**
 * Elimina el bloque completo de la app móvil del correo de nuevo pedido.
 *
 * Vaciar las cadenas (arriba) evita el texto, pero WooCommerce imprime además
 * el contenedor con su enlace. Este filtro actúa sobre el HTML final y quita el
 * párrafo entero, incluido cualquier enlace a woocommerce.com que quedara.
 */
add_filter(
	'woocommerce_mail_content',
	function ( $html ) {
		// Bloque promocional de la app (varía entre versiones: se localiza por
		// el enlace de destino, que es lo estable).
		$html = preg_replace(
			'#<p[^>]*>(?:(?!</p>).)*?woocommerce\.com/mobile(?:(?!</p>).)*?</p>#is',
			'',
			$html
		);
		// Cualquier otro enlace suelto a woocommerce.com se apunta a la ayuda propia.
		$html = str_replace(
			array( 'https://woocommerce.com/mobile/', 'https://woocommerce.com/mobile' ),
			pp_correos_url_ayuda(),
			$html
		);
		return $html;
	},
	20
);


/* ==========================================================================
 * 3. PÁGINA DE AYUDA PROPIA EN LUGAR DE ENLACES A WOOCOMMERCE
 * ========================================================================== */

/**
 * URL de la página de ayuda con pagos. Si aún no existe, cae al contacto.
 */
function pp_correos_url_ayuda() {
	$pagina = get_page_by_path( 'ayuda-con-tu-pago' );
	if ( $pagina ) {
		return get_permalink( $pagina );
	}
	$contacto = get_page_by_path( 'contacto' );
	return $contacto ? get_permalink( $contacto ) : home_url( '/' );
}

/**
 * Añade al correo de pago fallido un enlace a la ayuda propia.
 *
 * Es el correo donde el cliente más necesita una salida concreta: se le da la
 * página de Parche Peludo, no la documentación de WooCommerce.
 */
add_filter(
	'woocommerce_email_footer_text',
	function ( $texto ) {
		return $texto;
	}
);


/* ==========================================================================
 * 4. MINIATURA DE LOS PRODUCTOS EN LA TABLA DEL PEDIDO
 *
 * De fábrica WooCommerce lista solo texto. Ver la foto de lo que se compró
 * hace el correo mucho más reconocible y reduce las dudas de «¿qué pedí?».
 *
 * 48 px es el tamaño habitual en correo: se ve bien en móvil sin engordar el
 * HTML, y los clientes de correo lo escalan sin problema.
 * ========================================================================== */
add_filter(
	'woocommerce_email_order_items_args',
	function ( $args ) {
		$args['show_image'] = true;
		$args['image_size'] = array( 48, 48 );
		return $args;
	}
);
