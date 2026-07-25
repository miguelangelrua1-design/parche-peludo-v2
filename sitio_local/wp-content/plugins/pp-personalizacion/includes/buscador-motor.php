<?php
/**
 * MÓDULO BUSCADOR — Motor
 * -----------------------------------------------------------------------------
 * Decide QUÉ se encuentra y en qué ORDEN:
 *   1. Sinónimos      → se enchufan a la normalización del tema hijo.
 *   2. Ranking        → filtro posts_clauses (título > descripción, stock, foto).
 *   3. Redirecciones  → términos que llevan directo a una página.
 *   4. ¿Quisiste decir? → corrección de tipeo con índice del propio catálogo.
 *   5. Categorías     → sugerencias de categoría en el desplegable.
 *
 * @package pp-personalizacion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * 1. SINÓNIMOS
 *
 * No competimos por el hook 'posts_search' (lo usa la normalización del tema
 * hijo, que llegó antes): nos enganchamos a SU punto de extensión, que expande
 * cada palabra en variantes. El SQL resultante queda:
 *   (palabra OR sinónimo1 OR sinónimo2) AND (siguiente palabra …)
 * ====================================================================== */

/**
 * Grupos de sinónimos, ya normalizados y en minúsculas.
 * Formato guardado: una línea por grupo, términos separados por coma.
 *
 * @return array<int,array<int,string>>
 */
function pp_buscador_grupos_sinonimos() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$raw    = (string) get_option( 'pp_buscador_sinonimos_texto', pp_buscador_sinonimos_semilla() );
	$grupos = array();

	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $linea ) {
		$linea = trim( $linea );
		if ( '' === $linea || 0 === strpos( $linea, '#' ) ) {
			continue; // permite comentarios con #
		}
		$terminos = array();
		foreach ( explode( ',', $linea ) as $t ) {
			$t = trim( mb_strtolower( $t, 'UTF-8' ) );
			if ( '' !== $t ) {
				$terminos[] = $t;
			}
		}
		if ( count( $terminos ) > 1 ) {
			$grupos[] = array_values( array_unique( $terminos ) );
		}
	}

	$cache = $grupos;
	return $cache;
}

/**
 * Diccionario inicial del dominio mascotas. Miguel lo edita desde el panel;
 * esto es solo el punto de partida para que el módulo sirva desde el minuto uno.
 */
function pp_buscador_sinonimos_semilla() {
	return implode(
		"\n",
		array(
			'# Un grupo por línea. Los términos de una línea se consideran equivalentes.',
			'# Las líneas que empiezan con # son comentarios.',
			'comida, alimento, concentrado, croqueta, pienso',
			'champu, shampoo, shampu',
			'guarderia, cuidador, hotel canino, hospedaje',
			'paseo, paseador, paseos',
			'veterinario, veterinaria, vet, medico',
			'peluqueria, grooming, estetica, baño',
			'arena, arenero, piedras sanitarias',
			'correa, traila, tralla',
			'cama, colchoneta, cucha',
			'juguete, mordedor, pelota',
			'snack, premio, golosina, galleta',
			'antipulgas, pulgas, garrapatas, desparasitante',
			'adiestramiento, entrenamiento, entrenador',
			'gato, felino, minino',
			'perro, canino, can',
			'cachorro, puppy, bebe',
			'senior, adulto mayor, viejito',
			'esterilizado, castrado, sterilised',
			'transportadora, guacal, canil',
		)
	);
}

/**
 * Clave de comparación de un término del diccionario.
 *
 * Se le aplica la MISMA normalización que al término del usuario (incluida la
 * singularización), de modo que la comparación pueda ser EXACTA.
 *
 * Por qué exacta y no por prefijo: con prefijos, la palabra "canim" (un tipeo
 * de "canin") activaba el grupo "perro, canino, can" porque "can" es prefijo
 * suyo, y una búsqueda errónea devolvía 84 resultados en vez de cero. Detectado
 * al probar el "¿Quisiste decir…?" el 2026-07-25.
 *
 * @param string $termino Término del diccionario.
 * @return string
 */
function pp_buscador_clave_sinonimo( $termino ) {
	$clave = pp_buscador_normalizar( $termino );
	return mb_strtolower( trim( (string) $clave ), 'UTF-8' );
}

/**
 * Índice palabra normalizada => posición del grupo, para buscar en O(1).
 *
 * @return array{grupos:array,mapa:array<string,int>}
 */
function pp_buscador_indice_sinonimos() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$grupos = pp_buscador_grupos_sinonimos();
	$mapa   = array();

	foreach ( $grupos as $i => $grupo ) {
		foreach ( $grupo as $termino ) {
			// Las entradas de varias palabras ("hotel canino") siguen sirviendo
			// como VARIANTE a buscar, pero no como clave de coincidencia: el
			// filtro recibe palabras sueltas.
			if ( false !== strpos( trim( $termino ), ' ' ) ) {
				continue;
			}
			// Se indexa por la clave normalizada Y por el término tal cual: si
			// mañana cambian las reglas de normalización, el diccionario sigue
			// respondiendo a lo que Miguel escribió literalmente en el panel.
			foreach ( array( pp_buscador_clave_sinonimo( $termino ), mb_strtolower( trim( $termino ), 'UTF-8' ) ) as $clave ) {
				if ( '' !== $clave && ! isset( $mapa[ $clave ] ) ) {
					$mapa[ $clave ] = $i;
				}
			}
		}
	}

	$cache = array(
		'grupos' => $grupos,
		'mapa'   => $mapa,
	);
	return $cache;
}

add_filter( 'ppv2_search_variantes_palabra', 'pp_buscador_variantes_palabra', 10, 2 );
/**
 * Expande una palabra a sus sinónimos.
 *
 * @param array  $variantes Variantes actuales (llega con la palabra original).
 * @param string $palabra   Palabra ya normalizada por el tema hijo.
 * @return array
 */
function pp_buscador_variantes_palabra( $variantes, $palabra ) {
	if ( ! pp_buscador_activo( 'pp_buscador_sinonimos' ) ) {
		return $variantes;
	}

	$clave = mb_strtolower( trim( (string) $palabra ), 'UTF-8' );
	if ( '' === $clave ) {
		return $variantes;
	}

	$indice = pp_buscador_indice_sinonimos();
	if ( ! isset( $indice['mapa'][ $clave ] ) ) {
		return $variantes;
	}

	$variantes = array_merge( $variantes, $indice['grupos'][ $indice['mapa'][ $clave ] ] );

	return array_values( array_unique( array_filter( $variantes ) ) );
}

/* =========================================================================
 * 2. RANKING DE RELEVANCIA
 *
 * Ordena: coincidencia exacta de título > empieza por > contiene > solo en la
 * descripción. Desempates en productos: con stock antes que agotado, y con foto
 * antes que sin foto (el catálogo de Dropi trae muchas fichas incompletas).
 * Respeta SIEMPRE el orden que el usuario elija en el desplegable de la tienda.
 * ====================================================================== */

add_filter( 'posts_clauses', 'pp_buscador_ranking_clauses', 20, 2 );
function pp_buscador_ranking_clauses( $clauses, $query ) {
	if ( ! pp_buscador_activo( 'pp_buscador_ranking' ) ) {
		return $clauses;
	}
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $clauses;
	}

	$termino = $query->get( 's' );
	if ( empty( $termino ) || ! is_string( $termino ) ) {
		return $clauses;
	}

	// El usuario eligió un orden explícito (precio, novedad…): manda él.
	if ( isset( $_GET['orderby'] ) && '' !== $_GET['orderby'] ) {
		return $clauses;
	}

	$post_type = $query->get( 'post_type' );
	$tipos     = empty( $post_type ) ? array() : (array) $post_type;
	$es_producto = in_array( 'product', $tipos, true );
	$es_listado  = in_array( 'listing', $tipos, true );
	if ( ! $es_producto && ! $es_listado && ! empty( $tipos ) ) {
		return $clauses;
	}

	$norm = pp_buscador_normalizar( $termino );
	if ( '' === $norm ) {
		return $clauses;
	}

	global $wpdb;

	// Mismo saneado de título que usa la normalización, para comparar peras con
	// peras (si el tema no la aporta, se compara contra el título crudo).
	$titulo = function_exists( 'ppv2_sql_titulo_normalizado' )
		? ppv2_sql_titulo_normalizado()
		: "{$wpdb->posts}.post_title";

	$exacto  = $wpdb->prepare( '%s', $norm );
	$empieza = $wpdb->prepare( '%s', $wpdb->esc_like( $norm ) . '%' );
	$contiene = $wpdb->prepare( '%s', '%' . $wpdb->esc_like( $norm ) . '%' );

	$relevancia = "CASE
		WHEN LOWER({$titulo}) = LOWER({$exacto}) THEN 0
		WHEN LOWER({$titulo}) LIKE LOWER({$empieza}) THEN 1
		WHEN LOWER({$titulo}) LIKE LOWER({$contiene}) THEN 2
		ELSE 3 END";

	$orden = array( $relevancia . ' ASC' );

	// Desempates de producto: stock y foto. Se unen por LEFT JOIN para no
	// excluir nunca filas (un producto sin fila en el lookup sigue apareciendo).
	if ( $es_producto || empty( $tipos ) ) {
		$lookup = $wpdb->prefix . 'wc_product_meta_lookup';
		if ( false === strpos( $clauses['join'], 'pp_bsq_stock' ) ) {
			$clauses['join'] .= " LEFT JOIN {$lookup} AS pp_bsq_stock ON pp_bsq_stock.product_id = {$wpdb->posts}.ID";
		}
		$orden[] = "(pp_bsq_stock.stock_status = 'instock') DESC";
	}

	if ( false === strpos( $clauses['join'], 'pp_bsq_foto' ) ) {
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS pp_bsq_foto ON ( pp_bsq_foto.post_id = {$wpdb->posts}.ID AND pp_bsq_foto.meta_key = '_thumbnail_id' )";
	}
	$orden[] = '(pp_bsq_foto.post_id IS NOT NULL) DESC';

	// Nuestro criterio va PRIMERO; lo que ya venía queda como desempate final
	// (así se respeta el orden natural de WooCommerce/Listeo dentro de cada grupo).
	$previo = trim( (string) $clauses['orderby'] );
	$clauses['orderby'] = implode( ', ', $orden ) . ( '' !== $previo ? ', ' . $previo : '' );

	return $clauses;
}

/* =========================================================================
 * 3. REDIRECCIONES DE BÚSQUEDA
 * Formato guardado: una por línea, "término | URL".
 * ====================================================================== */

/** @return array<string,string> término normalizado => URL */
function pp_buscador_reglas_redireccion() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$raw    = (string) get_option( 'pp_buscador_redirecciones_texto', '' );
	$reglas = array();

	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $linea ) {
		$linea = trim( $linea );
		if ( '' === $linea || 0 === strpos( $linea, '#' ) || false === strpos( $linea, '|' ) ) {
			continue;
		}
		list( $termino, $url ) = array_map( 'trim', explode( '|', $linea, 2 ) );
		$termino = pp_buscador_normalizar( $termino );
		if ( '' !== $termino && '' !== $url ) {
			$reglas[ $termino ] = $url;
		}
	}

	$cache = $reglas;
	return $cache;
}

add_action( 'template_redirect', 'pp_buscador_aplicar_redireccion' );
function pp_buscador_aplicar_redireccion() {
	if ( ! pp_buscador_activo( 'pp_buscador_redirecciones' ) || ! is_search() ) {
		return;
	}
	$reglas = pp_buscador_reglas_redireccion();
	if ( empty( $reglas ) ) {
		return;
	}
	$norm = pp_buscador_normalizar( get_search_query() );
	if ( '' !== $norm && isset( $reglas[ $norm ] ) ) {
		wp_safe_redirect( $reglas[ $norm ], 302 );
		exit;
	}
}

/* =========================================================================
 * 4. "¿QUISISTE DECIR…?"
 *
 * Índice de palabras construido del propio catálogo (se regenera solo con el
 * cron diario y cuando se guardan productos/listados). No hay nada que
 * mantener a mano: si mañana entran 500 productos de una marca nueva, sus
 * palabras entran al índice automáticamente.
 * ====================================================================== */

/** Reconstruye el índice de palabras del catálogo. */
function pp_buscador_reconstruir_indice_palabras() {
	global $wpdb;

	$titulos = $wpdb->get_col(
		"SELECT post_title FROM {$wpdb->posts}
		 WHERE post_status = 'publish' AND post_type IN ('product','listing')"
	);

	$indice = array();
	foreach ( (array) $titulos as $titulo ) {
		$limpio = pp_buscador_aplanar( $titulo );
		foreach ( preg_split( '/[^a-z0-9]+/', $limpio, -1, PREG_SPLIT_NO_EMPTY ) as $palabra ) {
			if ( strlen( $palabra ) < 4 || is_numeric( $palabra ) ) {
				continue;
			}
			$indice[ $palabra ] = isset( $indice[ $palabra ] ) ? $indice[ $palabra ] + 1 : 1;
		}
	}

	arsort( $indice );
	// Tope defensivo: con el catálogo actual son ~1.500 palabras.
	$indice = array_slice( $indice, 0, 5000, true );

	update_option( 'pp_buscador_indice_palabras', $indice, false );
	update_option( 'pp_buscador_indice_fecha', current_time( 'mysql' ), false );

	return count( $indice );
}

/** Minúsculas + sin tildes + sin puntuación: base para comparar tipeos. */
function pp_buscador_aplanar( $texto ) {
	$texto = html_entity_decode( (string) $texto, ENT_QUOTES, 'UTF-8' );
	$texto = remove_accents( $texto );
	return strtolower( $texto );
}

// Marca el índice como "sucio" al guardar catálogo; se reconstruye en el
// siguiente cron (evita rehacerlo 500 veces durante una importación de Dropi).
add_action( 'save_post_product', 'pp_buscador_marcar_indice_sucio' );
add_action( 'save_post_listing', 'pp_buscador_marcar_indice_sucio' );
function pp_buscador_marcar_indice_sucio( $post_id ) {
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}
	update_option( 'pp_buscador_indice_sucio', 1, false );
}

/**
 * Propone una corrección para un término sin resultados.
 *
 * @param string $termino Lo buscado.
 * @param string $ambito  tienda | directorio | … (define a dónde enlaza).
 * @return array{sugerencia:string,url:string}|false
 */
function pp_buscador_quisiste_decir( $termino, $ambito = '' ) {
	if ( ! pp_buscador_activo( 'pp_buscador_quisiste_decir' ) ) {
		return false;
	}

	$termino = trim( (string) $termino );
	if ( '' === $termino ) {
		return false;
	}

	$indice = get_option( 'pp_buscador_indice_palabras', array() );
	if ( empty( $indice ) || ! is_array( $indice ) ) {
		pp_buscador_reconstruir_indice_palabras();
		$indice = get_option( 'pp_buscador_indice_palabras', array() );
		if ( empty( $indice ) ) {
			return false;
		}
	}

	$palabras  = preg_split( '/[^a-z0-9]+/', pp_buscador_aplanar( $termino ), -1, PREG_SPLIT_NO_EMPTY );
	$corregido = array();
	$hubo_cambio = false;

	foreach ( $palabras as $palabra ) {
		if ( strlen( $palabra ) < 4 || isset( $indice[ $palabra ] ) || is_numeric( $palabra ) ) {
			$corregido[] = $palabra;
			continue;
		}
		$mejor = pp_buscador_palabra_mas_cercana( $palabra, $indice );
		if ( $mejor ) {
			$corregido[]  = $mejor;
			$hubo_cambio = true;
		} else {
			$corregido[] = $palabra;
		}
	}

	if ( ! $hubo_cambio ) {
		return false;
	}

	$sugerencia = implode( ' ', $corregido );

	return array(
		'sugerencia' => $sugerencia,
		'url'        => pp_buscador_url_busqueda( $sugerencia, $ambito ),
	);
}

/**
 * Palabra del índice más parecida (distancia de edición), o false.
 * Reglas: misma inicial, longitud similar y distancia máxima proporcional.
 */
function pp_buscador_palabra_mas_cercana( $palabra, $indice ) {
	$len       = strlen( $palabra );
	$max_dist  = $len <= 5 ? 1 : 2;
	$inicial   = $palabra[0];
	$mejor     = false;
	$mejor_dist = PHP_INT_MAX;
	$mejor_freq = 0;

	foreach ( $indice as $candidata => $freq ) {
		if ( abs( strlen( $candidata ) - $len ) > $max_dist ) {
			continue;
		}
		// Filtro barato antes del cálculo costoso: misma letra inicial.
		if ( $candidata[0] !== $inicial ) {
			continue;
		}
		$dist = levenshtein( $palabra, $candidata );
		if ( $dist > $max_dist ) {
			continue;
		}
		// Ante misma distancia, gana la palabra más frecuente del catálogo.
		if ( $dist < $mejor_dist || ( $dist === $mejor_dist && $freq > $mejor_freq ) ) {
			$mejor      = $candidata;
			$mejor_dist = $dist;
			$mejor_freq = $freq;
		}
	}

	return $mejor;
}

/** URL de búsqueda para un término, según el ámbito. */
function pp_buscador_url_busqueda( $termino, $ambito = '' ) {
	if ( 'tienda' === $ambito ) {
		return home_url( '/?s=' . rawurlencode( $termino ) . '&post_type=product' );
	}
	$base = get_post_type_archive_link( 'listing' );
	if ( ! $base ) {
		return home_url( '/?s=' . rawurlencode( $termino ) );
	}
	$url = add_query_arg( 'keyword_search', rawurlencode( $termino ), $base );
	if ( $ambito && 'directorio' !== $ambito ) {
		$url = add_query_arg( '_listing_type', $ambito, $url );
	}
	return $url;
}

/* =========================================================================
 * 5. CATEGORÍAS EN LAS SUGERENCIAS
 * Escribir "ali" ofrece saltar a la categoría "Alimento" completa, con sus
 * filtros, en vez de ver solo 5 productos sueltos.
 * ====================================================================== */

/**
 * Categorías que coinciden con el término.
 *
 * @param string $termino Término.
 * @param int    $limite  Máximo de categorías.
 * @return array<int,array{label:string,link:string,count:int}>
 */
function pp_buscador_sugerir_categorias( $termino, $limite = 3 ) {
	if ( ! pp_buscador_activo( 'pp_buscador_categorias' ) ) {
		return array();
	}
	$termino = trim( (string) $termino );
	if ( mb_strlen( $termino, 'UTF-8' ) < 3 ) {
		return array();
	}

	$taxonomias = array( 'product_cat' );
	foreach ( get_object_taxonomies( 'listing' ) as $tax ) {
		if ( false !== strpos( $tax, 'category' ) ) {
			$taxonomias[] = $tax;
		}
	}

	$terminos = get_terms(
		array(
			'taxonomy'   => $taxonomias,
			'name__like' => $termino,
			'hide_empty' => true,
			'number'     => (int) $limite,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);

	if ( is_wp_error( $terminos ) || empty( $terminos ) ) {
		return array();
	}

	$salida = array();
	foreach ( $terminos as $t ) {
		$link = get_term_link( $t );
		if ( is_wp_error( $link ) ) {
			continue;
		}
		$salida[] = array(
			'label' => $t->name,
			'link'  => $link,
			'count' => (int) $t->count,
		);
	}

	return $salida;
}
