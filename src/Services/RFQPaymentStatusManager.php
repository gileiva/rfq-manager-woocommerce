<?php
/**
 * Helper para gestionar el estado de pago de ofertas RFQ
 *
 * @package    GiVendor\GiPlugin\Services
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Services;

/**
 * RFQPaymentStatusManager - Gestor del estado de pago de ofertas aceptadas
 *
 * Esta clase proporciona:
 * - Marcar ofertas como pendientes de pago al aceptar
 * - Actualizar estado a pagado cuando la orden se completa
 * - Consultar fácilmente el estado de pago desde frontend/backend
 * - Logging detallado de todas las transiciones
 *
 * @since 0.1.0
 */
class RFQPaymentStatusManager 
{
    /**
     * Meta key para el estado de pago de ofertas
     */
    const PAYMENT_STATUS_META_KEY = '_rfq_offer_paid';

    /**
     * Marca una oferta como pendiente de pago al ser aceptada
     * 
     * @param int $cotizacion_id ID de la cotización aceptada
     * @param int $solicitud_id ID de la solicitud relacionada
     * @param int $order_id ID de la orden WooCommerce creada
     * @since 0.1.0
     */
    public static function mark_offer_as_pending_payment(int $cotizacion_id, int $solicitud_id, int $order_id): void {
        // Marcar cotización como pendiente de pago
        update_post_meta($cotizacion_id, self::PAYMENT_STATUS_META_KEY, false);
        // error_log("[RFQ-PAGO] Cotización #{$cotizacion_id} marcada como pendiente de pago");

        // Marcar solicitud como pendiente de pago
        update_post_meta($solicitud_id, self::PAYMENT_STATUS_META_KEY, false);
        // error_log("[RFQ-PAGO] Solicitud #{$solicitud_id} marcada como pendiente de pago");

        // Guardar relación para tracking futuro
        update_post_meta($cotizacion_id, '_rfq_woocommerce_order_id', $order_id);
        update_post_meta($solicitud_id, '_rfq_woocommerce_order_id', $order_id);
        
        // error_log("[RFQ-PAGO] Relación establecida: Orden #{$order_id} ↔ Cotización #{$cotizacion_id} ↔ Solicitud #{$solicitud_id}");
    }

    /**
     * Marca una oferta como pagada cuando la orden se completa
     * 
     * @param int $order_id ID de la orden WooCommerce completada
     * @since 0.1.0
     */
    public static function mark_offer_as_paid(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            // error_log("[RFQ-PAGO] ERROR: Orden #{$order_id} no encontrada para marcar como pagada");
            return;
        }

        // Verificar si es una orden RFQ
        $cotizacion_id = $order->get_meta('_rfq_cotizacion_id');
        $solicitud_id = $order->get_meta('_rfq_solicitud_id');

        if (!$cotizacion_id || !$solicitud_id) {
            // error_log("[RFQ-PAGO] Orden #{$order_id} no es una orden RFQ - saltando actualización de pago");
            return;
        }

        // Marcar cotización como pagada
        update_post_meta($cotizacion_id, self::PAYMENT_STATUS_META_KEY, true);
        // error_log("[RFQ-PAGO] Cotización #{$cotizacion_id} marcada como PAGADA ✅");

        // Marcar solicitud como pagada
        update_post_meta($solicitud_id, self::PAYMENT_STATUS_META_KEY, true);
        // error_log("[RFQ-PAGO] Solicitud #{$solicitud_id} marcada como PAGADA ✅");

        // Log consolidado del cambio
        // error_log("[RFQ-PAGO] PAGO COMPLETADO: Orden #{$order_id} → Cotización #{$cotizacion_id} + Solicitud #{$solicitud_id} = PAGADAS");
    }

    /**
     * Consulta si una oferta ha sido pagada
     * 
     * @param int $post_id ID de la cotización o solicitud
     * @param string $post_type Tipo de post ('cotizacion' o 'solicitud') para logging
     * @return bool true si está pagada, false si no
     * @since 0.1.0
     */
    public static function is_offer_paid(int $post_id, string $post_type = 'post'): bool {
        $is_paid = (bool) get_post_meta($post_id, self::PAYMENT_STATUS_META_KEY, true);
        
        // error_log("[RFQ-PAGO] Consulta estado pago: {$post_type} #{$post_id} = " . ($is_paid ? 'PAGADA ✅' : 'PENDIENTE ⏳'));
        
        return $is_paid;
    }

    /**
     * Obtiene el estado de pago con información detallada
     * 
     * @param int $post_id ID de la cotización o solicitud
     * @return array Estado detallado del pago
     * @since 0.1.0
     */
    public static function get_payment_status_details(int $post_id): array {
        $is_paid = self::is_offer_paid($post_id, 'consulta_detallada');
        $order_id = get_post_meta($post_id, '_rfq_woocommerce_order_id', true);
        
        $details = [
            'is_paid' => $is_paid,
            'order_id' => $order_id ? (int) $order_id : null,
            'status_text' => $is_paid ? __('Pagada', 'rfq-manager-woocommerce') : __('Pendiente de pago', 'rfq-manager-woocommerce'),
            'status_class' => $is_paid ? 'paid' : 'pending',
            'show_pay_button' => !$is_paid && $order_id,
        ];
        
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $details['order_status'] = $order->get_status();
                $details['order_total'] = $order->get_total();
                $details['order_currency'] = $order->get_currency();
                $details['pay_url'] = $order->get_checkout_payment_url();
            }
        }
        
        // error_log("[RFQ-PAGO] Detalles estado pago para post #{$post_id}: " . json_encode($details));
        
        return $details;
    }

    /**
     * Obtiene todas las ofertas pendientes de pago
     * 
     * @param string $post_type Tipo de post ('cotizacion' o 'solicitud')
     * @return array IDs de posts con pagos pendientes
     * @since 0.1.0
     */
    public static function get_pending_payment_offers(string $post_type = 'cotizacion'): array {
        $pending_offers = get_posts([
            'post_type' => $post_type,
            'post_status' => 'rfq-accepted',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => self::PAYMENT_STATUS_META_KEY,
                    'value' => false,
                    'compare' => '='
                ]
            ]
        ]);

        // error_log("[RFQ-PAGO] Ofertas pendientes de pago ({$post_type}): " . count($pending_offers) . " encontradas");
        
        return $pending_offers;
    }

    /**
     * Inicializa los hooks necesarios para el funcionamiento automático
     * 
     * @since 0.1.0
     */
    public static function init_hooks(): void {
        // Hook para marcar como pagada cuando orden se completa
        add_action('woocommerce_order_status_completed', [self::class, 'mark_offer_as_paid']);
        add_action('woocommerce_order_status_processing', [self::class, 'mark_offer_as_paid']);
        
        // Hook para marcar como pendiente al aceptar oferta
        add_action('rfq_cotizacion_accepted', [self::class, 'on_offer_accepted'], 10, 4);
        
        // error_log("[RFQ-PAGO] Hooks de pago inicializados: order_status_completed, order_status_processing, rfq_cotizacion_accepted");
    }

    /**
     * Callback para el hook de aceptación de cotización
     * 
     * @param int $cotizacion_id ID de la cotización
     * @param int $solicitud_id ID de la solicitud
     * @param int $order_id ID de la orden
     * @param int $user_id ID del usuario
     * @since 0.1.0
     */
    public static function on_offer_accepted(int $cotizacion_id, int $solicitud_id, int $order_id, int $user_id): void {
        // error_log("[RFQ-PAGO] Hook rfq_cotizacion_accepted ejecutado - iniciando marcado de pago pendiente");
        self::mark_offer_as_pending_payment($cotizacion_id, $solicitud_id, $order_id);
    }
}
