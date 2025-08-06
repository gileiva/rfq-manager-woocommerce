<?php
/**
 * Interceptor de órdenes de WooCommerce
 *
 * @package    GiVendor\GiPlugin\Order
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Order;

use GiVendor\GiPlugin\Solicitud\SolicitudCreator;
use WC_Order;

/**
 * OrderInterceptor - Intercepta órdenes de WooCommerce
 *
 * Esta clase es responsable de capturar las órdenes de WooCommerce
 * y delegarlas al creador de solicitudes.
 *
 * @package    GiVendor\GiPlugin\Order
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class OrderInterceptor {
    
    /**
     * Inicializa los hooks para interceptar órdenes
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        // Hook principal de checkout
        add_action(
            'woocommerce_checkout_order_processed',
            [ __CLASS__, 'intercept_order' ],
            10,
            1
        );
        
        // Hooks adicionales para capturar órdenes de diferentes maneras
        add_action(
            'woocommerce_new_order',
            [ __CLASS__, 'intercept_order' ],
            10,
            1
        );
        
        // Hook específico para órdenes creadas con la pasarela RFQ
        add_action(
            'woocommerce_thankyou_rfq_gateway',
            [ __CLASS__, 'mark_rfq_gateway_order' ],
            10, 
            1
        );
        
        // Filtro para redireccionar después del checkout con RFQ Gateway
        // Solo afecta pedidos enviados con el método de cotización (ID: 'rfq_gateway')
        add_filter(
            'woocommerce_get_return_url',
            [ __CLASS__, 'maybe_redirect_rfq_return_url' ],
            10,
            2
        );
    }

    /**
     * Intercepta una orden y la convierte en solicitud
     *
     * @since  0.1.0
     * @param  int $order_id ID de la orden
     * @return void
     */
    public static function intercept_order(int $order_id): void {
        // Sanitizar ID
        $order_id = absint($order_id);

        // Obtener la WC_Order
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return;
        }

        // Verificar si esta orden ya ha sido procesada
        $processed = get_post_meta($order_id, '_rfq_processed', true);
        if ($processed) {
            return;
        }

        // Verificar si la orden se creó con la pasarela RFQ
        if ($order->get_payment_method() === 'rfq_gateway') {
            self::process_rfq_order($order);
        }
    }

    /**
     * Marca específicamente las órdenes creadas con la pasarela RFQ
     *
     * @since  0.1.0
     * @param  int $order_id ID de la orden
     * @return void
     */
    public static function mark_rfq_gateway_order(int $order_id): void {
        // Obtener la WC_Order
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return;
        }
        
        // Procesar la orden como RFQ
        self::process_rfq_order($order);
    }
    
    /**
     * Procesa una orden como solicitud de cotización
     * 
     * @since 0.1.0
     * @param WC_Order $order La orden a procesar
     * @return void
     */
    private static function process_rfq_order(WC_Order $order): void {
        $order_id = $order->get_id();
        
        // Verificar si ya está procesada
        if (get_post_meta($order_id, '_rfq_processed', true)) {
            return;
        }
        
        // Crear una solicitud a partir de la orden
        $solicitud_id = SolicitudCreator::create_from_order($order_id);
        
        if (!$solicitud_id) {
            return;
        }
        
        // Marcar la orden como procesada usando el OrderStatusManager
        OrderStatusManager::mark_order_as_rfq_processed($order_id, $solicitud_id);
        
        // Disparar acción para integración con otros sistemas
        do_action('rfq_manager_solicitud_created', $solicitud_id, $order_id);
    }
    
    /**
     * Filtra la URL de retorno para redireccionar solicitudes de cotización
     * 
     * Este método intercepta la URL de retorno de WooCommerce únicamente
     * para pedidos creados con el gateway de RFQ (ID: 'rfq_gateway').
     * Permite mantener el flujo estándar de thank you para pagos reales.
     *
     * @since 0.1.0
     * @param string $return_url URL de retorno original de WooCommerce
     * @param \WC_Order|null $order Objeto de la orden (opcional)
     * @return string URL de retorno modificada o original
     */
    public static function maybe_redirect_rfq_return_url($return_url, $order = null): string {
        // Verificar que tenemos una orden válida
        if (!$order instanceof \WC_Order) {
            return $return_url;
        }
        
        // Verificar que la orden fue creada con el gateway RFQ
        // ID del gateway obtenido del código fuente: RFQGateway->id = 'rfq_gateway'
        if ($order->get_payment_method() !== 'rfq_gateway') {
            return $return_url;
        }
        
        // Verificar que el usuario esté autenticado
        if (!is_user_logged_in()) {
            return $return_url;
        }
        
        // Redirigir a la página de agradecimiento específica para RFQ
        return site_url('solicitud-creada-gracias/');
    }
}