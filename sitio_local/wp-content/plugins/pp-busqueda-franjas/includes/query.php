<?php
/**
 * Búsqueda por franjas — integración con las búsquedas de Listeo.
 *
 * Mismo patrón probado del módulo Listados de pp-personalizacion: un
 * pre_get_posts que actúa SOLO sobre (a) la query principal del archivo de
 * listados / sus taxonomías y (b) las queries del AJAX `listeo_get_listings`
 * (lista + mapa salen de la misma consulta → consistencia garantizada).
 *
 * El filtro corre ÚNICAMENTE cuando la petición trae una franja válida
 * (pp_franja_fecha + pp_franja_hora): la búsqueda normal no paga ningún
 * costo. La franja la envía el usuario al pulsar "Buscar disponibilidad"
 * (nunca se auto-ejecuta).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'pre_get_posts', 'pp_franjas_filtrar_query', 998 );
function pp_franjas_filtrar_query( $query ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return;
	}
	if ( ! pp_franjas_esta_activo() ) {
		return;
	}

	// Solo las queries de resultados de listados (idéntico al módulo Listados).
	if ( wp_doing_ajax() ) {
		if ( ! isset( $_REQUEST['action'] ) || 'listeo_get_listings' !== $_REQUEST['action'] ) {
			return;
		}
		if ( 'listing' !== $query->get( 'post_type' ) ) {
			return;
		}
	} else {
		if ( ! $query->is_main_query() ) {
			return;
		}
		$es_archivo = $query->is_post_type_archive( 'listing' );
		$es_tax     = $query->is_tax() && pp_franjas_query_es_tax_listing( $query );
		if ( ! $es_archivo && ! $es_tax ) {
			return;
		}
	}

	$franja = pp_franjas_leer_parametros();
	if ( ! $franja ) {
		return; // sin parámetros válidos no se filtra nada (costo cero)
	}

	$ids = pp_franjas_ids_para( $franja );
	if ( null === $ids ) {
		return; // motor no operativo (listeo-core ausente): no romper la búsqueda
	}

	// Intersectar con un post__in previo de otro filtro, si existiera.
	$previos = $query->get( 'post__in' );
	if ( is_array( $previos ) && $previos ) {
		$ids = array_values( array_intersect( array_map( 'intval', $previos ), $ids ) );
	}

	// post__in vacío significa "sin restricción" para WP_Query: forzamos
	// "sin resultados" con un id imposible.
	$query->set( 'post__in', $ids ? $ids : array( 0 ) );
}

/** ¿La taxonomía consultada pertenece al CPT listing? */
function pp_franjas_query_es_tax_listing( $query ) {
	$qo = $query->get_queried_object();
	if ( ! $qo || empty( $qo->taxonomy ) ) {
		return false;
	}
	return in_array( $qo->taxonomy, get_object_taxonomies( 'listing' ), true );
}
