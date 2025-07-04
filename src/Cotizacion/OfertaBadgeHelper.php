<?php 

namespace GiVendor\GiPlugin\Cotizacion;

class OfertaBadgeHelper
{
    public static function should_show_new_badge($cotizacion, $user_id, $solicitud = null): bool {
        if (!$solicitud) {
            $solicitud_id = get_post_meta($cotizacion->ID, '_solicitud_parent', true);
            $solicitud = get_post($solicitud_id);
        }
        if (!$solicitud || (int)$solicitud->post_author !== (int)$user_id) return false;
        if ($cotizacion->post_status !== 'publish') return false;
        if (!\GiVendor\GiPlugin\Solicitud\SolicitudStatusHelper::is_pending($solicitud)
            && !\GiVendor\GiPlugin\Solicitud\SolicitudStatusHelper::is_active($solicitud)) return false;
        $meta_key = '_oferta_vista_' . $user_id;
        return !get_post_meta($cotizacion->ID, $meta_key, true);
    }

    public static function mark_as_seen($cotizacion_id, $user_id): void {
        $meta_key = '_oferta_vista_' . $user_id;
        update_post_meta($cotizacion_id, $meta_key, 1);
    }

    public static function render_new_badge(): string {
        return '<span class="rfq-badge-nueva-oferta">' . esc_html__('Nueva oferta', 'rfq-manager-woocommerce') . '</span>';
    }
}
