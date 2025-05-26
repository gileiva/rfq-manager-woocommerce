<?php
/**
 * Plantilla para la notificación de cambio a estado Aceptada (Usuario)
 *
 * Esta plantilla se utiliza para notificar a un usuario cuando el estado de su solicitud
 * cambia a "aceptada" tras seleccionar una cotización.
 *
 * NOTA: Esta plantilla está siendo reemplazada por cotizacion-accepted.php.
 * Se mantiene por compatibilidad con versiones anteriores.
 *
 * Variables disponibles:
 * - $solicitud_id: ID de la solicitud
 * - $user: Objeto usuario destinatario
 * - $new_status: Nuevo estado
 * - $old_status: Estado anterior
 *
 * @package    GiVendor\GiPlugin\Email\Templates
 * @since      0.1.0
 */

// Seguridad: Evitar acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

use GiVendor\GiPlugin\Email\Templates\NotificationTemplateFactory;

// Obtener el título de la solicitud
$solicitud_title = get_the_title($solicitud_id);

// Obtener información de la cotización aceptada
$cotizacion_id = get_post_meta($solicitud_id, '_rfq_accepted_cotizacion', true);

// Si tenemos un ID de cotización, redirigir a la plantilla específica
if ($cotizacion_id) {
    $cotizacion = get_post($cotizacion_id);
    if ($cotizacion) {
        // Incluir la plantilla específica de cotización aceptada
        include RFQ_MANAGER_WOO_PLUGIN_DIR . 'src/Email/Templates/User/cotizacion-accepted.php';
        return;
    }
}

// Si no se encuentra la cotización, seguir con esta plantilla como fallback

// Construir un enlace a la página de detalle de la solicitud
$solicitud_link = '';
$solicitud_slug = get_post_field('post_name', $solicitud_id);
$view_url = home_url('ver-solicitud/' . $solicitud_slug);
$payment_url = home_url('pago-cotizacion/' . $solicitud_slug);

// Obtener información del proveedor (si está disponible)
$supplier_id = $cotizacion_id ? get_post_field('post_author', $cotizacion_id) : 0;
$supplier = $supplier_id ? get_userdata($supplier_id) : null;
$supplier_name = $supplier ? $supplier->display_name : __('Proveedor', 'rfq-manager-woocommerce');

// Contenido personalizado para el correo
$content = '<p>' . sprintf(__('Hola %s,', 'rfq-manager-woocommerce'), esc_html($user->display_name)) . '</p>';
$content .= '<p>' . sprintf(__('¡Enhorabuena! Tu solicitud "%s" ha sido completada con éxito al aceptar una cotización.', 'rfq-manager-woocommerce'), esc_html($solicitud_title)) . '</p>';

if (isset($porcentaje_ahorro) && $porcentaje_ahorro > 0) {
    $content .= '<p style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0;">';
    $content .= sprintf(__('¡Enhorabuena! Gracias a The Clever Dentist has conseguido un %s%% de descuento sobre el precio medio del mercado.', 'rfq-manager-woocommerce'), number_format($porcentaje_ahorro, 2));
    $content .= '</p>';
}

$content .= '<div style="background-color: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #4CAF50;">';
$content .= '<h3 style="margin-top: 0; color: #4CAF50;">' . __('Información de la solicitud completada', 'rfq-manager-woocommerce') . '</h3>';
$content .= '<p><strong>' . __('Solicitud:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($solicitud_title) . '</p>';
$content .= '<p><strong>' . __('Estado anterior:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($old_status) . '</p>';
$content .= '<p><strong>' . __('Nuevo estado:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($new_status) . '</p>';
$content .= '<p><strong>' . __('Fecha de aceptación:', 'rfq-manager-woocommerce') . '</strong> ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format')) . '</p>';
$content .= '</div>';

// Información de pago
$content .= '<div style="background-color: #fff7e6; padding: 15px; margin: 15px 0; border-left: 4px solid #ff9800;">';
$content .= '<h3 style="margin-top: 0; color: #ff9800;">' . __('Información de pago', 'rfq-manager-woocommerce') . '</h3>';
$content .= '<p>' . __('Para completar el proceso, es necesario realizar el pago correspondiente a la cotización aceptada.', 'rfq-manager-woocommerce') . '</p>';
$content .= '<p>' . __('Una vez realizado el pago, el proveedor será notificado y procederá con el servicio o entrega del producto.', 'rfq-manager-woocommerce') . '</p>';
$content .= '</div>';

if ($supplier) {
    $content .= '<p>' . __('El proveedor ha sido notificado de tu aceptación y está a la espera de tu pago para proceder.', 'rfq-manager-woocommerce') . '</p>';
}

// Agregar enlaces para ver la solicitud y realizar el pago
$content .= '<p style="text-align: center; margin-top: 20px;">';

if ($view_url) {
    $content .= '<a href="' . esc_url($view_url) . '" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;">';
    $content .= __('Ver detalles', 'rfq-manager-woocommerce');
    $content .= '</a>';
}

if ($payment_url) {
    $content .= '<a href="' . esc_url($payment_url) . '" style="background-color: #ff9800; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">';
    $content .= __('Realizar pago', 'rfq-manager-woocommerce');
    $content .= '</a>';
}

$content .= '</p>';

$content .= '<p>' . __('Si tienes alguna pregunta o necesitas más información, no dudes en contactarnos.', 'rfq-manager-woocommerce') . '</p>';
$content .= '<p>' . __('Gracias por utilizar nuestro sistema de solicitud de cotizaciones.', 'rfq-manager-woocommerce') . '</p>';

// Crear la plantilla utilizando el factory
echo NotificationTemplateFactory::create(
    'user',
    __('Tu solicitud ha sido completada con éxito - Información de pago', 'rfq-manager-woocommerce'),
    $content,
    [
        'footer_text' => __('Esperamos que hayas tenido una buena experiencia con nuestro servicio.', 'rfq-manager-woocommerce')
    ]
); 