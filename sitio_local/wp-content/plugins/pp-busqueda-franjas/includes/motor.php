<?php
/**
 * Búsqueda por franjas — MOTOR de disponibilidad.
 *
 * Responde UNA pregunta de forma masiva: ¿qué listados publicados tienen
 * libre la franja (fecha + hora) pedida?
 *
 * Diseño de performance (la parte sensible):
 *  - CERO consultas por listado (nada de count_free_places en bucle, que
 *    haría 4-5 consultas por listado). Todo sale de consultas AGRUPADAS:
 *      1. Candidatos (id + tipo) de los tipos con reserva de cita.
 *      2. update_meta_cache de esos ids (1 consulta: _slots, horarios, etc.).
 *      3. Reservas del DÍA pedido de todos los candidatos (1 consulta).
 *      4. (opcional) disponibilidad por DÍA de los tipos date_range, vía los
 *         helpers ya existentes de Listeo_Core_Search (2 consultas).
 *    Total: ~5 consultas fijas, sin importar si hay 50 o 5.000 listados.
 *  - Caché en transient por (fecha, hora, config), TTL 10 min, con
 *    invalidación por versión cuando se crea/cancela/expira una reserva o
 *    se edita un listado.
 *
 * Semántica (calcada de listeo-core para no contradecir el popup de reserva):
 *  - Tipos single_day CON slots (_slots_status + _slots): la franja está
 *    libre si existe un slot de ese día de la semana que CONTIENE la hora
 *    pedida y le quedan cupos (capacidad - reservas con coincidencia exacta
 *    de horario). Días bloqueados (reserva multi-día o bloqueo 00:00-23:59)
 *    descartan el listado ese día.
 *  - Tipos single_day SIN slots (agenda que gestiona el dueño): libre si el
 *    día no está bloqueado, ninguna reserva se solapa con [hora, hora+1h) y,
 *    si el listado usa horario de apertura, ese día abre a esa hora.
 *  - Tipos date_range (p. ej. Guardería), si la opción está activa: libres
 *    si NO tienen reserva que los ocupe desde la hora pedida hasta el fin
 *    del día (un checkout esa mañana no bloquea; un check-in esa noche sí).
 *  - Reservas que ocupan: type='reservation' con status distinto de
 *    cancelled/expired (igual que get_slots_bookings de listeo-core).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Parámetros de la petición
 * ---------------------------------------------------------------------- */

/** Valida una fecha Y-m-d entre hoy y +18 meses. Devuelve la fecha o ''. */
function pp_franjas_validar_fecha( $fecha ) {
	$dt = DateTime::createFromFormat( 'Y-m-d', $fecha, wp_timezone() );
	if ( ! $dt || $dt->format( 'Y-m-d' ) !== $fecha ) {
		return '';
	}
	$hoy = new DateTime( 'today', wp_timezone() );
	$max = ( clone $hoy )->modify( '+18 months' );
	return ( $dt < $hoy || $dt > $max ) ? '' : $fecha;
}

/**
 * Lee y valida los parámetros del filtro de disponibilidad.
 *
 * Dos modos, según lo que traiga la petición:
 *  - 'franja': pp_franja_fecha + pp_franja_hora (cita: día y hora exactos)
 *  - 'rango':  pp_franja_entrada + pp_franja_salida (estadía: entrada-salida)
 * Ambos aceptan pp_franja_servicio (slug del catálogo de Tipos de Servicio)
 * para acotar a los listados que OFRECEN ese servicio (índice del plugin).
 *
 * @return array|null p.ej. {modo:'franja',fecha,hora,servicio} o
 *                    {modo:'rango',entrada,salida,servicio}; null = no filtrar.
 */
function pp_franjas_leer_parametros() {
	$servicio = '';
	if ( isset( $_REQUEST['pp_franja_servicio'] ) ) {
		$servicio = sanitize_key( wp_unslash( $_REQUEST['pp_franja_servicio'] ) );
		if ( $servicio && function_exists( 'pp_serv_tipos' ) && ! isset( pp_serv_tipos()[ $servicio ] ) ) {
			return null; // servicio inexistente: mejor no filtrar que filtrar mal
		}
	}

	/* ---- Modo RANGO (entrada-salida) ---- */
	if ( isset( $_REQUEST['pp_franja_entrada'], $_REQUEST['pp_franja_salida'] ) ) {
		$entrada = pp_franjas_validar_fecha( sanitize_text_field( wp_unslash( $_REQUEST['pp_franja_entrada'] ) ) );
		$salida  = pp_franjas_validar_fecha( sanitize_text_field( wp_unslash( $_REQUEST['pp_franja_salida'] ) ) );
		if ( ! $entrada || ! $salida || $salida < $entrada ) {
			return null;
		}
		// Tope de sanidad: estadías de máximo 92 días.
		$dias = ( strtotime( $salida ) - strtotime( $entrada ) ) / DAY_IN_SECONDS;
		if ( $dias > 92 ) {
			return null;
		}
		return array(
			'modo'     => 'rango',
			'entrada'  => $entrada,
			'salida'   => $salida,
			'servicio' => $servicio,
		);
	}

	/* ---- Modo FRANJA (fecha + hora) ---- */
	if ( ! isset( $_REQUEST['pp_franja_fecha'], $_REQUEST['pp_franja_hora'] ) ) {
		return null;
	}
	$fecha = pp_franjas_validar_fecha( sanitize_text_field( wp_unslash( $_REQUEST['pp_franja_fecha'] ) ) );
	$hora  = sanitize_text_field( wp_unslash( $_REQUEST['pp_franja_hora'] ) );
	if ( ! $fecha || ! preg_match( '/^([01]\d|2[0-3]):[0-5][05]$/', $hora ) ) {
		return null;
	}
	return array(
		'modo'     => 'franja',
		'fecha'    => $fecha,
		'hora'     => $hora,
		'servicio' => $servicio,
	);
}

/* -------------------------------------------------------------------------
 * Contexto de la página de resultados
 * ---------------------------------------------------------------------- */

/**
 * ¿El panel de disponibilidad APLICA en la petición actual?
 * Regla (Miguel 2026-07-11): el panel es para Directorio y Guardería.
 * En los contextos de tipos separados (Adopción, Mascotas Perdidas — módulo
 * Listados de pp-personalizacion) NO aplica: allí no se reserva nada y el
 * carrusel contextual de categorías de ese módulo debe seguir vivo.
 */
function pp_franjas_contexto_aplica() {
	if ( function_exists( 'pp_listados_contexto_tipo' ) && '' !== pp_listados_contexto_tipo() ) {
		return false;
	}
	// Sin el módulo: si la URL trae _listing_type de un tipo NO reservable,
	// tampoco aplica (el filtro de franjas no le aporta nada).
	if ( isset( $_REQUEST['_listing_type'] ) && ! is_array( $_REQUEST['_listing_type'] ) && function_exists( 'listeo_core_get_listing_type_booking_type' ) ) {
		$bt = listeo_core_get_listing_type_booking_type( sanitize_key( wp_unslash( $_REQUEST['_listing_type'] ) ) );
		if ( ! in_array( $bt, array( 'single_day', 'date_range' ), true ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Término de categoría del contexto actual (página de taxonomía de listing o
 * parámetro tax-listing_category del buscador). 0 = sin contexto (archivo).
 *
 * @return array{taxonomy:string, term_id:int}
 */
function pp_franjas_contexto_termino() {
	// Página de taxonomía de listing (p. ej. /categoria-de-listado/salud/).
	if ( is_tax() ) {
		$qo = get_queried_object();
		if ( $qo && ! empty( $qo->taxonomy ) && in_array( $qo->taxonomy, get_object_taxonomies( 'listing' ), true ) ) {
			return array( 'taxonomy' => $qo->taxonomy, 'term_id' => (int) $qo->term_id );
		}
	}
	// Parámetro del buscador (?tax-listing_category=slug).
	if ( isset( $_GET['tax-listing_category'] ) && ! is_array( $_GET['tax-listing_category'] ) ) {
		$term = get_term_by( 'slug', sanitize_title( wp_unslash( $_GET['tax-listing_category'] ) ), 'listing_category' );
		if ( $term && ! is_wp_error( $term ) ) {
			return array( 'taxonomy' => 'listing_category', 'term_id' => (int) $term->term_id );
		}
	}
	return array( 'taxonomy' => '', 'term_id' => 0 );
}

/**
 * Tipos de servicio con OFERTA en el contexto: los que declara (índice
 * _pp_tipo_servicio) al menos un listado publicado — acotado al término de
 * categoría actual (incluyendo sus subcategorías) si lo hay.
 * UNA consulta, cacheada 10 min con la misma versión del motor.
 *
 * @return string[] slugs con oferta.
 */
function pp_franjas_servicios_con_oferta( $taxonomy = '', $term_id = 0 ) {
	global $wpdb;

	$ver   = (int) get_option( 'pp_franjas_cache_ver', 1 );
	$clave = 'pp_franjas_of_' . md5( $ver . '|' . $taxonomy . '|' . (int) $term_id );

	$cacheado = get_transient( $clave );
	if ( is_array( $cacheado ) && isset( $cacheado['slugs'] ) ) {
		return $cacheado['slugs'];
	}

	$join_tax = '';
	$params   = array();
	if ( $term_id && $taxonomy ) {
		// El término y todas sus subcategorías (en "Salud" cuentan los
		// listados de "Comportamiento", "Higiene", etc.).
		$term_ids = array_merge( array( (int) $term_id ), array_map( 'intval', (array) get_term_children( (int) $term_id, $taxonomy ) ) );
		$marcas   = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
		$ttids    = $wpdb->get_col( $wpdb->prepare(
			"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s AND term_id IN ({$marcas})",
			array_merge( array( $taxonomy ), $term_ids )
		) );
		$ttids = array_map( 'intval', (array) $ttids );
		if ( ! $ttids ) {
			set_transient( $clave, array( 'slugs' => array() ), 10 * MINUTE_IN_SECONDS );
			return array();
		}
		$join_tax = 'INNER JOIN ' . $wpdb->term_relationships . ' tr ON tr.object_id = p.ID AND tr.term_taxonomy_id IN (' . implode( ',', $ttids ) . ')';
	}

	$slugs = $wpdb->get_col(
		"SELECT DISTINCT pm.meta_value
		 FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p
		         ON p.ID = pm.post_id AND p.post_type = 'listing' AND p.post_status = 'publish'
		 {$join_tax}
		 WHERE pm.meta_key = '_pp_tipo_servicio'"
	);
	$slugs = array_values( array_filter( array_map( 'sanitize_key', (array) $slugs ) ) );

	set_transient( $clave, array( 'slugs' => $slugs ), 10 * MINUTE_IN_SECONDS );
	return $slugs;
}

/* -------------------------------------------------------------------------
 * Servicios del panel: qué tipologías se ofrecen y en qué modo se reservan
 * ---------------------------------------------------------------------- */

/**
 * Ícono (clase Font Awesome 4) de cada tipología de servicio. Mapa por
 * defecto para las tipologías conocidas + comodín (fa-paw). Filtrable, de
 * modo que a futuro pueda hacerse administrable por tipo sin tocar esto.
 */
function pp_franjas_icono_servicio( $slug ) {
	$mapa = array(
		'peluqueria-y-bano'  => 'fa-scissors',
		'adiestramiento'     => 'fa-graduation-cap',
		'cuidador'           => 'fa-home',
		'fotografia-y-video' => 'fa-camera',
		'reposteria'         => 'fa-birthday-cake',
		'fiestas-caninas'    => 'fa-gift',
	);
	$icono = isset( $mapa[ $slug ] ) ? $mapa[ $slug ] : 'fa-paw';
	return apply_filters( 'pp_franjas_icono_servicio', $icono, $slug );
}

/** Ícono del ítem "Todos los servicios". */
function pp_franjas_icono_todos() {
	return apply_filters( 'pp_franjas_icono_servicio', 'fa-th-large', '' );
}

/**
 * Tipologías del catálogo con su modo de búsqueda, derivado de la matriz
 * tipología×tipo-de-listado y del booking_type de cada tipo de listado:
 *  - 'rango'  si la tipología solo vive en tipos date_range (Guardería)
 *  - 'franja' si vive en tipos de cita (single_day) — o en ambos
 * Tipologías sin ningún tipo de listado reservable no aparecen.
 *
 * @return array slug => array{nombre:string, modo:string}
 */
function pp_franjas_servicios_para_panel() {
	if ( ! function_exists( 'pp_serv_tipos' ) || ! function_exists( 'pp_serv_tipos_permitidos_para_listado' ) || ! function_exists( 'listeo_core_get_listing_type_slugs_by_booking_type' ) ) {
		return array();
	}

	$catalogo  = pp_serv_tipos();
	$lt_franja = (array) listeo_core_get_listing_type_slugs_by_booking_type( 'single_day' );
	$lt_rango  = (array) listeo_core_get_listing_type_slugs_by_booking_type( 'date_range' );

	/* Modos SOLO desde entradas EXPLÍCITAS de la matriz. La regla de
	   compatibilidad del módulo Servicios ("tipo de listado sin entrada →
	   permite todos") sirve para publicar, pero contaminaría los modos:
	   cualquier tipo de listado suelto marcaría TODAS las tipologías. */
	$matriz    = function_exists( 'pp_serv_tipos_por_listado' ) ? pp_serv_tipos_por_listado() : array();
	$en_franja = array();
	$en_rango  = array();
	foreach ( $matriz as $lt => $tipologias ) {
		$lt = sanitize_key( $lt );
		if ( ! is_array( $tipologias ) ) {
			continue;
		}
		foreach ( array_map( 'sanitize_key', $tipologias ) as $s ) {
			if ( in_array( $lt, $lt_rango, true ) ) {
				$en_rango[ $s ] = true;
			} elseif ( in_array( $lt, $lt_franja, true ) ) {
				$en_franja[ $s ] = true;
			}
		}
	}

	$out = array();
	foreach ( $catalogo as $slug => $t ) {
		if ( isset( $en_rango[ $slug ] ) && ! isset( $en_franja[ $slug ] ) ) {
			$modo = 'rango';
		} else {
			// En franja, en ambos, o sin entrada en la matriz: cita.
			$modo = 'franja';
		}
		$out[ $slug ] = array(
			'nombre' => $t['nombre'],
			'modo'   => $modo,
			'icono'  => pp_franjas_icono_servicio( $slug ),
		);
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * Caché
 * ---------------------------------------------------------------------- */

/** Invalidación barata: subir la versión deja huérfanos los transients viejos. */
function pp_franjas_invalidar_cache() {
	$ver = (int) get_option( 'pp_franjas_cache_ver', 1 );
	update_option( 'pp_franjas_cache_ver', $ver + 1, true );
}

// Cualquier movimiento de reservas cambia la disponibilidad.
add_action( 'listeo_after_insert_booking', 'pp_franjas_invalidar_cache' );
add_action( 'listeo_booking_cancelled', 'pp_franjas_invalidar_cache' );
add_action( 'listeo_booking_status_changed', 'pp_franjas_invalidar_cache' );
add_action( 'listeo_expire_booking', 'pp_franjas_invalidar_cache' );

// Editar un listado puede cambiar sus slots/horarios.
add_action( 'save_post_listing', 'pp_franjas_invalidar_cache' );

/* -------------------------------------------------------------------------
 * Motor
 * ---------------------------------------------------------------------- */

/**
 * IDs de listados publicados disponibles para los parámetros del filtro
 * (cualquier modo), con caché transient + memo de petición.
 *
 * @param array $params Salida de pp_franjas_leer_parametros().
 * @return int[]|null Lista de IDs (puede ser vacía = nadie disponible);
 *                    null si el motor no puede operar (listeo-core ausente).
 */
function pp_franjas_ids_para( $params ) {
	static $memo = array();

	if ( ! function_exists( 'listeo_core_get_listing_type_slugs_by_booking_type' ) || ! is_array( $params ) ) {
		return null; // listeo-core inactivo o sin parámetros: no filtrar nada.
	}

	$incluir_dr = (bool) get_option( 'pp_franjas_incluir_date_range', 1 );
	$ver        = (int) get_option( 'pp_franjas_cache_ver', 1 );
	$clave      = 'pp_franjas_' . md5( $ver . '|' . wp_json_encode( $params ) . '|' . ( $incluir_dr ? 1 : 0 ) );

	if ( isset( $memo[ $clave ] ) ) {
		return $memo[ $clave ];
	}

	$cacheado = get_transient( $clave );
	if ( is_array( $cacheado ) && isset( $cacheado['ids'] ) ) {
		$memo[ $clave ] = array_map( 'intval', $cacheado['ids'] );
		return $memo[ $clave ];
	}

	if ( 'rango' === $params['modo'] ) {
		$ids = pp_franjas_calcular_ids_rango( $params['entrada'], $params['salida'], $params['servicio'] );
	} else {
		$ids = pp_franjas_calcular_ids( $params['fecha'], $params['hora'], $incluir_dr, $params['servicio'] );
	}

	// Envuelto en array para poder cachear también el resultado "vacío".
	set_transient( $clave, array( 'ids' => $ids ), 10 * MINUTE_IN_SECONDS );
	$memo[ $clave ] = $ids;
	return $ids;
}

/** Compatibilidad con la firma v1.x (usada por pruebas y llamadas directas). */
function pp_franjas_ids_disponibles( $fecha, $hora ) {
	return pp_franjas_ids_para( array(
		'modo'     => 'franja',
		'fecha'    => $fecha,
		'hora'     => $hora,
		'servicio' => '',
	) );
}

/**
 * Modo RANGO: listados de tipos date_range disponibles para la estadía
 * [entrada, salida], opcionalmente acotados a un servicio del índice.
 *
 * Regla de noches (Miguel 2026-07-11): con 2+ días se validan TODAS las
 * noches de la estadía; entrada = salida (guardería de día, sin estadía)
 * valida solo ese día. Implementación uniforme: ocupa cualquier reserva que
 * se solape con [entrada 00:00, fin), donde fin = salida 00:00 (o entrada+1
 * día si es el mismo día). Coincide con el chequeo nativo de Listeo, así el
 * filtro nunca muestra algo que el popup de reserva luego rechazaría.
 *
 * @return int[]
 */
function pp_franjas_calcular_ids_rango( $entrada, $salida, $servicio = '' ) {
	$slugs_dr = listeo_core_get_listing_type_slugs_by_booking_type( 'date_range' );
	$slugs_dr = is_array( $slugs_dr ) ? array_values( array_filter( $slugs_dr ) ) : array();
	if ( ! $slugs_dr ) {
		return array();
	}

	$ids = pp_franjas_candidatos_por_tipo( $slugs_dr, $servicio );
	if ( ! $ids ) {
		return array();
	}

	$fin = ( $salida > $entrada ) ? $salida . ' 00:00:00'
		: date( 'Y-m-d', strtotime( $entrada . ' +1 day' ) ) . ' 00:00:00';
	$ini = $entrada . ' 00:00:00';

	$ocupadas = pp_franjas_reservas_ventana( array_keys( $ids ), $ini, $fin );

	$disponibles = array();
	foreach ( array_keys( $ids ) as $id ) {
		if ( empty( $ocupadas[ $id ] ) ) {
			$disponibles[] = $id;
		}
	}
	return $disponibles;
}

/**
 * Candidatos publicados de una lista de tipos de listado, opcionalmente
 * acotados a los que ofrecen un servicio (índice _pp_tipo_servicio).
 * UNA consulta.
 *
 * @return array id => tipo de listado
 */
function pp_franjas_candidatos_por_tipo( $slugs, $servicio = '' ) {
	global $wpdb;

	$slugs = is_array( $slugs ) ? array_values( array_filter( $slugs ) ) : array();
	if ( ! $slugs ) {
		return array();
	}

	$marcas    = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );
	$join_serv = '';
	$params    = $slugs;
	if ( $servicio ) {
		$join_serv = "INNER JOIN {$wpdb->postmeta} ps
		              ON p.ID = ps.post_id AND ps.meta_key = '_pp_tipo_servicio' AND ps.meta_value = %s";
		// OJO: prepare() llena los %s en ORDEN DE APARICIÓN en el SQL, y el
		// JOIN va antes del WHERE — el servicio debe ir de primero.
		array_unshift( $params, $servicio );
	}

	$filas = $wpdb->get_results( $wpdb->prepare(
		"SELECT DISTINCT p.ID, pm.meta_value AS tipo
		 FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} pm
		         ON p.ID = pm.post_id AND pm.meta_key = '_listing_type'
		 {$join_serv}
		 WHERE p.post_type = 'listing'
		   AND p.post_status = 'publish'
		   AND pm.meta_value IN ({$marcas})",
		$params
	) );

	$out = array();
	foreach ( (array) $filas as $f ) {
		$out[ (int) $f->ID ] = (string) $f->tipo;
	}
	return $out;
}

/**
 * Cálculo real (sin caché) de los IDs disponibles en la franja (fecha+hora).
 *
 * @param string $servicio Slug del índice para acotar candidatos ('' = todos).
 * @return int[]
 */
function pp_franjas_calcular_ids( $fecha, $hora, $incluir_dr, $servicio = '' ) {
	global $wpdb;

	$disponibles = array();

	/* ---- 1. Candidatos: listados de tipos con reserva de cita ---- */
	$slugs_cita = listeo_core_get_listing_type_slugs_by_booking_type( 'single_day' );
	$candidatos = pp_franjas_candidatos_por_tipo( $slugs_cita, $servicio );

	if ( $candidatos ) {
		$ids_candidatos = array_keys( $candidatos );

		/* ---- 2. SOLO las metas que el motor usa, en UNA consulta ----
		   (update_meta_cache traería TODAS las metas — galerías, menús de
		   servicios, etc. — y con cientos de listados eso pesa; aquí viajan
		   únicamente slots, su estado y los horarios de apertura). */
		$metas = pp_franjas_metas_candidatos( $ids_candidatos );

		/* ---- 3. Reservas del día de TODOS los candidatos en UNA consulta ---- */
		$reservas_por_listado = pp_franjas_reservas_del_dia( $ids_candidatos, $fecha );

		/* ---- 4. Evaluación en PHP (sin más consultas) ---- */
		foreach ( $ids_candidatos as $id ) {
			$m = isset( $metas[ $id ] ) ? $metas[ $id ] : array();
			$r = isset( $reservas_por_listado[ $id ] ) ? $reservas_por_listado[ $id ] : array();
			if ( pp_franjas_listado_disponible( $fecha, $hora, $m, $r ) ) {
				$disponibles[] = $id;
			}
		}
	}

	/* ---- 5. Tipos date_range (Guardería): libres desde la HORA elegida ----
	   Un listado de rango está disponible si ninguna reserva lo ocupa desde
	   la hora pedida hasta el fin del día (así un checkout en la mañana no
	   bloquea una búsqueda para la tarde, pero un check-in de esa noche sí:
	   la estadía del usuario chocaría con él).
	   Corre cuando la opción lo permite, o siempre que se busca un servicio
	   concreto (el índice ya acota a los que lo ofrecen). */
	if ( $incluir_dr || $servicio ) {
		$slugs_dr = listeo_core_get_listing_type_slugs_by_booking_type( 'date_range' );
		$ids_dr   = array_keys( pp_franjas_candidatos_por_tipo( $slugs_dr, $servicio ) );
		if ( $ids_dr ) {
			$reservas_dr = pp_franjas_reservas_del_dia( $ids_dr, $fecha );
			$franja_ini  = $fecha . ' ' . $hora . ':00';
			foreach ( $ids_dr as $id_dr ) {
				$libre = true;
				foreach ( isset( $reservas_dr[ $id_dr ] ) ? $reservas_dr[ $id_dr ] : array() as $r ) {
					// Las filas ya tocan el día: ocupan la franja si terminan
					// DESPUÉS de la hora pedida.
					if ( $r['end'] > $franja_ini ) {
						$libre = false;
						break;
					}
				}
				if ( $libre ) {
					$disponibles[] = $id_dr;
				}
			}
		}
	}

	return array_values( array_unique( array_map( 'intval', $disponibles ) ) );
}

/**
 * Metas que usa el motor, de todos los candidatos, en UNA consulta.
 *
 * @return array<int, array<string, mixed>> id => [meta_key => valor]
 */
function pp_franjas_metas_candidatos( $ids ) {
	global $wpdb;

	if ( ! $ids ) {
		return array();
	}

	$claves = array( '_slots', '_slots_status', '_opening_hours_status' );
	foreach ( array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ) as $dia ) {
		$claves[] = '_' . $dia . '_opening_hour';
		$claves[] = '_' . $dia . '_closing_hour';
	}

	$marcas_id    = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	$marcas_clave = implode( ',', array_fill( 0, count( $claves ), '%s' ) );

	$filas = $wpdb->get_results( $wpdb->prepare(
		"SELECT post_id, meta_key, meta_value
		 FROM {$wpdb->postmeta}
		 WHERE post_id IN ({$marcas_id})
		   AND meta_key IN ({$marcas_clave})",
		array_merge( $ids, $claves )
	) );

	$map = array();
	foreach ( (array) $filas as $f ) {
		$map[ (int) $f->post_id ][ (string) $f->meta_key ] = maybe_unserialize( $f->meta_value );
	}
	return $map;
}

/**
 * Reservas que OCUPAN (type=reservation, no cancelada/expirada) que se
 * solapan con la ventana [ini, fin], de todos los listados dados,
 * agrupadas por listado. UNA consulta.
 *
 * @param string $ini Datetime 'Y-m-d H:i:s' inclusive.
 * @param string $fin Datetime 'Y-m-d H:i:s' (solape estricto: start < fin).
 * @return array<int, array<int, array{start:string,end:string}>>
 */
function pp_franjas_reservas_ventana( $ids, $ini, $fin ) {
	global $wpdb;

	if ( ! $ids ) {
		return array();
	}

	$marcas = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

	$sql = "SELECT listing_id, date_start, date_end
	        FROM {$wpdb->prefix}bookings_calendar
	        WHERE listing_id IN ({$marcas})
	          AND type = 'reservation'
	          AND status NOT IN ('cancelled', 'expired')
	          AND date_start < %s
	          AND date_end   > %s";

	$params = array_merge( $ids, array( $fin, $ini ) );
	$filas  = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

	$por_listado = array();
	foreach ( (array) $filas as $f ) {
		$por_listado[ (int) $f->listing_id ][] = array(
			'start' => (string) $f->date_start,
			'end'   => (string) $f->date_end,
		);
	}
	return $por_listado;
}

/** Reservas que tocan el día pedido (ventana de día completo). */
function pp_franjas_reservas_del_dia( $ids, $fecha ) {
	return pp_franjas_reservas_ventana( $ids, $fecha . ' 00:00:00', $fecha . ' 23:59:59' );
}

/**
 * Decodifica el JSON de _slots con la misma tolerancia que listeo-core
 * (repara el guion "u2013" corrupto y normaliza a arrays anidados).
 *
 * @return array|null Matriz [día 0-6][n] => "HH:MM - HH:MM|cupos", o null.
 */
function pp_franjas_decodificar_slots( $raw ) {
	if ( ! is_string( $raw ) || '' === $raw ) {
		return null;
	}
	// Mismo saneo que Listeo_Core_Bookings_Calendar::get_slots_from_meta().
	$raw = preg_replace( '/\s+u201[34]\s+/', ' - ', $raw );
	// Y su MISMA regla del guion: si el JSON no contiene ningún rango
	// "HH:MM - HH:MM" (p. ej. slots activados pero todos los días vacíos),
	// el core lo trata como SIN slots (agenda del dueño) — nosotros igual.
	if ( false === strpos( $raw, '-' ) && false === strpos( $raw, '–' ) && false === strpos( $raw, '—' ) ) {
		return null;
	}
	$slots = json_decode( $raw, true );
	return is_array( $slots ) ? $slots : null;
}

/**
 * ¿Un listado de tipo cita (single_day) tiene libre la franja?
 * Toda la información llega ya cargada (metas y reservas del día):
 * esta función NO ejecuta consultas.
 *
 * @param array $metas    Metas del listado [meta_key => valor].
 * @param array $reservas Reservas del día del listado [{start,end}...].
 */
function pp_franjas_listado_disponible( $fecha, $hora, $metas, $reservas ) {
	/* Día bloqueado: reserva multi-día que lo cubre, o bloqueo de día
	   completo 00:00-23:59 (misma regla que listeo-core). */
	foreach ( $reservas as $r ) {
		$d_ini = substr( $r['start'], 0, 10 );
		$d_fin = substr( $r['end'], 0, 10 );
		if ( $d_ini !== $d_fin ) {
			return false; // multi-día: bloquea todos los días del rango
		}
		if ( '00:00' === substr( $r['start'], 11, 5 ) && '23:59' === substr( $r['end'], 11, 5 ) ) {
			return false; // bloqueo de día completo
		}
	}

	// Día de la semana en el índice de _slots: 0=lunes … 6=domingo.
	$dow = (int) date( 'N', strtotime( $fecha ) ) - 1;

	$slots_status = ! empty( $metas['_slots_status'] );
	$slots        = $slots_status ? pp_franjas_decodificar_slots( isset( $metas['_slots'] ) ? $metas['_slots'] : '' ) : null;

	if ( $slots && is_array( $slots ) && ! empty( $slots[ $dow ] ) && is_array( $slots[ $dow ] ) ) {
		/* ---- Con slots: buscar un slot del día que contenga la hora ---- */
		foreach ( $slots[ $dow ] as $slot_raw ) {
			$partes = explode( '|', (string) $slot_raw );
			if ( count( $partes ) < 2 ) {
				continue;
			}
			$horas = explode( ' - ', $partes[0] );
			if ( count( $horas ) < 2 ) {
				continue;
			}
			$ini = date( 'H:i', strtotime( trim( $horas[0] ) ) );
			$fin = date( 'H:i', strtotime( trim( $horas[1] ) ) );
			if ( ! ( $ini <= $hora && $hora < $fin ) ) {
				continue; // este slot no cubre la hora pedida
			}

			// Cupos: capacidad - reservas con coincidencia EXACTA de horario
			// (así es como listeo-core registra las reservas de slot).
			$capacidad  = (int) $partes[1];
			$slot_start = $fecha . ' ' . date( 'H:i:s', strtotime( trim( $horas[0] ) ) );
			$slot_end   = $fecha . ' ' . date( 'H:i:s', strtotime( trim( $horas[1] ) ) );
			$ocupadas   = 0;
			foreach ( $reservas as $r ) {
				if ( $r['start'] === $slot_start && $r['end'] === $slot_end ) {
					$ocupadas++;
				}
			}
			if ( $capacidad - $ocupadas > 0 ) {
				return true;
			}
		}
		return false; // había slots ese día pero ninguno libre a esa hora
	}

	if ( $slots_status && ! $slots ) {
		/* Slots activados pero datos ilegibles: mismo criterio que el core
		   (cae al modo "sin slots", el dueño gestiona su agenda). */
	} elseif ( $slots && is_array( $slots ) && empty( $slots[ $dow ] ) ) {
		return false; // con slots configurados, un día sin slots = cerrado
	}

	/* ---- Sin slots: agenda del dueño ---- */

	// Si usa horario de apertura, ese día debe abrir y cubrir la hora.
	// Listeo guarda estos metas como ARRAY (un día puede tener varios turnos:
	// p. ej. 08:00-12:00 y 14:00-18:00): la hora vale si cae en ALGÚN turno.
	if ( ! empty( $metas['_opening_hours_status'] ) ) {
		$dias   = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		$abre   = isset( $metas[ '_' . $dias[ $dow ] . '_opening_hour' ] ) ? $metas[ '_' . $dias[ $dow ] . '_opening_hour' ] : array();
		$cierra = isset( $metas[ '_' . $dias[ $dow ] . '_closing_hour' ] ) ? $metas[ '_' . $dias[ $dow ] . '_closing_hour' ] : array();
		$abre   = array_values( array_filter( (array) $abre ) );
		$cierra = array_values( array_filter( (array) $cierra ) );
		if ( ! $abre || ! $cierra ) {
			return false; // ese día no abre
		}
		$en_turno   = false;
		$evaluables = 0;
		foreach ( $abre as $i => $desde ) {
			$hasta = isset( $cierra[ $i ] ) ? $cierra[ $i ] : '';
			if ( ! is_string( $desde ) || ! is_string( $hasta ) || '' === $hasta ) {
				continue;
			}
			$desde_ts = strtotime( $desde );
			$hasta_ts = strtotime( $hasta );
			if ( false === $desde_ts || false === $hasta_ts ) {
				continue;
			}
			$evaluables++;
			$desde_h = date( 'H:i', $desde_ts );
			$hasta_h = date( 'H:i', $hasta_ts );
			if ( $desde_h <= $hora && $hora < $hasta_h ) {
				$en_turno = true;
				break;
			}
		}
		// Con turnos legibles y ninguno cubre la hora: cerrado. Si nada fue
		// legible, no excluimos (mejor mostrar de más que ocultar un abierto).
		if ( $evaluables > 0 && ! $en_turno ) {
			return false;
		}
	}

	// Libre si ninguna reserva se solapa con [hora, hora + 1h).
	$franja_ini = $fecha . ' ' . $hora . ':00';
	$franja_fin = date( 'Y-m-d H:i:s', strtotime( $franja_ini ) + HOUR_IN_SECONDS );
	foreach ( $reservas as $r ) {
		if ( $r['start'] < $franja_fin && $r['end'] > $franja_ini ) {
			return false;
		}
	}
	return true;
}
