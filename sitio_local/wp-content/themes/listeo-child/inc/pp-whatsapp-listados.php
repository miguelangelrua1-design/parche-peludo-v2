<?php
/**
 * WhatsApp de los listados a partir del teléfono
 * =============================================================================
 *
 * QUÉ RESUELVE
 * La API de Google Places no devuelve perfiles sociales, pero SÍ el teléfono.
 * El importador de Listeo solo rellena `_whatsapp` si el teléfono contiene
 * "wa.me" (algo que no ocurre nunca), así que los listados importados se quedan
 * sin botón de WhatsApp aunque su número sea un móvil.
 *
 * En Colombia la numeración permite distinguirlos con fiabilidad:
 *   - Móvil: 10 dígitos que empiezan por 3   (300–35x)
 *   - Fijo:  10 dígitos que empiezan por 60  (601 Bogotá, 604 Medellín, …)
 * Un negocio que publica un móvil en Google casi siempre tiene WhatsApp; un
 * fijo, nunca. Por eso solo se deduce a partir de móviles.
 *
 * FORMATO — ESTO IMPORTA
 * Listeo construye el enlace concatenando sin más (single-listing-socials.php):
 *
 *     echo "https://wa.me/" . esc_attr($whatsapp);
 *
 * `wa.me` exige el número internacional COMPLETO y sin "+". Un valor de 10
 * dígitos como "3192928183" genera `wa.me/3192928183`, que WhatsApp puede
 * interpretar como indicativo +31 (Países Bajos) y no resuelve. Por eso aquí
 * siempre se guarda con el 57 delante: "573192928183".
 *
 * QUÉ NO HACE
 *   - NO toca `_phone`: ambos campos conviven, cada uno con su formato.
 *   - NO pisa un WhatsApp puesto a mano: si ya hay una URL completa, se respeta.
 *     Lo único que corrige es un número al que le falte el indicativo, porque
 *     ahí el número es el mismo y solo estaba mal formado.
 *   - NO deduce nada de un fijo ni de un formato que no reconozca.
 *
 * RESISTENTE A ACTUALIZACIONES
 *   - Vive en el tema hijo: las actualizaciones de Listeo o del importador no
 *     lo tocan.
 *   - Usa hooks estándar de WordPress (`added/updated_post_meta`, `save_post`),
 *     no funciones internas del plugin.
 *   - No sobrescribe ninguna plantilla, así que no hay que re-sincronizarla
 *     cuando el plugin cambie su maquetación.
 *   - Solo escribe cuando `_whatsapp` está vacío. Si algún día el importador
 *     empieza a rellenarlo, este módulo deja de intervenir por sí mismo.
 *
 * KILL-SWITCH
 *     define( 'PP_WA_OFF', true );   // en wp-config.php
 *
 * @package listeo-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PP_WA_BACKFILL_VERSION', '1' ); // Subir para volver a pasar por los listados existentes.

function pp_wa_activo() {
	if ( defined( 'PP_WA_OFF' ) && PP_WA_OFF ) {
		return false;
	}
	return (bool) apply_filters( 'pp_wa_activo', true );
}

/**
 * Normaliza un teléfono colombiano al formato que necesita wa.me.
 *
 * @param string $telefono Teléfono en cualquier formato.
 * @return string|false    "57XXXXXXXXXX" si es móvil; false si es fijo,
 *                         extranjero o no se reconoce.
 */
function pp_wa_normalizar( $telefono ) {
	$d = preg_replace( '/\D+/', '', (string) $telefono );
	if ( '' === $d ) {
		return false;
	}

	// Con indicativo: 57 + 10 dígitos.
	if ( 12 === strlen( $d ) && 0 === strpos( $d, '57' ) ) {
		$local = substr( $d, 2 );
	} elseif ( 10 === strlen( $d ) ) {
		// Sin indicativo: se asume Colombia (el sitio es nacional).
		$local = $d;
	} else {
		// Longitud inesperada: número extranjero, extensión, mal capturado…
		// No se adivina.
		return false;
	}

	// Solo móviles: 10 dígitos empezando por 3. Los fijos (60x) se descartan.
	if ( ! preg_match( '/^3\d{9}$/', $local ) ) {
		return false;
	}

	return '57' . $local;
}

/**
 * Rellena `_whatsapp` de un listado a partir de su `_phone`.
 *
 * @param int  $post_id ID del listado.
 * @param bool $forzar  Si es true, corrige también un valor existente mal
 *                      formado (sin indicativo). Nunca toca una URL completa.
 * @return string|false El valor guardado, o false si no se hizo nada.
 */
function pp_wa_rellenar( $post_id, $forzar = true ) {
	if ( ! pp_wa_activo() ) {
		return false;
	}

	$actual = trim( (string) get_post_meta( $post_id, '_whatsapp', true ) );

	// Una URL puesta a mano (wa.me/…, api.whatsapp.com/…) manda: no se toca.
	if ( '' !== $actual && 0 === stripos( $actual, 'http' ) ) {
		return false;
	}

	if ( '' !== $actual ) {
		if ( ! $forzar ) {
			return false;
		}
		// Ya tiene número: solo se corrige si le falta el indicativo. El número
		// en sí no cambia, únicamente se completa el formato.
		$corregido = pp_wa_normalizar( $actual );
		if ( $corregido && $corregido !== preg_replace( '/\D+/', '', $actual ) ) {
			update_post_meta( $post_id, '_whatsapp', $corregido );
			return $corregido;
		}
		return false;
	}

	// Campo vacío: se deduce del teléfono.
	$movil = pp_wa_normalizar( get_post_meta( $post_id, '_phone', true ) );
	if ( ! $movil ) {
		return false; // Fijo o formato no reconocido: se deja vacío a propósito.
	}

	update_post_meta( $post_id, '_whatsapp', $movil );
	return $movil;
}

/* -----------------------------------------------------------------------------
 * Disparadores
 * -------------------------------------------------------------------------- */

/**
 * En cuanto se escribe `_phone`. Es el momento correcto para el importador:
 * `save_post` se dispara ANTES de que el importador guarde sus metas, así que
 * ahí el teléfono todavía no existe.
 */
function pp_wa_al_guardar_meta( $meta_id, $post_id, $meta_key ) {
	if ( '_phone' !== $meta_key ) {
		return; // Evita además cualquier recursión al escribir _whatsapp.
	}
	if ( 'listing' !== get_post_type( $post_id ) ) {
		return;
	}
	pp_wa_rellenar( $post_id );
}
add_action( 'added_post_meta', 'pp_wa_al_guardar_meta', 20, 3 );
add_action( 'updated_post_meta', 'pp_wa_al_guardar_meta', 20, 3 );

/**
 * Red de seguridad al guardar un listado desde el escritorio (prioridad alta
 * para correr después de que Listeo haya guardado sus campos).
 */
add_action(
	'save_post_listing',
	function ( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		pp_wa_rellenar( $post_id );
	},
	30,
	1
);

/**
 * Pasada única sobre los listados que ya existían antes de instalar esto.
 * Se ejecuta una sola vez por versión y queda registrada en una opción.
 */
add_action(
	'admin_init',
	function () {
		if ( ! pp_wa_activo() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_option( 'pp_wa_backfill' ) === PP_WA_BACKFILL_VERSION ) {
			return;
		}

		$listados = get_posts(
			array(
				'post_type'      => 'listing',
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'posts_per_page' => 300, // Cota de seguridad.
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => '_phone',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		foreach ( $listados as $id ) {
			pp_wa_rellenar( $id );
		}

		update_option( 'pp_wa_backfill', PP_WA_BACKFILL_VERSION, false );
	}
);
