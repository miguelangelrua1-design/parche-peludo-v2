<?php
/**
 * Conteo de resultados del listado — Parche Peludo V2 (override del hijo).
 *
 * Muestra dos versiones: la LARGA de WooCommerce ("Mostrando 1–8 de 21 resultados")
 * para escritorio y una CORTA ("21 resultados") para móvil, donde el conteo va en
 * la misma fila que "Filtros" y "Ordenar". El CSS decide cuál se ve (style.css).
 *
 * Basado en woocommerce/loop/result-count.php v3.7.0.
 *
 * @package listeo-child
 */

defined( 'ABSPATH' ) || exit;
?>
<p class="woocommerce-result-count">
	<span class="pp-count-long">
	<?php
	// Texto en español directo (no dependemos de la traducción de WooCommerce,
	// que en esta instalación aparece en inglés).
	if ( 1 === intval( $total ) ) {
		esc_html_e( 'Mostrando el único resultado', 'listeo-child' );
	} elseif ( $total <= $per_page || -1 === $per_page ) {
		/* translators: %d: total de resultados */
		printf( esc_html( _n( 'Mostrando %d resultado', 'Mostrando los %d resultados', $total, 'listeo-child' ) ), (int) $total );
	} else {
		$first = ( $per_page * $current ) - $per_page + 1;
		$last  = min( $total, $per_page * $current );
		/* translators: 1: primer resultado 2: último resultado 3: total de resultados */
		printf( wp_kses_post( _nx( 'Mostrando %1$d&ndash;%2$d de %3$d resultado', 'Mostrando %1$d&ndash;%2$d de %3$d resultados', $total, 'con primer y último resultado', 'listeo-child' ) ), (int) $first, (int) $last, (int) $total );
	}
	?>
	</span>
	<span class="pp-count-short">
		<?php
		/* translators: %d: total de resultados */
		printf( esc_html( _n( '%d resultado', '%d resultados', $total, 'listeo-child' ) ), (int) $total );
		?>
	</span>
</p>
