<?php
/**
 * Módulo: Quick wins del formulario "Agregar Publicación" (auditoría 2026-07).
 *
 * Mejoras de experiencia SIN tocar el flujo nativo de Listeo (todo por
 * capas: JS con guardas + gettext). Si algún selector no existe, no pasa
 * nada — el formulario sigue idéntico.
 *
 *  QW1  Aviso bajo el toggle "Precios y Servicios Reservables": sin
 *       activarlo no hay botón Reservar, ni vitrina, ni búsqueda por
 *       disponibilidad (el hueco de conversión nº 1 de la auditoría).
 *  QW2  El campo "Categoría" GLOBAL se oculta cuando el tipo ya tiene su
 *       propio campo de categorías (dos selectores de categoría confundían).
 *       Condicional: si el tipo NO tiene categoría propia, la global queda.
 *  QW3  Place ID / Longitud / Latitud tras un plegable "Opciones avanzadas"
 *       (el mapa los sigue llenando solo; solo se esconden de la vista).
 *  +    Guía breve en Galería (las fotos venden).
 *  QW5  Mensajes post-envío claros (gettext, frontend).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---- Assets: SOLO en la página del formulario (crear y editar) ---- */
add_action( 'wp_enqueue_scripts', 'pp_fp_assets', 125 );
function pp_fp_assets() {
	if ( is_admin() || ! is_user_logged_in() ) {
		return;
	}
	$submit_page = (int) get_option( 'listeo_submit_page' );
	if ( ! $submit_page || ! is_page( $submit_page ) ) {
		return;
	}
	$css = PP_PERS_DIR . 'css/pp-form-publicacion.css';
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'pp-form-publicacion', PP_PERS_URL . 'css/pp-form-publicacion.css', array(), filemtime( $css ) );
	}
	$js = PP_PERS_DIR . 'js/pp-form-publicacion.js';
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'pp-form-publicacion', PP_PERS_URL . 'js/pp-form-publicacion.js', array( 'jquery' ), filemtime( $js ), true );
	}
}

/* ---- M3: dirección obligatoria por tipo (server, fuente de verdad) ----
 * Un negocio físico sin dirección no aparece en el mapa ni le sirve al
 * cliente. Se exige SOLO en los tipos donde tiene sentido; el resto
 * (Adopción, Mascotas perdidas…) queda igual. El aviso amable del cliente
 * vive en el JS; esta validación es la garantía real. */
function pp_fp_tipos_con_direccion() {
	return apply_filters( 'pp_fp_tipos_con_direccion', array( 'directorio', 'guarderia' ) );
}

add_filter( 'submit_listing_form_validate_fields', 'pp_fp_validar_direccion', 25, 3 );
function pp_fp_validar_direccion( $valido, $fields, $values ) {
	if ( is_wp_error( $valido ) ) {
		return $valido;
	}
	$tipo = isset( $_POST['_listing_type'] ) ? sanitize_key( wp_unslash( $_POST['_listing_type'] ) ) : '';
	if ( ! $tipo || ! in_array( $tipo, pp_fp_tipos_con_direccion(), true ) ) {
		return $valido;
	}
	$direccion = isset( $_POST['_address'] ) ? trim( (string) wp_unslash( $_POST['_address'] ) ) : '';
	if ( '' === $direccion ) {
		return new WP_Error(
			'pp_fp_direccion',
			'Escribe la dirección de tu negocio (sección Ubicación): sin ella no apareces en el mapa ni te encuentran los clientes.'
		);
	}
	return $valido;
}

/* ---- E3: badge de completitud en "Mis Publicaciones" ----
 * Espejo server-side del checklist M1: mide qué tan completa está la ficha
 * y lo muestra en cada tarjeta del panel, con enlace directo a editar.
 * Se cuelga del hook nativo listeo_core_my_listings_after_meta, así que no
 * toca plantillas; la página ya precarga los metas en bloque (bulk cache),
 * por lo que el cálculo no agrega consultas por listado (solo términos). */

function pp_fp_completitud( $listing_id ) {
	$tipo       = sanitize_key( (string) get_post_meta( $listing_id, '_listing_type', true ) );
	$es_negocio = in_array( $tipo, pp_fp_tipos_con_direccion(), true );

	$puntos = 0.0;
	$total  = 0;
	$faltan = array();

	// Título con sustancia.
	$total++;
	if ( mb_strlen( trim( get_the_title( $listing_id ) ) ) >= 3 ) {
		$puntos++;
	} else {
		$faltan[] = 'título';
	}

	// Descripción (mínimo un párrafo).
	$total++;
	$post = get_post( $listing_id );
	$desc = $post ? trim( wp_strip_all_tags( $post->post_content ) ) : '';
	if ( mb_strlen( $desc ) >= 20 ) {
		$puntos++;
	} else {
		$faltan[] = 'descripción';
	}

	// Fotos: 3+ completa el punto; 1-2 vale medio.
	$total++;
	$galeria = get_post_meta( $listing_id, '_gallery', true );
	$fotos   = is_array( $galeria ) ? count( array_filter( array_keys( $galeria ) ) ) : 0;
	if ( ! $fotos && has_post_thumbnail( $listing_id ) ) {
		$fotos = 1;
	}
	if ( $fotos >= 3 ) {
		$puntos++;
	} elseif ( $fotos >= 1 ) {
		$puntos  += 0.5;
		$faltan[] = 'más fotos';
	} else {
		$faltan[] = 'fotos';
	}

	// Categoría: en la global o en la del tipo (cualquier taxonomía *_category).
	$total++;
	$con_categoria = false;
	foreach ( get_object_taxonomies( 'listing' ) as $tax ) {
		if ( false === strpos( $tax, 'category' ) ) {
			continue;
		}
		$terminos = get_the_terms( $listing_id, $tax );
		if ( $terminos && ! is_wp_error( $terminos ) ) {
			$con_categoria = true;
			break;
		}
	}
	if ( $con_categoria ) {
		$puntos++;
	} else {
		$faltan[] = 'categoría';
	}

	// Solo negocios (Directorio/Guardería): dirección, servicios y horario.
	if ( $es_negocio ) {
		$total++;
		if ( trim( (string) get_post_meta( $listing_id, '_address', true ) ) ) {
			$puntos++;
		} else {
			$faltan[] = 'dirección';
		}

		$total++;
		$con_servicios = false;
		if ( get_post_meta( $listing_id, '_menu_status', true ) ) {
			$menus = get_post_meta( $listing_id, '_menu', true );
			if ( is_array( $menus ) ) {
				foreach ( $menus as $menu ) {
					if ( empty( $menu['menu_elements'] ) || ! is_array( $menu['menu_elements'] ) ) {
						continue;
					}
					foreach ( $menu['menu_elements'] as $servicio ) {
						if ( ! empty( $servicio['name'] ) && trim( (string) $servicio['name'] ) ) {
							$con_servicios = true;
							break 2;
						}
					}
				}
			}
		}
		if ( $con_servicios ) {
			$puntos++;
		} else {
			$faltan[] = 'servicios reservables';
		}

		$total++;
		if ( get_post_meta( $listing_id, '_opening_hours_status', true ) ) {
			$puntos++;
		} else {
			$faltan[] = 'horario';
		}
	}

	return array(
		'pct'    => $total ? (int) round( 100 * $puntos / $total ) : 100,
		'faltan' => $faltan,
	);
}

add_action( 'listeo_core_my_listings_after_meta', 'pp_fp_badge_completitud', 10, 2 );
function pp_fp_badge_completitud( $listing, $listing_id ) {
	$datos = pp_fp_completitud( $listing_id );
	$pct   = $datos['pct'];

	$nivel = ( $pct >= 80 ) ? 'verde' : ( ( $pct >= 50 ) ? 'ambar' : 'rojo' );

	// Estilos una sola vez por página (el hook corre por cada tarjeta).
	static $css_impreso = false;
	if ( ! $css_impreso ) {
		$css_impreso = true;
		echo '<style id="pp-fp-badge-css">
.pp-fp-badge{display:inline-flex;align-items:center;gap:6px;margin:6px 8px 0 0;padding:3px 12px;border-radius:999px;font-size:12.5px;font-weight:700;line-height:1.6;vertical-align:middle}
.pp-fp-badge--verde{background:#e3f4ec;color:#1e7a4f}
.pp-fp-badge--ambar{background:#fff3d9;color:#8a6410}
.pp-fp-badge--rojo{background:#fdeae8;color:#b3261e}
.pp-fp-badge__ir{font-size:12px;font-weight:600;text-decoration:underline;white-space:nowrap}
@media (max-width:767px){.pp-fp-badge{display:flex;width:fit-content;margin-top:8px}}
</style>';
	}

	$texto = 'Ficha completa al ' . $pct . '%';
	$title = $datos['faltan'] ? 'Falta: ' . implode( ', ', $datos['faltan'] ) : 'Tu publicación tiene toda la información clave';

	echo '<span class="pp-fp-badge pp-fp-badge--' . esc_attr( $nivel ) . '" title="' . esc_attr( $title ) . '">';
	echo ( 100 === $pct ? '✓ ' : '' ) . esc_html( $texto );
	if ( $pct < 100 ) {
		$submit_page = (int) get_option( 'listeo_submit_page' );
		if ( $submit_page ) {
			$url = add_query_arg(
				array(
					'action'     => 'edit',
					'listing_id' => $listing_id,
				),
				get_permalink( $submit_page )
			);
			echo ' <a class="pp-fp-badge__ir" href="' . esc_url( $url ) . '">Completar</a>';
		}
	}
	echo '</span>';
}

/* ---- QW5: mensajes post-envío claros (solo frontend) ---- */
add_filter( 'gettext_listeo_core', 'pp_fp_mensajes_envio', 20, 2 );
function pp_fp_mensajes_envio( $traduccion, $texto ) {
	if ( is_admin() ) {
		return $traduccion;
	}
	switch ( $texto ) {
		case 'Thanks for your submission!':
			return '¡Gracias! Recibimos tu publicación 🐾';
		case 'Your listing has been saved and is awaiting admin approval':
			return 'Nuestro equipo la está revisando para asegurar la calidad del directorio. Estará visible muy pronto.';
		case 'Your changes have been saved.':
			return 'Tus cambios fueron guardados y están en revisión. Muy pronto se verán reflejados.';
	}
	return $traduccion;
}
