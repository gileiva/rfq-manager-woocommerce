<?php
namespace GiVendor\GiPlugin\Solicitud;

use WP_Post;
use WP_User;
use WP_Error;

class SolicitudAcceptHandler {
    public static function can_accept(WP_User $user, WP_Post $solicitud, WP_Post $cotizacion): bool {
        // Validaciones reales para aceptar una cotización
        if (!$user || !$user->ID) {
            return false;
        }
        if ((int)$solicitud->post_author !== (int)$user->ID) {
            return false;
        }
        $allowed_statuses = ['rfq-pending', 'rfq-active'];
        if (!in_array($solicitud->post_status, $allowed_statuses, true)) {
            return false;
        }
        $parent_id = get_post_meta($cotizacion->ID, '_solicitud_parent', true);
        if ((int)$parent_id !== (int)$solicitud->ID) {
            return false;
        }
        if (in_array($cotizacion->post_status, ['rfq-historic', 'rfq-accepted'], true)) {
            return false;
        }
        $accepted = get_posts([
            'post_type' => 'cotizacion',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => '_solicitud_parent',
                    'value' => $solicitud->ID,
                ],
            ],
            'post_status' => 'rfq-accepted',
            'fields' => 'ids',
        ]);
        if (!empty($accepted)) {
            return false;
        }
        return true;
    }

    /**
     * Acepta una cotización para una solicitud.
     * @return true|WP_Error
     */
    public static function accept(WP_User $user, WP_Post $solicitud, WP_Post $cotizacion) {
        // Validar primero con can_accept(). Si no se puede, devolver WP_Error.
        if (!self::can_accept($user, $solicitud, $cotizacion)) {
            return new WP_Error('cannot_accept', __('No se puede aceptar esta cotización.', 'rfq-manager-woocommerce'));
        }

        // Cambiar estado de cotización a rfq-accepted
        $cotizacion_update = wp_update_post([
            'ID' => $cotizacion->ID,
            'post_status' => 'rfq-accepted',
        ], true);
        if (is_wp_error($cotizacion_update)) {
            return $cotizacion_update;
        }

        // Cambiar estado de solicitud a rfq-accepted
        $solicitud_update = wp_update_post([
            'ID' => $solicitud->ID,
            'post_status' => 'rfq-accepted',
        ], true);
        if (is_wp_error($solicitud_update)) {
            return $solicitud_update;
        }

        // Marcar las demás cotizaciones como rfq-historic
        $cotizaciones = get_posts([
            'post_type' => 'cotizacion',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_solicitud_parent',
                    'value' => $solicitud->ID,
                ],
            ],
            'post_status' => ['publish', 'draft'],
            'fields' => 'ids',
        ]);
        foreach ($cotizaciones as $cid) {
            if ((int)$cid !== (int)$cotizacion->ID) {
                $historic_update = wp_update_post([
                    'ID' => $cid,
                    'post_status' => 'rfq-historic',
                ], true);
                // Si hay error, continuar con las demás, pero podrías loguear si lo deseas
            }
        }

        // Disparar hook de aceptación
        do_action('rfq_cotizacion_accepted', $cotizacion->ID, $solicitud->ID);
        return true;
    }
}
