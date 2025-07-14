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
        $visto_timestamp = (int) get_post_meta($cotizacion->ID, $meta_key, true);
        $historial = get_post_meta($cotizacion->ID, '_cotizacion_historial', true);

        if (!is_array($historial) || empty($historial)) {
            // No hay historial, usar la fecha de creación/modificación del post
            $cotizacion_timestamp = strtotime($cotizacion->post_modified);
            return (!$visto_timestamp || $cotizacion_timestamp > $visto_timestamp);
        }

        // Ordenar por fecha descendente y tomar la última
        usort($historial, function($a, $b) {
            return strtotime($b['fecha']) - strtotime($a['fecha']);
        });
        $ultimo_cambio = $historial[0];
        $historial_timestamp = isset($ultimo_cambio['fecha']) ? strtotime($ultimo_cambio['fecha']) : 0;

        // Mostrar badge si nunca fue vista o si la cotización fue modificada después de la última vista
        return (!$visto_timestamp || $historial_timestamp > $visto_timestamp);
    }

    // public static function should_show_new_badge($cotizacion, $user_id, $solicitud = null): bool {
    //     if (!$solicitud) {
    //         $solicitud_id = get_post_meta($cotizacion->ID, '_solicitud_parent', true);
    //         $solicitud = get_post($solicitud_id);
    //     }
    //     if (!$solicitud || (int)$solicitud->post_author !== (int)$user_id) return false;
    //     if ($cotizacion->post_status !== 'publish') return false;
    //     if (!\GiVendor\GiPlugin\Solicitud\SolicitudStatusHelper::is_pending($solicitud)
    //         && !\GiVendor\GiPlugin\Solicitud\SolicitudStatusHelper::is_active($solicitud)) return false;
    //     $meta_key = '_oferta_vista_' . $user_id;
    //     $visto_timestamp = (int) get_post_meta($cotizacion->ID, $meta_key, true);
    //     $historial = get_post_meta($cotizacion->ID, '_cotizacion_historial', true);
    //     if (!is_array($historial) || empty($historial)) {
    //         // Si no hay historial, no mostrar badge por seguridad
    //         return false;
    //     }
    //     // Ordenar por fecha descendente y tomar la última
    //     usort($historial, function($a, $b) {
    //         return strtotime($b['fecha']) - strtotime($a['fecha']);
    //     });
    //     $ultimo_cambio = $historial[0];
    //     $historial_timestamp = isset($ultimo_cambio['fecha']) ? strtotime($ultimo_cambio['fecha']) : 0;
    //     // Mostrar badge si nunca fue vista o si la cotización fue modificada después de la última vista
    //     if (!$visto_timestamp || $historial_timestamp > $visto_timestamp) {
    //         return true;
    //     }
    //     return false;
    // }

    public static function mark_as_seen($cotizacion_id, $user_id): void {
        $meta_key = '_oferta_vista_' . $user_id;
        // Guardar el timestamp actual como valor de visto
        update_post_meta($cotizacion_id, $meta_key, current_time('timestamp'));
    }

    public static function render_new_badge(): string {
        return '<span class="rfq-nueva-oferta">' . esc_html__('Nueva oferta', 'rfq-manager-woocommerce') . '</span>';
    }
}
