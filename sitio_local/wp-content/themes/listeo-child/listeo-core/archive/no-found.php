<?php
/**
 * OVERRIDE del tema hijo — plantilla "sin resultados" del directorio.
 * (Listeo Core carga listeo-child/listeo-core/archive/no-found.php antes que
 * la suya propia, vía Gamajo Template Loader → a prueba de actualizaciones.)
 *
 * Mantiene la estructura y los textos originales (los renombres via filtro
 * gettext del tema hijo siguen aplicando) y añade el PUENTE a la Tienda:
 * si la palabra buscada SÍ tiene productos, se ofrece el enlace con el conteo
 * real — sin mezclar resultados. Esta plantilla se usa tanto en la carga
 * normal como dentro de las respuestas AJAX de los filtros, así que el puente
 * funciona también al refinar filtros.
 */

$ppv2_term = '';
if ( isset( $_REQUEST['keyword_search'] ) && class_exists( 'Listeo_Core_Search' ) ) {
	$ppv2_term = Listeo_Core_Search::sanitize_keyword_search( wp_unslash( $_REQUEST['keyword_search'] ) );
}
$ppv2_count = ( $ppv2_term && function_exists( 'ppv2_cross_search_count' ) )
	? ppv2_cross_search_count( $ppv2_term, 'product' )
	: 0;
?>
<div id="listeo-listings-container">
							<div class="loader-ajax-container" style=""> <div class="loader-ajax"></div> </div>
<section id="listings-not-found" class="margin-bottom-50 col-md-12">
	<h2><?php esc_html_e('Nothing found','listeo_core'); ?></h2>
	<p><?php _e( 'We&rsquo;re sorry but we do not have any listings matching your search, try to change you search settings', 'listeo_core' ); ?></p>
	<?php if ( $ppv2_count > 0 ) : ?>
	<div class="ppv2-cross-search">
		<span class="ppv2-cross-search-icon" aria-hidden="true">🐾</span>
		<div class="ppv2-cross-search-text">
			<strong>&ldquo;<?php echo esc_html( $ppv2_term ); ?>&rdquo; sí está en la Tienda</strong>
			<span><?php echo esc_html( 1 === $ppv2_count
				? 'Encontramos 1 producto que coincide con tu búsqueda.'
				: sprintf( 'Encontramos %s productos que coinciden con tu búsqueda.', number_format_i18n( $ppv2_count ) ) ); ?></span>
		</div>
		<a class="ppv2-cross-search-btn" href="<?php echo esc_url( home_url( '/?s=' . rawurlencode( $ppv2_term ) . '&post_type=product' ) ); ?>">Ver en la Tienda</a>
	</div>
	<?php endif; ?>
</section>
</div>
