<?php
/**
 * Gestor centralizado de flags de sesión RFQ
 *
 * @package    GiVendor\GiPlugin\Services
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Services;

/**
 * RFQFlagsManager - Clase para gestionar de forma centralizada los flags de sesión RFQ
 *
 * Esta clase proporciona:
 * - Establecimiento seguro de flags de contexto RFQ
 * - Limpieza agresiva y completa de flags
 * - Logging detallado de todas las transiciones
 * - Diagnóstico del estado actual de flags
 *
 * @since 0.1.0
 */
class RFQFlagsManager 
{
    /**
     * Establece el contexto de nueva solicitud RFQ
     * 
     * @param string $trigger Descripción del trigger que causa este cambio
     * @since 0.1.0
     */
    public static function set_rfq_request_context($trigger = 'unknown') {
        if (!WC()->session) {
            error_log('[RFQ-FLAGS-MANAGER] ERROR: WC Session no disponible - trigger: ' . $trigger);
            return;
        }

        // Limpiar completamente contexto anterior
        WC()->session->set('rfq_offer_payment', null);
        WC()->session->set('chosen_payment_method', null);
        
        // Establecer contexto de nueva solicitud
        WC()->session->set('rfq_context', true);
        \GiVendor\GiPlugin\WooCommerce\RFQPurchasableOverride::set_rfq_context(true);
        
        error_log("[RFQ-FLAGS-MANAGER] NUEVA SOLICITUD establecida → rfq_context=true, rfq_offer_payment=null (trigger: $trigger)");
        self::log_current_state();
    }

    /**
     * Establece el contexto de pago de oferta aceptada
     * 
     * @param string $trigger Descripción del trigger que causa este cambio
     * @since 0.1.0
     */
    public static function set_offer_payment_context($trigger = 'unknown') {
        if (!WC()->session) {
            error_log('[RFQ-FLAGS-MANAGER] ERROR: WC Session no disponible - trigger: ' . $trigger);
            return;
        }

        // Limpiar completamente contexto anterior
        WC()->session->set('rfq_context', null);
        WC()->session->set('chosen_payment_method', null);
        \GiVendor\GiPlugin\WooCommerce\RFQPurchasableOverride::set_rfq_context(false);
        
        // Establecer contexto de pago de oferta
        WC()->session->set('rfq_offer_payment', true);
        
        error_log("[RFQ-FLAGS-MANAGER] PAGO OFERTA establecido → rfq_offer_payment=true, rfq_context=null (trigger: $trigger)");
        self::log_current_state();
    }

    /**
     * Limpia completamente todos los flags RFQ
     * 
     * @param string $trigger Descripción del trigger que causa esta limpieza
     * @since 0.1.0
     */
    public static function clear_all_flags($trigger = 'unknown') {
        if (!WC()->session) {
            error_log('[RFQ-FLAGS-MANAGER] ERROR: WC Session no disponible para limpieza - trigger: ' . $trigger);
            return;
        }

        // Limpieza agresiva de todos los flags
        WC()->session->set('rfq_context', null);
        WC()->session->set('rfq_offer_payment', null);
        WC()->session->set('chosen_payment_method', null);
        \GiVendor\GiPlugin\WooCommerce\RFQPurchasableOverride::set_rfq_context(false);
        
        error_log("[RFQ-FLAGS-MANAGER] LIMPIEZA COMPLETA → todas las flags=null (trigger: $trigger)");
        self::log_current_state();
    }

    /**
     * Registra el estado actual de todos los flags
     * 
     * @since 0.1.0
     */
    public static function log_current_state() {
        if (!WC()->session) {
            error_log('[RFQ-FLAGS-MANAGER] ESTADO: WC Session no disponible');
            return;
        }

        $rfq_context = WC()->session->get('rfq_context') ? 'true' : 'false';
        $rfq_offer_payment = WC()->session->get('rfq_offer_payment') ? 'true' : 'false';
        $chosen_payment = WC()->session->get('chosen_payment_method') ?: 'null';
        $url = $_SERVER['REQUEST_URI'] ?? 'N/A';
        
        error_log("[RFQ-FLAGS-MANAGER] ESTADO ACTUAL → rfq_context=$rfq_context, rfq_offer_payment=$rfq_offer_payment, chosen_payment_method=$chosen_payment, URL=$url");
    }

    /**
     * Diagnóstico completo del estado RFQ
     * 
     * @since 0.1.0
     * @return array Estado detallado
     */
    public static function get_diagnostic_info() {
        $diagnostic = [
            'session_available' => (bool) WC()->session,
            'rfq_context' => false,
            'rfq_offer_payment' => false,
            'chosen_payment_method' => null,
            'rfq_purchasable_override' => false,
            'current_url' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'is_admin' => is_admin(),
            'is_checkout' => function_exists('is_checkout') ? is_checkout() : false,
            'cart_count' => WC()->cart ? WC()->cart->get_cart_contents_count() : 0
        ];

        if (WC()->session) {
            $diagnostic['rfq_context'] = (bool) WC()->session->get('rfq_context');
            $diagnostic['rfq_offer_payment'] = (bool) WC()->session->get('rfq_offer_payment');
            $diagnostic['chosen_payment_method'] = WC()->session->get('chosen_payment_method');
        }

        if (class_exists('\GiVendor\GiPlugin\WooCommerce\RFQPurchasableOverride')) {
            $diagnostic['rfq_purchasable_override'] = \GiVendor\GiPlugin\WooCommerce\RFQPurchasableOverride::is_rfq_context_active();
        }

        return $diagnostic;
    }

    /**
     * Verifica si hay flags inconsistentes o problemáticos
     * 
     * @since 0.1.0
     * @return array Lista de problemas detectados
     */
    public static function detect_flag_issues() {
        $issues = [];
        $diagnostic = self::get_diagnostic_info();

        // Problema: Ambos flags activos simultáneamente
        if ($diagnostic['rfq_context'] && $diagnostic['rfq_offer_payment']) {
            $issues[] = 'CRÍTICO: rfq_context y rfq_offer_payment están ambos activos simultáneamente';
        }

        // Problema: Flag de oferta persiste sin contexto de pago
        if ($diagnostic['rfq_offer_payment'] && $diagnostic['is_checkout'] && !strpos($diagnostic['current_url'], 'order-pay')) {
            $issues[] = 'ADVERTENCIA: rfq_offer_payment=true en checkout pero no es pay_for_order';
        }

        // Problema: Carrito vacío pero flags activos
        if ($diagnostic['cart_count'] === 0 && ($diagnostic['rfq_context'] || $diagnostic['rfq_offer_payment'])) {
            $issues[] = 'ADVERTENCIA: Carrito vacío pero flags RFQ activos';
        }

        // Problema: Inconsistencia entre sesión y RFQPurchasableOverride
        if ($diagnostic['rfq_context'] !== $diagnostic['rfq_purchasable_override']) {
            $issues[] = 'ADVERTENCIA: Inconsistencia entre rfq_context sesión (' . ($diagnostic['rfq_context'] ? 'true' : 'false') . ') y RFQPurchasableOverride (' . ($diagnostic['rfq_purchasable_override'] ? 'true' : 'false') . ')';
        }

        return $issues;
    }
}
