<?php
/**
 * OVERRIDE del tema hijo — plantilla "sin resultados" del directorio.
 * (Listeo Core carga listeo-child/listeo-core/archive/no-found.php antes que
 * la suya propia, vía Gamajo Template Loader → a prueba de actualizaciones.)
 *
 * Diseño según mock de Miguel (2026-07-18): icono lupa-x en badge redondeado,
 * título "Sin resultados", texto con término y sección en negrita, y el PUENTE
 * a la Tienda como tarjeta teal (badge "EN LA TIENDA", conteo real en negrita,
 * botón blanco pill con flecha, huellas decorativas). Sin mezclar resultados.
 * Se usa tanto en la carga normal como en las respuestas AJAX de los filtros.
 * Estilos: style.css, bloques "ppv2-nores" y "ppv2-cross-search".
 */

$ppv2_term = '';
if ( isset( $_REQUEST['keyword_search'] ) && class_exists( 'Listeo_Core_Search' ) ) {
	$ppv2_term = Listeo_Core_Search::sanitize_keyword_search( wp_unslash( $_REQUEST['keyword_search'] ) );
}
$ppv2_count = ( $ppv2_term && function_exists( 'ppv2_cross_search_count' ) )
	? ppv2_cross_search_count( $ppv2_term, 'product' )
	: 0;

// Sección activa: Directorio por defecto; con un tipo separado activo
// (módulo Listados de pp-personalizacion: chip "+", ruta /adopcion/, etc.)
// se usa su nombre visible (Adopción / Mascotas perdidas).
$ppv2_seccion = 'Directorio';
if ( function_exists( 'pp_listados_contexto_tipo' ) && function_exists( 'pp_listados_tipos_activos' ) ) {
	$ppv2_tipo = pp_listados_contexto_tipo();
	if ( $ppv2_tipo ) {
		foreach ( pp_listados_tipos_activos() as $ppv2_t ) {
			if ( $ppv2_t->slug === $ppv2_tipo && ! empty( $ppv2_t->name ) ) {
				$ppv2_seccion = $ppv2_t->name;
				break;
			}
		}
	}
}
// Frase con artículo natural: "el Directorio" / "Adopción" / "Mascotas perdidas".
$ppv2_seccion_frase = ( 'Directorio' === $ppv2_seccion ) ? 'el Directorio' : $ppv2_seccion;
?>
<div id="listeo-listings-container">
							<div class="loader-ajax-container" style=""> <div class="loader-ajax"></div> </div>
<section id="listings-not-found" class="margin-bottom-50 col-md-12 ppv2-nores">
	<span class="ppv2-nores-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/><path d="m8.8 8.8 4.4 4.4"/><path d="m13.2 8.8-4.4 4.4"/></svg></span>
	<h2>Sin resultados</h2>
	<?php if ( $ppv2_term ) : ?>
		<p class="ppv2-nores-text">No encontramos resultados para <strong>&ldquo;<?php echo esc_html( $ppv2_term ); ?>&rdquo;</strong> en <strong><?php echo esc_html( $ppv2_seccion_frase ); ?></strong>.</p>
	<?php else : ?>
		<p class="ppv2-nores-text">No encontramos resultados que coincidan con tu búsqueda en <strong><?php echo esc_html( $ppv2_seccion_frase ); ?></strong>.</p>
	<?php endif; ?>
	<?php if ( $ppv2_count > 0 ) : ?>
	<div class="ppv2-cross-search">
		<span class="ppv2-cross-paws" aria-hidden="true">🐾</span>
		<span class="ppv2-cross-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12l1.2 13H4.8L6 7z"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/></svg>En la Tienda</span>
		<h3 class="ppv2-cross-title">&ldquo;<?php echo esc_html( $ppv2_term ); ?>&rdquo; sí está en Tienda</h3>
		<p class="ppv2-cross-text">Encontramos <strong><?php echo esc_html( number_format_i18n( $ppv2_count ) ); ?> <?php echo esc_html( 1 === $ppv2_count ? 'producto' : 'productos' ); ?></strong> que <?php echo esc_html( 1 === $ppv2_count ? 'coincide' : 'coinciden' ); ?> con tu búsqueda.</p>
		<a class="ppv2-cross-search-btn" href="<?php echo esc_url( home_url( '/?s=' . rawurlencode( $ppv2_term ) . '&post_type=product' ) ); ?>">Ver en la Tienda <span class="ppv2-cross-arrow" aria-hidden="true">→</span></a>
	</div>
	<?php endif; ?>
</section>
</div>
