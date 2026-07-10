<?php

/**
 * Booking Modal - Step 2: User Info & Confirm
 *
 * ── OVERRIDE de Parche Peludo (tema hijo) ──
 * Copiado del plugin listeo-booking-plus v1.0.9 usando su ruta oficial de
 * override. Cambios respecto al original, SOLO en el bloque de dirección:
 *  - Orden: Departamento → Ciudad → Dirección (antes la dirección iba primero).
 *  - Resumen de la dirección guardada + botón Editar (los campos quedan
 *    ocultos pero CON valores; la validación del plugin valida valores, no
 *    visibilidad, así que el envío funciona sin abrir los campos).
 *  - País fijo CO (hidden) cuando el campo país no está visible.
 * Si el plugin actualiza esta plantilla, comparar y re-aplicar. Todo lo demás
 * quedó idéntico al original.
 *
 * @var object $data
 *
 * @package Listeo_Booking_Plus
 */

if (! defined('ABSPATH')) {
    exit;
}

// Mirror Core's templates/booking.php prefill + required-field logic so the
// popup respects the same Theme Options ("Booking" → required fields and
// "Add address fields to booking form").
$req = isset($data->field_requirements) && is_array($data->field_requirements)
    ? $data->field_requirements
    : array();

$address_enabled   = ! empty($data->address_fields_enabled);
$address_displayed = isset($data->address_displayed) && is_array($data->address_displayed)
    ? $data->address_displayed
    : array();
$address_required  = isset($data->address_required) && is_array($data->address_required)
    ? $data->address_required
    : array();

$is_required  = function ($key) use ($req) {
    return ! empty($req[$key]);
};
$addr_visible = function ($key) use ($address_displayed) {
    return in_array($key, $address_displayed, true);
};
$addr_required = function ($key) use ($address_required) {
    return in_array($key, $address_required, true);
};
?>
<div class="lbp-step lbp-step-2" data-step="2">
    <div class="lbp-step-header">
        <?php
        $_popup_mode = isset( $data->popup_mode ) ? $data->popup_mode : 'resources';
        $_eyebrow    = ( 'listing' === $_popup_mode )
            ? __( 'Step 2 of 2', 'listeo-booking-plus' )
            : __( 'Step 3 of 3', 'listeo-booking-plus' );
        ?>
        <span class="lbp-step-eyebrow"><?php echo esc_html( $_eyebrow ); ?></span>
        <h3><?php esc_html_e('Your Information', 'listeo-booking-plus'); ?></h3>
        <p class="lbp-step-desc"><?php esc_html_e('Please provide your contact details to complete the booking.', 'listeo-booking-plus'); ?></p>
    </div>

    <div class="lbp-info-form">
        <?php
        /*
         * Guest registration (logged-out + "Allow user to book without
         * being logged in" enabled). Mirrors Core's templates/booking.php:
         * an account with role "guest" is created from these details at
         * submission, so the visitor can manage the booking later. The
         * username/password fields follow the same Theme Options as the
         * standard widget; captcha + consent render further down.
         */
        if ( ! empty( $data->guest_registration ) ) : ?>
            <div class="lbp-form-row lbp-form-row-full">
                <div class="lbp-guest-notice woocommerce-info">
                    <?php esc_html_e( 'Your account will be created automatically based on the data you provide below.', 'listeo-booking-plus' ); ?>
                    <?php esc_html_e( 'If you already have an account, please', 'listeo-booking-plus' ); ?>
                    <?php if ( ! empty( $data->guest_login_is_popup ) ) : ?>
                        <a href="#sign-in-dialog" class="popup-with-zoom-anim"><?php esc_html_e( 'log in', 'listeo-booking-plus' ); ?></a>.
                    <?php else : ?>
                        <a href="<?php echo esc_url( $data->guest_login_url ); ?>"><?php esc_html_e( 'log in', 'listeo-booking-plus' ); ?></a>.
                    <?php endif; ?>
                </div>
            </div>

            <?php if ( ! empty( $data->guest_show_username ) || ! empty( $data->guest_show_password ) ) : ?>
                <div class="lbp-form-row">
                    <?php if ( ! empty( $data->guest_show_username ) ) : ?>
                        <div class="lbp-field">
                            <label for="lbp-username">
                                <?php esc_html_e( 'Username', 'listeo-booking-plus' ); ?>
                                <span class="required">*</span>
                            </label>
                            <input type="text" id="lbp-username" name="username" value="" autocomplete="username" required>
                            <span class="lbp-field-error"></span>
                        </div>
                    <?php endif; ?>
                    <?php if ( ! empty( $data->guest_show_password ) ) : ?>
                        <div class="lbp-field">
                            <label for="lbp-password">
                                <?php esc_html_e( 'Password', 'listeo-booking-plus' ); ?>
                                <span class="required">*</span>
                            </label>
                            <input type="password" id="lbp-password" name="password" value="" autocomplete="new-password" required>
                            <span class="lbp-field-error"></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="lbp-form-row">
            <div class="lbp-field">
                <label for="lbp-firstname">
                    <?php esc_html_e('First Name', 'listeo-booking-plus'); ?>
                    <?php if ($is_required('first_name')) : ?><span class="required">*</span><?php endif; ?>
                </label>
                <input type="text" id="lbp-firstname" name="firstname" value="<?php echo esc_attr($data->user_first_name); ?>" <?php if ($is_required('first_name')) echo ' required'; ?>>
                <span class="lbp-field-error"></span>
            </div>
            <div class="lbp-field">
                <label for="lbp-lastname">
                    <?php esc_html_e('Last Name', 'listeo-booking-plus'); ?>
                    <?php if ($is_required('last_name')) : ?><span class="required">*</span><?php endif; ?>
                </label>
                <input type="text" id="lbp-lastname" name="lastname" value="<?php echo esc_attr($data->user_last_name); ?>" <?php if ($is_required('last_name')) echo ' required'; ?>>
                <span class="lbp-field-error"></span>
            </div>
        </div>
        <div class="lbp-form-row">
            <div class="lbp-field">
                <label for="lbp-email">
                    <?php esc_html_e('Email', 'listeo-booking-plus'); ?>
                    <?php if ($is_required('email')) : ?><span class="required">*</span><?php endif; ?>
                </label>
                <input type="email" id="lbp-email" name="email" value="<?php echo esc_attr($data->user_email); ?>" <?php if ($is_required('email')) echo ' required'; ?>>
                <span class="lbp-field-error"></span>
            </div>
            <div class="lbp-field">
                <label for="lbp-phone">
                    <?php esc_html_e('Phone', 'listeo-booking-plus'); ?>
                    <?php if ($is_required('phone')) : ?><span class="required">*</span><?php endif; ?>
                </label>
                <input type="tel" id="lbp-phone" name="phone" value="<?php echo esc_attr($data->user_phone); ?>" <?php if ($is_required('phone')) echo ' required'; ?>>
                <span class="lbp-field-error"></span>
            </div>
        </div>

        <?php if ($address_enabled) : ?>
            <?php
            /*
             * ── Personalización Parche Peludo (override en el tema hijo) ──
             * Orden estándar de mercado: Departamento → Ciudad → Dirección.
             * - Ciudad se renderiza como <input> y ppv2-lbp-direccion.js la
             *   convierte en <select> dependiente del departamento (dataset
             *   ppv2-ciudades-co.js). Si el JS no corre, queda el input de
             *   texto y la reserva funciona igual.
             * - Si el usuario ya tiene dirección completa guardada (depto +
             *   ciudad + dirección), se muestra un RESUMEN con botón Editar y
             *   los campos van ocultos pero CON sus valores: la validación de
             *   lbp-booking.js valida valores (no visibilidad), así que el
             *   resumen no bloquea el envío.
             * - El país no se muestra (solo Colombia): viaja fijo como CO.
             * El resto de campos (empresa, código postal, país) conserva sus
             * condicionales originales por si se activan en Theme Options.
             */
            $ppv2_country = $data->user_billing_country ? $data->user_billing_country : 'CO';
            $ppv2_states  = (function_exists('WC') && WC() && WC()->countries)
                ? WC()->countries->get_states($ppv2_country)
                : array();
            $ppv2_state_label = ($data->user_billing_state && isset($ppv2_states[$data->user_billing_state]))
                ? $ppv2_states[$data->user_billing_state]
                : $data->user_billing_state;
            $ppv2_tiene_direccion = ('' !== (string) $data->user_billing_state
                && '' !== (string) $data->user_billing_city
                && '' !== (string) $data->user_billing_address_1);
            ?>

            <?php if ($addr_visible('billing_company')) : ?>
                <div class="lbp-form-row lbp-form-row-full">
                    <div class="lbp-field">
                        <label for="lbp-billing_company">
                            <?php esc_html_e('Company Name', 'listeo-booking-plus'); ?>
                            <?php if ($addr_required('billing_company')) : ?><span class="required">*</span><?php endif; ?>
                        </label>
                        <input type="text" id="lbp-billing_company" name="billing_company" value="<?php echo esc_attr($data->user_billing_company); ?>" <?php if ($addr_required('billing_company')) echo ' required'; ?>>
                        <span class="lbp-field-error"></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($ppv2_tiene_direccion) : ?>
                <div class="lbp-form-row lbp-form-row-full ppv2-lbp-addr-resumen" id="ppv2-lbp-addr-resumen">
                    <div class="ppv2-lbp-addr-resumen-caja">
                        <div class="ppv2-lbp-addr-resumen-texto">
                            <strong><?php echo esc_html__('Tu dirección', 'listeo-child'); ?></strong>
                            <span><?php
                                echo esc_html($ppv2_state_label) . ' · ' . esc_html($data->user_billing_city) . ' · ' . esc_html($data->user_billing_address_1);
                                if ('' !== (string) $data->user_billing_address_2) {
                                    echo ', ' . esc_html($data->user_billing_address_2);
                                }
                            ?></span>
                        </div>
                        <button type="button" class="ppv2-lbp-addr-editar" id="ppv2-lbp-addr-editar" aria-expanded="false"><?php echo esc_html__('Editar', 'listeo-child'); ?></button>
                    </div>
                </div>
            <?php endif; ?>

            <div class="ppv2-lbp-addr-campos" id="ppv2-lbp-addr-campos"<?php if ($ppv2_tiene_direccion) echo ' hidden'; ?>>

                <?php if ($ppv2_tiene_direccion) : ?>
                    <?php
                    /*
                     * "Cancelar" solo existe cuando hay un resumen al cual
                     * volver. No es un simple colapso: el JS RESTAURA los
                     * valores guardados antes de cerrar — si colapsara sin
                     * restaurar, el resumen mostraría la dirección vieja
                     * mientras el formulario enviaría la editada.
                     */
                    ?>
                    <div class="ppv2-lbp-addr-campos-head">
                        <span><?php echo esc_html__('Editar dirección', 'listeo-child'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($addr_visible('billing_country') && function_exists('WC') && WC() && WC()->countries) :
                    $country_required = $addr_required('billing_country');
                ?>
                    <div class="lbp-form-row lbp-form-row-full">
                        <div class="lbp-field lbp-field-country">
                            <label for="lbp-billing_country">
                                <?php esc_html_e('Country', 'listeo-booking-plus'); ?>
                                <?php if ($country_required) : ?><span class="required">*</span><?php endif; ?>
                            </label>
                            <?php $countries = WC()->countries->get_allowed_countries(); ?>
                            <select id="lbp-billing_country" name="billing_country" class="lbp-billing-country" <?php if ($country_required) echo ' required'; ?>>
                                <option value=""><?php esc_html_e('Select a country…', 'listeo-booking-plus'); ?></option>
                                <?php foreach ($countries as $code => $name) : ?>
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected($ppv2_country, $code); ?>>
                                        <?php echo esc_html($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="lbp-field-error"></span>
                        </div>
                    </div>
                <?php else : ?>
                    <input type="hidden" name="billing_country" value="<?php echo esc_attr($ppv2_country); ?>">
                <?php endif; ?>

                <?php if ($addr_visible('billing_state') || $addr_visible('billing_city')) : ?>
                    <div class="lbp-form-row">
                        <?php if ($addr_visible('billing_state')) :
                            $state_required = $addr_required('billing_state');
                        ?>
                            <div class="lbp-field lbp-field-state">
                                <label for="lbp-billing_state">
                                    <?php echo esc_html__('Departamento', 'listeo-child'); ?>
                                    <?php if ($state_required) : ?><span class="required">*</span><?php endif; ?>
                                </label>
                                <?php if (empty($ppv2_states)) : ?>
                                    <input type="text" id="lbp-billing_state" name="billing_state" value="<?php echo esc_attr($data->user_billing_state); ?>" <?php if ($state_required) echo ' required'; ?>>
                                <?php else : ?>
                                    <select id="lbp-billing_state" name="billing_state" class="lbp-billing-state" <?php if ($state_required) echo ' required'; ?>>
                                        <option value=""><?php echo esc_html__('Selecciona tu departamento…', 'listeo-child'); ?></option>
                                        <?php foreach ($ppv2_states as $code => $name) : ?>
                                            <option value="<?php echo esc_attr($code); ?>" <?php selected($data->user_billing_state, $code); ?>>
                                                <?php echo esc_html($name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                                <span class="lbp-field-error"></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($addr_visible('billing_city')) : ?>
                            <div class="lbp-field lbp-field-city">
                                <label for="lbp-billing_city">
                                    <?php echo esc_html__('Ciudad / Municipio', 'listeo-child'); ?>
                                    <?php if ($addr_required('billing_city')) : ?><span class="required">*</span><?php endif; ?>
                                </label>
                                <input type="text" id="lbp-billing_city" name="billing_city" value="<?php echo esc_attr($data->user_billing_city); ?>" <?php if ($addr_required('billing_city')) echo ' required'; ?>>
                                <span class="lbp-field-error"></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($addr_visible('billing_address_1') || $addr_visible('billing_address_2')) : ?>
                    <div class="lbp-form-row">
                        <?php if ($addr_visible('billing_address_1')) : ?>
                            <div class="lbp-field">
                                <label for="lbp-billing_address_1">
                                    <?php echo esc_html__('Dirección', 'listeo-child'); ?>
                                    <?php if ($addr_required('billing_address_1')) : ?><span class="required">*</span><?php endif; ?>
                                </label>
                                <input type="text" id="lbp-billing_address_1" name="billing_address_1" value="<?php echo esc_attr($data->user_billing_address_1); ?>" <?php if ($addr_required('billing_address_1')) echo ' required'; ?>>
                                <span class="lbp-field-error"></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($addr_visible('billing_address_2')) : ?>
                            <div class="lbp-field">
                                <label for="lbp-billing_address_2">
                                    <?php echo esc_html__('Apartamento, habitación, etc. (opcional)', 'listeo-child'); ?>
                                    <?php if ($addr_required('billing_address_2')) : ?><span class="required">*</span><?php endif; ?>
                                </label>
                                <input type="text" id="lbp-billing_address_2" name="billing_address_2" value="<?php echo esc_attr($data->user_billing_address_2); ?>" <?php if ($addr_required('billing_address_2')) echo ' required'; ?>>
                                <span class="lbp-field-error"></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($addr_visible('billing_postcode')) : ?>
                    <div class="lbp-form-row">
                        <div class="lbp-field">
                            <label for="lbp-billing_postcode">
                                <?php esc_html_e('Postcode/ZIP', 'listeo-booking-plus'); ?>
                                <?php if ($addr_required('billing_postcode')) : ?><span class="required">*</span><?php endif; ?>
                            </label>
                            <input type="text" id="lbp-billing_postcode" name="billing_postcode" value="<?php echo esc_attr($data->user_billing_postcode); ?>" <?php if ($addr_required('billing_postcode')) echo ' required'; ?>>
                            <span class="lbp-field-error"></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($ppv2_tiene_direccion) : ?>
                    <?php
                    /*
                     * "Cancelar" al FINAL del bloque de dirección (a la derecha,
                     * antes del chip de Mascota), como pidió Miguel. Solo existe
                     * cuando hay un resumen al cual volver. No es un colapso a
                     * secas: el JS RESTAURA los valores guardados antes de
                     * cerrar — si colapsara sin restaurar, el resumen mostraría
                     * la dirección vieja mientras el form enviaría la editada.
                     */
                    ?>
                    <div class="ppv2-lbp-addr-campos-foot">
                        <button type="button" class="ppv2-lbp-addr-cancelar" id="ppv2-lbp-addr-cancelar"><?php echo esc_html__('Cancelar', 'listeo-child'); ?></button>
                    </div>
                <?php endif; ?>

            </div><!-- /.ppv2-lbp-addr-campos -->
        <?php endif; ?>

        <?php
        /*
         * Attendee details (events only). Rendered dynamically by
         * lbp-booking.js (`buildAttendeeFields()`) — one row per ticket —
         * when the listing collects attendee names/emails
         * (`listeo_lbp_collect_attendee_*` + per-individual codes on).
         * Empty + hidden for every other booking type.
         */
        ?>
        <div class="lbp-form-row lbp-form-row-full lbp-attendee-fields-row" id="lbp-attendee-fields-row" style="display:none;">
            <h4 class="lbp-attendee-heading"><?php esc_html_e('Attendee details', 'listeo-booking-plus'); ?></h4>
            <div class="lbp-attendee-fields" id="lbp-attendee-fields"></div>
        </div>

        <div class="lbp-form-row lbp-form-row-full">
            <div class="lbp-field">
                <label for="lbp-message"><?php esc_html_e('Message (optional)', 'listeo-booking-plus'); ?></label>
                <textarea id="lbp-message" name="message" rows="3"></textarea>
            </div>
        </div>

        <?php
        /*
         * Per-listing-type custom booking fields from Listeo Editor →
         * Booking Fields (`listeo_{type}_booking_fields` option). The
         * helper renders Bootstrap-grid markup (col-md-X), which is
         * fine inside the popup since `.lbp-info-form` already grids
         * by row. Required attributes flagged in the editor flow
         * through to the rendered inputs, so the popup's submit-time
         * `required` sweep validates them automatically. The submit
         * handler in lbp-booking.js then sweeps every input inside
         * `.lbp-custom-booking-fields` and forwards the values.
         */
        if ( ! empty( $data->listing_type ) && function_exists( 'listeo_get_extra_booking_fields' ) ) {
            $_custom_fields_html = listeo_get_extra_booking_fields( $data->listing_type );
            if ( $_custom_fields_html ) {
                echo '<div class="lbp-form-row lbp-form-row-full lbp-custom-booking-fields-row">';
                echo '<div class="lbp-custom-booking-fields">';
                /* Output is built by the Core helper with esc_attr /
                   esc_html on field values — safe to echo. */
                echo $_custom_fields_html;
                echo '</div>';
                echo '</div>';
            }
        }
        ?>

        <?php
        /*
         * Captcha + consent for guest registration — same options and
         * markup conventions as Core's booking.php confirmation step. The
         * v3 token is fetched by lbp-booking.js right before submit
         * (action "login", which Core's verifier expects).
         */
        if ( ! empty( $data->guest_registration ) ) :
            $_guest_captcha = isset( $data->guest_captcha_version ) ? $data->guest_captcha_version : '';
        ?>
            <?php
            /*
             * Neutral container instead of the auto-render classes
             * (g-recaptcha / h-captcha / cf-turnstile): Magnific Popup
             * moves the modal in the DOM when opening, which reloads an
             * already-rendered captcha iframe and bricks the widget.
             * lbp-booking.js renders it explicitly each time the confirm
             * step is shown (renderGuestCaptcha()).
             */
            $_captcha_sitekeys = array(
                'v2'        => get_option( 'listeo_recaptcha_sitekey' ),
                'hcaptcha'  => get_option( 'listeo_hcaptcha_sitekey' ),
                'turnstile' => get_option( 'listeo_turnstile_sitekey' ),
            );
            if ( isset( $_captcha_sitekeys[ $_guest_captcha ] ) && $_captcha_sitekeys[ $_guest_captcha ] ) : ?>
                <div class="lbp-form-row lbp-form-row-full lbp-guest-captcha">
                    <div class="lbp-captcha-widget" id="lbp-captcha-widget" data-captcha="<?php echo esc_attr( $_guest_captcha ); ?>" data-sitekey="<?php echo esc_attr( $_captcha_sitekeys[ $_guest_captcha ] ); ?>"></div>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $data->guest_privacy_policy ) ) : ?>
                <div class="lbp-form-row lbp-form-row-full">
                    <div class="lbp-field lbp-field-checkbox checkboxes">
                        <input type="checkbox" id="lbp-privacy-policy" name="privacy_policy">
                        <label for="lbp-privacy-policy"><?php esc_html_e( 'I agree to the', 'listeo-booking-plus' ); ?> <a target="_blank" href="<?php echo esc_url( get_privacy_policy_url() ); ?>"><?php esc_html_e( 'Privacy Policy', 'listeo-booking-plus' ); ?></a></label>
                        <span class="lbp-field-error"></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $data->guest_terms ) ) : ?>
                <div class="lbp-form-row lbp-form-row-full">
                    <div class="lbp-field lbp-field-checkbox checkboxes">
                        <input type="checkbox" id="lbp-terms-conditions" name="terms_and_conditions">
                        <label for="lbp-terms-conditions"><?php esc_html_e( 'I agree to the', 'listeo-booking-plus' ); ?> <a target="_blank" href="<?php echo esc_url( get_permalink( get_option( 'listeo_terms_and_conditions_page' ) ) ); ?>"><?php esc_html_e( 'Terms and Conditions', 'listeo-booking-plus' ); ?></a></label>
                        <span class="lbp-field-error"></span>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
    /*
     * No in-form "Extras" box on this step. Selected extras are summarized
     * in the sidebar's Booking Summary "Extras" row (a compact count) with
     * the full itemized list shown on hover — see buildConfirmSummary() in
     * lbp-booking.js and `.lbp-summary-extras-tip` in modal-sidebar.php.
     */
    ?>

    <!-- Error message area -->
    <div class="lbp-error-message" id="lbp-error-message" style="display:none;"></div>

    <div class="lbp-step-actions">
        <button type="button" class="button lbp-btn-back" data-step="1">
            <i class="fa fa-arrow-left"></i> <?php esc_html_e('Back', 'listeo-booking-plus'); ?>
        </button>
        <button type="button" class="button lbp-btn-confirm" id="lbp-confirm-btn">
            <i class="fa fa-check"></i> <?php esc_html_e('Confirm Booking', 'listeo-booking-plus'); ?>
        </button>
    </div>
</div>