<?php
/**
 * MÓDULO BUSCADOR — Panel de administración
 * -----------------------------------------------------------------------------
 * wp-admin → Personalización Parche → Buscador
 * Pestañas: Registro · Sinónimos · Redirecciones · Ajustes
 *
 * @package pp-personalizacion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'pp_buscador_menu', 31 );
function pp_buscador_menu() {
	add_submenu_page(
		'pp-personalizacion',
		'Buscador',
		'Buscador',
		'manage_options',
		'pp-buscador',
		'pp_buscador_admin_page'
	);
}

/* -------------------------------------------------------------------------
 * Guardado
 * ---------------------------------------------------------------------- */

add_action( 'admin_init', 'pp_buscador_admin_handler' );
function pp_buscador_admin_handler() {
	if ( empty( $_POST['pp_buscador_action'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'pp_buscador_admin', 'pp_buscador_nonce' );

	$accion = sanitize_key( wp_unslash( $_POST['pp_buscador_action'] ) );
	$tab    = 'registro';

	switch ( $accion ) {
		case 'sinonimos':
			$texto = isset( $_POST['pp_buscador_sinonimos_texto'] )
				? sanitize_textarea_field( wp_unslash( $_POST['pp_buscador_sinonimos_texto'] ) )
				: '';
			update_option( 'pp_buscador_sinonimos_texto', $texto );
			pp_buscador_bump_dict_version(); // invalida cachés de sugerencias
			$tab = 'sinonimos';
			break;

		case 'redirecciones':
			$texto = isset( $_POST['pp_buscador_redirecciones_texto'] )
				? sanitize_textarea_field( wp_unslash( $_POST['pp_buscador_redirecciones_texto'] ) )
				: '';
			update_option( 'pp_buscador_redirecciones_texto', $texto );
			pp_buscador_bump_dict_version();
			$tab = 'redirecciones';
			break;

		case 'ajustes':
			foreach ( array_keys( pp_buscador_ajustes_disponibles() ) as $clave ) {
				update_option( $clave, empty( $_POST[ $clave ] ) ? '0' : '1' );
			}
			pp_buscador_bump_dict_version();
			$tab = 'ajustes';
			break;

		case 'reconstruir_indice':
			$n   = pp_buscador_reconstruir_indice_palabras();
			$tab = 'ajustes';
			wp_safe_redirect( admin_url( 'admin.php?page=pp-buscador&tab=ajustes&pp_ok=indice&n=' . (int) $n ) );
			exit;

		case 'vaciar_registro':
			global $wpdb;
			$wpdb->query( 'TRUNCATE TABLE ' . pp_buscador_tabla() );
			delete_transient( 'pp_buscador_populares' );
			$tab = 'registro';
			break;
	}

	wp_safe_redirect( admin_url( 'admin.php?page=pp-buscador&tab=' . $tab . '&pp_ok=guardado' ) );
	exit;
}

/* -------------------------------------------------------------------------
 * Exportar CSV
 * ---------------------------------------------------------------------- */

add_action( 'admin_init', 'pp_buscador_exportar_csv' );
function pp_buscador_exportar_csv() {
	if ( empty( $_GET['pp_buscador_export'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'pp_buscador_export' );

	$filas = pp_buscador_top_terminos( 90, 500, false );

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=busquedas-parche-peludo.csv' );
	$out = fopen( 'php://output', 'w' );
	fputs( $out, "\xEF\xBB\xBF" ); // BOM para que Excel respete las tildes
	fputcsv( $out, array( 'Término', 'Veces', 'Resultados', 'Última búsqueda' ) );
	foreach ( $filas as $f ) {
		fputcsv( $out, array( $f->termino, $f->veces, $f->resultados, $f->ultima ) );
	}
	fclose( $out );
	exit;
}

/* -------------------------------------------------------------------------
 * Página
 * ---------------------------------------------------------------------- */

function pp_buscador_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$tab  = sanitize_key( $_GET['tab'] ?? 'registro' );
	$tabs = array(
		'registro'      => '📊 Registro',
		'sinonimos'     => '🔁 Sinónimos',
		'redirecciones' => '↪️ Redirecciones',
		'ajustes'       => '⚙️ Ajustes',
	);
	if ( ! isset( $tabs[ $tab ] ) ) {
		$tab = 'registro';
	}
	?>
	<div class="wrap pp-buscador-wrap">
		<h1>🔎 Buscador</h1>
		<p style="max-width:820px">Motor de búsqueda de Parche Peludo. Aquí ves <strong>qué busca la gente</strong>,
		defines <strong>sinónimos</strong> para que encuentren aunque usen otras palabras, creas
		<strong>redirecciones</strong> y enciendes o apagas cada función.</p>

		<?php if ( ! pp_buscador_activo() ) : ?>
			<div class="notice notice-warning"><p><strong>El módulo está apagado.</strong> Actívalo en la pestaña Ajustes.</p></div>
		<?php endif; ?>

		<?php if ( 'guardado' === ( $_GET['pp_ok'] ?? '' ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Cambios guardados.</p></div>
		<?php elseif ( 'indice' === ( $_GET['pp_ok'] ?? '' ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Índice reconstruido: <strong><?php echo (int) ( $_GET['n'] ?? 0 ); ?></strong> palabras del catálogo.</p></div>
		<?php endif; ?>

		<h2 class="nav-tab-wrapper">
			<?php foreach ( $tabs as $slug => $label ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pp-buscador&tab=' . $slug ) ); ?>"
				   class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</h2>

		<div style="margin-top:18px">
		<?php
		switch ( $tab ) {
			case 'sinonimos':
				pp_buscador_tab_sinonimos();
				break;
			case 'redirecciones':
				pp_buscador_tab_redirecciones();
				break;
			case 'ajustes':
				pp_buscador_tab_ajustes();
				break;
			default:
				pp_buscador_tab_registro();
		}
		?>
		</div>
	</div>
	<style>
		.pp-bsq-cards { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:22px; }
		.pp-bsq-card { background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:16px 20px; min-width:170px; }
		.pp-bsq-card .n { font-size:28px; font-weight:700; line-height:1.1; color:#1e5d64; }
		.pp-bsq-card .l { font-size:12px; text-transform:uppercase; letter-spacing:.05em; color:#666; margin-top:4px; }
		.pp-bsq-card.alerta .n { color:#b32d2e; }
		.pp-bsq-cols { display:flex; gap:22px; flex-wrap:wrap; align-items:flex-start; }
		.pp-bsq-cols > div { flex:1 1 380px; }
		.pp-bsq-wide { max-width:900px; }
		.pp-bsq-wide textarea { width:100%; font-family:Menlo,Consolas,monospace; font-size:13px; line-height:1.6; }
		.pp-bsq-cero { color:#b32d2e; font-weight:600; }
	</style>
	<?php
}

/* ---------------------------------------------------------------- Registro */

function pp_buscador_tab_registro() {
	$m       = pp_buscador_metricas( 30 );
	$top     = pp_buscador_top_terminos( 30, 20, false );
	$sin_res = pp_buscador_top_terminos( 30, 20, true );
	?>
	<div class="pp-bsq-cards">
		<div class="pp-bsq-card"><div class="n"><?php echo esc_html( number_format_i18n( $m['total'] ) ); ?></div><div class="l">Búsquedas (30 días)</div></div>
		<div class="pp-bsq-card"><div class="n"><?php echo esc_html( number_format_i18n( $m['unicos'] ) ); ?></div><div class="l">Términos distintos</div></div>
		<div class="pp-bsq-card <?php echo $m['porcentaje'] > 20 ? 'alerta' : ''; ?>">
			<div class="n"><?php echo esc_html( $m['porcentaje'] ); ?>%</div><div class="l">Sin resultados</div>
		</div>
		<div class="pp-bsq-card"><div class="n"><?php echo esc_html( number_format_i18n( $m['sin_resultados'] ) ); ?></div><div class="l">Búsquedas fallidas</div></div>
	</div>

	<p>
		<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=pp-buscador&pp_buscador_export=1' ), 'pp_buscador_export' ) ); ?>">⬇️ Exportar CSV (90 días)</a>
		<?php if ( $m['total'] > 0 ) : ?>
			<form method="post" style="display:inline-block;margin-left:8px" onsubmit="return confirm('¿Vaciar todo el registro de búsquedas? Esta acción no se puede deshacer.');">
				<?php wp_nonce_field( 'pp_buscador_admin', 'pp_buscador_nonce' ); ?>
				<input type="hidden" name="pp_buscador_action" value="vaciar_registro">
				<button class="button button-link-delete">Vaciar registro</button>
			</form>
		<?php endif; ?>
	</p>

	<?php if ( 0 === $m['total'] ) : ?>
		<div class="notice notice-info"><p>Todavía no hay búsquedas registradas. Haz algunas búsquedas en el sitio y vuelve aquí.</p></div>
	<?php endif; ?>

	<div class="pp-bsq-cols">
		<div>
			<h3>🔥 Más buscadas</h3>
			<table class="widefat striped">
				<thead><tr><th>Término</th><th style="width:70px">Veces</th><th style="width:90px">Resultados</th></tr></thead>
				<tbody>
				<?php if ( empty( $top ) ) : ?>
					<tr><td colspan="3"><em>Sin datos aún.</em></td></tr>
				<?php else : foreach ( $top as $f ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $f->termino ); ?></strong></td>
						<td><?php echo (int) $f->veces; ?></td>
						<td class="<?php echo 0 === (int) $f->resultados ? 'pp-bsq-cero' : ''; ?>"><?php echo (int) $f->resultados; ?></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>

		<div>
			<h3>⚠️ Sin resultados <span style="font-weight:400;font-size:13px;color:#666">— tu lista de trabajo</span></h3>
			<table class="widefat striped">
				<thead><tr><th>Término</th><th style="width:70px">Veces</th><th style="width:110px">Acción</th></tr></thead>
				<tbody>
				<?php if ( empty( $sin_res ) ) : ?>
					<tr><td colspan="3"><em>¡Ninguna búsqueda falló! 🎉</em></td></tr>
				<?php else : foreach ( $sin_res as $f ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $f->termino ); ?></strong></td>
						<td><?php echo (int) $f->veces; ?></td>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=pp-buscador&tab=sinonimos' ) ); ?>">Crear sinónimo</a></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
			<?php if ( ! empty( $sin_res ) ) : ?>
				<p style="color:#666;font-size:13px">Estas personas no encontraron lo que buscaban. Si el producto <em>sí</em> existe con otro nombre, agrégalo como sinónimo. Si no existe, es una pista de qué sumar al catálogo.</p>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/* --------------------------------------------------------------- Sinónimos */

function pp_buscador_tab_sinonimos() {
	$texto  = (string) get_option( 'pp_buscador_sinonimos_texto', pp_buscador_sinonimos_semilla() );
	$grupos = pp_buscador_grupos_sinonimos();
	?>
	<div class="pp-bsq-wide">
		<p>Cada línea es un <strong>grupo de palabras equivalentes</strong>, separadas por comas. Si alguien busca
		cualquiera de ellas, encontrará los resultados de todas. Ejemplo: con
		<code>comida, alimento, concentrado</code>, quien busque <em>“comida para perro”</em> encontrará los productos
		llamados <em>“Alimento…”</em>.</p>
		<p style="color:#666">Escribe en minúsculas y sin tildes cuando puedas. Las líneas que empiezan con <code>#</code> son notas y se ignoran.
		Actualmente hay <strong><?php echo count( $grupos ); ?></strong> grupos activos.</p>

		<form method="post">
			<?php wp_nonce_field( 'pp_buscador_admin', 'pp_buscador_nonce' ); ?>
			<input type="hidden" name="pp_buscador_action" value="sinonimos">
			<textarea name="pp_buscador_sinonimos_texto" rows="20" spellcheck="false"><?php echo esc_textarea( $texto ); ?></textarea>
			<p><button class="button button-primary">Guardar sinónimos</button></p>
		</form>
	</div>
	<?php
}

/* ----------------------------------------------------------- Redirecciones */

function pp_buscador_tab_redirecciones() {
	$texto  = (string) get_option( 'pp_buscador_redirecciones_texto', '' );
	$reglas = pp_buscador_reglas_redireccion();
	?>
	<div class="pp-bsq-wide">
		<p>Términos que llevan <strong>directo a una página</strong> en vez de mostrar resultados. Una por línea, con el
		formato <code>término | URL</code>.</p>
		<p style="color:#666">Ejemplos:<br>
			<code>adoptar | /adopcion/</code><br>
			<code>domicilio | /cobertura/</code>
		</p>
		<p style="color:#666">Actualmente hay <strong><?php echo count( $reglas ); ?></strong> redirecciones activas.</p>

		<form method="post">
			<?php wp_nonce_field( 'pp_buscador_admin', 'pp_buscador_nonce' ); ?>
			<input type="hidden" name="pp_buscador_action" value="redirecciones">
			<textarea name="pp_buscador_redirecciones_texto" rows="12" spellcheck="false"><?php echo esc_textarea( $texto ); ?></textarea>
			<p><button class="button button-primary">Guardar redirecciones</button></p>
		</form>
	</div>
	<?php
}

/* ------------------------------------------------------------------ Ajustes */

function pp_buscador_tab_ajustes() {
	$ajustes = pp_buscador_ajustes_disponibles();
	$indice  = get_option( 'pp_buscador_indice_palabras', array() );
	$fecha   = get_option( 'pp_buscador_indice_fecha', '' );
	?>
	<div class="pp-bsq-wide">
		<form method="post">
			<?php wp_nonce_field( 'pp_buscador_admin', 'pp_buscador_nonce' ); ?>
			<input type="hidden" name="pp_buscador_action" value="ajustes">

			<table class="widefat striped" style="max-width:640px">
				<tbody>
				<?php foreach ( $ajustes as $clave => $etiqueta ) :
					$on = '1' === (string) get_option( $clave, '1' ); ?>
					<tr>
						<td><?php echo esc_html( $etiqueta ); ?>
							<?php if ( 'pp_buscador_activo' === $clave ) : ?>
								<br><span style="color:#666;font-size:12px">Al apagarlo, el buscador vuelve exactamente a como estaba antes de este módulo.</span>
							<?php endif; ?>
						</td>
						<td style="width:80px;text-align:center">
							<label class="pp-switch">
								<input type="checkbox" name="<?php echo esc_attr( $clave ); ?>" value="1" <?php checked( $on ); ?>>
								<span class="pp-switch__track"><span class="pp-switch__thumb"></span></span>
							</label>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button class="button button-primary">Guardar ajustes</button></p>
		</form>

		<hr style="margin:28px 0">

		<h3>Índice de palabras del catálogo</h3>
		<p style="color:#666">Alimenta la sugerencia <em>“¿Quisiste decir…?”</em>. Se reconstruye solo cada día y cuando
		guardas productos o publicaciones; este botón lo fuerza ahora.</p>
		<p>
			Palabras indexadas: <strong><?php echo esc_html( number_format_i18n( count( (array) $indice ) ) ); ?></strong>
			<?php if ( $fecha ) : ?>
				· última actualización: <?php echo esc_html( $fecha ); ?>
			<?php endif; ?>
		</p>
		<form method="post">
			<?php wp_nonce_field( 'pp_buscador_admin', 'pp_buscador_nonce' ); ?>
			<input type="hidden" name="pp_buscador_action" value="reconstruir_indice">
			<button class="button">Reconstruir índice ahora</button>
		</form>
	</div>
	<style>
		.pp-switch { display:inline-block; cursor:pointer; }
		.pp-switch input { display:none; }
		.pp-switch__track { width:42px; height:24px; border-radius:999px; background:#c3c4c7; position:relative; display:block; transition:background .15s ease; }
		.pp-switch__thumb { position:absolute; top:3px; left:3px; width:18px; height:18px; border-radius:50%; background:#fff; transition:transform .15s ease; }
		.pp-switch input:checked + .pp-switch__track { background:#79C8D0; }
		.pp-switch input:checked + .pp-switch__track .pp-switch__thumb { transform:translateX(18px); }
	</style>
	<?php
}
