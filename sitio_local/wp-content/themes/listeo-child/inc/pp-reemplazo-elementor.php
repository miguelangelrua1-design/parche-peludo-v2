<?php
/**
 * HERRAMIENTA TEMPORAL — Buscar y reemplazar texto dentro de páginas de Elementor
 * =============================================================================
 *
 * POR QUÉ HACE FALTA
 * Las páginas construidas con Elementor NO guardan su texto en el contenido de
 * WordPress, sino en un JSON dentro del post meta `_elementor_data`. Por eso
 * editar la página por la API REST o por el editor clásico no cambia nada de lo
 * que se ve: Elementor sigue pintando desde su propio almacén.
 *
 * Cambiar un párrafo obliga entonces a tocar ese JSON. Hacerlo a mano es
 * arriesgado (un carácter de más deja la página en blanco), así que esta
 * herramienta lo hace con tres salvaguardas:
 *
 *   1. VISTA PREVIA. Primero muestra dónde aparece el texto y con qué contexto,
 *      sin modificar nada. Así se confirma que se va a tocar lo que se cree.
 *   2. VALIDACIÓN. Tras el reemplazo comprueba que el resultado siga siendo
 *      JSON válido. Si no lo es, ABORTA y no guarda: es imposible dejar la
 *      página rota por un error de escapado.
 *   3. RESPALDO. Antes de guardar copia el valor anterior en el meta
 *      `_pp_elementor_backup`, de modo que se puede revertir.
 *
 * Además limpia la caché de CSS de Elementor, que si no seguiría sirviendo la
 * versión anterior.
 *
 * ⚠️ ES TEMPORAL. Borrar el archivo y su require en functions.php al terminar.
 *
 * @package listeo-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_management_page(
			'Reemplazo en Elementor',
			'Reemplazo en Elementor',
			'manage_options',
			'pp-reemplazo-elementor',
			'pp_reemplazo_elementor_pantalla'
		);
	}
);

/**
 * Limpia la caché de CSS de Elementor para que el cambio se vea de inmediato.
 */
function pp_reemplazo_elementor_limpiar_cache() {
	if ( class_exists( '\Elementor\Plugin' ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
}

function pp_reemplazo_elementor_pantalla() {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permisos.' );
	}

	$post_id  = isset( $_POST['pp_post_id'] ) ? absint( $_POST['pp_post_id'] ) : 1067;
	$buscar   = isset( $_POST['pp_buscar'] ) ? wp_unslash( $_POST['pp_buscar'] ) : '';
	$sustituir = isset( $_POST['pp_sustituir'] ) ? wp_unslash( $_POST['pp_sustituir'] ) : '';
	$mensajes = array();

	$datos = get_post_meta( $post_id, '_elementor_data', true );
	if ( is_array( $datos ) ) {
		$datos = wp_json_encode( $datos );
	}

	// ---- Vista previa -------------------------------------------------------
	if ( isset( $_POST['pp_previa'] ) && check_admin_referer( 'pp_reemplazo_elementor' ) && $buscar ) {
		$n = substr_count( $datos, $buscar );
		if ( 0 === $n ) {
			$mensajes[] = array( 'error', 'No se encontró el texto. Ojo: dentro del JSON las comillas van escapadas (\") y algunas etiquetas HTML también. Busca un fragmento corto y sin comillas.' );
		} else {
			$pos     = strpos( $datos, $buscar );
			$ctx     = substr( $datos, max( 0, $pos - 220 ), strlen( $buscar ) + 440 );
			$mensajes[] = array( 'info', sprintf( 'Se encontraron %d coincidencia(s). Contexto:', $n ) );
			$mensajes[] = array( 'code', $ctx );
		}
	}

	// ---- Aplicar ------------------------------------------------------------
	if ( isset( $_POST['pp_aplicar'] ) && check_admin_referer( 'pp_reemplazo_elementor' ) && $buscar ) {

		$n = substr_count( $datos, $buscar );

		if ( 0 === $n ) {
			$mensajes[] = array( 'error', 'No se encontró el texto: no se hizo ningún cambio.' );
		} else {
			$nuevo = str_replace( $buscar, $sustituir, $datos );

			// Salvaguarda: el resultado tiene que seguir siendo JSON válido.
			json_decode( $nuevo, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$mensajes[] = array( 'error', 'El resultado NO es JSON válido (' . json_last_error_msg() . '). Se abortó sin guardar: la página queda intacta.' );
			} else {
				// Respaldo antes de tocar nada.
				update_post_meta( $post_id, '_pp_elementor_backup', wp_slash( $datos ) );

				// Elementor guarda el JSON con slashes; hay que respetarlo o se
				// corrompen las comillas al pasar por update_post_meta().
				update_post_meta( $post_id, '_elementor_data', wp_slash( $nuevo ) );

				pp_reemplazo_elementor_limpiar_cache();

				$mensajes[] = array( 'ok', sprintf( '%d reemplazo(s) aplicados y guardados. Copia de respaldo creada. Caché de Elementor limpiada.', $n ) );
			}
		}
	}

	// ---- Revertir -----------------------------------------------------------
	if ( isset( $_POST['pp_revertir'] ) && check_admin_referer( 'pp_reemplazo_elementor' ) ) {
		$backup = get_post_meta( $post_id, '_pp_elementor_backup', true );
		if ( $backup ) {
			update_post_meta( $post_id, '_elementor_data', wp_slash( $backup ) );
			pp_reemplazo_elementor_limpiar_cache();
			$mensajes[] = array( 'ok', 'Restaurada la versión anterior desde el respaldo.' );
		} else {
			$mensajes[] = array( 'error', 'No hay respaldo para esta página.' );
		}
	}
	?>
	<div class="wrap">
		<h1>Reemplazo en Elementor</h1>
		<p>Cambia texto dentro del contenido de una página hecha con Elementor. Usa primero
		<strong>Ver coincidencias</strong>: no modifica nada y te muestra el contexto exacto.</p>

		<?php foreach ( $mensajes as $m ) :
			if ( 'code' === $m[0] ) : ?>
				<pre style="background:#1d2327;color:#c3c4c7;padding:12px;overflow:auto;max-width:900px;font-size:12px;"><?php echo esc_html( $m[1] ); ?></pre>
			<?php else :
				$clase = ( 'ok' === $m[0] ) ? 'notice-success' : ( ( 'error' === $m[0] ) ? 'notice-error' : 'notice-info' ); ?>
				<div class="notice <?php echo esc_attr( $clase ); ?>"><p><?php echo esc_html( $m[1] ); ?></p></div>
			<?php endif;
		endforeach; ?>

		<form method="post">
			<?php wp_nonce_field( 'pp_reemplazo_elementor' ); ?>
			<table class="form-table">
				<tr>
					<th>ID de la página</th>
					<td><input type="number" name="pp_post_id" value="<?php echo esc_attr( $post_id ); ?>">
					<p class="description">1067 = Nosotros. Tiene datos de Elementor:
					<?php echo $datos ? '<strong style="color:#0a0">sí (' . number_format( strlen( $datos ) ) . ' caracteres)</strong>' : '<strong style="color:#a00">no</strong>'; ?></p></td>
				</tr>
				<tr>
					<th>Buscar</th>
					<td><textarea name="pp_buscar" rows="4" cols="90" style="font-family:monospace;font-size:12px;"><?php echo esc_textarea( $buscar ); ?></textarea></td>
				</tr>
				<tr>
					<th>Reemplazar por</th>
					<td><textarea name="pp_sustituir" rows="4" cols="90" style="font-family:monospace;font-size:12px;"><?php echo esc_textarea( $sustituir ); ?></textarea></td>
				</tr>
			</table>
			<p>
				<button type="submit" name="pp_previa" class="button">Ver coincidencias</button>
				<button type="submit" name="pp_aplicar" class="button button-primary">Aplicar reemplazo</button>
				<button type="submit" name="pp_revertir" class="button" style="float:right;">Revertir al respaldo</button>
			</p>
		</form>
	</div>
	<?php
}
