<?php
/**
 * Override de producto comprable para contexto RFQ
 *
 * @package    GiVendor\GiPlugin\WooCommerce
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\WooCommerce;

/**
 * RFQPurchasableOverride - Permite que productos sin precio sean comprables en contexto RFQ
 *
 * Esta clase implementa un filtro sobre woocommerce_is_purchasable que permite
 * que productos sin precio o con precio 0 sean considerados comprables únicamente
 * cuando se está en el contexto de una solicitud de cotización (RFQ).
 *
 * @package    GiVendor\GiPlugin\WooCommerce
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class RFQPurchasableOverride {

    /**
     * Constructor - Registra el filtro de producto comprable
     *
     * @since 0.1.0
     */
    public function __construct() {
        // Registrar el filtro con alta prioridad para ejecutarse después de WooCommerce
        add_filter('woocommerce_is_purchasable', [$this, 'override_purchasable_for_rfq'], 20, 2);
        
        // Log de inicialización
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[RFQ-DEBUG] RFQPurchasableOverride inicializado');
        }
    }

    /**
     * Override del método is_purchasable para productos en contexto RFQ
     *
     * REFACTOR: Lógica movida completamente al filtro para evitar flags globales
     * y problemas de ciclo de vida de WordPress. La detección de contexto RFQ 
     * se realiza on-demand en el momento exacto de la consulta.
     * 
     * Criterios de detección RFQ:
     * 1. Gateway RFQ seleccionado en sesión/POST
     * 2. Endpoint de repetir solicitud
     * 3. Acciones AJAX relacionadas con RFQ
     * 4. Activación manual por SolicitudRepeat
     *
     * @since 0.1.0
     * @param bool $is_purchasable Estado actual de comprable
     * @param \WC_Product $product Objeto del producto
     * @return bool Estado modificado de comprable
     */
    public function override_purchasable_for_rfq($is_purchasable, $product) {
        // Solo actuar si no es comprable originalmente
        if ($is_purchasable) {
            return $is_purchasable;
        }

        // Verificar si estamos en contexto RFQ - detección on-demand
        if (!$this->detect_rfq_context_active()) {
            return $is_purchasable;
        }

        // Verificaciones básicas de seguridad
        if (!$product || !$product->exists()) {
            return false;
        }

        // El producto debe estar publicado
        if ($product->get_status() !== 'publish') {
            return false;
        }

        // Verificar stock solo si se gestiona
        if ($product->managing_stock() && !$product->is_in_stock()) {
            return false;
        }

        // Log para auditoría
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[RFQ-DEBUG] Producto %d considerado comprable en contexto RFQ (precio: %s)',
                $product->get_id(),
                $product->get_price() ?: 'sin precio'
            ));
        }

        // En contexto RFQ, permitir productos sin precio
        return true;
    }

    /**
     * Verifica si estamos en contexto RFQ - detección on-demand
     * 
     * Esta función realiza la detección completa en el momento de la consulta
     * sin depender de flags globales o hooks de inicialización que causan
     * problemas con el ciclo de vida de WordPress/WooCommerce.
     *
     * @since 0.1.0
     * @return bool
     */
    private function detect_rfq_context_active() {
        // Variable estática local para cachear durante la misma petición
        static $context_checked = null;
        static $is_rfq_context = false;
        
        // Si ya verificamos en esta petición, usar cache
        if ($context_checked !== null) {
            return $is_rfq_context;
        }
        
        $context_checked = true;
        
        // Criterio 1: Gateway RFQ seleccionado en sesión/POST
        if ($this->is_rfq_gateway_selected()) {
            $is_rfq_context = true;
            return $is_rfq_context;
        }

        // Criterio 2: Endpoint de repetir solicitud
        if ($this->is_repeat_solicitud_endpoint()) {
            $is_rfq_context = true;
            return $is_rfq_context;
        }

        // Criterio 3: POST con acción RFQ
        if ($this->is_rfq_action_request()) {
            $is_rfq_context = true;
            return $is_rfq_context;
        }

        // Criterio 4: Activación manual por otras clases (ej: SolicitudRepeat)
        if ($this->is_manual_rfq_activation()) {
            $is_rfq_context = true;
            return $is_rfq_context;
        }

        return $is_rfq_context;
    }

    /**
     * Verifica si el gateway RFQ está seleccionado
     *
     * @since 0.1.0
     * @return bool
     */
    private function is_rfq_gateway_selected() {
        // Verificar WooCommerce disponible
        if (!function_exists('WC') || !WC()->session) {
            return false;
        }

        // Verificar método de pago seleccionado en sesión
        $chosen_payment_method = WC()->session->get('chosen_payment_method');
        if ($chosen_payment_method === 'rfq_gateway') {
            return true;
        }

        // Verificar en POST data
        if (isset($_POST['payment_method']) && $_POST['payment_method'] === 'rfq_gateway') {
            return true;
        }

        return false;
    }

    /**
     * Verifica si estamos en endpoint de repetir solicitud
     *
     * @since 0.1.0
     * @return bool
     */
    private function is_repeat_solicitud_endpoint() {
        // Verificar query vars
        if (isset($_GET['repeat_solicitud']) || get_query_var('repeat_solicitud')) {
            return true;
        }

        // Verificar URL patterns
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($request_uri, 'repeat-solicitud') !== false || 
            strpos($request_uri, 'repetir-solicitud') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Verifica si es una acción POST relacionada con RFQ
     *
     * @since 0.1.0
     * @return bool
     */
    private function is_rfq_action_request() {
        // Verificar acciones AJAX
        $ajax_action = $_POST['action'] ?? $_GET['action'] ?? '';
        if (strpos($ajax_action, 'rfq') !== false || strpos($ajax_action, 'solicitud') !== false) {
            return true;
        }

        // Verificar form data
        if (isset($_POST['rfq_repeat_solicitud']) || isset($_POST['add_rfq_to_cart'])) {
            return true;
        }

        return false;
    }

    /**
     * Verifica activación manual por otras clases (ej: SolicitudRepeat)
     * 
     * Utiliza una variable superglobal temporal para permitir activación
     * manual desde otras partes del plugin sin depender de flags de clase.
     *
     * @since 0.1.0
     * @return bool
     */
    private function is_manual_rfq_activation() {
        return isset($GLOBALS['rfq_context_manual_override']) && $GLOBALS['rfq_context_manual_override'] === true;
    }

    /**
     * Método estático para activar contexto RFQ manualmente
     * 
     * Mantiene compatibilidad con llamadas desde SolicitudRepeat
     * usando variable superglobal temporal en lugar de flag de clase.
     *
     * @since 0.1.0
     * @param bool $active Estado del contexto
     * @return void
     */
    public static function set_rfq_context($active = true) {
        if ($active) {
            $GLOBALS['rfq_context_manual_override'] = true;
        } else {
            unset($GLOBALS['rfq_context_manual_override']);
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[RFQ-DEBUG] Contexto RFQ establecido manualmente: ' . ($active ? 'ACTIVO' : 'INACTIVO'));
        }
    }

    /**
     * Método estático para verificar contexto RFQ (compatibilidad)
     *
     * @since 0.1.0
     * @return bool
     */
    public static function is_rfq_context_active() {
        return isset($GLOBALS['rfq_context_manual_override']) && $GLOBALS['rfq_context_manual_override'] === true;
    }
}
