<?php
/**
 * Gestor de métodos de pago
 *
 * @package    GiVendor\GiPlugin\Services
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Services;

use GiVendor\GiPlugin\Services\Payment\RFQGateway;
use GiVendor\GiPlugin\Services\Payment\RFQGatewayBlockSupport;

/**
 * PaymentManager - Gestiona las pasarelas de pago
 *
 * Esta clase es responsable de deshabilitar las pasarelas de pago
 * hasta que una solicitud de cotización sea aceptada.
 *
 * @package    GiVendor\GiPlugin\Services
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class PaymentManager {

    /**
     * Soporte para bloques de checkout
     *
     * @var RFQGatewayBlockSupport
     */
    private static $blocks_support;

    /**
     * Inicializa los hooks relacionados con pasarelas de pago
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        // Registrar nuestra pasarela RFQ personalizada
        add_filter('woocommerce_payment_gateways', [__CLASS__, 'register_rfq_gateway']);
        
        // Priorizar nuestra pasarela para solicitudes RFQ
        add_filter('woocommerce_available_payment_gateways', [__CLASS__, 'prioritize_rfq_gateway']);
        
        // Inicializar soporte para bloques de checkout
        self::init_block_support();
        
        // error_log('RFQ Manager - PaymentManager inicializado con pasarela RFQ y soporte para bloques');
    }
    
    /**
     * Inicializar el soporte para bloques de checkout
     *
     * @since  0.1.0
     * @return void
     */
    private static function init_block_support(): void {
        // Verificar si la clase existe antes de instanciarla
        if (class_exists('\\Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')) {
            // Incluir la clase de soporte para bloques
            require_once dirname(__FILE__) . '/Payment/RFQGatewayBlockSupport.php';
            
            // Inicializar el soporte para bloques
            self::$blocks_support = new RFQGatewayBlockSupport();
            
            // error_log('RFQ Manager - Soporte para bloques de checkout inicializado');
        }
    }
    
    /**
     * Registra nuestra pasarela RFQ en la lista de pasarelas disponibles de WooCommerce
     *
     * @since  0.1.0
     * @param  array $gateways Array de pasarelas registradas
     * @return array Array de pasarelas actualizado
     */
    public static function register_rfq_gateway($gateways): array {
        // Añadir nuestra pasarela personalizada al array de pasarelas
        $gateways[] = RFQGateway::class;
        
        return $gateways;
    }
    
    /**
     * Modifica el orden de las pasarelas para priorizar nuestra opción RFQ
     * cuando corresponda.
     *
     * @since  0.1.0
     * @param  array $available_gateways Pasarelas disponibles
     * @return array Pasarelas modificadas
     */
    public static function prioritize_rfq_gateway($available_gateways): array {
        // Si nuestra pasarela está disponible, la colocamos al principio
        if (isset($available_gateways['rfq_gateway'])) {
            // Extraer nuestra pasarela
            $rfq_gateway = $available_gateways['rfq_gateway'];
            
            // Eliminarla del array original
            unset($available_gateways['rfq_gateway']);
            
            // Colocarla al principio
            $available_gateways = ['rfq_gateway' => $rfq_gateway] + $available_gateways;
        }
        
        return $available_gateways;
    }
}