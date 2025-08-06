<?php
/**
 * Creador de órdenes a partir de ofertas aceptadas
 *
 * @package    GiVendor\GiPlugin\Order
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Order;

use WC_Order;
use WP_Post;
use WP_User;
use WP_Error;
use Exception;

/**
 * OfferOrderCreator - Crea órdenes de WooCommerce a partir de cotizaciones aceptadas
 *
 * Esta clase es responsable de crear una nueva orden de WooCommerce cuando
 * un usuario acepta una cotización, copiando exactamente los productos,
 * cantidades y precios de la oferta sin usar el carrito.
 *
 * @package    GiVendor\GiPlugin\Order
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class OfferOrderCreator {

    /**
     * Configuración por defecto para el vencimiento del pago (en horas)
     */
    const DEFAULT_PAYMENT_EXPIRY_HOURS = 24;

    /**
     * Crea una nueva orden de WooCommerce a partir de una cotización aceptada
     *
     * @param WP_User $user Usuario que acepta la cotización
     * @param WP_Post $solicitud Solicitud asociada
     * @param WP_Post $cotizacion Cotización aceptada
     * @return int|WP_Error ID de la orden creada o WP_Error si falla
     */
    public static function create_from_accepted_offer(WP_User $user, WP_Post $solicitud, WP_Post $cotizacion) {
        error_log('[RFQ] OfferOrderCreator::create_from_accepted_offer iniciado para cotización: ' . $cotizacion->ID);

        // Obtener datos de la cotización
        $precio_items = get_post_meta($cotizacion->ID, '_precio_items', true);
        $total_cotizacion = get_post_meta($cotizacion->ID, '_total', true);
        $solicitud_items = get_post_meta($solicitud->ID, '_solicitud_items', true);

        if (empty($precio_items) || empty($solicitud_items)) {
            error_log('[RFQ] Error: Datos de cotización o solicitud faltantes');
            return new WP_Error('missing_data', __('Datos de cotización incompletos.', 'rfq-manager-woocommerce'));
        }

        // Decodificar items de solicitud si es necesario
        if (is_string($solicitud_items)) {
            $solicitud_items = json_decode($solicitud_items, true);
        }

        // Crear nueva orden
        $order = wc_create_order([
            'customer_id' => $user->ID,
            'status' => 'pending'
        ]);

        if (is_wp_error($order)) {
            error_log('[RFQ] Error creando orden: ' . $order->get_error_message());
            return $order;
        }

        // LIMPIEZA AGRESIVA: Establecer contexto de pago de oferta aceptada
        \GiVendor\GiPlugin\Services\RFQFlagsManager::set_offer_payment_context('OfferOrderCreator_create_order');

        try {
            // Agregar productos a la orden
            $order_total = 0.0;
            foreach ($solicitud_items as $item) {
                $product_id = absint($item['product_id']);
                $quantity = absint($item['qty']);

                // Verificar que existe precio para este producto en la cotización
                if (!isset($precio_items[$product_id])) {
                    error_log('[RFQ] Precio no encontrado para producto: ' . $product_id);
                    continue;
                }

                $precio_data = $precio_items[$product_id];
                $precio_unitario = floatval($precio_data['precio']);
                $subtotal = floatval($precio_data['subtotal']); // Ya incluye impuestos

                // Obtener producto
                $product = wc_get_product($product_id);
                if (!$product) {
                    error_log('[RFQ] Producto no encontrado: ' . $product_id);
                    continue;
                }

                // Agregar item a la orden con precio fijo de la cotización
                $item_id = $order->add_product($product, $quantity, [
                    'subtotal' => $subtotal,
                    'total' => $subtotal,
                ]);

                if (!$item_id) {
                    error_log('[RFQ] Error agregando producto a orden: ' . $product_id);
                    continue;
                }

                // Agregar meta del precio original cotizado
                wc_add_order_item_meta($item_id, '_rfq_cotized_price', $precio_unitario);
                wc_add_order_item_meta($item_id, '_rfq_cotized_subtotal', $subtotal);

                $order_total += $subtotal;

                error_log('[RFQ] Producto agregado a orden - ID: ' . $product_id . ', Cantidad: ' . $quantity . ', Subtotal: ' . $subtotal);
            }

            // Establecer total de la orden (usar el total de la cotización para mayor precisión)
            $final_total = !empty($total_cotizacion) ? floatval($total_cotizacion) : $order_total;
            $order->set_total($final_total);

            // Agregar meta datos personalizados
            self::add_order_meta($order, $solicitud, $cotizacion);

            // Calcular fecha de vencimiento del pago
            $expiry_timestamp = self::calculate_payment_expiry();
            $order->update_meta_data('_rfq_order_acceptance_expiry', $expiry_timestamp);

            // Guardar la orden
            $order->save();

            error_log('[RFQ] Orden creada exitosamente - ID: ' . $order->get_id() . ', Total: ' . $final_total);

            return $order->get_id();

        } catch (Exception $e) {
            error_log('[RFQ] Excepción creando orden: ' . $e->getMessage());
            
            // Limpiar orden parcial si existe
            if ($order && $order->get_id()) {
                wp_delete_post($order->get_id(), true);
            }

            return new WP_Error('order_creation_failed', __('Error creando la orden: ', 'rfq-manager-woocommerce') . $e->getMessage());
        }
    }

    /**
     * Agrega meta datos personalizados a la orden
     *
     * @param WC_Order $order Orden de WooCommerce
     * @param WP_Post $solicitud Solicitud asociada
     * @param WP_Post $cotizacion Cotización aceptada
     */
    private static function add_order_meta(WC_Order $order, WP_Post $solicitud, WP_Post $cotizacion): void {
        // Marcar como orden de oferta aceptada
        $order->update_meta_data('_rfq_offer_order', 'yes');
        
        // Referencias a solicitud y cotización
        $order->update_meta_data('_rfq_solicitud_id', $solicitud->ID);
        $order->update_meta_data('_rfq_cotizacion_id', $cotizacion->ID);
        
        // UUID de la solicitud para referencia
        $solicitud_uuid = get_post_meta($solicitud->ID, '_solicitud_uuid', true);
        if ($solicitud_uuid) {
            $order->update_meta_data('_rfq_solicitud_uuid', $solicitud_uuid);
        }

        // Timestamp de aceptación
        $order->update_meta_data('_rfq_acceptance_timestamp', current_time('timestamp'));

        // Proveedor de la cotización
        $proveedor_id = $cotizacion->post_author;
        $order->update_meta_data('_rfq_proveedor_id', $proveedor_id);

        // METAS DE TRAZABILIDAD: Información de aceptación por cliente
        $current_user_id = get_current_user_id();
        if ($current_user_id) {
            $order->update_meta_data('_rfq_aceptada_por_cliente', $current_user_id);
        }
        $order->update_meta_data('_rfq_aceptacion_timestamp', current_time('mysql'));
        $order->update_meta_data('_rfq_contexto_pago', 'oferta_aceptada');

        error_log('[RFQ] Meta datos agregados a orden: ' . $order->get_id() . ' (aceptada por usuario: ' . $current_user_id . ')');
    }

    /**
     * Calcula la fecha de vencimiento del pago
     *
     * @return int Timestamp de vencimiento
     */
    private static function calculate_payment_expiry(): int {
        $expiry_hours = self::DEFAULT_PAYMENT_EXPIRY_HOURS;
        
        // Permitir configuración futura del vencimiento
        $expiry_hours = apply_filters('rfq_payment_expiry_hours', $expiry_hours);
        
        $expiry_timestamp = current_time('timestamp') + ($expiry_hours * HOUR_IN_SECONDS);
        
        error_log('[RFQ] Vencimiento de pago calculado: ' . date('Y-m-d H:i:s', $expiry_timestamp) . ' (' . $expiry_hours . ' horas)');
        
        return $expiry_timestamp;
    }

    /**
     * Obtiene la URL del checkout para una orden específica
     *
     * @param int $order_id ID de la orden
     * @return string URL del checkout
     */
    public static function get_checkout_url(int $order_id): string {
        $order = wc_get_order($order_id);
        if (!$order) {
            return wc_get_checkout_url();
        }

        return $order->get_checkout_payment_url();
    }

    /**
     * Inicializa hooks para limpieza de sesión
     */
    public static function init_hooks(): void {
        // Limpiar contexto RFQ cuando la orden cambia de estado
        add_action('woocommerce_order_status_changed', [__CLASS__, 'maybe_clear_rfq_session'], 10, 3);
    }

    /**
     * Limpia el contexto RFQ de la sesión cuando es apropiado
     */
    public static function maybe_clear_rfq_session(int $order_id, string $old_status, string $new_status): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Si la orden tiene meta de RFQ y está completada, cancelada o fallida, limpiar sesión
        if ($order->get_meta('_rfq_cotizacion_id') && in_array($new_status, ['completed', 'cancelled', 'failed', 'processing'])) {
            \GiVendor\GiPlugin\Services\RFQFlagsManager::clear_all_flags("OfferOrderCreator_order_status_changed_{$old_status}_to_{$new_status}_order_{$order_id}");
        }
    }
}
