<?php
/**
 * Template para mostrar la página de pago de una cotización
 *
 * @package    GiVendor\GiPlugin\Templates
 * @since      0.1.0
 */

defined('ABSPATH') || exit;

get_header();

// Obtener el ID de la cotización desde la URL
global $wp;
$current_url = home_url($wp->request);
$cotizacion_id = absint(basename(parse_url($current_url, PHP_URL_PATH)));

// Verificar que la cotización existe y está aceptada
$cotizacion = get_post($cotizacion_id);
if (!$cotizacion || $cotizacion->post_type !== 'cotizacion' || $cotizacion->post_status !== 'rfq-accepted') {
    echo '<div class="rfq-error-container">';
    echo '<p class="rfq-error">' . esc_html__('Cotización no encontrada o no disponible para pago.', 'rfq-manager-woocommerce') . '</p>';
    echo '</div>';
    get_footer();
    exit;
}

// Verificar que el usuario tiene permisos
$user = wp_get_current_user();
$solicitud_id = get_post_meta($cotizacion_id, '_solicitud_parent', true);
$solicitud = get_post($solicitud_id);

if (!$solicitud || ((int)$solicitud->post_author !== (int)$user->ID && !current_user_can('manage_options'))) {
    echo '<div class="rfq-error-container">';
    echo '<p class="rfq-error">' . esc_html__('No tienes permisos para acceder a esta página.', 'rfq-manager-woocommerce') . '</p>';
    echo '</div>';
    get_footer();
    exit;
}

// Obtener datos de la cotización
$proveedor = get_userdata($cotizacion->post_author);
$total = get_post_meta($cotizacion_id, '_total', true);
$items = json_decode(get_post_meta($cotizacion_id, '_items', true), true);
?>

<main class="rfq-payment-container">
    <article class="rfq-payment-content">
        <header class="rfq-payment-header">
            <h1 class="rfq-payment-title"><?php esc_html_e('Proceder al Pago', 'rfq-manager-woocommerce'); ?></h1>
            <div class="rfq-payment-info">
                <p><?php printf(esc_html__('Cotización de %s', 'rfq-manager-woocommerce'), esc_html($proveedor->display_name)); ?></p>
                <p class="rfq-payment-total"><?php printf(esc_html__('Total: %s', 'rfq-manager-woocommerce'), wc_price($total)); ?></p>
            </div>
        </header>

        <div class="rfq-payment-content-wrapper">
            <!-- Resumen de la cotización -->
            <div class="rfq-payment-summary">
                <h2><?php esc_html_e('Resumen de la Cotización', 'rfq-manager-woocommerce'); ?></h2>
                
                <div class="rfq-summary-details">
                    <div class="rfq-summary-item">
                        <span class="rfq-summary-label"><?php esc_html_e('Proveedor:', 'rfq-manager-woocommerce'); ?></span>
                        <span class="rfq-summary-value"><?php echo esc_html($proveedor->display_name); ?></span>
                    </div>
                    <div class="rfq-summary-item">
                        <span class="rfq-summary-label"><?php esc_html_e('Fecha de cotización:', 'rfq-manager-woocommerce'); ?></span>
                        <span class="rfq-summary-value"><?php echo esc_html(get_the_date('d/m/Y H:i', $cotizacion_id)); ?></span>
                    </div>
                    <div class="rfq-summary-item rfq-summary-total">
                        <span class="rfq-summary-label"><?php esc_html_e('Total a pagar:', 'rfq-manager-woocommerce'); ?></span>
                        <span class="rfq-summary-value"><?php echo wc_price($total); ?></span>
                    </div>
                </div>

                <?php if (!empty($items)): ?>
                <div class="rfq-items-summary">
                    <h3><?php esc_html_e('Productos cotizados:', 'rfq-manager-woocommerce'); ?></h3>
                    <table class="rfq-items-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Producto', 'rfq-manager-woocommerce'); ?></th>
                                <th><?php esc_html_e('Cantidad', 'rfq-manager-woocommerce'); ?></th>
                                <th><?php esc_html_e('Precio Unit.', 'rfq-manager-woocommerce'); ?></th>
                                <th><?php esc_html_e('Subtotal', 'rfq-manager-woocommerce'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?php echo esc_html($item['nombre']); ?></td>
                                    <td><?php echo esc_html($item['cantidad']); ?></td>
                                    <td><?php echo wc_price($item['precio']); ?></td>
                                    <td><?php echo wc_price($item['subtotal']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Métodos de pago -->
            <div class="rfq-payment-methods">
                <h2><?php esc_html_e('Selecciona tu método de pago', 'rfq-manager-woocommerce'); ?></h2>
                
                <div class="rfq-payment-options">
                    <!-- Aquí se pueden agregar diferentes métodos de pago -->
                    <p class="rfq-payment-notice">
                        <?php esc_html_e('Los métodos de pago estarán disponibles próximamente. Por favor, contacta con el administrador para proceder con el pago.', 'rfq-manager-woocommerce'); ?>
                    </p>
                    
                    <!-- Botón temporal para contactar -->
                    <div class="rfq-payment-contact">
                        <a href="<?php echo esc_url(home_url('/contacto')); ?>" class="button button-primary">
                            <?php esc_html_e('Contactar para completar el pago', 'rfq-manager-woocommerce'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <footer class="rfq-payment-footer">
            <a href="<?php echo esc_url(home_url('/ver-solicitud/' . $solicitud->post_name . '/')); ?>" class="button">
                <?php esc_html_e('Volver a la solicitud', 'rfq-manager-woocommerce'); ?>
            </a>
        </footer>
    </article>
</main>

<style>
.rfq-payment-container {
    max-width: 800px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.rfq-payment-header {
    text-align: center;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.rfq-payment-title {
    color: #2c3e50;
    margin-bottom: 1rem;
}

.rfq-payment-info p {
    margin: 0.5rem 0;
    color: #666;
}

.rfq-payment-total {
    font-size: 1.2em;
    font-weight: bold;
    color: #27ae60;
}

.rfq-payment-content-wrapper {
    display: grid;
    gap: 2rem;
    margin-bottom: 2rem;
}

.rfq-payment-summary,
.rfq-payment-methods {
    background: #fff;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.rfq-summary-details {
    margin-bottom: 1.5rem;
}

.rfq-summary-item {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid #eee;
}

.rfq-summary-total {
    font-size: 1.1em;
    font-weight: bold;
    color: #27ae60;
}

.rfq-items-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.rfq-items-table th,
.rfq-items-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.rfq-items-table th {
    background: #f8f9fa;
    font-weight: bold;
}

.rfq-payment-notice {
    text-align: center;
    padding: 1rem;
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
    color: #856404;
}

.rfq-payment-contact {
    text-align: center;
    margin-top: 1.5rem;
}

.rfq-payment-footer {
    text-align: center;
    margin-top: 2rem;
}

.rfq-error-container {
    max-width: 600px;
    margin: 2rem auto;
    text-align: center;
}

.rfq-error {
    background: #f8d7da;
    color: #721c24;
    padding: 1rem;
    border-radius: 4px;
    border: 1px solid #f5c6cb;
}
</style>

<?php
get_footer();
?> 