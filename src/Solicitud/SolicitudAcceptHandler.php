<?php
namespace GiVendor\GiPlugin\Solicitud;

use WP_Post;
use WP_User;
use WP_Error;
use GiVendor\GiPlugin\Order\OfferOrderCreator;
use GiVendor\GiPlugin\Utils\RfqLogger;

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
     * Acepta una cotización para una solicitud y crea la orden de WooCommerce
     * @return array|WP_Error Array con datos de éxito o WP_Error si falla
     */
    public static function accept(WP_User $user, WP_Post $solicitud, WP_Post $cotizacion) {
        RfqLogger::logCotizacionAcceptance('accept_initiated', $cotizacion->ID, $solicitud->ID, $user->ID);

        // Validar primero con can_accept(). Si no se puede, devolver WP_Error.
        if (!self::can_accept($user, $solicitud, $cotizacion)) {
            RfqLogger::error('No se puede aceptar la cotización - validación fallida', [
                'user_id' => $user->ID,
                'solicitud_id' => $solicitud->ID,
                'cotizacion_id' => $cotizacion->ID
            ]);
            return new WP_Error('cannot_accept', __('No se puede aceptar esta cotización.', 'rfq-manager-woocommerce'));
        }

        // Crear orden de WooCommerce antes de cambiar estados
        $order_id = OfferOrderCreator::create_from_accepted_offer($user, $solicitud, $cotizacion);
        
        if (is_wp_error($order_id)) {
            RfqLogger::order('Error creando orden: ' . $order_id->get_error_message(), RfqLogger::LEVEL_ERROR, [
                'cotizacion_id' => $cotizacion->ID,
                'solicitud_id' => $solicitud->ID,
                'user_id' => $user->ID,
                'error_code' => $order_id->get_error_code()
            ]);
            return $order_id;
        }

        RfqLogger::order('Orden creada exitosamente', RfqLogger::LEVEL_SUCCESS, [
            'order_id' => $order_id,
            'cotizacion_id' => $cotizacion->ID,
            'solicitud_id' => $solicitud->ID,
            'user_id' => $user->ID
        ]);

        // Cambiar estado de cotización a rfq-accepted
        $cotizacion_update = wp_update_post([
            'ID' => $cotizacion->ID,
            'post_status' => 'rfq-accepted',
        ], true);
        
        if (is_wp_error($cotizacion_update)) {
            error_log('[RFQ] Error actualizando estado de cotización: ' . $cotizacion_update->get_error_message());
            // Intentar limpiar la orden creada
            wp_delete_post($order_id, true);
            return $cotizacion_update;
        }

        // Cambiar estado de solicitud a rfq-accepted
        $solicitud_update = wp_update_post([
            'ID' => $solicitud->ID,
            'post_status' => 'rfq-accepted',
        ], true);
        
        if (is_wp_error($solicitud_update)) {
            error_log('[RFQ] Error actualizando estado de solicitud: ' . $solicitud_update->get_error_message());
            // Intentar rollback
            wp_update_post(['ID' => $cotizacion->ID, 'post_status' => 'publish'], true);
            wp_delete_post($order_id, true);
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
                
                if (is_wp_error($historic_update)) {
                    error_log('[RFQ] Error marcando cotización como histórica: ' . $cid);
                }
            }
        }

        // Agregar referencia de la orden en la cotización aceptada
        update_post_meta($cotizacion->ID, '_rfq_generated_order_id', $order_id);

        // Disparar hook de aceptación con todos los datos
        RfqLogger::logHook('rfq_cotizacion_accepted', [$cotizacion->ID, $solicitud->ID, $order_id, $user->ID], 'firing');
        do_action('rfq_cotizacion_accepted', $cotizacion->ID, $solicitud->ID, $order_id, $user->ID);
        
        // Nuevo hook específico para aceptación por usuario (Fase 0)
        RfqLogger::logHook('rfq_cotizacion_accepted_by_user', [$cotizacion->ID, $solicitud->ID, $order_id, $user->ID], 'firing');
        do_action('rfq_cotizacion_accepted_by_user', $cotizacion->ID, $solicitud->ID, $order_id, $user->ID);

        RfqLogger::logCotizacionAcceptance('process_completed', $cotizacion->ID, $solicitud->ID, $user->ID, $order_id);

        // Obtener URL de checkout
        $checkout_url = OfferOrderCreator::get_checkout_url($order_id);
        RfqLogger::order('URL de checkout generada', RfqLogger::LEVEL_INFO, [
            'order_id' => $order_id,
            'checkout_url' => $checkout_url
        ]);

        // Retornar datos para redirección
        $result = [
            'success' => true,
            'order_id' => $order_id,
            'checkout_url' => $checkout_url,
            'message' => __('Oferta aceptada exitosamente. Redirigiendo al pago...', 'rfq-manager-woocommerce')
        ];
        
        error_log('[RFQ] Datos de retorno: ' . json_encode($result));
        return $result;
    }
}
