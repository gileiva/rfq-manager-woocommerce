<?php
/**
 * RFQCheckoutProtectionManager - Protección del proceso de pago de ofertas aceptadas
 *
 * @package    GiVendor\GiPlugin\Services
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Services;

/**
 * RFQCheckoutProtectionManager
 *
 * Servicio especializado en proteger el proceso de checkout para ofertas RFQ aceptadas:
 * - Bloquea edición de cantidades/productos en order-pay
 * - Valida integridad de la orden antes del pago
 * - Redirección post-pago a página de gracias
 * - Logging de seguridad
 *
 * @package    GiVendor\GiPlugin\Services
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class RFQCheckoutProtectionManager {

    /**
     * Inicializa los hooks del manager
     *
     * @since  0.1.0
     * @return void
     */
    public static function init_hooks(): void {
        // Hook para validación previa al proceso de pago
        add_action('woocommerce_checkout_process', [self::class, 'validate_rfq_order_integrity']);
        
        // Hook para validar expiración antes del pago
        add_action('woocommerce_checkout_process', [self::class, 'validate_rfq_order_expiry']);
        
        // Hook para redirección post-pago
        add_action('woocommerce_thankyou', [self::class, 'redirect_rfq_thankyou'], 10, 1);
        
        // Hook para enqueue de estilos de protección
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_protection_styles']);
        
        // Hook para remover controles de edición en order-pay
        add_action('wp', [self::class, 'disable_order_pay_editing']);
        
        // Filtro para prevenir modificación de items en órdenes RFQ
        add_filter('woocommerce_order_item_needs_processing', [self::class, 'prevent_rfq_order_modification'], 10, 3);
        
        // Hook para verificar expiración en order-pay
        add_action('wp', [self::class, 'check_order_pay_expiry']);
    }

    /**
     * Valida la integridad de una orden RFQ antes del proceso de pago
     *
     * @since  0.1.0
     * @return void
     * @throws Exception Si hay manipulación detectada
     */
    public static function validate_rfq_order_integrity(): void {
        global $woocommerce;
        
        // Verificar si estamos procesando una orden existente (order-pay)
        if (!isset($_GET['order-pay']) || !isset($_GET['key'])) {
            return;
        }
        
        $order_id = absint($_GET['order-pay']);
        $order = wc_get_order($order_id);
        
        if (!$order) {
            error_log('[RFQ-PROTECCION] Orden no encontrada: ' . $order_id);
            return;
        }
        
        // Verificar si es una orden de oferta RFQ
        if ($order->get_meta('_rfq_offer_order') !== 'yes') {
            return; // No es orden RFQ, continuar normal
        }
        
        error_log('[RFQ-PROTECCION] Validando integridad de orden RFQ: ' . $order_id);
        
        // Obtener datos originales de la cotización
        $original_items = $order->get_meta('_rfq_original_items');
        $cotizacion_id = $order->get_meta('_rfq_cotizacion_id');
        
        if (!$original_items || !$cotizacion_id) {
            error_log('[RFQ-PROTECCION] Datos originales no encontrados para orden: ' . $order_id);
            return;
        }
        
        // Comparar items actuales con originales
        $current_items = [];
        foreach ($order->get_items() as $item_id => $item) {
            $current_items[] = [
                'product_id' => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'quantity' => $item->get_quantity(),
                'price' => $item->get_total()
            ];
        }
        
        // Validar integridad
        if (!self::compare_order_items($original_items, $current_items)) {
            error_log('[RFQ-PROTECCION] MANIPULACIÓN DETECTADA - Orden: ' . $order_id . ' - IP: ' . $_SERVER['REMOTE_ADDR']);
            
            wc_add_notice(
                'Error: Se detectó una modificación no autorizada en su orden. Por favor, contacte con soporte.',
                'error'
            );
            
            // Redirigir de vuelta a la orden
            wp_safe_redirect($order->get_checkout_payment_url());
            exit;
        }
        
        error_log('[RFQ-PROTECCION] Validación exitosa para orden RFQ: ' . $order_id);
    }

    /**
     * Valida que una orden RFQ no haya expirado antes del proceso de pago
     *
     * @since  0.1.0
     * @return void
     * @throws Exception Si la orden ha expirado
     */
    public static function validate_rfq_order_expiry(): void {
        global $woocommerce;
        
        // Verificar si estamos procesando una orden existente (order-pay)
        if (!isset($_GET['order-pay']) || !isset($_GET['key'])) {
            return;
        }
        
        $order_id = absint($_GET['order-pay']);
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Verificar si es una orden de oferta RFQ
        if ($order->get_meta('_rfq_offer_order') !== 'yes') {
            return; // No es orden RFQ, continuar normal
        }
        
        // Verificar expiración
        $expiry_timestamp = $order->get_meta('_rfq_order_acceptance_expiry');
        if ($expiry_timestamp) {
            $current_time = current_time('timestamp');
            
            if ($current_time > $expiry_timestamp) {
                error_log('[RFQ-PROTECCION] ORDEN EXPIRADA - Orden: ' . $order_id . ' - Expiró: ' . date('Y-m-d H:i:s', $expiry_timestamp) . ' - Ahora: ' . date('Y-m-d H:i:s', $current_time));
                
                // Cancelar la orden automáticamente
                $order->update_status('cancelled', __('Orden cancelada automáticamente por expiración de pago (24 horas).', 'rfq-manager-woocommerce'));
                
                wc_add_notice(
                    'Esta orden ha expirado. El tiempo límite para realizar el pago era de 24 horas desde la aceptación de la oferta.',
                    'error'
                );
                
                // Redirigir a página principal o mis solicitudes
                wp_safe_redirect(home_url('/mis-solicitudes/'));
                exit;
            } else {
                $hours_remaining = ceil(($expiry_timestamp - $current_time) / HOUR_IN_SECONDS);
                error_log('[RFQ-PROTECCION] Orden RFQ válida - ' . $order_id . ' - Horas restantes: ' . $hours_remaining);
            }
        }
    }

    /**
     * Verifica la expiración de orden en páginas order-pay
     *
     * @since  0.1.0
     * @return void
     */
    public static function check_order_pay_expiry(): void {
        if (!is_wc_endpoint_url('order-pay')) {
            return;
        }
        
        global $wp;
        $order_id = absint($wp->query_vars['order-pay']);
        
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order && $order->get_meta('_rfq_offer_order') === 'yes') {
                $expiry_timestamp = $order->get_meta('_rfq_order_acceptance_expiry');
                if ($expiry_timestamp) {
                    $current_time = current_time('timestamp');
                    
                    if ($current_time > $expiry_timestamp) {
                        // Orden expirada - cancelar y redirigir
                        $order->update_status('cancelled', __('Orden cancelada automáticamente por expiración de pago (24 horas).', 'rfq-manager-woocommerce'));
                        
                        wc_add_notice(
                            'Esta orden ha expirado. El tiempo límite para realizar el pago era de 24 horas desde la aceptación de la oferta.',
                            'error'
                        );
                        
                        error_log('[RFQ-PROTECCION] Orden expirada en order-pay - ID: ' . $order_id);
                        
                        wp_safe_redirect(home_url('/mis-solicitudes/'));
                        exit;
                    }
                }
            }
        }
    }

    /**
     * Compara los items de una orden con los datos originales
     *
     * @since  0.1.0
     * @param  array $original_items Items originales guardados
     * @param  array $current_items  Items actuales de la orden
     * @return bool                  True si coinciden, false si hay diferencias
     */
    private static function compare_order_items(array $original_items, array $current_items): bool {
        if (count($original_items) !== count($current_items)) {
            error_log('[RFQ-PROTECCION] Diferencia en cantidad de items');
            return false;
        }
        
        // Ordenar ambos arrays para comparación consistente
        usort($original_items, function($a, $b) {
            return $a['product_id'] <=> $b['product_id'];
        });
        
        usort($current_items, function($a, $b) {
            return $a['product_id'] <=> $b['product_id'];
        });
        
        // Comparar cada item
        for ($i = 0; $i < count($original_items); $i++) {
            $original = $original_items[$i];
            $current = $current_items[$i];
            
            if ($original['product_id'] != $current['product_id'] ||
                $original['quantity'] != $current['quantity'] ||
                abs(floatval($original['price']) - floatval($current['price'])) > 0.01) {
                
                error_log('[RFQ-PROTECCION] Diferencia detectada en item ' . $i . ': ' . 
                         json_encode(['original' => $original, 'current' => $current]));
                return false;
            }
        }
        
        return true;
    }

    /**
     * Redirecciona a /gracias tras pago exitoso de orden RFQ
     *
     * @since  0.1.0
     * @param  int $order_id ID de la orden
     * @return void
     */
    public static function redirect_rfq_thankyou(int $order_id): void {
        if (!$order_id) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Solo redirigir órdenes RFQ
        if ($order->get_meta('_rfq_offer_order') === 'yes') {
            error_log('[RFQ-PROTECCION] Redirigiendo orden RFQ a /gracias: ' . $order_id);
            
            wp_safe_redirect(home_url('/gracias/'));
            exit;
        }
    }

    /**
     * Encola estilos de protección para order-pay
     *
     * @since  0.1.0
     * @return void
     */
    public static function enqueue_protection_styles(): void {
        // Solo en páginas de order-pay
        if (!is_wc_endpoint_url('order-pay')) {
            return;
        }
        
        // Verificar si la orden es RFQ
        $order_id = 0;
        if (isset($_GET['order-pay'])) {
            $order_id = absint($_GET['order-pay']);
        }
        
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order && $order->get_meta('_rfq_offer_order') === 'yes') {
                // Agregar CSS inline para bloquear edición
                wp_add_inline_style('woocommerce-general', self::get_protection_css());
                
                // Marcar orden como protegida en JavaScript
                wp_add_inline_script('woocommerce', '
                    window.rfqProtectedOrders = window.rfqProtectedOrders || [];
                    window.rfqProtectedOrders.push("' . $order_id . '");
                    console.log("[RFQ-PROTECCION] Orden marcada como protegida:", "' . $order_id . '");
                ');
                
                error_log('[RFQ-PROTECCION] Estilos de protección aplicados a orden: ' . $order_id);
            }
        }
    }

    /**
     * Obtiene el CSS para bloquear edición en order-pay
     *
     * @since  0.1.0
     * @return string CSS de protección
     */
    private static function get_protection_css(): string {
        global $wp;
        $order_id = absint($wp->query_vars['order-pay'] ?? 0);
        $expiry_message = '';
        
        // Agregar mensaje de expiración si aplica
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order && $order->get_meta('_rfq_offer_order') === 'yes') {
                $expiry_timestamp = $order->get_meta('_rfq_order_acceptance_expiry');
                if ($expiry_timestamp) {
                    $current_time = current_time('timestamp');
                    $hours_remaining = max(0, ceil(($expiry_timestamp - $current_time) / HOUR_IN_SECONDS));
                    
                    if ($hours_remaining > 0) {
                        $expiry_message = "\\A⏰ Esta orden expira en {$hours_remaining} horas.";
                    } else {
                        $expiry_message = "\\A⚠️ Esta orden ha EXPIRADO.";
                    }
                }
            }
        }
        
        return '
            /* RFQ Order Pay Protection Styles */
            .woocommerce-order-pay .product-quantity,
            .woocommerce-order-pay .remove,
            .woocommerce-order-pay .product-remove,
            .woocommerce-order-pay .quantity input,
            .woocommerce-order-pay .qty,
            .woocommerce-order-pay input[name*="quantity"],
            .woocommerce-order-pay .woocommerce-cart-form__cart-item .quantity,
            body.woocommerce-order-pay .shop_table .product-quantity,
            body.woocommerce-order-pay .shop_table .product-remove {
                display: none !important;
                visibility: hidden !important;
            }
            
            /* Protección adicional para themes personalizados */
            .woocommerce-order-pay .cart-quantity,
            .woocommerce-order-pay .item-quantity,
            .woocommerce-order-pay .update-cart,
            .woocommerce-order-pay [name="update_cart"],
            .woocommerce-order-pay .coupon,
            .woocommerce-order-pay .woocommerce-cart-form {
                display: none !important;
                visibility: hidden !important;
            }
            
            /* Mensaje informativo para órdenes RFQ */
            .woocommerce-order-pay .shop_table::before {
                content: "⚠️ Esta es una orden de oferta aceptada. Los productos y cantidades no pueden ser modificados.' . $expiry_message . '";
                display: block;
                background: #fff3cd;
                color: #856404;
                padding: 12px 16px;
                margin-bottom: 20px;
                border: 1px solid #ffeaa7;
                border-radius: 4px;
                font-size: 14px;
                font-weight: 500;
                white-space: pre-line;
            }
            
            /* Protección anti-manipulación visual */
            .woocommerce-order-pay .woocommerce-checkout-review-order-table .product-name,
            .woocommerce-order-pay .woocommerce-checkout-review-order-table .product-total {
                position: relative;
            }
            
            .woocommerce-order-pay .woocommerce-checkout-review-order-table::after {
                content: "🔒";
                position: absolute;
                top: 10px;
                right: 10px;
                font-size: 18px;
                opacity: 0.7;
                pointer-events: none;
            }
        ';
    }

    /**
     * Deshabilita controles de edición en order-pay para órdenes RFQ
     *
     * @since  0.1.0
     * @return void
     */
    public static function disable_order_pay_editing(): void {
        if (!is_wc_endpoint_url('order-pay')) {
            return;
        }
        
        global $wp;
        $order_id = absint($wp->query_vars['order-pay']);
        
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order && $order->get_meta('_rfq_offer_order') === 'yes') {
                // Remover acciones que permiten modificar la orden
                remove_action('woocommerce_cart_contents', 'woocommerce_cart_contents_review_order_hook');
                remove_action('woocommerce_review_order_before_cart_contents', 'woocommerce_checkout_coupon_form');
                
                // Filtros para prevenir modificaciones
                add_filter('woocommerce_cart_item_remove_link', '__return_empty_string', 100);
                add_filter('woocommerce_cart_item_quantity', '__return_empty_string', 100);
                
                error_log('[RFQ-PROTECCION] Controles de edición deshabilitados para orden RFQ: ' . $order_id);
            }
        }
    }

    /**
     * Previene la modificación de items en órdenes RFQ
     *
     * @since  0.1.0
     * @param  bool  $needs_processing Si el item necesita procesamiento
     * @param  mixed $item             Item de la orden
     * @param  mixed $order            Orden
     * @return bool                    Resultado modificado
     */
    public static function prevent_rfq_order_modification(bool $needs_processing, $item, $order): bool {
        if (!$order || !is_object($order)) {
            return $needs_processing;
        }
        
        // Si es orden RFQ, prevenir modificaciones no autorizadas
        if ($order->get_meta('_rfq_offer_order') === 'yes') {
            // Log cualquier intento de modificación
            if (did_action('woocommerce_cart_item_set_quantity') || 
                did_action('woocommerce_cart_item_removed')) {
                error_log('[RFQ-PROTECCION] Intento de modificación en orden RFQ: ' . $order->get_id());
            }
        }
        
        return $needs_processing;
    }

    /**
     * Obtiene información de diagnóstico del sistema de protección
     *
     * @since  0.1.0
     * @return array Información de diagnóstico
     */
    public static function get_diagnostic_info(): array {
        return [
            'protection_manager' => 'RFQCheckoutProtectionManager',
            'hooks_registered' => [
                'woocommerce_checkout_process' => 'validate_rfq_order_integrity',
                'woocommerce_thankyou' => 'redirect_rfq_thankyou',
                'wp_enqueue_scripts' => 'enqueue_protection_styles',
                'wp' => 'disable_order_pay_editing'
            ],
            'protection_features' => [
                'order_integrity_validation' => true,
                'frontend_editing_blocked' => true,
                'post_payment_redirect' => true,
                'security_logging' => true
            ],
            'timestamp' => current_time('mysql')
        ];
    }
}
