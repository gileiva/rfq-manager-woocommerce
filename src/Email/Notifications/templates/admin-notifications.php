<?php
if (!defined('ABSPATH')) {
    exit;
}

$roles = ['administrator', 'shop_manager', 'customer'];
?>

<div class="wrap">
    <h1>Gestión de Notificaciones RFQ</h1>

    <?php foreach ($roles as $role): ?>
        <div class="notification-section">
            <h2>Notificaciones para <?php echo esc_html(ucfirst($role)); ?></h2>
            <button class="button button-secondary reset-notifications-btn" data-role="<?php echo esc_attr($role); ?>">
                Reiniciar Notificaciones
            </button>
            <!-- Resto del contenido de la sección -->
        </div>
    <?php endforeach; ?>
</div>

<script>
    var rfqNotifications = {
        nonce: '<?php echo wp_create_nonce('rfq_notifications_nonce'); ?>'
    };
</script> 