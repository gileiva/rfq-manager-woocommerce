<?php
/**
 * Pasarela de pago RFQ para WooCommerce
 *
 * @package    GiVendor\GiPlugin\Services\Payment
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Services\Payment;

use GiVendor\GiPlugin\Order\OrderStatusManager;

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RFQGateway - Pasarela de pago específica para solicitudes de cotización
 *
 * Esta clase implementa una pasarela de pago "fantasma" que permite
 * a los usuarios enviar solicitudes de cotización sin realizar un pago real.
 *
 * @package    GiVendor\GiPlugin\Services\Payment
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class RFQGateway extends \WC_Payment_Gateway {
    
    /**
     * Constructor para la pasarela
     *
     * @since 0.1.0
     */
    public function __construct() {
        // ID único de la pasarela
        $this->id = 'rfq_gateway';
        
        // Título y descripción mostrados en la página de checkout
        $this->title = __('Solicitud de cotización', 'rfq-manager-woocommerce');
        $this->description = __('Enviar solicitud de cotización sin pago inmediato. Un representante se pondrá en contacto con usted.', 'rfq-manager-woocommerce');
        
        // Soporte para reembolsos, etc.
        $this->has_fields = false;
        $this->supports = [
            'products',
        ];

        // Ícono para mostrar en checkout (opcional)
        $this->icon = apply_filters('rfq_gateway_icon', plugin_dir_url(dirname(dirname(dirname(__FILE__)))) . 'assets/img/rfq-icon.png');
        
        // Método para mostrar en el panel de administración
        $this->method_title = __('Pasarela RFQ', 'rfq-manager-woocommerce');
        $this->method_description = __('Permite a los clientes enviar solicitudes de cotización sin realizar un pago real.', 'rfq-manager-woocommerce');
        
        // Define los campos de configuración de la pasarela
        $this->init_form_fields();
        
        // Carga la configuración guardada
        $this->init_settings();
        
        // Define las variables de configuración
        $this->enabled = $this->get_option('enabled', 'yes');
        
        // Hook para guardar las opciones
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    /**
     * Inicializa los campos de configuración
     * 
     * @since 0.1.0
     * @return void
     */
    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'       => __('Activar/Desactivar', 'rfq-manager-woocommerce'),
                'type'        => 'checkbox',
                'label'       => __('Habilitar pasarela de solicitud de cotización', 'rfq-manager-woocommerce'),
                'default'     => 'yes',
                'description' => __('Esta pasarela permite a los clientes enviar solicitudes de cotización sin realizar un pago real.', 'rfq-manager-woocommerce'),
            ],
        ];
    }

    /**
     * Manejo del proceso de pago
     * 
     * @since 0.1.0
     * @param int $order_id ID de la orden
     * @return array
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        // Aplicar directamente nuestro estado personalizado "rfq-enviada"
        $order->update_status(
            'rfq-enviada', 
            __('Solicitud de cotización recibida.', 'rfq-manager-woocommerce')
        );
        
        // Añadir nota a la orden
        $order->add_order_note(__('Solicitud de cotización enviada por el cliente.', 'rfq-manager-woocommerce'));
        
        // Marcar la orden con metadatos RFQ
        update_post_meta($order_id, '_rfq_processed', true);
        update_post_meta($order_id, '_payment_method_title', __('Solicitud de Cotización', 'rfq-manager-woocommerce'));
        
        // Registrar en el log para depuración
        error_log('RFQ Gateway - Orden #' . $order_id . ' marcada con estado rfq-enviada');
        
        // Vaciar el carrito
        WC()->cart->empty_cart();

        // Obtener la URL de agradecimiento
        $redirect_url = $this->get_return_url($order);

        // Redireccionar al cliente a la página de agradecimiento
        return [
            'result'   => 'success',
            'redirect' => $redirect_url,
        ];
    }
}