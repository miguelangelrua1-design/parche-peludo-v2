<?php
/**
 * Búsqueda por franjas — assets del front.
 *
 * El panel se inyecta por JS solo si la página tiene resultados de listados
 * (#listeo-listings-container). Los assets pesan ~6 KB; se encolan en las
 * vistas de listados (archivo, taxonomías) y en cualquier página, porque las
 * plantillas de búsqueda de Listeo también son páginas normales — el JS
 * decide en el navegador si hay dónde pintarse.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Layout "barra superior" (2026-07-12, decisión de Miguel): en las páginas de
   resultados con mapa (Directorio/Guardería), la fila de Mostrar Filtros +
   Disponibilidad se convierte en una BARRA a todo lo ancho entre el header y
   las columnas; el mapa arranca debajo de la barra. Como la página no hace
   scroll (el scroll es interno de la columna de resultados), la barra queda
   fija por sí sola. El body lleva esta clase solo donde aplica: el CSS/JS de
   la barra no toca Adopción / Mascotas Perdidas ni otras páginas. */
add_filter( 'body_class', 'pp_franjas_body_class' );
function pp_franjas_body_class( $classes ) {
	if ( pp_franjas_esta_activo() && pp_franjas_reemplaza_carrusel() && pp_franjas_contexto_aplica() ) {
		$classes[] = 'pp-barra-superior';
	}
	return $classes;
}

add_action( 'wp_enqueue_scripts', 'pp_franjas_assets', 150 );
function pp_franjas_assets() {
	if ( is_admin() || ! pp_franjas_esta_activo() ) {
		return;
	}
	// El panel es de Directorio/Guardería: en contextos de Adopción o
	// Mascotas Perdidas ni siquiera se cargan los assets.
	if ( ! pp_franjas_contexto_aplica() ) {
		return;
	}

	wp_enqueue_style(
		'pp-franjas',
		PP_FRANJAS_URL . 'css/pp-franjas.css',
		array(),
		PP_FRANJAS_VERSION
	);
	// jquery-ui-datepicker: en DESKTOP los campos de fecha usan este calendario
	// (estilizado con la marca en el CSS del plugin). En móvil el JS lo omite y
	// deja el selector nativo del sistema. La librería la trae WordPress; si ya
	// está en la página (Listeo la usa), no se duplica.
	wp_enqueue_script(
		'pp-franjas',
		PP_FRANJAS_URL . 'js/pp-franjas.js',
		array( 'jquery', 'jquery-ui-datepicker' ),
		PP_FRANJAS_VERSION,
		true
	);

	$hoy = new DateTime( 'today', wp_timezone() );
	$max = ( clone $hoy )->modify( '+18 months' );

	// Filtro activo en la petición actual (para pintar el estado al cargar).
	$franja   = pp_franjas_leer_parametros();
	$contexto = pp_franjas_contexto_termino();

	wp_localize_script( 'pp-franjas', 'PP_FRANJAS', array(
		'horaMin'   => (string) get_option( 'pp_franjas_hora_min', '08:00' ),
		'paso'      => (int) get_option( 'pp_franjas_paso', 30 ),
		'fechaMin'  => $hoy->format( 'Y-m-d' ),
		'fechaMax'  => $max->format( 'Y-m-d' ),
		'reemplazo' => pp_franjas_reemplaza_carrusel() ? 1 : 0,
		// Catálogo para el selector: slug => {nombre, modo, icono}.
		'servicios'   => pp_franjas_servicios_para_panel(),
		'iconoTodos'  => pp_franjas_icono_todos(),
		// Servicios CON oferta en el contexto actual (categoría + sus hijas,
		// o todo el sitio en el archivo): los demás salen deshabilitados
		// como "(sin resultados)".
		'oferta'    => array_values( pp_franjas_servicios_con_oferta( $contexto['taxonomy'], $contexto['term_id'] ) ),
		// Estado inicial (si la página llegó con el filtro en la URL).
		'modo'      => $franja ? $franja['modo'] : '',
		'servicio'  => $franja ? $franja['servicio'] : '',
		'fecha'     => ( $franja && 'franja' === $franja['modo'] ) ? $franja['fecha'] : '',
		'hora'      => ( $franja && 'franja' === $franja['modo'] ) ? $franja['hora'] : '',
		'entrada'   => ( $franja && 'rango' === $franja['modo'] ) ? $franja['entrada'] : '',
		'salida'    => ( $franja && 'rango' === $franja['modo'] ) ? $franja['salida'] : '',
	) );
}
