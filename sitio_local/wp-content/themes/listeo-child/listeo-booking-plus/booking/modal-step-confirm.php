<?php

/**
 * Override de Parche Peludo — Paso "Confirmar" del popup de reserva.
 *
 * Ajuste MÍNIMO, sin duplicar el formulario: cuando el cliente todavía no
 * tiene país de facturación guardado (p. ej. un cliente nuevo o sin cuenta),
 * asumimos el país base de la tienda (Colombia). Así el campo "Departamento"
 * (billing_state) se renderiza como <select> con los 33 departamentos que
 * trae WooCommerce, en lugar de un cuadro de texto libre donde se puede
 * escribir cualquier cosa.
 *
 * Después delega en la plantilla ORIGINAL del plugin, para no copiar toda la
 * lógica del formulario: si Booking Plus actualiza esa plantilla, este
 * override sigue funcionando (solo aporta el valor por defecto del país).
 *
 * @var object $data
 *
 * @package Listeo_Booking_Plus
 */

if (! defined('ABSPATH')) {
    exit;
}

// Si el cliente no tiene país de facturación, usar el país base de la tienda
// (Parche Peludo lo tiene en Colombia). Solo actúa cuando está vacío: si el
// cliente ya tiene un país guardado, se respeta el suyo.
if (
    isset($data)
    && empty($data->user_billing_country)
    && function_exists('WC') && WC() && WC()->countries
) {
    $data->user_billing_country = WC()->countries->get_base_country();
}

// NOTA: el arreglo VISUAL del <select> (para que se vea igual que los campos de
// texto; Booking Plus no estiliza los <select>) vive en el tema hijo, encolado
// en el <head> desde functions.php (listeo_child_enqueue_styles → wp_add_inline_style),
// para garantizar presencia y ganar en especificidad. Aquí solo va el ajuste de datos.

// Renderiza la plantilla original del plugin con $data ya ajustado.
$pp_lbp_confirm_original = defined('LBP_PLUGIN_DIR')
    ? LBP_PLUGIN_DIR . 'templates/booking/modal-step-confirm.php'
    : '';

if ($pp_lbp_confirm_original && file_exists($pp_lbp_confirm_original)) {
    include $pp_lbp_confirm_original;
}
