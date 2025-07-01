<?php
namespace GiVendor\GiPlugin\Shortcode;

use GiVendor\GiPlugin\Shortcode\Components\SolicitudFilters;

class SolicitudFiltersShortcode
{
    /**
     * Renderiza la cabecera de filtros para el usuario cliente (customer/subscriber).
     * Uso: [rfq_filters]
     * @param array $atts
     * @return string
     */
    public static function render_filters($atts = []): string
    {
        if (!is_user_logged_in()) {
            return '<p class="rfq-error">' . __('Debes iniciar sesión para ver los filtros.', 'rfq-manager-woocommerce') . '</p>';
        }
        $user = wp_get_current_user();
        $roles = (array) $user->roles;
        $selected_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $selected_order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : '';

        if (in_array('customer', $roles) || in_array('subscriber', $roles)) {
            $statuses = SolicitudFilters::get_status_counts($user->ID);
            return SolicitudFilters::render_filter_header($statuses, $selected_status, $selected_order);
        }
        if (in_array('proveedor', $roles) || in_array('administrator', $roles)) {
            return SolicitudFilters::render_provider_header($selected_status, $selected_order);
        }
        // Si no tiene ninguno de los roles válidos, mostrar error o vacío
        return '<p class="rfq-error">' . __('No tienes permisos para ver los filtros.', 'rfq-manager-woocommerce') . '</p>';
    }
}
