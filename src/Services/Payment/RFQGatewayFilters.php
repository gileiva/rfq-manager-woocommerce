<?php
/**
 * Filtros globales para la pasarela RFQ
 *
 * @package    GiVendor\GiPlugin\Services\Payment
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Services\Payment;

// error_log('Cargando RFQGatewayFilters.php');

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
        // error_log('Cargando RFQGatewayFilters.php');
    }

    public function filter_available_gateways($gateways)
    {
        error_log('RFQGatewayFilters: === INICIO FILTRO GATEWAY ===');
        error_log('RFQGatewayFilters: Total de gateways disponibles: ' . count($gateways));
        error_log('RFQGatewayFilters: URL actual: ' . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
        
        // Log del estado actual de flags de sesión
        if (WC()->session) {
            $rfq_context = WC()->session->get('rfq_context') ? 'true' : 'false';
            $rfq_offer_payment = WC()->session->get('rfq_offer_payment') ? 'true' : 'false';
            $chosen_payment = WC()->session->get('chosen_payment_method') ?: 'null';
            error_log("RFQGatewayFilters: FLAGS SESIÓN → rfq_context=$rfq_context, rfq_offer_payment=$rfq_offer_payment, chosen_payment_method=$chosen_payment");
        }
        
        if (is_admin()) {
            error_log('RFQGatewayFilters: Saltando - contexto administrativo');
            return $gateways;
        }
        
        // PRIORIDAD 1: Detección por orden existente con metas RFQ (pago de oferta aceptada)
        $is_offer_payment = $this->detect_offer_payment_context();
        
        // PRIORIDAD 2: Detección por contexto de nueva solicitud RFQ
        $is_rfq_request = false;
        if (!$is_offer_payment) {
            $is_rfq_request = $this->detect_rfq_request_context();
        }
        
        // Log del contexto detectado
        if ($is_offer_payment) {
            error_log('RFQGatewayFilters: CONTEXTO DETECTADO → Pago de oferta aceptada');
        } elseif ($is_rfq_request) {
            error_log('RFQGatewayFilters: CONTEXTO DETECTADO → Nueva solicitud RFQ');
        } else {
            error_log('RFQGatewayFilters: CONTEXTO DETECTADO → WooCommerce estándar');
        }
        
        // Aplicar filtros según contexto
        if ($is_offer_payment) {
            // Pago de oferta: mostrar solo gateways tradicionales
            if (isset($gateways['rfq_gateway'])) {
                unset($gateways['rfq_gateway']);
                error_log('RFQGatewayFilters: Gateway RFQ removido para pago de oferta. Gateways restantes: ' . count($gateways));
            }
        } elseif ($is_rfq_request) {
            // Nueva solicitud: mostrar solo gateway RFQ
            if (isset($gateways['rfq_gateway'])) {
                $gateways = ['rfq_gateway' => $gateways['rfq_gateway']];
                error_log('RFQGatewayFilters: Solo gateway RFQ habilitado para solicitud');
            } else {
                error_log('RFQGatewayFilters: ERROR - Gateway RFQ no encontrado para solicitud');
            }
        } else {
            // WooCommerce estándar: remover gateway RFQ
            if (isset($gateways['rfq_gateway'])) {
                unset($gateways['rfq_gateway']);
                error_log('RFQGatewayFilters: Gateway RFQ removido para checkout estándar');
            }
        }
        
        error_log('RFQGatewayFilters: === FIN FILTRO GATEWAY ===');
        return $gateways;
    }

    /**
     * Detecta si estamos en contexto de pago de oferta aceptada
     * PRIORIDAD 1: Basado en orden existente con metas RFQ
     *
     * @since 0.1.0
     * @return bool
     */
    private function detect_offer_payment_context() {
        // Criterio 1: Verificar si hay orden RFQ en URL pay_for_order
        $order_id = $this->get_order_id_from_url();
        if ($order_id && $this->is_rfq_order($order_id)) {
            error_log("RFQGatewayFilters: Detectado pago de oferta por orden RFQ #{$order_id} en URL");
            return true;
        }
        
        // Criterio 2: Contexto pay_for_order con flag de sesión
        if ($this->is_pay_for_order_context() && WC()->session && WC()->session->get('rfq_offer_payment')) {
            error_log('RFQGatewayFilters: Detectado pago de oferta por contexto pay_for_order + flag sesión');
            return true;
        }
        
        // Criterio 3: Solo flag de sesión como fallback
        if (WC()->session && WC()->session->get('rfq_offer_payment')) {
            error_log('RFQGatewayFilters: Detectado pago de oferta por flag de sesión (fallback)');
            return true;
        }
        
        error_log('RFQGatewayFilters: No se detectó contexto de pago de oferta');
        return false;
    }

    /**
     * Detecta si estamos en contexto de nueva solicitud RFQ
     * PRIORIDAD 2: Solo si no es pago de oferta
     *
     * @since 0.1.0
     * @return bool
     */
    private function detect_rfq_request_context() {
        // Criterio 1: Endpoint checkout estándar + flag rfq_context
        if ($this->is_standard_checkout() && WC()->session && WC()->session->get('rfq_context')) {
            // Verificar que NO hay rfq_offer_payment activo
            if (!WC()->session->get('rfq_offer_payment')) {
                error_log('RFQGatewayFilters: Detectado solicitud RFQ por checkout estándar + flag sesión');
                return true;
            }
        }
        
        // Criterio 2: RFQPurchasableOverride como respaldo
        if (class_exists('\GiVendor\GiPlugin\WooCommerce\RFQPurchasableOverride')) {
            $rfq_context = \GiVendor\GiPlugin\WooCommerce\RFQPurchasableOverride::is_rfq_context_active();
            if ($rfq_context) {
                error_log('RFQGatewayFilters: Detectado solicitud RFQ por RFQPurchasableOverride');
                return true;
            }
        }
        
        error_log('RFQGatewayFilters: No se detectó contexto de solicitud RFQ');
        return false;
    }

    /**
     * Obtiene el ID de orden desde la URL de pay_for_order
     *
     * @since 0.1.0
     * @return int|null
     */
    private function get_order_id_from_url() {
        // Método 1: Parámetro directo
        if (isset($_GET['order_id'])) {
            return absint($_GET['order_id']);
        }
        
        // Método 2: Extraer de URL /order-pay/XXXX/
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('/\/order-pay\/(\d+)\//', $request_uri, $matches)) {
            return absint($matches[1]);
        }
        
        // Método 3: Endpoint de WooCommerce
        if (function_exists('get_query_var')) {
            $order_id = get_query_var('order-pay');
            if ($order_id) {
                return absint($order_id);
            }
        }
        
        return null;
    }

    /**
     * Verifica si una orden es de tipo RFQ (tiene metas específicos)
     *
     * @since 0.1.0
     * @param int $order_id
     * @return bool
     */
    private function is_rfq_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        $cotizacion_id = $order->get_meta('_rfq_cotizacion_id');
        $solicitud_id = $order->get_meta('_rfq_solicitud_id');
        
        $is_rfq = !empty($cotizacion_id) && !empty($solicitud_id);
        
        if ($is_rfq) {
            error_log("RFQGatewayFilters: Orden #{$order_id} confirmada como orden RFQ (solicitud: {$solicitud_id}, cotización: {$cotizacion_id})");
        }
        
        return $is_rfq;
    }

    /**
     * Verifica si estamos en checkout estándar (no pay_for_order)
     *
     * @since 0.1.0
     * @return bool
     */
    private function is_standard_checkout() {
        // No debe ser pay_for_order
        if ($this->is_pay_for_order_context()) {
            return false;
        }
        
        // Debe ser página de checkout
        if (function_exists('is_checkout') && is_checkout()) {
            return true;
        }
        
        // Verificar por URL
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($request_uri, '/checkout/') !== false && strpos($request_uri, '/order-pay/') === false;
    }

    /**
     * Verifica si estamos en contexto pay_for_order (pago de orden existente)
     *
     * @since 0.1.0
     * @return bool
     */
    private function is_pay_for_order_context() {
        // Verificar parámetro GET pay_for_order
        if (isset($_GET['pay_for_order']) && $_GET['pay_for_order'] === 'true') {
            return true;
        }

        // Verificar si estamos en endpoint de pago de orden
        if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-pay')) {
            return true;
        }

        // Verificar URL patterns de pago de orden
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($request_uri, '/order-pay/') !== false || 
            strpos($request_uri, 'pay_for_order=true') !== false) {
            return true;
        }

        return false;
    }
}

