<?php


namespace GiVendor\GiPlugin\Solicitud;

class SolicitudStatusHelper
{
    const STATUS_PENDING  = 'rfq-pending';
    const STATUS_ACTIVE   = 'rfq-active';
    const STATUS_ACCEPTED = 'rfq-accepted';
    const STATUS_CLOSED   = 'rfq-closed';
    const STATUS_HISTORIC = 'rfq-historic';

    public static function render_badge(\WP_Post $solicitud): string
    {
        $status = $solicitud->post_status;
        $status_map = [
            self::STATUS_PENDING  => ['class' => 'rfq-status-badge rfq-status-pendiente', 'label' => __('Pendiente', 'rfq-manager-woocommerce')],
            self::STATUS_ACTIVE   => ['class' => 'rfq-status-badge rfq-status-activa', 'label' => __('Activa', 'rfq-manager-woocommerce')],
            self::STATUS_ACCEPTED => ['class' => 'rfq-status-badge rfq-status-aceptada', 'label' => __('Aceptada', 'rfq-manager-woocommerce')],
            self::STATUS_CLOSED   => ['class' => 'rfq-status-badge rfq-status-historica', 'label' => __('Cerrada', 'rfq-manager-woocommerce')],
            self::STATUS_HISTORIC => ['class' => 'rfq-status-badge rfq-status-historica', 'label' => __('Histórica', 'rfq-manager-woocommerce')],
        ];
        $class = isset($status_map[$status]) ? $status_map[$status]['class'] : 'rfq-status-badge';
        $label = isset($status_map[$status]) ? $status_map[$status]['label'] : esc_html($status);

        ob_start(); ?>
        <div class="<?php echo esc_attr($class); ?>">
            <span class="rfq-status-dot"></span>
            <span class="rfq-status-text"><?php echo esc_html($label); ?></span>
        </div>
        <?php
        return trim(ob_get_clean());
    }

    public static function is_historic($solicitud): bool {
        $status = is_object($solicitud) ? $solicitud->post_status : (string)$solicitud;
        return $status === self::STATUS_HISTORIC;
    }

    public static function is_pending($solicitud): bool {
        return is_object($solicitud) ? $solicitud->post_status === self::STATUS_PENDING : (string)$solicitud === self::STATUS_PENDING;
    }

    public static function is_active($solicitud): bool {
        return is_object($solicitud) ? $solicitud->post_status === self::STATUS_ACTIVE : (string)$solicitud === self::STATUS_ACTIVE;
    }

    public static function is_accepted($solicitud): bool {
        return is_object($solicitud) ? $solicitud->post_status === self::STATUS_ACCEPTED : (string)$solicitud === self::STATUS_ACCEPTED;
    }

    public static function is_closed($solicitud): bool {
        return is_object($solicitud) ? $solicitud->post_status === self::STATUS_CLOSED : (string)$solicitud === self::STATUS_CLOSED;
    }

    public static function is_repeatable($solicitud): bool {
        $status = is_object($solicitud) ? $solicitud->post_status : (string)$solicitud;
        return in_array($status, [self::STATUS_HISTORIC, self::STATUS_ACCEPTED], true);
    }

    public static function is_cancelable($solicitud): bool {
        $status = is_object($solicitud) ? $solicitud->post_status : (string)$solicitud;
        return in_array($status, [self::STATUS_PENDING, self::STATUS_ACTIVE], true);
    }
}
