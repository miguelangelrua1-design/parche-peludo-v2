<?php
/**
 * PPV2 — Normalización del Buscador (Tienda + Directorio + Sugerencias)
 * -----------------------------------------------------------------------------
 * Aplica una capa de normalización transparente en las búsquedas (WP_Query)
 * para ignorar diferencias de puntuación, apóstrofos, entidades HTML (&amp;),
 * separadores decimales (2,5 kg vs 2.5 kg), espaciado de unidades (3kg vs 3 kg),
 * variantes de unidades (kilos/kgs/kg, gramos/gr/g) y plurales en español.
 *
 * Kill-switch: define( 'PP_SEARCH_NORM_OFF', true );
 * Filtro: add_filter( 'ppv2_search_norm_enabled', '__return_false' );
 *
 * @package listeo-child
 * @version 1.0.1 — fix diagnóstico 2026-07-25: alcance título+contenido+extracto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normaliza un término de búsqueda en PHP.
 *
 * @param string $term Término ingresado por el usuario.
 * @return string Término normalizado.
 */
function ppv2_normalizar_termino_busqueda( $term ) {
	$term = (string) $term;
	if ( '' === trim( $term ) ) {
		return '';
	}

	// 1. Decodificar entidades HTML y remover ampersands / conjunciones
	$term = html_entity_decode( $term, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$term = str_replace( array( '&amp;', '&AMP;', '&' ), '', $term );

	// 2. Equivalencia de conjunciones ' y ' / ' and ' (remover para equiparar Small & Mini -> Small Mini)
	$term = preg_replace( '/\b(y|and)\b/iu', '', $term );

	// 3. Normalizar apóstrofos rectos y curvos (eliminar para equiparar Hill's -> Hills)
	$term = str_replace( array( "'", "’", "‘", "`", "&#039;", "&rsquo;" ), '', $term );

	// 4. Normalizar decimales: reemplazar coma por punto entre dígitos (ej. 2,5 -> 2.5)
	$term = preg_replace( '/(\d+),(\d+)/', '$1.$2', $term );

	// 5. Normalizar espacio entre número y unidad (ej. 3kg -> 3 kg, 500g -> 500 g)
	$term = preg_replace( '/(\d+(?:\.\d+)?)\s*(kgs?|kilos?|gramos?|gr|g|lbs?|lb|ml|l)\b/iu', '$1 $2', $term );

	// 6. Normalizar nombres de unidades
	$term = preg_replace( '/\b(kilos|kgs)\b/iu', 'kg', $term );
	$term = preg_replace( '/\b(gramos|gr)\b/iu', 'g', $term );

	// 7. Puntuación y caracteres especiales no alfanuméricos a espacios (conservando . para decimales)
	$term = str_replace( array( '-', '(', ')', '/', '+', ',', ':', ';', '!', '?' ), ' ', $term );

	// Limpiar puntos aislados al final de palabras (ej. Dr. -> Dr) sin alterar decimales (2.5)
	$term = preg_replace( '/(?<=\s|^|\p{L})\.+(?=\s|$|\p{L})/u', '', $term );

	// 8. Normalizar espacios múltiples
	$term = preg_replace( '/\s+/', ' ', trim( $term ) );

	// 9. Morfología básica del español (singularización de términos)
	$palabras = explode( ' ', $term );
	$palabras_norm = array();

	foreach ( $palabras as $p ) {
		$len = mb_strlen( $p, 'UTF-8' );
		if ( $len > 3 ) {
			// Plural en -es: SOLO se recortan las dos letras cuando la consonante
			// previa es de las que sí cierran palabra en español (l, r, n, d, s,
			// z, j, x): "mordedores"→"mordedor", "flores"→"flor", "papeles"→"papel".
			// FIX 2026-07-25: la regla anterior aceptaba cualquier consonante y
			// convertía "juguetes" en "juguet" (el singular es "juguete"), lo que
			// impedía que casara con el diccionario de sinónimos. Las palabras como
			// "juguetes" caen ahora en la regla de vocal + s, que recorta una sola.
			if ( preg_match( '/[lrndszjx]es$/iu', $p ) ) {
				$p = mb_substr( $p, 0, $len - 2, 'UTF-8' );
			}
			// Palabras que terminan en -s antecedidas por vocal (ej. juguetes -> juguete, perros -> perro)
			elseif ( preg_match( '/[aeiouáéíóú]s$/iu', $p ) ) {
				$p = mb_substr( $p, 0, $len - 1, 'UTF-8' );
			}
		}
		$palabras_norm[] = $p;
	}

	return implode( ' ', array_filter( $palabras_norm ) );
}

/**
 * Expresión SQL que normaliza una columna de la base de datos: reemplaza
 * entidades HTML (&amp;), ampersands (&), apóstrofos (' y ’), guiones y
 * paréntesis. Se conserva el punto decimal para no romper '2.5 kg'.
 *
 * Pública para que el módulo Buscador (pp-personalizacion) compare el título
 * con el MISMO criterio al calcular el ranking de relevancia.
 *
 * @param string $col Columna SQL ya calificada (p. ej. wp_posts.post_title).
 * @return string
 */
function ppv2_sql_columna_normalizada( $col ) {
	return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$col}, '&amp;', ''), '&', ''), '’', ''), '\'', ''), '-', ' '), '(', ''), ')', '')";
}

/**
 * Título normalizado, listo para usar en ORDER BY (lo usa el ranking).
 *
 * @return string
 */
function ppv2_sql_titulo_normalizado() {
	global $wpdb;
	return ppv2_sql_columna_normalizada( "{$wpdb->posts}.post_title" );
}

/**
 * Filtro sobre `posts_search` de WP_Query para aplicar la normalización en SQL.
 *
 * @param string   $search SQL generado por WordPress para la cláusula WHERE.
 * @param WP_Query $query Objeto WP_Query evaluado.
 * @return string SQL modificado con reemplazos en post_title.
 */
function ppv2_normalizar_posts_search( $search, $query ) {
	// Verificar si la normalización está desactivada
	if ( defined( 'PP_SEARCH_NORM_OFF' ) && PP_SEARCH_NORM_OFF ) {
		return $search;
	}
	if ( ! apply_filters( 'ppv2_search_norm_enabled', true ) ) {
		return $search;
	}

	// No modificar búsquedas del admin de WordPress (salvo peticiones AJAX del frontend)
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $search;
	}

	// Obtener el término de búsqueda original
	$raw_s = $query->get( 's' );
	if ( empty( $raw_s ) || ! is_string( $raw_s ) ) {
		return $search;
	}

	$raw_s = trim( $raw_s );
	if ( '' === $raw_s ) {
		return $search;
	}

	// Restringir a post_types de interés (product, listing o búsquedas del frontend)
	$post_type = $query->get( 'post_type' );
	$es_tipo_valido = false;

	if ( empty( $post_type ) || 'any' === $post_type ) {
		$es_tipo_valido = true;
	} else {
		$tipos = (array) $post_type;
		if ( array_intersect( array( 'product', 'listing', 'post' ), $tipos ) ) {
			$es_tipo_valido = true;
		}
	}

	if ( ! $es_tipo_valido ) {
		return $search;
	}

	// Normalizar el término de búsqueda en PHP
	$norm_s = ppv2_normalizar_termino_busqueda( $raw_s );
	if ( '' === $norm_s ) {
		return $search;
	}

	global $wpdb;

	$sql_title   = ppv2_sql_columna_normalizada( "{$wpdb->posts}.post_title" );
	// FIX 2026-07-25 (diagnóstico): la primera versión buscaba SOLO en el
	// título y recortó el alcance de la búsqueda nativa (título + contenido +
	// extracto): "omega" pasó de ~155 coincidencias a 3. Se restaura el
	// alcance completo, con la MISMA normalización en las tres columnas para
	// que "hills" también encuentre menciones de "Hill's" en descripciones.
	// Costo: REPLACE sobre longtext sin índice — igual que el LIKE '%..%'
	// nativo, asumible con el catálogo actual (~1.000 ítems); si el catálogo
	// crece a decenas de miles, migrar a columna normalizada indexada
	// (previsto en PLAN-EVOLUCION-BUSCADOR.md, Fase 4).
	$sql_content = ppv2_sql_columna_normalizada( "{$wpdb->posts}.post_content" );
	$sql_excerpt = ppv2_sql_columna_normalizada( "{$wpdb->posts}.post_excerpt" );

	// Dividir el término normalizado en palabras clave
	$words = explode( ' ', $norm_s );
	$where_clauses = array();

	foreach ( $words as $word ) {
		$word = trim( $word );
		if ( '' === $word ) {
			continue;
		}

		// PUNTO DE EXTENSIÓN: el módulo Buscador de pp-personalizacion añade
		// aquí los SINÓNIMOS de la palabra. Así el diccionario se enchufa sin
		// competir por el hook posts_search (dos filtros que reemplazan la
		// cláusula chocarían). Sin el plugin, la lista queda con la palabra
		// original y todo funciona igual que antes.
		$variantes = apply_filters( 'ppv2_search_variantes_palabra', array( $word ), $word );
		$variantes = array_values( array_unique( array_filter( (array) $variantes ) ) );

		// Cada variante puede aparecer en título, contenido o extracto; el
		// grupo entero va en OR y los grupos (palabras) se unen con AND.
		$ors = array();
		foreach ( $variantes as $variante ) {
			$like  = '%' . $wpdb->esc_like( $variante ) . '%';
			$ors[] = $wpdb->prepare(
				"{$sql_title} LIKE %s OR {$sql_content} LIKE %s OR {$sql_excerpt} LIKE %s",
				$like,
				$like,
				$like
			);
		}
		if ( $ors ) {
			$where_clauses[] = '(' . implode( ' OR ', $ors ) . ')';
		}
	}

	if ( empty( $where_clauses ) ) {
		return $search;
	}

	// Reemplazar la cláusula search de WP con nuestra consulta normalizada
	$new_search = ' AND (' . implode( ' AND ', $where_clauses ) . ') ';

	return $new_search;
}
add_filter( 'posts_search', 'ppv2_normalizar_posts_search', 10, 2 );

/**
 * Purgado automático de transitorios del buscador al actualizar la versión del módulo.
 */
function ppv2_purgar_transitorios_busqueda() {
	$version_actual = '1.0.1'; // bump del fix: purga cachés (incl. el [] cacheado de "hills")
	$version_guardada = get_option( 'ppv2_search_norm_version' );

	if ( $version_guardada !== $version_actual ) {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ppv2_suggest_%' OR option_name LIKE '_transient_timeout_ppv2_suggest_%' OR option_name LIKE '_transient_ppv2_xcount_%' OR option_name LIKE '_transient_timeout_ppv2_xcount_%'" );
		update_option( 'ppv2_search_norm_version', $version_actual );
	}
}
add_action( 'init', 'ppv2_purgar_transitorios_busqueda' );
