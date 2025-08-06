<?php
/**
 * Shortcode de diagnóstico para flags RFQ
 *
 * @package    GiVendor\GiPlugin\Shortcode
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Shortcode;

/**
 * RFQDiagnosticShortcode - Shortcode para mostrar el estado actual de flags RFQ
 *
 * Uso: [rfq_diagnostic]
 * Este shortcode solo funciona para usuarios administradores y muestra información
 * detallada sobre el estado actual de los flags de sesión RFQ.
 *
 * @since 0.1.0
 */
class RFQDiagnosticShortcode 
{
    /**
     * Registra el shortcode
     *
     * @since 0.1.0
     */
    public static function register() {
        add_shortcode('rfq_diagnostic', [self::class, 'render']);
    }

    /**
     * Renderiza el contenido del shortcode
     *
     * @param array $atts Atributos del shortcode
     * @return string HTML del diagnóstico
     * @since 0.1.0
     */
    public static function render($atts = []) {
        // Solo mostrar para administradores
        if (!current_user_can('manage_options')) {
            return '<p><strong>RFQ Diagnóstico:</strong> Solo disponible para administradores.</p>';
        }

        $diagnostic = \GiVendor\GiPlugin\Services\RFQFlagsManager::get_diagnostic_info();
        $issues = \GiVendor\GiPlugin\Services\RFQFlagsManager::detect_flag_issues();

        // Log del diagnóstico
        \GiVendor\GiPlugin\Services\RFQFlagsManager::log_current_state();

        ob_start();
        ?>
        <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin: 20px 0; font-family: monospace;">
            <h3>🔍 RFQ Flags Diagnóstico</h3>
            <p><strong>Timestamp:</strong> <?php echo current_time('Y-m-d H:i:s'); ?></p>
            
            <h4>📊 Estado Actual:</h4>
            <ul>
                <li><strong>Session Disponible:</strong> <?php echo $diagnostic['session_available'] ? '✅ Sí' : '❌ No'; ?></li>
                <li><strong>rfq_context:</strong> <?php echo $diagnostic['rfq_context'] ? '✅ true' : '❌ false'; ?></li>
                <li><strong>rfq_offer_payment:</strong> <?php echo $diagnostic['rfq_offer_payment'] ? '✅ true' : '❌ false'; ?></li>
                <li><strong>chosen_payment_method:</strong> <?php echo $diagnostic['chosen_payment_method'] ?: '❌ null'; ?></li>
                <li><strong>RFQPurchasableOverride:</strong> <?php echo $diagnostic['rfq_purchasable_override'] ? '✅ true' : '❌ false'; ?></li>
                <li><strong>Current URL:</strong> <?php echo esc_html($diagnostic['current_url']); ?></li>
                <li><strong>Is Admin:</strong> <?php echo $diagnostic['is_admin'] ? '✅ Sí' : '❌ No'; ?></li>
                <li><strong>Is Checkout:</strong> <?php echo $diagnostic['is_checkout'] ? '✅ Sí' : '❌ No'; ?></li>
                <li><strong>Cart Count:</strong> <?php echo $diagnostic['cart_count']; ?> productos</li>
            </ul>

            <?php if (!empty($issues)): ?>
                <h4>⚠️ Problemas Detectados:</h4>
                <ul style="color: #d63384;">
                    <?php foreach ($issues as $issue): ?>
                        <li><?php echo esc_html($issue); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <h4>✅ Sin Problemas Detectados</h4>
                <p style="color: #198754;">Todos los flags están en estado consistente.</p>
            <?php endif; ?>

            <h4>🎯 Contexto Esperado:</h4>
            <?php if ($diagnostic['rfq_context'] && !$diagnostic['rfq_offer_payment']): ?>
                <p style="color: #0d6efd;"><strong>✅ NUEVA SOLICITUD RFQ</strong> - Solo gateway RFQ debería aparecer</p>
            <?php elseif (!$diagnostic['rfq_context'] && $diagnostic['rfq_offer_payment']): ?>
                <p style="color: #fd7e14;"><strong>💳 PAGO DE OFERTA</strong> - Solo gateways tradicionales deberían aparecer</p>
            <?php elseif (!$diagnostic['rfq_context'] && !$diagnostic['rfq_offer_payment']): ?>
                <p style="color: #6c757d;"><strong>🛒 WOOCOMMERCE ESTÁNDAR</strong> - Solo gateways tradicionales (sin RFQ)</p>
            <?php else: ?>
                <p style="color: #d63384;"><strong>❌ ESTADO INCONSISTENTE</strong> - Ambos flags activos</p>
            <?php endif; ?>

            <small style="color: #6c757d;">
                <em>Este diagnóstico se registra automáticamente en debug.log con prefijo [RFQ-FLAGS-MANAGER]</em>
            </small>
        </div>
        <?php
        return ob_get_clean();
    }
}
