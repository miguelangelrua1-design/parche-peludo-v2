<?php
/**
 * Listeo Child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package listeo-child
 */

function listeo_child_enqueue_styles() {
    // Cargar estilo del tema padre
    wp_enqueue_style( 'listeo-parent-style', get_template_directory_uri() . '/style.css' );

    // Cargar estilo del tema hijo (hereda y sobrescribe con tokens de marca V2)
    wp_enqueue_style( 'listeo-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array( 'listeo-parent-style' ),
        wp_get_theme()->get('Version')
    );
}
add_action( 'wp_enqueue_scripts', 'listeo_child_enqueue_styles', 99 );

/**
 * Personalizaciones y ganchos adicionales de Parche Peludo V2
 * Agrega aqui funciones personalizadas para integraciones seguras.
 */
