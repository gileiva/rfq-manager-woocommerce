<?php
namespace GiVendor\GiPlugin\Solicitud\View;

if (!defined('ABSPATH')) exit;

class SolicitudHeaderRenderer
{
    /**
     * Renderiza la cabecera visual de la solicitud.
     *
     * @param \WP_Post $solicitud
     * @param string $context Contexto de visualización ('cliente', 'proveedor', etc.)
     * @param array $options Opciones adicionales (por ejemplo, ['show_expiry' => true])
     * @return string HTML de la cabecera
     */
    public static function render(\WP_Post $solicitud, string $context = 'cliente', array $options = []): string
    {
        $solicitud_id = $solicitud->ID;
        $uuid = get_post_meta($solicitud_id, '_solicitud_uuid', true);
        $numero = $uuid ? 'RFQ-' . substr(str_replace('-', '', $uuid), -5) : '';

        $fecha = get_the_date('', $solicitud_id);
        $ciudad = get_post_meta($solicitud_id, '_solicitud_ciudad', true);
        $cp = get_post_meta($solicitud_id, '_solicitud_cp', true);

        // Usar el helper centralizado para el badge de estado
        ob_start();
        ?>
        <div class="rfq-solicitud-header">
            <div class="rfq-header-item">
                <span class="rfq-header-label">Número de solicitud</span>
                <span class="rfq-header-value"><?php echo esc_html($numero); ?></span>
            </div>
            <div class="rfq-header-item">
                <span class="rfq-header-label">Status</span>
                <span class="rfq-header-value">
                    <?php echo \GiVendor\GiPlugin\Solicitud\SolicitudStatusHelper::render_badge($solicitud); ?>
                </span>
            </div>
            <div class="rfq-header-item">
                <span class="rfq-header-label">Fecha</span>
                <span class="rfq-header-value"><?php echo esc_html($fecha); ?></span>
            </div>
            <div class="rfq-header-item">
                <span class="rfq-header-label">Ciudad</span>
                <span class="rfq-header-value"><?php echo esc_html($ciudad); ?></span>
            </div>
            <div class="rfq-header-item">
                <span class="rfq-header-label">CP</span>
                <span class="rfq-header-value"><?php echo esc_html($cp); ?></span>
            </div>
            <?php if (!empty($options['show_expiry']) && $context === 'proveedor'):
                $expiry_raw = get_post_meta($solicitud_id, '_solicitud_expiry', true);
                $expiry_attr = '';
                if ($expiry_raw) {
                    $ts = strtotime($expiry_raw);
                    if ($ts) {
                        $expiry_attr = date('Y-m-d\TH:i:s', $ts);
                    }
                }
                if ($expiry_attr): ?>
                <div class="rfq-header-item rfq-header-item-countdown">
                    <span class="rfq-header-value">
                        <span class="rfq-expiry-countdown" data-expiry="<?php echo esc_attr($expiry_attr); ?>"></span>
                    </span>
                </div>
            <?php endif; endif; ?>
        </div>
        <?php
        return trim(ob_get_clean());
    }
    //
}
