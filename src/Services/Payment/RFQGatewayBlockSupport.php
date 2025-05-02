<?php
/**
 * Soporte para bloques de checkout para la pasarela RFQ
 *
 * @package    GiVendor\GiPlugin\Services\Payment
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Services\Payment;

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RFQGatewayBlockSupport - Integra la pasarela RFQ con los bloques de checkout
 *
 * Esta clase proporciona compatibilidad entre la pasarela de pago RFQ
 * y los bloques de checkout de WooCommerce.
 *
 * @package    GiVendor\GiPlugin\Services\Payment
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class RFQGatewayBlockSupport {
    
    /**
     * Constructor para la integración de bloques
     *
     * @since 0.1.0
     */
    public function __construct() {
        // Registrar scripts necesarios para la integración con bloques
        add_action('wp_enqueue_scripts', [$this, 'register_scripts'], 20);
        
        // Asegurar que nuestros scripts se cargan en todas las páginas de checkout
        add_action('enqueue_block_assets', [$this, 'maybe_enqueue_scripts']);
        
        // Registrar nuestra pasarela para bloques de pago
        add_action('woocommerce_blocks_payment_method_type_registration', [$this, 'register_payment_method']);
        
        // Asegurar que los datos del gateway estén disponibles para JavaScript
        add_action('wp_print_footer_scripts', [$this, 'add_payment_data_to_frontend'], 5);
    }
    
    /**
     * Registra los scripts necesarios para la integración con bloques
     * 
     * @since 0.1.0
     * @return void
     */
    public function register_scripts() {
        if (!function_exists('register_block_script_handle')) {
            error_log('RFQ Gateway - Error: La función register_block_script_handle no existe');
            return;
        }

        // Ruta absoluta y URL del plugin
        $plugin_base_path = plugin_dir_path(dirname(dirname(dirname(__FILE__))));
        $plugin_url = plugins_url('/', dirname(dirname(dirname(__FILE__))));
        
        // Asegurar que la ruta del script es correcta independientemente del entorno
        $script_path = $plugin_url . 'assets/js/frontend/blocks.js';
        $script_file_path = $plugin_base_path . 'assets/js/frontend/blocks.js';
        
        // Verificar que el archivo existe
        if (!file_exists($script_file_path)) {
            error_log('RFQ Gateway - Error: El archivo blocks.js no existe en la ruta: ' . $script_file_path);
            // Intentar buscar el archivo en un lugar alternativo
            $alt_script_path = $plugin_base_path . 'assets/js/blocks.js';
            if (file_exists($alt_script_path)) {
                $script_file_path = $alt_script_path;
                $script_path = $plugin_url . 'assets/js/blocks.js';
                error_log('RFQ Gateway - Script blocks.js encontrado en ubicación alternativa: ' . $alt_script_path);
            }
        } else {
            error_log('RFQ Gateway - Script blocks.js encontrado en: ' . $script_file_path);
        }
        
        // Registrar el script con todas las dependencias necesarias
        wp_register_script(
            'rfq-gateway-blocks',
            $script_path,
            ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-components', 'wp-html-entities', 'wp-i18n'],
            filemtime($script_file_path),
            true
        );
        
        // Comprobar si el script está registrado correctamente
        if (!wp_script_is('rfq-gateway-blocks', 'registered')) {
            error_log('RFQ Gateway - Error: El script rfq-gateway-blocks no se registró correctamente');
        } else {
            error_log('RFQ Gateway - Script rfq-gateway-blocks registrado correctamente');
        }
        
        // Registrar el controlador para los bloques
        register_block_script_handle(
            'rfq-gateway-blocks',
            'rfq-gateway-blocks',
            ['wc-blocks-registry', 'wc-settings']
        );
        
        error_log('RFQ Gateway - Script handler registrado para bloques');
    }
    
    /**
     * Encola scripts si estamos en una página de checkout
     * 
     * @since 0.1.0
     * @return void
     */
    public function maybe_enqueue_scripts() {
        // Detectar si estamos en una página de checkout
        $is_checkout = is_checkout() || 
                      (function_exists('has_block') && has_block('woocommerce/checkout'));
                      
        // También verificar si estamos en la página de carrito que podría tener un mini-checkout
        $is_cart = is_cart() || 
                  (function_exists('has_block') && has_block('woocommerce/cart'));
                  
        if ($is_checkout || $is_cart) {
            error_log('RFQ Gateway - Encolando script en página de checkout/cart');
            wp_enqueue_script('rfq-gateway-blocks');
        }
    }
    
    /**
     * Asegura que los datos de configuración de la pasarela estén disponibles para JavaScript
     * 
     * @since 0.1.0
     * @return void
     */
    public function add_payment_data_to_frontend() {
        if (!is_checkout() && 
            !is_cart() && 
            !has_block('woocommerce/checkout') && 
            !has_block('woocommerce/cart')) {
            return;
        }
        
        // Obtener instancia de la pasarela
        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        if (!isset($gateways['rfq_gateway'])) {
            error_log('RFQ Gateway - Error: La pasarela rfq_gateway no está disponible');
            return;
        }
        
        $gateway = $gateways['rfq_gateway'];
        
        // Datos que se pasarán al frontend
        $gateway_data = array(
            'title' => $gateway->get_title(),
            'description' => $gateway->get_description(),
            'enabled' => $gateway->is_available(),
            'supports' => array('products'),
            'environment' => defined('WP_DEBUG') && WP_DEBUG ? 'development' : 'production',
            'server_info' => array(
                'os' => PHP_OS,
                'php_version' => PHP_VERSION,
            ),
        );
        
        // Añadir datos a wcSettings para que estén disponibles para nuestro script
        echo '<script type="text/javascript">
            window.addEventListener("DOMContentLoaded", function() {
                if (typeof window.wcSettings === "undefined") {
                    window.wcSettings = {};
                }
                
                if (typeof window.wcSettings.payment_data === "undefined") {
                    window.wcSettings.payment_data = {};
                }
                
                window.wcSettings.payment_data.rfq_gateway = ' . json_encode($gateway_data) . ';
                console.log("RFQ Gateway - Datos de configuración cargados:", window.wcSettings.payment_data.rfq_gateway);
            });
        </script>';
        
        error_log('RFQ Gateway - Datos de configuración añadidos al frontend: ' . json_encode($gateway_data));
    }
    
    /**
     * Registra nuestra pasarela para uso con bloques de checkout
     * 
     * @since 0.1.0
     * @param \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry Registro de métodos de pago
     * @return void
     */
    public function register_payment_method($payment_method_registry) {
        // Registrar la integración de pasarela con bloques
        require_once plugin_dir_path(__FILE__) . 'RFQGatewayBlocks.php';
        
        // Verificar que la clase existe antes de instanciarla
        if (class_exists('\\GiVendor\\GiPlugin\\Services\\Payment\\RFQGatewayBlocks')) {
            // Registrar el método de pago
            $payment_method_registry->register(new RFQGatewayBlocks());
            error_log('RFQ Gateway - Método de pago registrado correctamente con bloques');
        } else {
            error_log('RFQ Gateway - Error: La clase RFQGatewayBlocks no existe');
        }
    }
}