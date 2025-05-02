<?php
/**
 * Gestor de estados de órdenes para WooCommerce
 *
 * @package    GiVendor\GiPlugin\Order
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Order;

/**
 * OrderStatusManager - Gestiona los estados de órdenes WooCommerce
 *
 * Esta clase es responsable de gestionar el procesamiento de órdenes
 * relacionadas con solicitudes de cotización.
 *
 * @package    GiVendor\GiPlugin\Order
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class OrderStatusManager {
    
    /**
     * Constante para el nombre de nuestro estado personalizado
     */
    const STATUS_RFQ_ENVIADA = 'wc-rfq-enviada';
    
    /**
     * Inicializa los hooks relacionados con órdenes
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        // Registrar el estado personalizado
        add_action('init', [__CLASS__, 'register_order_statuses']);
        
        // Añadir a la lista de estados de WooCommerce
        add_filter('wc_order_statuses', [__CLASS__, 'add_order_statuses']);
        
        // Añadir el estado personalizado a la lista de estados que no requieren pago
        add_filter('woocommerce_valid_order_statuses_for_payment', [__CLASS__, 'add_valid_order_statuses_for_payment'], 10, 2);
        
        // Añadir un hook para marcar órdenes como procesadas
        add_action('woocommerce_order_status_changed', [__CLASS__, 'handle_order_status_change'], 10, 4);
        
        // Personalizar el color del estado personalizado
        add_action('admin_head', [__CLASS__, 'add_status_color_style']);
    }
    
    /**
     * Registra los estados personalizados de WooCommerce para órdenes RFQ
     * 
     * @since 0.1.0
     * @return void
     */
    public static function register_order_statuses(): void {
        register_post_status(self::STATUS_RFQ_ENVIADA, [
            'label'                     => _x('RFQ Enviada', 'Order status', 'rfq-manager-woocommerce'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('RFQ Enviada <span class="count">(%s)</span>', 'RFQ Enviada <span class="count">(%s)</span>', 'rfq-manager-woocommerce'),
        ]);
    }
    
    /**
     * Añade nuestros estados personalizados a la lista de estados de WooCommerce
     * 
     * @since 0.1.0
     * @param array $order_statuses Array de estados de órdenes
     * @return array Estados de órdenes modificados
     */
    public static function add_order_statuses($order_statuses): array {
        $order_statuses[self::STATUS_RFQ_ENVIADA] = _x('RFQ Enviada', 'Order status', 'rfq-manager-woocommerce');
        return $order_statuses;
    }
    
    /**
     * Añade el estado personalizado a la lista de estados válidos para pago
     * 
     * @since 0.1.0
     * @param array $statuses Estados válidos para pago
     * @param object $order Objeto de orden (opcional)
     * @return array Estados válidos actualizados
     */
    public static function add_valid_order_statuses_for_payment($statuses, $order = null): array {
        $statuses[] = 'rfq-enviada';
        return $statuses;
    }
    
    /**
     * Agrega estilo CSS para colorear el estado personalizado
     * 
     * @since 0.1.0
     * @return void
     */
    public static function add_status_color_style(): void {
        echo '<style>
            mark.order-status.status-rfq-enviada {
                background-color: #2196f3;
                color: #ffffff;
            }
        </style>';
    }
    
    /**
     * Maneja los cambios de estado de órdenes
     * 
     * @since 0.1.0
     * @param int $order_id ID de la orden
     * @param string $old_status Estado anterior
     * @param string $new_status Nuevo estado
     * @param object $order Objeto de orden
     * @return void
     */
    public static function handle_order_status_change($order_id, $old_status, $new_status, $order): void {
        // Solo nos interesa procesar órdenes nuevas que vienen del proceso de cotización
        if ($new_status == 'processing' && $old_status == 'pending') {
            // Verificar si esta orden ya ha sido procesada como cotización
            $rfq_processed = get_post_meta($order_id, '_rfq_processed', true);
            
            if ($rfq_processed) {
                // Añadir una nota a la orden
                $order->add_order_note(__('Esta orden proviene de una solicitud de cotización procesada.', 'rfq-manager-woocommerce'));
            }
        }
    }
    
    /**
     * Marca una orden como procesada para RFQ y cambia su estado
     * 
     * @since 0.1.0
     * @param int $order_id ID de la orden
     * @param int $solicitud_id ID de la solicitud asociada
     * @return bool Resultado de la operación
     */
    public static function mark_order_as_rfq_processed($order_id, $solicitud_id = 0): bool {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }
        
        // Añadir una nota a la orden
        $order->add_order_note(__('Esta orden ha sido convertida en una solicitud de cotización.', 'rfq-manager-woocommerce'));
        
        // Marcar la orden con metadatos
        update_post_meta($order_id, '_rfq_processed', true);
        
        if ($solicitud_id) {
            update_post_meta($order_id, '_rfq_solicitud_id', $solicitud_id);
        }
        
        // Cambiar el estado de la orden al estado personalizado
        $order->update_status(
            'rfq-enviada', 
            __('Orden convertida a solicitud de cotización', 'rfq-manager-woocommerce'), 
            true
        );
        
        return true;
    }
}