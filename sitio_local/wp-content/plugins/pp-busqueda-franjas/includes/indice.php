<?php
/**
 * Filtros personalizados — ÍNDICE liviano de servicios por listado.
 *
 * Los Menús de Servicios viven serializados en la meta `_menu` (imposibles de
 * consultar en masa con SQL). Este módulo mantiene, por cada listado, metas
 * planas consultables:
 *
 *     _pp_tipo_servicio = <slug del tipo de servicio>   (una fila por tipo)
 *
 * Reglas de indexación (decisiones de Miguel 2026-07-11):
 *  - Listados de CITA (tipos single_day): ofrecen los tipos de servicio de
 *    sus menús QUE TENGAN al menos un servicio reservable (bookable=on).
 *    Un listado sin menús/servicios NO declara nada → queda fuera de las
 *    búsquedas por servicio (sigue saliendo en las búsquedas normales).
 *  - Listados de RANGO (tipos date_range, p. ej. Guardería): su "servicio"
 *    es la estadía misma (se reserva por calendario, no por menú), así que
 *    ofrecen implícitamente las tipologías que la matriz tipología×tipo-de-
 *    listado (pp_serv_tipos_por_listado) asigna a su tipo de listado
 *    (guarderia → cuidador), tengan o no menús.
 *
 * El índice se regenera al guardar el listado (cualquier vía: formulario del
 * front, admin, programática — se engancha al guardado de la meta `_menu` y
 * al save_post) y hay un backfill de todos los publicados que corre solo
 * cuando cambia la versión del índice (o desde el botón del admin).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Versión del algoritmo de indexación: súbela si cambian las reglas. */
define( 'PP_FRANJAS_INDICE_VER', '2' );

/* -------------------------------------------------------------------------
 * Cálculo de los tipos de servicio que ofrece un listado
 * ---------------------------------------------------------------------- */

/**
 * Tipos de servicio (slugs del catálogo) que ofrece un listado, según las
 * reglas de arriba. Devuelve array de slugs (puede ser vacío).
 */
function pp_franjas_calcular_tipos_de_listado( $listing_id ) {
	$tipos = array();

	$tipo_listado = sanitize_key( (string) get_post_meta( $listing_id, '_listing_type', true ) );
	$booking_type = function_exists( 'listeo_core_get_listing_type_booking_type' )
		? listeo_core_get_listing_type_booking_type( $tipo_listado )
		: '';

	// Catálogo vigente (si el módulo Servicios no está, no hay índice válido).
	$catalogo = function_exists( 'pp_serv_tipos' ) ? array_keys( pp_serv_tipos() ) : array();
	if ( ! $catalogo ) {
		return array();
	}

	if ( 'date_range' === $booking_type ) {
		// RANGO: tipologías implícitas de la matriz para su tipo de listado.
		if ( function_exists( 'pp_serv_tipos_permitidos_para_listado' ) ) {
			$tipos = pp_serv_tipos_permitidos_para_listado( $tipo_listado );
		}
	} elseif ( 'single_day' === $booking_type ) {
		// CITA: lo declarado en menús con servicios reservables.
		// (Listados de tipos NO reservables — o sin tipo — no se indexan:
		// la búsqueda jamás podría mostrarlos, y su oferta habilitaría
		// servicios "fantasma" en el selector.)
		$menu = get_post_meta( $listing_id, '_menu', true );
		if ( is_array( $menu ) ) {
			foreach ( $menu as $grupo ) {
				if ( ! is_array( $grupo ) ) {
					continue;
				}
				$tiene_reservable = false;
				foreach ( (array) ( $grupo['menu_elements'] ?? array() ) as $el ) {
					if ( ! empty( $el['bookable'] ) && 'on' === $el['bookable'] ) {
						$tiene_reservable = true;
						break;
					}
				}
				if ( ! $tiene_reservable ) {
					continue;
				}
				$slug = sanitize_key( $grupo['pp_tipo_servicio'] ?? '' );
				if ( $slug ) {
					$tipos[] = $slug;
				}
			}
		}
	}

	// Solo slugs vigentes del catálogo, sin duplicados.
	return array_values( array_unique( array_intersect( array_map( 'sanitize_key', (array) $tipos ), $catalogo ) ) );
}

/* -------------------------------------------------------------------------
 * Escritura del índice
 * ---------------------------------------------------------------------- */

/** Regenera las metas _pp_tipo_servicio de un listado. */
function pp_franjas_indexar_listado( $listing_id ) {
	$listing_id = (int) $listing_id;
	if ( ! $listing_id || 'listing' !== get_post_type( $listing_id ) ) {
		return;
	}

	$nuevos  = pp_franjas_calcular_tipos_de_listado( $listing_id );
	$viejos  = get_post_meta( $listing_id, '_pp_tipo_servicio' );
	$viejos  = is_array( $viejos ) ? array_map( 'strval', $viejos ) : array();

	sort( $nuevos );
	$ordenados = $viejos;
	sort( $ordenados );
	if ( $ordenados === $nuevos ) {
		return; // sin cambios: no tocar la base
	}

	delete_post_meta( $listing_id, '_pp_tipo_servicio' );
	foreach ( $nuevos as $slug ) {
		add_post_meta( $listing_id, '_pp_tipo_servicio', $slug );
	}

	// La disponibilidad cacheada puede referenciar el índice viejo.
	pp_franjas_invalidar_cache();
}

/* Guardado por CUALQUIER vía: al escribirse la meta _menu del listado…
   (updated/added cubren formulario del front, admin y programático). */
add_action( 'updated_post_meta', 'pp_franjas_indice_por_meta', 20, 3 );
add_action( 'added_post_meta', 'pp_franjas_indice_por_meta', 20, 3 );
function pp_franjas_indice_por_meta( $meta_id, $post_id, $meta_key ) {
	if ( '_menu' === $meta_key || '_listing_type' === $meta_key ) {
		pp_franjas_indexar_listado( $post_id );
	}
}

/* …y como cinturón, al guardar el post (cubre menús borrados por completo). */
add_action( 'save_post_listing', 'pp_franjas_indice_por_guardado', 99 );
function pp_franjas_indice_por_guardado( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	pp_franjas_indexar_listado( $post_id );
}

/* -------------------------------------------------------------------------
 * Backfill (reindexado total)
 * ---------------------------------------------------------------------- */

/** Reindexa todos los listados publicados. Devuelve cuántos se procesaron. */
function pp_franjas_reindexar_todo() {
	$ids = get_posts( array(
		'post_type'      => 'listing',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	foreach ( $ids as $id ) {
		pp_franjas_indexar_listado( $id );
	}
	update_option( 'pp_franjas_indice_ver', PP_FRANJAS_INDICE_VER );
	return count( $ids );
}

/* Auto-backfill: corre UNA vez cuando la versión del índice guardada no
   coincide (primera activación de esta versión o cambio de reglas). Va en
   admin_init para no costarle nada al front. */
add_action( 'admin_init', 'pp_franjas_indice_auto_backfill' );
function pp_franjas_indice_auto_backfill() {
	if ( get_option( 'pp_franjas_indice_ver' ) !== PP_FRANJAS_INDICE_VER ) {
		pp_franjas_reindexar_todo();
	}
}
