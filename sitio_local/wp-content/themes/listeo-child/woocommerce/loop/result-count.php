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
	if ( 1 === intval( $total ) ) {
		esc_html_e( 'Showing the single result', 'woocommerce' );
	} elseif ( $total <= $per_page || -1 === $per_page ) {
		printf( esc_html( _n( 'Showing all %d result', 'Showing all %d results', $total, 'woocommerce' ) ), (int) $total );
	} else {
		$first = ( $per_page * $current ) - $per_page + 1;
		$last  = min( $total, $per_page * $current );
		/* translators: 1: first result 2: last result 3: total results */
		printf( wp_kses_post( _nx( 'Showing %1$d&ndash;%2$d of %3$d result', 'Showing %1$d&ndash;%2$d of %3$d results', $total, 'with first and last result', 'woocommerce' ) ), (int) $first, (int) $last, (int) $total );
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
