<?php
/**
 * Filtros globales para la pasarela RFQ
 *
 * @package    GiVendor\GiPlugin\Services\Payment
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Services\Payment;

error_log('Cargando RFQGatewayFilters.php');

if (!defined('ABSPATH')) {
    exit;
}

class RFQGatewayFilters
{
    public function __construct()
    {
        // Importante: Registrá el filtro en el constructor, pero solo cuando WooCommerce esté cargado
        add_action('woocommerce_init', function () {
            add_filter(
                'woocommerce_available_payment_gateways',
                [$this, 'filter_available_gateways'],
                20,
                1
            );
        });
        error_log('Cargando RFQGatewayFilters.php');
    }

    public function filter_available_gateways($gateways)
    {
        error_log('RFQGatewayFilters: Filtro ejecutándose. Total de gateways: ' . count($gateways));
        
        if (is_admin()) {
            error_log('RFQGatewayFilters: Saltando - estamos en admin');
            return $gateways;
        }
        
        if (function_exists('WC') && WC()->cart && WC()->cart->total == 0) {
            error_log('RFQGatewayFilters: Carrito con total 0 detectado. Total: ' . WC()->cart->total);
            
            if (isset($gateways['rfq_gateway'])) {
                error_log('RFQGatewayFilters: Forzando solo rfq_gateway');
                $gateways = [
                    'rfq_gateway' => $gateways['rfq_gateway']
                ];
            } else {
                error_log('RFQGatewayFilters: PROBLEMA - rfq_gateway no encontrado en gateways disponibles');
            }
        } else {
            $total = function_exists('WC') && WC()->cart ? WC()->cart->total : 'N/A';
            error_log('RFQGatewayFilters: No se aplica filtro. Total carrito: ' . $total);
        }
        
        return $gateways;
    }
}

