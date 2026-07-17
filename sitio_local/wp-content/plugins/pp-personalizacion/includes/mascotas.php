<?php
/**
 * MÃ³dulo MASCOTAS del plugin PersonalizaciÃ³n Parche.
 * (Se carga desde pp-personalizacion.php; la versiÃ³n vive allÃ­.)
 * Description: Sección "Mis Mascotas" del dashboard de usuario: cada persona registra sus perros y gatos (foto, nombre, especie, sexo, nacimiento, raza, peso, galería y descripción). Incluye vista de administración en wp-admin.
 *
 *
 *
 *
 * Migrado desde el tema hijo listeo-child el 2026-07-03. Requiere el tema
 * Listeo + plugin listeo-core (usa su dashboard, hooks y plantillas).
 *
 * Diseño de referencia: Diseño/Stitch/mis_mascotas_*.html y
 * registrar_nueva_mascota_*.html (marca: teal #79C8D0 / ink #1E2E33).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Constantes PP_PERS_DIR / PP_PERS_URL definidas en pp-personalizacion.php


/* -------------------------------------------------------------------------
 * 0. Rutas de plantillas de listeo-core
 *
 * Dos objetivos:
 *  (a) Servir desde este plugin la copia de account/logged_section.php
 *      (menú "Mi Cuenta" del header) con el ítem "Mis Mascotas".
 *  (b) Restaurar la prioridad estándar de WordPress: Listeo Booking Plus
 *      altera 'listeo_core_template_paths' y deja la carpeta listeo-core/
 *      del tema hijo DE ÚLTIMA; aquí el tema hijo vuelve a ganar siempre
 *      (clave -100), seguido de este plugin (clave -90).
 * ---------------------------------------------------------------------- */

add_filter( 'listeo_core_template_paths', 'pp_mascotas_template_paths', 999 );
function pp_mascotas_template_paths( $paths ) {
	$paths = (array) $paths;
	if ( is_child_theme() ) {
		$paths[-100] = trailingslashit( get_stylesheet_directory() ) . 'listeo-core/';
	}
	$paths[-90] = PP_PERS_DIR . 'templates/';
	return $paths;
}

/* -------------------------------------------------------------------------
 * 1. Tipo de contenido "Mascota" (privado, uno por post, dueño = autor)
 * ---------------------------------------------------------------------- */

add_action( 'init', 'pp_mascotas_register_cpt' );
function pp_mascotas_register_cpt() {
	register_post_type( 'pp_mascota', array(
		'labels' => array(
			'name'          => 'Mascotas',
			'singular_name' => 'Mascota',
			'menu_name'     => 'Mascotas',
			'all_items'     => 'Mascotas', // etiqueta del submenÃº en PersonalizaciÃ³n Parche
			'edit_item'     => 'Editar mascota',
		),
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => true, // visible en wp-admin para administración
		'show_in_menu'        => 'pp-personalizacion', // submenÃº de PersonalizaciÃ³n Parche
		// (el icono lo pone el menÃº principal PersonalizaciÃ³n Parche)
		'supports'            => array( 'title', 'thumbnail', 'author' ),
		'has_archive'         => false,
		'rewrite'             => false,
	) );
}

/* -------------------------------------------------------------------------
 * 2. Página "Mis Mascotas" del dashboard (se crea sola si no existe)
 * ---------------------------------------------------------------------- */

// FIX PERF 2026-07-10: antes corría en 'init' (CADA petición del sitio,
// incluidos AJAX y cron) y su get_post_status() era una consulta por request
// solo para comprobar que la página sigue publicada. Ahora corre solo en el
// admin: si la página se borra por accidente, se recrea en la siguiente
// visita a wp-admin (auto-reparación con costo cero en el frontend).
add_action( 'admin_init', 'pp_mascotas_ensure_page', 20 );
function pp_mascotas_ensure_page() {
	$page_id = (int) get_option( 'pp_mascotas_page_id' );
	if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
		return;
	}

	$existente = get_page_by_path( 'mis-mascotas' );
	if ( $existente instanceof WP_Post ) {
		update_option( 'pp_mascotas_page_id', $existente->ID );
		return;
	}

	$page_id = wp_insert_post( array(
		'post_title'   => 'Mis Mascotas',
		'post_name'    => 'mis-mascotas',
		'post_content' => '[pp_mis_mascotas]',
		'post_status'  => 'publish',
		'post_type'    => 'page',
	) );

	if ( $page_id && ! is_wp_error( $page_id ) ) {
		update_post_meta( $page_id, '_wp_page_template', 'template-dashboard.php' );
		update_option( 'pp_mascotas_page_id', $page_id );
	}
}

function pp_mascotas_page_url() {
	$page_id = (int) get_option( 'pp_mascotas_page_id' );
	return $page_id ? get_permalink( $page_id ) : home_url( '/' );
}

/* -------------------------------------------------------------------------
 * 3. Ítem "Mis Mascotas" en el menú lateral del dashboard de Listeo
 * ---------------------------------------------------------------------- */

add_action( 'listeo/dashboard-menu/start', 'pp_mascotas_dashboard_menu' );
function pp_mascotas_dashboard_menu() {
	$page_id = (int) get_option( 'pp_mascotas_page_id' );
	if ( ! $page_id ) {
		return;
	}
	$active = ( get_queried_object_id() === $page_id ) ? ' class="active"' : '';
	echo '<ul data-submenu-title="Mascotas">';
	echo '<li' . $active . '><a href="' . esc_url( get_permalink( $page_id ) ) . '"><i class="fa fa-paw"></i> Mis Mascotas</a></li>';
	echo '</ul>';
}

/* -------------------------------------------------------------------------
 * 4. Razas por especie — administrables en wp-admin → Mascotas → Razas
 *
 * Se guardan en la opción 'pp_mascotas_razas' (sin "Otro"). Si la opción
 * aún no existe, se usan las listas iniciales definidas por Miguel en
 * razas_populares_colombia.xlsx (2026-07-03). Siempre se devuelven en
 * orden alfabético (ignorando tildes) y con "Otro" fija al final.
 * ---------------------------------------------------------------------- */

function pp_mascotas_razas_default() {
	return array(
		'perro' => array(
			'Criollo / Mestizo', 'Labrador Retriever', 'Bulldog Francés',
			'Pastor Alemán', 'Golden Retriever', 'Poodle (Caniche)', 'Shih Tzu',
			'Pug (Carlino)', 'Yorkshire Terrier', 'Chihuahua', 'Pitbull',
			'Pomerania', 'Schnauzer Miniatura', 'Beagle', 'Bulldog Inglés',
			'Cocker Spaniel', 'Dachshund (Salchicha)', 'Border Collie',
			'Rottweiler', 'Husky Siberiano',
		),
		'gato' => array(
			'Criollo / Mestizo', 'Siamés', 'Persa', 'Bengalí', 'Maine Coon',
			'Británico de Pelo Corto', 'Ragdoll', 'Himalayo', 'Scottish Fold',
			'Sphynx (Esfinge)',
		),
	);
}

/** Minúsculas, sin tildes ni espacios sobrantes: para comparar y ordenar. */
function pp_mascotas_normalizar( $s ) {
	return strtolower( trim( strtr( $s, array(
		'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
		'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'ñ' => 'n', 'Ñ' => 'n',
	) ) ) );
}

/** Listas editables (sin "Otro"), ya ordenadas alfabéticamente. */
function pp_mascotas_get_razas_editables() {
	$guardadas = get_option( 'pp_mascotas_razas' );
	$defaults  = pp_mascotas_razas_default();
	$listas    = array();
	foreach ( array( 'perro', 'gato' ) as $especie ) {
		$lista = ( is_array( $guardadas ) && isset( $guardadas[ $especie ] ) && is_array( $guardadas[ $especie ] ) )
			? $guardadas[ $especie ]
			: $defaults[ $especie ];
		// "Otro" nunca forma parte de la lista editable: se agrega al final al leer.
		$lista = array_values( array_filter( array_map( 'trim', array_map( 'strval', $lista ) ), function ( $r ) {
			return '' !== $r && 'otro' !== pp_mascotas_normalizar( $r );
		} ) );
		usort( $lista, function ( $a, $b ) {
			return strcmp( pp_mascotas_normalizar( $a ), pp_mascotas_normalizar( $b ) );
		} );
		$listas[ $especie ] = $lista;
	}
	return $listas;
}

function pp_mascotas_get_razas( $especie = '' ) {
	$listas = pp_mascotas_get_razas_editables();
	$listas['perro'][] = 'Otro';
	$listas['gato'][]  = 'Otro';
	if ( isset( $listas[ $especie ] ) ) {
		return $listas[ $especie ];
	}
	return $listas;
}

/* -------------------------------------------------------------------------
 * 5. CSS/JS solo en la página Mis Mascotas
 * ---------------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', 'pp_mascotas_assets', 100 );
function pp_mascotas_assets() {
	$page_id = (int) get_option( 'pp_mascotas_page_id' );
	if ( ! $page_id || ! is_page( $page_id ) ) {
		return;
	}

	$css = PP_PERS_DIR . 'css/pp-mascotas.css';
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'pp-mascotas', PP_PERS_URL . 'css/pp-mascotas.css', array(), filemtime( $css ) );
	}

	$js = PP_PERS_DIR . 'js/pp-mascotas.js';
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'pp-mascotas', PP_PERS_URL . 'js/pp-mascotas.js', array(), filemtime( $js ), true );
		wp_localize_script( 'pp-mascotas', 'PP_MASCOTAS', array(
			'razas'      => pp_mascotas_get_razas(),
			'maxGaleria' => 10,
		) );
	}
}

/* -------------------------------------------------------------------------
 * 6. Shortcode [pp_mis_mascotas]: listado o formulario según la URL
 * ---------------------------------------------------------------------- */

add_shortcode( 'pp_mis_mascotas', 'pp_mascotas_shortcode' );
function pp_mascotas_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '<p>Debes <a href="' . esc_url( wp_login_url( pp_mascotas_page_url() ) ) . '">iniciar sesión</a> para ver tus mascotas.</p>';
	}

	$vista = isset( $_GET['pp_vista'] ) ? sanitize_key( $_GET['pp_vista'] ) : 'lista';

	ob_start();
	echo '<div class="pp-mascotas">';
	// Los errores de validación (transient) solo tienen sentido en el
	// formulario (el guardado fallido SIEMPRE redirige ahí). En la vista de
	// lista no se muestran y se descarta el residuo para que no reaparezca.
	$es_form = in_array( $vista, array( 'nueva', 'editar' ), true );
	pp_mascotas_render_avisos( $es_form );
	if ( ! $es_form ) {
		delete_transient( 'pp_mascota_form_' . get_current_user_id() );
	}

	if ( 'nueva' === $vista ) {
		pp_mascotas_render_form( 0 );
	} elseif ( 'editar' === $vista ) {
		$mascota_id = isset( $_GET['mascota'] ) ? absint( $_GET['mascota'] ) : 0;
		if ( pp_mascotas_es_del_usuario( $mascota_id ) ) {
			pp_mascotas_render_form( $mascota_id );
		} else {
			echo '<p>Esa mascota no existe o no te pertenece.</p>';
			pp_mascotas_render_lista();
		}
	} else {
		pp_mascotas_render_lista();
	}

	echo '</div>';
	return ob_get_clean();
}

/** ¿La mascota existe y pertenece al usuario actual? */
function pp_mascotas_es_del_usuario( $mascota_id ) {
	if ( ! $mascota_id ) {
		return false;
	}
	$post = get_post( $mascota_id );
	return $post
		&& 'pp_mascota' === $post->post_type
		&& 'publish' === $post->post_status
		&& (int) $post->post_author === get_current_user_id();
}

/** Avisos de éxito (?pp_ok=) y errores de validación (transient) */
function pp_mascotas_render_avisos( $mostrar_errores = true ) {
	$ok = isset( $_GET['pp_ok'] ) ? sanitize_key( $_GET['pp_ok'] ) : '';
	$mensajes = array(
		'creada'      => '¡Listo! Tu mascota fue registrada con éxito. 🐾',
		'actualizada' => 'Los datos de tu mascota fueron actualizados.',
		'eliminada'   => 'La mascota fue eliminada de tu lista.',
	);
	if ( $ok && isset( $mensajes[ $ok ] ) ) {
		echo '<div class="pp-masc-aviso pp-masc-aviso--ok">' . esc_html( $mensajes[ $ok ] ) . '</div>';
	}

	if ( ! $mostrar_errores ) {
		return;
	}
	$datos = get_transient( 'pp_mascota_form_' . get_current_user_id() );
	if ( ! empty( $datos['errores'] ) ) {
		echo '<div class="pp-masc-aviso pp-masc-aviso--error"><strong>Revisa estos campos:</strong><ul>';
		foreach ( $datos['errores'] as $error ) {
			echo '<li>' . esc_html( $error ) . '</li>';
		}
		echo '</ul></div>';
	}
}

/** Edad legible a partir de la fecha de nacimiento (Y-m-d) */
function pp_mascotas_edad_legible( $fecha ) {
	$nacimiento = date_create( $fecha );
	if ( ! $nacimiento ) {
		return '';
	}
	$diff = date_diff( $nacimiento, date_create( 'now' ) );
	if ( $diff->invert ) {
		return '';
	}
	if ( $diff->y >= 1 ) {
		return $diff->y . ( 1 === $diff->y ? ' año' : ' años' );
	}
	if ( $diff->m >= 1 ) {
		return $diff->m . ( 1 === $diff->m ? ' mes' : ' meses' );
	}
	return 'Menos de 1 mes';
}

/* ----------------------------- Vista: listado --------------------------- */

function pp_mascotas_render_lista() {
	$url_nueva = add_query_arg( 'pp_vista', 'nueva', pp_mascotas_page_url() );

	$mascotas = get_posts( array(
		'post_type'   => 'pp_mascota',
		'author'      => get_current_user_id(),
		'post_status' => 'publish',
		'numberposts' => -1,
		'orderby'     => 'date',
		'order'       => 'ASC',
	) );
	?>
	<header class="pp-masc-header">
		<div>
			<h2 class="pp-masc-titulo">Mi familia peluda</h2>
			<p class="pp-masc-sub">Comparte la información de tus peludos, de esta forma podremos brindarles el mejor servicio.</p>
		</div>
		<a class="pp-btn pp-btn--primario" href="<?php echo esc_url( $url_nueva ); ?>">
			<i class="fa fa-plus-circle"></i> Añadir nueva mascota
		</a>
	</header>

	<div class="pp-masc-grid">
		<?php foreach ( $mascotas as $mascota ) :
			$foto    = get_the_post_thumbnail_url( $mascota->ID, 'medium_large' );
			$especie = get_post_meta( $mascota->ID, '_pp_especie', true );
			$raza    = get_post_meta( $mascota->ID, '_pp_raza', true );
			$edad    = pp_mascotas_edad_legible( get_post_meta( $mascota->ID, '_pp_fecha_nacimiento', true ) );
			$url_ver = add_query_arg( array( 'pp_vista' => 'editar', 'mascota' => $mascota->ID ), pp_mascotas_page_url() );
			?>
			<article class="pp-masc-card">
				<div class="pp-masc-card__foto">
					<?php if ( $foto ) : ?>
						<img src="<?php echo esc_url( $foto ); ?>" alt="<?php echo esc_attr( $mascota->post_title ); ?>">
					<?php else : ?>
						<span class="pp-masc-card__sinfoto"><i class="fa fa-paw"></i></span>
					<?php endif; ?>
					<span class="pp-masc-card__badge"><i class="fa fa-paw"></i> <?php echo esc_html( 'gato' === $especie ? 'Gato' : 'Perro' ); ?></span>
				</div>
				<div class="pp-masc-card__cuerpo">
					<h3 class="pp-masc-card__nombre"><?php echo esc_html( $mascota->post_title ); ?></h3>
					<p class="pp-masc-card__detalle">
						<?php echo esc_html( $raza ); ?><?php echo $edad ? ' · ' . esc_html( $edad ) : ''; ?>
					</p>
					<a class="pp-btn pp-btn--borde" href="<?php echo esc_url( $url_ver ); ?>">Ver perfil</a>
				</div>
			</article>
		<?php endforeach; ?>

		<a class="pp-masc-card pp-masc-card--agregar" href="<?php echo esc_url( $url_nueva ); ?>">
			<span class="pp-masc-card--agregar__icono"><i class="fa fa-plus"></i></span>
			<span>Añadir nueva mascota</span>
		</a>
	</div>
	<?php
}

/* --------------------------- Vista: formulario -------------------------- */

function pp_mascotas_render_form( $mascota_id = 0 ) {
	$user_id  = get_current_user_id();
	$editando = $mascota_id > 0;

	// Valores previos: si la validación falló, repoblamos con lo enviado.
	$transient = get_transient( 'pp_mascota_form_' . $user_id );
	delete_transient( 'pp_mascota_form_' . $user_id );
	$enviado = ( ! empty( $transient['valores'] ) && is_array( $transient['valores'] ) ) ? $transient['valores'] : array();

	$valor = function ( $campo, $meta_default = '' ) use ( $enviado, $editando, $mascota_id ) {
		if ( isset( $enviado[ $campo ] ) ) {
			return $enviado[ $campo ];
		}
		if ( $editando ) {
			return get_post_meta( $mascota_id, '_pp_' . $campo, true );
		}
		return $meta_default;
	};

	$nombre  = isset( $enviado['nombre'] ) ? $enviado['nombre'] : ( $editando ? get_the_title( $mascota_id ) : '' );
	$especie = $valor( 'especie' );
	$sexo    = $valor( 'sexo' );
	$fecha   = $valor( 'fecha_nacimiento' );
	$raza    = $valor( 'raza' );
	$peso    = $valor( 'peso' );
	$pelaje  = $valor( 'pelaje' );
	$sobre   = $valor( 'sobre' );

	$foto_actual = $editando ? get_the_post_thumbnail_url( $mascota_id, 'medium' ) : '';
	$galeria_ids = $editando ? (array) get_post_meta( $mascota_id, '_pp_galeria', true ) : array();
	$galeria_ids = array_filter( array_map( 'absint', $galeria_ids ) );

	$razas     = pp_mascotas_get_razas();
	$url_lista = pp_mascotas_page_url();
	?>
	<header class="pp-masc-header pp-masc-header--form">
		<div>
			<h2 class="pp-masc-titulo"><?php echo $editando ? esc_html( 'Perfil de ' . get_the_title( $mascota_id ) ) : 'Registrar nuevo integrante'; ?></h2>
			<p class="pp-masc-sub"><?php echo $editando ? 'Actualiza los datos de tu peludo cuando lo necesites.' : 'Completa los datos de tu peludo para empezar su historial digital.'; ?></p>
		</div>
	</header>

	<form class="pp-masc-form" method="post" enctype="multipart/form-data" id="pp-masc-form">
		<?php wp_nonce_field( 'pp_mascota_guardar', 'pp_mascota_nonce' ); ?>
		<input type="hidden" name="pp_mascota_action" value="guardar">
		<input type="hidden" name="pp_mascota_id" value="<?php echo esc_attr( $mascota_id ); ?>">
		<?php // Token de un solo uso: evita mascotas duplicadas por doble clic en Guardar. ?>
		<input type="hidden" name="pp_form_token" value="<?php echo esc_attr( str_replace( '-', '', wp_generate_uuid4() ) ); ?>">

		<!-- Sección obligatoria -->
		<section class="pp-masc-tarjeta">
			<h3 class="pp-masc-tarjeta__titulo"><i class="fa fa-paw"></i> Información Mascota</h3>

			<div class="pp-masc-form__grid">
				<div class="pp-masc-form__foto">
					<label class="pp-masc-label">Foto de perfil <span class="pp-req">*</span></label>
					<label class="pp-masc-fotocirculo" for="pp_foto">
						<img id="pp-foto-preview" src="<?php echo esc_url( $foto_actual ); ?>" alt="" style="<?php echo $foto_actual ? '' : 'display:none;'; ?>">
						<span class="pp-masc-fotocirculo__hint" id="pp-foto-hint" style="<?php echo $foto_actual ? 'display:none;' : ''; ?>">
							<i class="fa fa-camera"></i>
							<span>Subir foto</span>
						</span>
					</label>
					<input type="file" id="pp_foto" name="pp_foto" accept="image/jpeg,image/png,image/webp" <?php echo $editando ? '' : 'required'; ?> class="pp-masc-inputfile">
					<p class="pp-masc-ayuda">Sube una foto clara de tu mascota (JPG, PNG o WEBP).</p>
				</div>

				<div class="pp-masc-form__campos">
					<div class="pp-masc-campo">
						<label class="pp-masc-label" for="pp_nombre">Nombre <span class="pp-req">*</span></label>
						<input class="pp-masc-input" type="text" id="pp_nombre" name="pp_nombre" maxlength="60" placeholder="Ej: Max" value="<?php echo esc_attr( $nombre ); ?>" required>
					</div>

					<div class="pp-masc-fila">
						<div class="pp-masc-campo">
							<label class="pp-masc-label">Especie <span class="pp-req">*</span></label>
							<div class="pp-masc-pills">
								<label class="pp-masc-pill"><input type="radio" name="pp_especie" value="perro" <?php checked( $especie, 'perro' ); ?> required><span>🐶 Perro</span></label>
								<label class="pp-masc-pill"><input type="radio" name="pp_especie" value="gato" <?php checked( $especie, 'gato' ); ?>><span>🐱 Gato</span></label>
							</div>
						</div>
						<div class="pp-masc-campo">
							<label class="pp-masc-label">Sexo <span class="pp-req">*</span></label>
							<div class="pp-masc-pills">
								<label class="pp-masc-pill"><input type="radio" name="pp_sexo" value="macho" <?php checked( $sexo, 'macho' ); ?> required><span><em class="pp-sexo-simbolo pp-sexo-simbolo--m">&#9794;</em> Macho</span></label>
								<label class="pp-masc-pill"><input type="radio" name="pp_sexo" value="hembra" <?php checked( $sexo, 'hembra' ); ?>><span><em class="pp-sexo-simbolo pp-sexo-simbolo--h">&#9792;</em> Hembra</span></label>
							</div>
						</div>
					</div>

					<div class="pp-masc-fila">
						<div class="pp-masc-campo">
							<label class="pp-masc-label" for="pp_fecha_nacimiento">Fecha de nacimiento <span class="pp-req">*</span></label>
							<input class="pp-masc-input" type="date" id="pp_fecha_nacimiento" name="pp_fecha_nacimiento" value="<?php echo esc_attr( $fecha ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
						</div>
						<div class="pp-masc-campo">
							<label class="pp-masc-label" for="pp_peso">Peso (en kilos) <span class="pp-req">*</span></label>
							<input class="pp-masc-input" type="number" id="pp_peso" name="pp_peso" value="<?php echo esc_attr( $peso ); ?>" min="0.1" max="150" step="0.1" placeholder="0.0" required>
						</div>
					</div>

					<div class="pp-masc-campo">
						<label class="pp-masc-label" for="pp_raza">Raza <span class="pp-req">*</span></label>
						<select class="pp-masc-input" id="pp_raza" name="pp_raza" data-valor="<?php echo esc_attr( $raza ); ?>" required>
							<option value="" disabled <?php selected( $raza, '' ); ?>>Selecciona primero la especie…</option>
							<?php foreach ( array( 'perro' => 'Perros', 'gato' => 'Gatos' ) as $clave => $etiqueta ) : ?>
								<optgroup label="<?php echo esc_attr( $etiqueta ); ?>" data-especie="<?php echo esc_attr( $clave ); ?>">
									<?php foreach ( $razas[ $clave ] as $opcion ) : ?>
										<option value="<?php echo esc_attr( $opcion ); ?>" <?php selected( $raza, $opcion ); ?>><?php echo esc_html( $opcion ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
							<?php // Raza heredada: ya no está en el catálogo pero la mascota la conserva.
							if ( $raza && ! in_array( $raza, array_merge( $razas['perro'], $razas['gato'] ), true ) ) : ?>
								<option value="<?php echo esc_attr( $raza ); ?>" selected><?php echo esc_html( $raza ); ?></option>
							<?php endif; ?>
						</select>
					</div>

					<div class="pp-masc-fila">
						<div class="pp-masc-campo">
							<label class="pp-masc-label">Tipo de pelaje <span class="pp-req">*</span></label>
							<div class="pp-masc-pills">
								<label class="pp-masc-pill"><input type="radio" name="pp_pelaje" value="corto" <?php checked( $pelaje, 'corto' ); ?> required><span>Corto</span></label>
								<label class="pp-masc-pill"><input type="radio" name="pp_pelaje" value="largo" <?php checked( $pelaje, 'largo' ); ?>><span>Largo</span></label>
							</div>
						</div>
					</div>
				</div>
			</div>
		</section>

		<!-- Sección opcional -->
		<section class="pp-masc-tarjeta">
			<h3 class="pp-masc-tarjeta__titulo"><i class="fa fa-plus-circle"></i> Detalles adicionales <small>(opcional)</small></h3>

			<div class="pp-masc-campo">
				<label class="pp-masc-label" for="pp_galeria">Galería de fotos</label>

				<?php if ( $galeria_ids ) : ?>
					<div class="pp-masc-galeria" id="pp-galeria-actual">
						<?php foreach ( $galeria_ids as $adjunto_id ) :
							$mini = wp_get_attachment_image_url( $adjunto_id, 'thumbnail' );
							if ( ! $mini ) { continue; } ?>
							<span class="pp-masc-galeria__item" data-id="<?php echo esc_attr( $adjunto_id ); ?>">
								<img src="<?php echo esc_url( $mini ); ?>" alt="">
								<button type="button" class="pp-masc-galeria__quitar" title="Quitar de la galería">&times;</button>
							</span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<label class="pp-masc-zona-subida" for="pp_galeria">
					<i class="fa fa-cloud-upload"></i>
					<span>Haz clic aquí para subir fotos</span>
					<small>Máximo 10 fotos en total (JPG, PNG o WEBP)</small>
				</label>
				<input type="file" id="pp_galeria" name="pp_galeria[]" accept="image/jpeg,image/png,image/webp" multiple class="pp-masc-inputfile">
				<div class="pp-masc-galeria" id="pp-galeria-nuevas"></div>
			</div>

			<div class="pp-masc-campo">
				<label class="pp-masc-label" for="pp_sobre">Sobre tu mascota</label>
				<textarea class="pp-masc-input" id="pp_sobre" name="pp_sobre" rows="4" maxlength="2000" placeholder="Cuéntanos sobre su personalidad, gustos o cuidados especiales…"><?php echo esc_textarea( $sobre ); ?></textarea>
			</div>
		</section>

		<div class="pp-masc-acciones">
			<a class="pp-btn pp-btn--plano" href="<?php echo esc_url( $url_lista ); ?>">Cancelar</a>
			<button class="pp-btn pp-btn--primario" type="submit"><i class="fa fa-check"></i> Guardar mascota</button>
		</div>
	</form>

	<?php if ( $editando ) : ?>
		<form method="post" class="pp-masc-eliminar" id="pp-masc-eliminar">
			<?php wp_nonce_field( 'pp_mascota_eliminar', 'pp_mascota_nonce_eliminar' ); ?>
			<input type="hidden" name="pp_mascota_action" value="eliminar">
			<input type="hidden" name="pp_mascota_id" value="<?php echo esc_attr( $mascota_id ); ?>">
			<button type="submit" class="pp-btn pp-btn--peligro"><i class="fa fa-trash"></i> Eliminar mascota</button>
		</form>
	<?php endif; ?>
	<?php
}

/* -------------------------------------------------------------------------
 * 7. Guardado / eliminación (POST con nonce, siempre en la página Mascotas)
 * ---------------------------------------------------------------------- */

add_action( 'template_redirect', 'pp_mascotas_handle_post' );
function pp_mascotas_handle_post() {
	if ( empty( $_POST['pp_mascota_action'] ) || ! is_user_logged_in() ) {
		return;
	}

	$action    = sanitize_key( $_POST['pp_mascota_action'] );
	$user_id   = get_current_user_id();
	$url_lista = pp_mascotas_page_url();

	/* ---- Eliminar ---- */
	if ( 'eliminar' === $action ) {
		if ( ! isset( $_POST['pp_mascota_nonce_eliminar'] ) || ! wp_verify_nonce( $_POST['pp_mascota_nonce_eliminar'], 'pp_mascota_eliminar' ) ) {
			wp_die( 'La sesión expiró. Vuelve atrás e inténtalo de nuevo.' );
		}
		$mascota_id = absint( $_POST['pp_mascota_id'] ?? 0 );
		$post       = get_post( $mascota_id );
		if ( ! $post || 'pp_mascota' !== $post->post_type || (int) $post->post_author !== $user_id ) {
			wp_die( 'No tienes permiso para eliminar esa mascota.' );
		}
		if ( 'trash' !== $post->post_status ) { // doble clic: ya estaba eliminada
			wp_trash_post( $mascota_id );
		}
		wp_safe_redirect( add_query_arg( 'pp_ok', 'eliminada', $url_lista ) );
		exit;
	}

	if ( 'guardar' !== $action ) {
		return;
	}
	if ( ! isset( $_POST['pp_mascota_nonce'] ) || ! wp_verify_nonce( $_POST['pp_mascota_nonce'], 'pp_mascota_guardar' ) ) {
		wp_die( 'La sesión expiró. Vuelve atrás e inténtalo de nuevo.' );
	}

	$mascota_id = absint( $_POST['pp_mascota_id'] ?? 0 );
	$editando   = $mascota_id > 0;
	if ( $editando && ! pp_mascotas_es_del_usuario( $mascota_id ) ) {
		wp_die( 'No tienes permiso para editar esa mascota.' );
	}

	// Si este formulario ya se procesó (doble clic en Guardar), no repetir.
	$form_token = sanitize_key( $_POST['pp_form_token'] ?? '' );
	if ( $form_token && get_transient( 'pp_masc_tk_' . $form_token ) ) {
		wp_safe_redirect( add_query_arg( 'pp_ok', $editando ? 'actualizada' : 'creada', $url_lista ) );
		exit;
	}

	/* ---- Recoger y validar ---- */
	$valores = array(
		'nombre'           => sanitize_text_field( wp_unslash( $_POST['pp_nombre'] ?? '' ) ),
		'especie'          => sanitize_key( $_POST['pp_especie'] ?? '' ),
		'sexo'             => sanitize_key( $_POST['pp_sexo'] ?? '' ),
		'fecha_nacimiento' => sanitize_text_field( $_POST['pp_fecha_nacimiento'] ?? '' ),
		'raza'             => sanitize_text_field( wp_unslash( $_POST['pp_raza'] ?? '' ) ),
		'peso'             => str_replace( ',', '.', sanitize_text_field( $_POST['pp_peso'] ?? '' ) ),
		'pelaje'           => sanitize_key( $_POST['pp_pelaje'] ?? '' ),
		'sobre'            => sanitize_textarea_field( wp_unslash( $_POST['pp_sobre'] ?? '' ) ),
	);

	$errores = array();

	if ( '' === $valores['nombre'] ) {
		$errores[] = 'El nombre es obligatorio.';
	}
	if ( ! in_array( $valores['especie'], array( 'perro', 'gato' ), true ) ) {
		$errores[] = 'Selecciona la especie (perro o gato).';
	}
	if ( ! in_array( $valores['sexo'], array( 'macho', 'hembra' ), true ) ) {
		$errores[] = 'Selecciona el sexo (macho o hembra).';
	}
	if ( ! in_array( $valores['pelaje'], array( 'corto', 'largo' ), true ) ) {
		$errores[] = 'Selecciona el tipo de pelaje (corto o largo).';
	}

	$fecha_ts = strtotime( $valores['fecha_nacimiento'] );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $valores['fecha_nacimiento'] ) || ! $fecha_ts ) {
		$errores[] = 'La fecha de nacimiento no es válida.';
	} elseif ( $fecha_ts > current_time( 'timestamp' ) ) {
		$errores[] = 'La fecha de nacimiento no puede ser futura.';
	}

	if ( in_array( $valores['especie'], array( 'perro', 'gato' ), true ) ) {
		$raza_valida = in_array( $valores['raza'], pp_mascotas_get_razas( $valores['especie'] ), true );
		if ( ! $raza_valida && $editando && $valores['raza'] === get_post_meta( $mascota_id, '_pp_raza', true ) ) {
			// Raza heredada: se eliminó del catálogo pero la mascota ya la tenía.
			$raza_valida = true;
		}
		if ( ! $raza_valida ) {
			$errores[] = 'Selecciona una raza válida para la especie elegida.';
		}
	}

	$peso = (float) $valores['peso'];
	if ( $peso < 0.1 || $peso > 150 ) {
		$errores[] = 'Ingresa un peso válido en kilos (entre 0.1 y 150).';
	}

	$hay_foto_nueva = ! empty( $_FILES['pp_foto']['name'] );
	if ( ! $editando && ! $hay_foto_nueva ) {
		$errores[] = 'La foto de perfil es obligatoria.';
	}

	$volver_al_form = $editando
		? add_query_arg( array( 'pp_vista' => 'editar', 'mascota' => $mascota_id ), $url_lista )
		: add_query_arg( 'pp_vista', 'nueva', $url_lista );

	if ( $errores ) {
		set_transient( 'pp_mascota_form_' . $user_id, array( 'errores' => $errores, 'valores' => $valores ), 120 );
		wp_safe_redirect( $volver_al_form );
		exit;
	}

	// Validación superada: marcar el token como usado ANTES de crear,
	// para cerrar la ventana del doble envío simultáneo.
	if ( $form_token ) {
		set_transient( 'pp_masc_tk_' . $form_token, 1, HOUR_IN_SECONDS );
	}

	/* ---- Crear / actualizar el post ---- */
	$post_args = array(
		'post_title'  => $valores['nombre'],
		'post_type'   => 'pp_mascota',
		'post_status' => 'publish',
		'post_author' => $user_id,
	);
	if ( $editando ) {
		$post_args['ID'] = $mascota_id;
		wp_update_post( $post_args );
	} else {
		$mascota_id = wp_insert_post( $post_args );
		if ( ! $mascota_id || is_wp_error( $mascota_id ) ) {
			set_transient( 'pp_mascota_form_' . $user_id, array( 'errores' => array( 'No pudimos guardar la mascota. Inténtalo de nuevo.' ), 'valores' => $valores ), 120 );
			wp_safe_redirect( $volver_al_form );
			exit;
		}
	}

	foreach ( array( 'especie', 'sexo', 'fecha_nacimiento', 'raza', 'pelaje', 'sobre' ) as $campo ) {
		update_post_meta( $mascota_id, '_pp_' . $campo, $valores[ $campo ] );
	}
	update_post_meta( $mascota_id, '_pp_peso', $peso );

	/* ---- Foto de perfil ---- */
	if ( $hay_foto_nueva ) {
		$foto_id = pp_mascotas_subir_imagen( 'pp_foto', $mascota_id );
		if ( is_wp_error( $foto_id ) ) {
			set_transient( 'pp_mascota_form_' . $user_id, array( 'errores' => array( 'La foto de perfil no se pudo subir: ' . $foto_id->get_error_message() ), 'valores' => $valores ), 120 );
			wp_safe_redirect( add_query_arg( array( 'pp_vista' => 'editar', 'mascota' => $mascota_id ), $url_lista ) );
			exit;
		}
		set_post_thumbnail( $mascota_id, $foto_id );
	}

	/* ---- Galería: quitar marcadas y agregar nuevas (tope 10) ---- */
	$galeria = array_filter( array_map( 'absint', (array) get_post_meta( $mascota_id, '_pp_galeria', true ) ) );

	$eliminar = array_map( 'absint', (array) ( $_POST['pp_galeria_eliminar'] ?? array() ) );
	if ( $eliminar ) {
		$galeria = array_values( array_diff( $galeria, $eliminar ) );
	}

	// is_array: si un form manipulado envía pp_galeria SIN [] llega como
	// string y count()/[$i] fatalearían en PHP 8 con el post ya creado.
	if ( isset( $_FILES['pp_galeria']['name'] ) && is_array( $_FILES['pp_galeria']['name'] ) && ! empty( $_FILES['pp_galeria']['name'][0] ) ) {
		$archivos = $_FILES['pp_galeria'];
		$total    = count( $archivos['name'] );
		for ( $i = 0; $i < $total && count( $galeria ) < 10; $i++ ) {
			if ( empty( $archivos['name'][ $i ] ) || UPLOAD_ERR_OK !== $archivos['error'][ $i ] ) {
				continue;
			}
			$_FILES['pp_galeria_item'] = array(
				'name'     => $archivos['name'][ $i ],
				'type'     => $archivos['type'][ $i ],
				'tmp_name' => $archivos['tmp_name'][ $i ],
				'error'    => $archivos['error'][ $i ],
				'size'     => $archivos['size'][ $i ],
			);
			$adjunto_id = pp_mascotas_subir_imagen( 'pp_galeria_item', $mascota_id );
			if ( ! is_wp_error( $adjunto_id ) ) {
				$galeria[] = (int) $adjunto_id;
			}
		}
		unset( $_FILES['pp_galeria_item'] );
	}

	update_post_meta( $mascota_id, '_pp_galeria', array_values( array_unique( $galeria ) ) );

	wp_safe_redirect( add_query_arg( 'pp_ok', $editando ? 'actualizada' : 'creada', $url_lista ) );
	exit;
}

/* -------------------------------------------------------------------------
 * 8. Administración: wp-admin → Mascotas
 *    Columnas con la información clave, filtro por especie, ficha de solo
 *    lectura al abrir una mascota y sección "Mascotas" en el perfil de
 *    cada usuario.
 * ---------------------------------------------------------------------- */

add_filter( 'manage_pp_mascota_posts_columns', 'pp_mascotas_admin_columns' );
function pp_mascotas_admin_columns( $cols ) {
	return array(
		'cb'         => isset( $cols['cb'] ) ? $cols['cb'] : '<input type="checkbox" />',
		'pp_foto'    => 'Foto',
		'title'      => 'Nombre',
		'pp_especie' => 'Especie',
		'pp_raza'    => 'Raza',
		'pp_sexo'    => 'Sexo',
		'pp_pelaje'  => 'Pelaje',
		'pp_edad'    => 'Edad',
		'pp_peso'    => 'Peso (kg)',
		'author'     => 'Dueño',
		'date'       => 'Registrada',
	);
}

add_action( 'manage_pp_mascota_posts_custom_column', 'pp_mascotas_admin_column_value', 10, 2 );
function pp_mascotas_admin_column_value( $col, $post_id ) {
	switch ( $col ) {
		case 'pp_foto':
			echo get_the_post_thumbnail( $post_id, array( 56, 56 ), array( 'style' => 'border-radius:8px;object-fit:cover;width:56px;height:56px;' ) ) ?: '—';
			break;
		case 'pp_especie':
			$especie = get_post_meta( $post_id, '_pp_especie', true );
			echo esc_html( 'gato' === $especie ? '🐱 Gato' : ( 'perro' === $especie ? '🐶 Perro' : '—' ) );
			break;
		case 'pp_raza':
			echo esc_html( get_post_meta( $post_id, '_pp_raza', true ) ?: '—' );
			break;
		case 'pp_sexo':
			$sexo = get_post_meta( $post_id, '_pp_sexo', true );
			echo esc_html( $sexo ? ucfirst( $sexo ) : '—' );
			break;
		case 'pp_pelaje':
			$pelaje = get_post_meta( $post_id, '_pp_pelaje', true );
			echo esc_html( $pelaje ? ucfirst( $pelaje ) : '—' );
			break;
		case 'pp_edad':
			echo esc_html( pp_mascotas_edad_legible( get_post_meta( $post_id, '_pp_fecha_nacimiento', true ) ) ?: '—' );
			break;
		case 'pp_peso':
			$peso = get_post_meta( $post_id, '_pp_peso', true );
			echo esc_html( $peso ? $peso : '—' );
			break;
	}
}

// Filtro por especie encima de la lista.
add_action( 'restrict_manage_posts', 'pp_mascotas_admin_filtro_especie' );
function pp_mascotas_admin_filtro_especie( $post_type ) {
	if ( 'pp_mascota' !== $post_type ) {
		return;
	}
	$actual = isset( $_GET['pp_filtro_especie'] ) ? sanitize_key( $_GET['pp_filtro_especie'] ) : '';
	echo '<select name="pp_filtro_especie">';
	echo '<option value="">Todas las especies</option>';
	echo '<option value="perro"' . selected( $actual, 'perro', false ) . '>Perros</option>';
	echo '<option value="gato"' . selected( $actual, 'gato', false ) . '>Gatos</option>';
	echo '</select>';
}

add_filter( 'parse_query', 'pp_mascotas_admin_aplicar_filtro' );
function pp_mascotas_admin_aplicar_filtro( $query ) {
	global $pagenow;
	if ( ! is_admin() || 'edit.php' !== $pagenow || 'pp_mascota' !== ( $_GET['post_type'] ?? '' ) ) {
		return $query;
	}
	$especie = sanitize_key( $_GET['pp_filtro_especie'] ?? '' );
	if ( in_array( $especie, array( 'perro', 'gato' ), true ) ) {
		$query->query_vars['meta_key']   = '_pp_especie';
		$query->query_vars['meta_value'] = $especie;
	}
	return $query;
}

// Ficha de solo lectura al abrir una mascota en wp-admin.
add_action( 'add_meta_boxes_pp_mascota', 'pp_mascotas_admin_metabox' );
function pp_mascotas_admin_metabox() {
	add_meta_box( 'pp_masc_ficha', 'Ficha de la mascota', 'pp_mascotas_admin_metabox_render', 'pp_mascota', 'normal', 'high' );
}

function pp_mascotas_admin_metabox_render( $post ) {
	$campos = array(
		'Especie'             => ( 'gato' === get_post_meta( $post->ID, '_pp_especie', true ) ) ? 'Gato' : 'Perro',
		'Sexo'                => ucfirst( get_post_meta( $post->ID, '_pp_sexo', true ) ),
		'Fecha de nacimiento' => get_post_meta( $post->ID, '_pp_fecha_nacimiento', true ),
		'Edad'                => pp_mascotas_edad_legible( get_post_meta( $post->ID, '_pp_fecha_nacimiento', true ) ),
		'Raza'                => get_post_meta( $post->ID, '_pp_raza', true ),
		'Peso'                => get_post_meta( $post->ID, '_pp_peso', true ) . ' kg',
		'Tipo de pelaje'      => ucfirst( get_post_meta( $post->ID, '_pp_pelaje', true ) ) ?: '—',
		'Sobre la mascota'    => get_post_meta( $post->ID, '_pp_sobre', true ) ?: '—',
		'Dueño'               => get_the_author_meta( 'display_name', $post->post_author ) . ' (' . get_the_author_meta( 'user_email', $post->post_author ) . ')',
	);
	echo '<table class="widefat striped" style="max-width:640px">';
	foreach ( $campos as $etiqueta => $valor ) {
		echo '<tr><td style="width:190px"><strong>' . esc_html( $etiqueta ) . '</strong></td><td>' . esc_html( $valor ) . '</td></tr>';
	}
	echo '</table>';

	$galeria = array_filter( array_map( 'absint', (array) get_post_meta( $post->ID, '_pp_galeria', true ) ) );
	if ( $galeria ) {
		echo '<p style="margin:14px 0 6px"><strong>Galería (' . count( $galeria ) . ')</strong></p><div style="display:flex;gap:8px;flex-wrap:wrap">';
		foreach ( $galeria as $adjunto_id ) {
			$mini = wp_get_attachment_image_url( $adjunto_id, 'thumbnail' );
			if ( $mini ) {
				echo '<a href="' . esc_url( get_edit_post_link( $adjunto_id ) ) . '"><img src="' . esc_url( $mini ) . '" style="width:84px;height:84px;object-fit:cover;border-radius:8px" alt=""></a>';
			}
		}
		echo '</div>';
	}
	echo '<p class="description" style="margin-top:12px">Los datos se editan desde la web, en el panel del usuario (Mis Mascotas). La "Imagen destacada" de la derecha es la foto de perfil.</p>';
}

// Sección "Mascotas" en el perfil de usuario de wp-admin.
add_action( 'show_user_profile', 'pp_mascotas_admin_perfil_usuario' );
add_action( 'edit_user_profile', 'pp_mascotas_admin_perfil_usuario' );
function pp_mascotas_admin_perfil_usuario( $user ) {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$mascotas = get_posts( array( 'post_type' => 'pp_mascota', 'author' => $user->ID, 'post_status' => 'publish', 'numberposts' => -1 ) );
	echo '<h2>🐾 Mascotas (' . count( $mascotas ) . ')</h2>';
	if ( ! $mascotas ) {
		echo '<p>Este usuario aún no ha registrado mascotas.</p>';
		return;
	}
	echo '<table class="widefat striped" style="max-width:760px"><thead><tr><th>Foto</th><th>Nombre</th><th>Especie</th><th>Raza</th><th>Edad</th></tr></thead><tbody>';
	foreach ( $mascotas as $m ) {
		$especie = get_post_meta( $m->ID, '_pp_especie', true );
		echo '<tr>';
		echo '<td>' . ( get_the_post_thumbnail( $m->ID, array( 44, 44 ), array( 'style' => 'border-radius:6px;width:44px;height:44px;object-fit:cover;' ) ) ?: '—' ) . '</td>';
		echo '<td><a href="' . esc_url( get_edit_post_link( $m->ID ) ) . '"><strong>' . esc_html( $m->post_title ) . '</strong></a></td>';
		echo '<td>' . esc_html( 'gato' === $especie ? 'Gato' : 'Perro' ) . '</td>';
		echo '<td>' . esc_html( get_post_meta( $m->ID, '_pp_raza', true ) ) . '</td>';
		echo '<td>' . esc_html( pp_mascotas_edad_legible( get_post_meta( $m->ID, '_pp_fecha_nacimiento', true ) ) ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
}

/* -------------------------------------------------------------------------
 * 9. Administración de razas: wp-admin → Mascotas → Razas
 *    Agregar, renombrar y eliminar razas de perros y gatos. Al renombrar,
 *    las mascotas existentes se actualizan; al eliminar, conservan el
 *    nombre que ya tenían (raza "heredada"). "Otro" es fija.
 * ---------------------------------------------------------------------- */

// "Razas" NO es un ítem del menú lateral: es un TAB dentro de "Mascotas".
// Se registra como página oculta (accesible por URL) y se quita del menú.
add_action( 'admin_menu', 'pp_mascotas_admin_menu_razas', 20 );
function pp_mascotas_admin_menu_razas() {
	add_submenu_page(
		'pp-personalizacion',
		'Razas de mascotas',
		'Razas',
		'manage_options',
		'pp-razas',
		'pp_mascotas_admin_razas_page'
	);
	remove_submenu_page( 'pp-personalizacion', 'pp-razas' );
}

/**
 * Barra de pestañas de la sección Mascotas: "Registradas" (lista del CPT) y
 * "Razas". Se muestra en ambas pantallas para que convivan bajo Mascotas.
 *
 * @param string $activo 'registradas' | 'razas'
 */
function pp_mascotas_render_tabs( $activo ) {
	$tabs = array(
		'registradas' => array( 'Registradas', admin_url( 'edit.php?post_type=pp_mascota' ) ),
		'razas'       => array( 'Razas',       admin_url( 'admin.php?page=pp-razas' ) ),
	);
	echo '<h2 class="nav-tab-wrapper" style="margin-bottom:16px">';
	foreach ( $tabs as $clave => $tab ) {
		$clase = 'nav-tab' . ( $clave === $activo ? ' nav-tab-active' : '' );
		echo '<a href="' . esc_url( $tab[1] ) . '" class="' . esc_attr( $clase ) . '">' . esc_html( $tab[0] ) . '</a>';
	}
	echo '</h2>';
}

// Inyecta la barra de pestañas en la pantalla de listado del CPT Mascotas
// (admin_notices se pinta justo bajo el título "Mascotas" / "Añadir entrada").
add_action( 'admin_notices', 'pp_mascotas_tabs_en_lista_cpt' );
function pp_mascotas_tabs_en_lista_cpt() {
	$screen = get_current_screen();
	if ( $screen && 'edit-pp_mascota' === $screen->id ) {
		pp_mascotas_render_tabs( 'registradas' );
	}
}

add_action( 'admin_init', 'pp_mascotas_admin_razas_handler' );
function pp_mascotas_admin_razas_handler() {
	if ( empty( $_POST['pp_razas_action'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'pp_razas_admin', 'pp_razas_nonce' );

	$action  = sanitize_key( $_POST['pp_razas_action'] );
	$especie = sanitize_key( $_POST['pp_especie'] ?? '' );
	$volver  = admin_url( 'admin.php?page=pp-razas' );
	$ir      = function ( $args ) use ( $volver ) {
		wp_safe_redirect( add_query_arg( $args, $volver ) );
		exit;
	};

	if ( ! in_array( $especie, array( 'perro', 'gato' ), true ) ) {
		$ir( array( 'pp_err' => 'especie' ) );
	}

	$listas = pp_mascotas_get_razas_editables();
	$lista  = $listas[ $especie ];
	$existe = function ( $nombre ) use ( $lista ) {
		$n = pp_mascotas_normalizar( $nombre );
		if ( 'otro' === $n ) {
			return true; // "Otro" es fija: no se puede duplicar
		}
		foreach ( $lista as $r ) {
			if ( pp_mascotas_normalizar( $r ) === $n ) {
				return true;
			}
		}
		return false;
	};

	/* ---- Agregar ---- */
	if ( 'agregar' === $action ) {
		$nueva = sanitize_text_field( wp_unslash( $_POST['pp_raza_nueva'] ?? '' ) );
		if ( '' === $nueva || mb_strlen( $nueva ) > 60 ) {
			$ir( array( 'pp_err' => 'nombre' ) );
		}
		if ( $existe( $nueva ) ) {
			$ir( array( 'pp_err' => 'duplicada' ) );
		}
		$lista[]            = $nueva;
		$listas[ $especie ] = $lista;
		update_option( 'pp_mascotas_razas', $listas );
		$ir( array( 'pp_ok' => 'agregada' ) );
	}

	$original = sanitize_text_field( wp_unslash( $_POST['pp_raza_original'] ?? '' ) );
	$pos      = array_search( $original, $lista, true );
	if ( false === $pos ) {
		$ir( array( 'pp_err' => 'noexiste' ) );
	}

	/* ---- Renombrar (y actualizar las mascotas que la usaban) ---- */
	if ( 'renombrar' === $action ) {
		$nuevo = sanitize_text_field( wp_unslash( $_POST['pp_raza_nombre'] ?? '' ) );
		if ( '' === $nuevo || mb_strlen( $nuevo ) > 60 ) {
			$ir( array( 'pp_err' => 'nombre' ) );
		}
		if ( $nuevo === $original ) {
			$ir( array( 'pp_ok' => 'sincambios' ) );
		}
		if ( pp_mascotas_normalizar( $nuevo ) !== pp_mascotas_normalizar( $original ) && $existe( $nuevo ) ) {
			$ir( array( 'pp_err' => 'duplicada' ) );
		}
		$lista[ $pos ]      = $nuevo;
		$listas[ $especie ] = $lista;
		update_option( 'pp_mascotas_razas', $listas );

		$afectadas = get_posts( array(
			'post_type'   => 'pp_mascota',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_query'  => array(
				array( 'key' => '_pp_raza', 'value' => $original ),
				array( 'key' => '_pp_especie', 'value' => $especie ),
			),
		) );
		foreach ( $afectadas as $pid ) {
			update_post_meta( $pid, '_pp_raza', $nuevo );
		}
		$ir( array( 'pp_ok' => 'renombrada', 'pp_n' => count( $afectadas ) ) );
	}

	/* ---- Eliminar (las mascotas conservan su valor heredado) ---- */
	if ( 'eliminar' === $action ) {
		unset( $lista[ $pos ] );
		$listas[ $especie ] = array_values( $lista );
		update_option( 'pp_mascotas_razas', $listas );
		$ir( array( 'pp_ok' => 'eliminada' ) );
	}
}

function pp_mascotas_admin_razas_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Cuántas mascotas activas usan cada raza (clave "especie|raza").
	$uso = array();
	foreach ( get_posts( array( 'post_type' => 'pp_mascota', 'post_status' => 'publish', 'numberposts' => -1 ) ) as $m ) {
		$clave         = get_post_meta( $m->ID, '_pp_especie', true ) . '|' . get_post_meta( $m->ID, '_pp_raza', true );
		$uso[ $clave ] = ( $uso[ $clave ] ?? 0 ) + 1;
	}

	$listas = pp_mascotas_get_razas_editables();

	$ok  = sanitize_key( $_GET['pp_ok'] ?? '' );
	$err = sanitize_key( $_GET['pp_err'] ?? '' );
	$n   = absint( $_GET['pp_n'] ?? 0 );
	$mensajes_ok = array(
		'agregada'   => 'Raza agregada. Ya aparece en el formulario de los usuarios.',
		'renombrada' => 'Raza renombrada.' . ( $n ? " Se actualizaron $n mascota(s) que la usaban." : '' ),
		'eliminada'  => 'Raza eliminada del catálogo. Las mascotas que la usaban conservan el nombre en su perfil.',
		'sincambios' => 'No había cambios que guardar.',
	);
	$mensajes_err = array(
		'nombre'    => 'Escribe un nombre válido (máximo 60 caracteres).',
		'duplicada' => 'Esa raza ya existe en la lista (se ignoran mayúsculas y tildes).',
		'noexiste'  => 'La raza que intentas modificar ya no existe. Recarga la página.',
		'especie'   => 'Especie no válida.',
	);
	?>
	<div class="wrap">
		<h1>🐾 Mascotas</h1>
		<?php pp_mascotas_render_tabs( 'razas' ); ?>
		<h2 style="margin-top:0">Razas</h2>
		<p>Estas son las razas que los usuarios pueden elegir al registrar una mascota. Se muestran siempre en <strong>orden alfabético</strong> y la opción <strong>"Otro"</strong> aparece fija al final de cada lista.</p>

		<?php if ( $ok && isset( $mensajes_ok[ $ok ] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $mensajes_ok[ $ok ] ); ?></p></div>
		<?php endif; ?>
		<?php if ( $err && isset( $mensajes_err[ $err ] ) ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $mensajes_err[ $err ] ); ?></p></div>
		<?php endif; ?>

		<div style="display:flex;gap:32px;flex-wrap:wrap;align-items:flex-start;margin-top:16px">
			<?php foreach ( array( 'perro' => '🐶 Perros', 'gato' => '🐱 Gatos' ) as $especie => $titulo ) : ?>
				<div style="flex:1;min-width:360px;max-width:560px">
					<h2 style="margin-bottom:10px"><?php echo esc_html( $titulo ); ?> <span style="font-weight:400;color:#666">(<?php echo count( $listas[ $especie ] ); ?> razas + Otro)</span></h2>

					<form method="post" style="display:flex;gap:8px;margin-bottom:14px">
						<?php wp_nonce_field( 'pp_razas_admin', 'pp_razas_nonce' ); ?>
						<input type="hidden" name="pp_razas_action" value="agregar">
						<input type="hidden" name="pp_especie" value="<?php echo esc_attr( $especie ); ?>">
						<input type="text" name="pp_raza_nueva" class="regular-text" style="flex:1" placeholder="Nueva raza…" maxlength="60" required>
						<button class="button button-primary">Agregar</button>
					</form>

					<table class="widefat striped">
						<thead><tr><th>Raza (edita el nombre y pulsa Guardar)</th><th style="width:64px;text-align:center">En uso</th></tr></thead>
						<tbody>
							<?php foreach ( $listas[ $especie ] as $raza ) :
								$en_uso = $uso[ $especie . '|' . $raza ] ?? 0; ?>
								<tr>
									<td>
										<form method="post" style="display:flex;gap:6px;align-items:center">
											<?php wp_nonce_field( 'pp_razas_admin', 'pp_razas_nonce' ); ?>
											<input type="hidden" name="pp_especie" value="<?php echo esc_attr( $especie ); ?>">
											<input type="hidden" name="pp_raza_original" value="<?php echo esc_attr( $raza ); ?>">
											<input type="text" name="pp_raza_nombre" value="<?php echo esc_attr( $raza ); ?>" maxlength="60" style="flex:1" required>
											<button class="button" name="pp_razas_action" value="renombrar" title="Guardar el nuevo nombre">Guardar</button>
											<button class="button" name="pp_razas_action" value="eliminar" style="color:#b32d2e;border-color:#b32d2e"
												onclick="return confirm('¿Eliminar la raza «<?php echo esc_js( $raza ); ?>»?<?php echo $en_uso ? esc_js( " Hay $en_uso mascota(s) que la usan: conservarán el nombre en su perfil." ) : ''; ?>');">Eliminar</button>
										</form>
									</td>
									<td style="text-align:center"><?php echo $en_uso ? '<strong>' . esc_html( $en_uso ) . '</strong>' : '—'; ?></td>
								</tr>
							<?php endforeach; ?>
							<tr>
								<td><em>Otro</em> <span class="description">— opción fija, siempre al final</span></td>
								<td style="text-align:center"><?php $eu = $uso[ $especie . '|Otro' ] ?? 0; echo $eu ? '<strong>' . esc_html( $eu ) . '</strong>' : '—'; ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			<?php endforeach; ?>
		</div>

		<p class="description" style="margin-top:18px">Al <strong>renombrar</strong> una raza, las mascotas que la usaban se actualizan automáticamente. Al <strong>eliminar</strong> una raza en uso, las mascotas conservan el nombre que ya tenían (aparece como opción "heredada" al editarlas).</p>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * 10. Integración con reservas (popup de Listeo Booking Plus)
 *
 * En el paso de confirmación del popup se inyecta (vía JS, sin tocar los
 * archivos de Booking Plus) el selector "¿Para cuál de tus peludos es la
 * reserva?", con creación rápida de mascota sin salir del flujo (sin foto;
 * la foto se puede añadir luego en Mis Mascotas). El campo viaja en el
 * POST de lbp_submit_booking gracias al barrido que hace lbp-booking.js
 * sobre .lbp-custom-booking-fields; aquí se valida (filtro
 * listeo_before_insert_booking_data), se guarda dentro del comment JSON de
 * la reserva —el resumen se añade al mensaje, que el prestador y el
 * cliente ven en sus paneles y correos— y se registra la meta
 * _pp_mascota_id en wp_bookings_meta (listeo_after_insert_booking).
 * ---------------------------------------------------------------------- */

/**
 * Ajustes de la integración con reservas (wp-admin → Mascotas → Ajustes):
 * - pp_mascotas_reserva_activa: mostrar (o no) el paso Mascota en TODAS las
 *   reservas. Interruptor global, activo por defecto.
 * - pp_mascotas_precio_categorias: categorías de listado cuyas reservas
 *   usarán datos de la mascota (peso, etc.) para calcular el precio. Por
 *   ahora solo se guarda y se expone (helper + flag al JS): la lógica de
 *   precios se implementará después, empezando por Peluquería y Baño.
 */
function pp_mascotas_reserva_activa() {
	return '0' !== get_option( 'pp_mascotas_reserva_activa', '1' );
}

/** ¿Este listado usa datos de la mascota para el precio? (por categoría) */
function pp_mascotas_listing_usa_precio( $listing_id ) {
	$cats = array_filter( array_map( 'absint', (array) get_option( 'pp_mascotas_precio_categorias', array() ) ) );
	if ( ! $cats ) {
		return false;
	}
	$terms = wp_get_post_terms( $listing_id, 'listing_category', array( 'fields' => 'ids' ) );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return false;
	}
	// Cuenta también si la categoría marcada es ancestro de la del listado
	// (p. ej. se marca "Higiene" y el listado está en "Peluquería").
	$con_ancestros = $terms;
	foreach ( $terms as $t ) {
		$con_ancestros = array_merge( $con_ancestros, get_ancestors( $t, 'listing_category' ) );
	}
	return (bool) array_intersect( $cats, array_map( 'absint', $con_ancestros ) );
}

add_action( 'wp_enqueue_scripts', 'pp_mascotas_reserva_assets', 110 );
function pp_mascotas_reserva_assets() {
	if ( ! is_singular( 'listing' ) || ! pp_mascotas_reserva_activa() ) {
		return;
	}

	$css = PP_PERS_DIR . 'css/pp-mascotas-reserva.css';
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'pp-mascotas-reserva', PP_PERS_URL . 'css/pp-mascotas-reserva.css', array(), filemtime( $css ) );
	}

	$js = PP_PERS_DIR . 'js/pp-mascotas-reserva.js';
	if ( ! file_exists( $js ) ) {
		return;
	}
	wp_enqueue_script( 'pp-mascotas-reserva', PP_PERS_URL . 'js/pp-mascotas-reserva.js', array( 'jquery' ), filemtime( $js ), true );

	$mascotas = array();
	if ( is_user_logged_in() ) {
		$posts = get_posts( array(
			'post_type'   => 'pp_mascota',
			'author'      => get_current_user_id(),
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
		) );
		foreach ( $posts as $m ) {
			$mascotas[] = array(
				'id'      => $m->ID,
				'nombre'  => $m->post_title,
				'especie' => get_post_meta( $m->ID, '_pp_especie', true ),
				'raza'    => get_post_meta( $m->ID, '_pp_raza', true ),
				// pelaje y peso alimentan el filtrado de servicios por
				// mascota (Tipos de servicio, módulo Servicios v2.5.0).
				'pelaje'  => get_post_meta( $m->ID, '_pp_pelaje', true ),
				'peso'    => get_post_meta( $m->ID, '_pp_peso', true ),
				'foto'    => get_the_post_thumbnail_url( $m->ID, 'thumbnail' ) ?: '',
			);
		}
	}

	wp_localize_script( 'pp-mascotas-reserva', 'PP_MASC_RESERVA', array(
		'ajax_url'      => admin_url( 'admin-ajax.php' ),
		'nonce'         => wp_create_nonce( 'pp_masc_reserva' ),
		'logueado'      => is_user_logged_in() ? 1 : 0,
		'mascotas'      => $mascotas,
		'razas'         => pp_mascotas_get_razas(),
		'hoy'           => current_time( 'Y-m-d' ),
		'listado'       => get_the_title(),
		// Bandera para la futura lógica de precios según la mascota
		// (peso/tamaño/pelaje). Aún sin efecto en el cálculo.
		'precioMascota' => pp_mascotas_listing_usa_precio( get_the_ID() ) ? 1 : 0,
	) );
}

/** Creación rápida de mascota desde el popup de reserva (sin foto). */
add_action( 'wp_ajax_pp_mascota_crear_rapida', 'pp_mascotas_ajax_crear_rapida' );
function pp_mascotas_ajax_crear_rapida() {
	check_ajax_referer( 'pp_masc_reserva', 'nonce' );
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Debes iniciar sesión para crear una mascota.' ) );
	}

	$valores = array(
		'nombre'           => sanitize_text_field( wp_unslash( $_POST['nombre'] ?? '' ) ),
		'especie'          => sanitize_key( $_POST['especie'] ?? '' ),
		'sexo'             => sanitize_key( $_POST['sexo'] ?? '' ),
		'fecha_nacimiento' => sanitize_text_field( $_POST['fecha_nacimiento'] ?? '' ),
		'raza'             => sanitize_text_field( wp_unslash( $_POST['raza'] ?? '' ) ),
		'pelaje'           => sanitize_key( $_POST['pelaje'] ?? '' ),
	);
	$peso = (float) str_replace( ',', '.', sanitize_text_field( $_POST['peso'] ?? '' ) );

	$errores = array();
	if ( '' === $valores['nombre'] || mb_strlen( $valores['nombre'] ) > 60 ) {
		$errores[] = 'El nombre es obligatorio.';
	}
	if ( ! in_array( $valores['especie'], array( 'perro', 'gato' ), true ) ) {
		$errores[] = 'Selecciona la especie.';
	}
	if ( ! in_array( $valores['sexo'], array( 'macho', 'hembra' ), true ) ) {
		$errores[] = 'Selecciona el sexo.';
	}
	if ( ! in_array( $valores['pelaje'], array( 'corto', 'largo' ), true ) ) {
		$errores[] = 'Selecciona el tipo de pelaje.';
	}
	$fecha_ts = strtotime( $valores['fecha_nacimiento'] );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $valores['fecha_nacimiento'] ) || ! $fecha_ts || $fecha_ts > current_time( 'timestamp' ) ) {
		$errores[] = 'La fecha de nacimiento no es válida.';
	}
	if ( in_array( $valores['especie'], array( 'perro', 'gato' ), true )
		&& ! in_array( $valores['raza'], pp_mascotas_get_razas( $valores['especie'] ), true ) ) {
		$errores[] = 'Selecciona una raza válida.';
	}
	if ( $peso < 0.1 || $peso > 150 ) {
		$errores[] = 'Ingresa un peso válido (0.1 a 150 kg).';
	}
	if ( $errores ) {
		wp_send_json_error( array( 'message' => implode( ' ', $errores ) ) );
	}

	$mascota_id = wp_insert_post( array(
		'post_title'  => $valores['nombre'],
		'post_type'   => 'pp_mascota',
		'post_status' => 'publish',
		'post_author' => get_current_user_id(),
	) );
	if ( ! $mascota_id || is_wp_error( $mascota_id ) ) {
		wp_send_json_error( array( 'message' => 'No pudimos crear la mascota. Inténtalo de nuevo.' ) );
	}
	foreach ( array( 'especie', 'sexo', 'fecha_nacimiento', 'raza', 'pelaje' ) as $campo ) {
		update_post_meta( $mascota_id, '_pp_' . $campo, $valores[ $campo ] );
	}
	update_post_meta( $mascota_id, '_pp_peso', $peso );

	wp_send_json_success( array(
		'id'      => $mascota_id,
		'nombre'  => $valores['nombre'],
		'especie' => $valores['especie'],
		'raza'    => $valores['raza'],
		// pelaje y peso: los usa el filtrado de servicios por mascota.
		'pelaje'  => $valores['pelaje'],
		'peso'    => $peso,
	) );
}

/** Resumen corto de una mascota para el mensaje de la reserva. */
function pp_mascotas_resumen( $mascota_id ) {
	$especie = get_post_meta( $mascota_id, '_pp_especie', true );
	$peso    = get_post_meta( $mascota_id, '_pp_peso', true );
	$pelaje  = get_post_meta( $mascota_id, '_pp_pelaje', true );
	$partes  = array_filter( array(
		'gato' === $especie ? 'Gato' : 'Perro',
		get_post_meta( $mascota_id, '_pp_raza', true ),
		'hembra' === get_post_meta( $mascota_id, '_pp_sexo', true ) ? 'Hembra' : 'Macho',
		pp_mascotas_edad_legible( get_post_meta( $mascota_id, '_pp_fecha_nacimiento', true ) ),
		$peso ? $peso . ' kg' : '',
		$pelaje ? 'Pelo ' . $pelaje : '',
	) );
	return get_the_title( $mascota_id ) . ' (' . implode( ' · ', $partes ) . ')';
}

/** Valida y adjunta la mascota a la reserva justo antes de guardarla. */
add_filter( 'listeo_before_insert_booking_data', 'pp_mascotas_adjuntar_a_reserva' );
function pp_mascotas_adjuntar_a_reserva( $args ) {
	// Solo aplica al envío del popup de Booking Plus.
	if ( ( $_POST['action'] ?? '' ) !== 'lbp_submit_booking' ) {
		return $args;
	}

	$user_id = get_current_user_id();

	// Invitados: por decisión de producto pueden reservar sin mascota (botón
	// "Continuar sin mascota" del gate) y no tienen mascotas que validar.
	if ( ! $user_id ) {
		return $args;
	}

	// FIX 2026-07-10 (server-side): antes la obligatoriedad dependía de que
	// llegara pp_mascota_check — un campo que inyecta NUESTRO JS — así que
	// borrándolo (DevTools / POST directo) se reservaba sin mascota. Ahora,
	// con el ajuste global activo, la mascota es obligatoria en el servidor
	// para usuarios logueados, llegue o no el campo del cliente. Si el
	// ajuste está apagado, se respeta el comportamiento anterior (solo
	// valida si el selector estuvo presente).
	if ( ! pp_mascotas_reserva_activa() && empty( $_POST['pp_mascota_check'] ) ) {
		return $args;
	}

	$mascota_id = absint( $_POST['pp_mascota_reserva'] ?? 0 );

	$valida = false;
	if ( $mascota_id ) {
		$p = get_post( $mascota_id );
		// Propiedad contra el usuario REAL de la sesión (dato del servidor),
		// no contra bookings_author (valor derivado de la petición).
		$valida = $p && 'pp_mascota' === $p->post_type && 'publish' === $p->post_status && (int) $p->post_author === $user_id;
	}
	if ( ! $valida ) {
		wp_send_json_error( array(
			'message' => 'Selecciona la mascota para la que es la reserva. Si aún no la has registrado, usa "Crear nueva mascota" en este mismo formulario.',
		) );
	}

	$comment = json_decode( $args['comment'] ?? '', true );
	if ( is_array( $comment ) ) {
		$sobre = get_post_meta( $mascota_id, '_pp_sobre', true );

		$comment['mascota'] = array(
			'id'      => $mascota_id,
			'nombre'  => get_the_title( $mascota_id ),
			'especie' => get_post_meta( $mascota_id, '_pp_especie', true ),
			'raza'    => get_post_meta( $mascota_id, '_pp_raza', true ),
			'sexo'    => get_post_meta( $mascota_id, '_pp_sexo', true ),
			'edad'    => pp_mascotas_edad_legible( get_post_meta( $mascota_id, '_pp_fecha_nacimiento', true ) ),
			'peso'    => get_post_meta( $mascota_id, '_pp_peso', true ),
			'pelaje'  => get_post_meta( $mascota_id, '_pp_pelaje', true ),
		);

		// El resumen viaja dentro del mensaje: es lo que el prestador (y el
		// cliente) ven en sus paneles de reservas y en los correos.
		$linea = '🐾 Mascota: ' . pp_mascotas_resumen( $mascota_id );
		if ( $sobre ) {
			$linea .= "\nSobre la mascota: " . mb_substr( $sobre, 0, 300 );
		}
		$comment['message'] = trim( ( $comment['message'] ?? '' ) . "\n\n" . $linea );

		$args['comment'] = wp_json_encode( $comment );
	}

	// Para que listeo_after_insert_booking sepa qué mascota guardar como meta.
	$GLOBALS['pp_mascota_reserva_actual'] = $mascota_id;

	return $args;
}

/** Guarda la mascota como meta estructurada de la reserva. */
add_action( 'listeo_after_insert_booking', 'pp_mascotas_meta_reserva', 10, 2 );
function pp_mascotas_meta_reserva( $booking_id, $args ) {
	if ( empty( $GLOBALS['pp_mascota_reserva_actual'] ) || ! $booking_id ) {
		return;
	}
	// Inserción directa en wp_bookings_meta: la función update_booking_meta
	// de listeo-core no puede CREAR metas nuevas (delegan en add_metadata,
	// que exige $wpdb->bookingmeta registrado y nadie lo registra).
	// get_booking_meta sí lee directo de la tabla, así que la lectura funciona.
	global $wpdb;
	$wpdb->insert( $wpdb->prefix . 'bookings_meta', array(
		'booking_id' => (int) $booking_id,
		'meta_key'   => '_pp_mascota_id',
		'meta_value' => (int) $GLOBALS['pp_mascota_reserva_actual'],
	) );
	unset( $GLOBALS['pp_mascota_reserva_actual'] );
}

/* -------------------------------------------------------------------------
 * 11. Ajustes: wp-admin → Mascotas → Ajustes
 * ---------------------------------------------------------------------- */

// "Ajustes" ya NO es un ítem del menú lateral: es un TAB dentro de "Reservas"
// (ver pp_pers_pagina_reservas en pp-personalizacion.php, que llama a
// pp_mascotas_ajustes_form()). El handler redirige al tab correspondiente.
add_action( 'admin_init', 'pp_mascotas_admin_ajustes_handler' );
function pp_mascotas_admin_ajustes_handler() {
	if ( empty( $_POST['pp_ajustes_action'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'pp_ajustes_admin', 'pp_ajustes_nonce' );

	update_option( 'pp_mascotas_reserva_activa', empty( $_POST['pp_reserva_activa'] ) ? '0' : '1' );

	$cats = array_filter( array_map( 'absint', (array) ( $_POST['pp_precio_categorias'] ?? array() ) ) );
	update_option( 'pp_mascotas_precio_categorias', $cats );

	wp_safe_redirect( add_query_arg(
		array( 'tab' => 'ajustes', 'pp_ok' => 'guardado' ),
		admin_url( 'admin.php?page=pp-reservas' )
	) );
	exit;
}

/**
 * Formulario de Ajustes (mascota en las reservas / precio según la mascota).
 * Es EMBEBIBLE: no imprime el <div class="wrap"> ni <h1> — eso lo pone la
 * página contenedora (Reservas → tab Ajustes).
 */
function pp_mascotas_ajustes_form() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$activa    = pp_mascotas_reserva_activa();
	$marcadas  = array_map( 'absint', (array) get_option( 'pp_mascotas_precio_categorias', array() ) );
	$terminos  = get_terms( array( 'taxonomy' => 'listing_category', 'hide_empty' => false ) );
	?>
		<?php if ( 'guardado' === sanitize_key( $_GET['pp_ok'] ?? '' ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Ajustes guardados.</p></div>
		<?php endif; ?>

		<form method="post" style="max-width:720px">
			<?php wp_nonce_field( 'pp_ajustes_admin', 'pp_ajustes_nonce' ); ?>
			<input type="hidden" name="pp_ajustes_action" value="guardar">

			<h2 style="margin-top:24px">Mascota en las reservas</h2>
			<label style="display:flex;gap:8px;align-items:flex-start;margin:8px 0 4px">
				<input type="checkbox" name="pp_reserva_activa" value="1" <?php checked( $activa ); ?>>
				<span><strong>Pedir la mascota al reservar</strong><br>
				<span class="description">Muestra el paso "¿Para cuál de tus peludos es la reserva?" al inicio del formulario de reserva, en todos los listados. Si lo desactivas, las reservas vuelven a funcionar sin mascota.</span></span>
			</label>

			<h2 style="margin-top:28px">Precio según la mascota <span style="font-weight:400;color:#666;font-size:13px">(preparación — aún no calcula)</span></h2>
			<p class="description" style="max-width:640px">Marca las categorías de servicio cuyas reservas usarán los datos de la mascota (peso, tamaño, pelaje) para calcular el precio. La lógica de cálculo se implementará más adelante; por ahora esta marca deja todo preparado. Si marcas una categoría, aplica también a sus subcategorías.</p>

			<?php if ( is_wp_error( $terminos ) || ! $terminos ) : ?>
				<p><em>No hay categorías de listado disponibles.</em></p>
			<?php else : ?>
				<div style="columns:2;max-width:680px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 16px;margin-top:8px">
					<?php
					// Lista jerárquica simple (padres primero, hijas con sangría).
					$por_padre = array();
					foreach ( $terminos as $t ) {
						$por_padre[ $t->parent ][] = $t;
					}
					$pintar = function ( $padre, $nivel ) use ( &$pintar, $por_padre, $marcadas ) {
						if ( empty( $por_padre[ $padre ] ) ) {
							return;
						}
						foreach ( $por_padre[ $padre ] as $t ) {
							echo '<label style="display:block;margin:3px 0;padding-left:' . ( $nivel * 18 ) . 'px">';
							echo '<input type="checkbox" name="pp_precio_categorias[]" value="' . esc_attr( $t->term_id ) . '" ' . checked( in_array( $t->term_id, $marcadas, true ), true, false ) . '> ';
							echo esc_html( $t->name );
							echo '</label>';
							$pintar( $t->term_id, $nivel + 1 );
						}
					};
					$pintar( 0, 0 );
					?>
				</div>
			<?php endif; ?>

			<p style="margin-top:20px"><button class="button button-primary">Guardar ajustes</button></p>
		</form>
	<?php
}

/**
 * Sube una imagen de $_FILES[$campo] y la adjunta a la mascota.
 * Solo acepta JPG/PNG/WEBP. Devuelve el ID del adjunto o WP_Error.
 */
function pp_mascotas_subir_imagen( $campo, $mascota_id ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	return media_handle_upload( $campo, $mascota_id, array(), array(
		'test_form' => false,
		'mimes'     => array(
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'webp'     => 'image/webp',
		),
	) );
}
