<?php
/**
 * Integración de la pasarela RFQ con bloques de checkout
 *
 * @package    GiVendor\GiPlugin\Services\Payment
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Services\Payment;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RFQGatewayBlocks - Integración con bloques de checkout
 *
 * Esta clase proporciona la implementación para que la pasarela RFQ
 * funcione con los bloques de checkout de WooCommerce.
 *
 * @package    GiVendor\GiPlugin\Services\Payment
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class RFQGatewayBlocks extends AbstractPaymentMethodType {
    
    /**
     * Nombre de la pasarela de pago
     *
     * @var string
     */
    protected $name = 'rfq_gateway';
    
    /**
     * Inicializa la integración con bloques
     *
     * @return void
     */
    public function initialize() {
        $this->settings = get_option('woocommerce_rfq_gateway_settings', []);
        
        // Depuración: Registrar si la pasarela está activa según la configuración
        // error_log('RFQ Gateway Blocks - Inicializado. Está activo: ' . ($this->is_active() ? 'Sí' : 'No'));
        // error_log('RFQ Gateway Blocks - Configuración: ' . json_encode($this->settings));

        // Asegurar que los filtros para disponibilidad están activos
        add_filter('woocommerce_blocks_payment_method_type_registration', function($payment_methods) {
            // error_log('RFQ Gateway Blocks - Verificando registro en bloques');
            return $payment_methods;
        }, 20);
    }
    
    /**
     * Determina si el método de pago está activo para uso
     *
     * @return boolean
     */
    public function is_active() {
        $is_active = $this->get_setting('enabled') === 'yes';
        
        // Forzar que el método siempre esté disponible en frontend
        // mientras depuramos el problema
        $is_active = true;
        
        // error_log('RFQ Gateway Blocks - Verificando si está activo: ' . ($is_active ? 'Sí' : 'No'));
        
        return $is_active;
    }
    
    /**
     * Devuelve el nombre de la pasarela
     *
     * @return string
     */
    public function get_payment_method_script_handles() {
        return ['rfq-gateway-blocks'];
    }
    
    /**
     * Devuelve los datos que se pasarán al script para el frontend
     *
     * @return array
     */
    public function get_payment_method_data() {
        return [
            'title'       => $this->get_setting('title') ?: __('Solicitud de cotización', 'rfq-manager-woocommerce'),
            'description' => $this->get_setting('description') ?: __('Enviar solicitud de cotización sin pago inmediato. Un representante se pondrá en contacto con usted.', 'rfq-manager-woocommerce'),
            'supports'    => $this->get_supported_features(),
        ];
    }
    
    /**
     * Obtener una configuración específica de la pasarela
     *
     * @param string $name Nombre de la configuración
     * @param mixed $default Valor predeterminado
     * @return mixed
     */
    protected function get_setting($name, $default = '') {
        return isset($this->settings[$name]) ? $this->settings[$name] : $default;
    }
    
    /**
     * Obtiene las características soportadas por la pasarela
     *
     * @return array
     */
    public function get_supported_features() {
        return [
            'products',
        ];
    }
}