<?php
/**
 * Plantilla para la notificación de cotización recibida (Usuario)
 *
 * Esta plantilla se utiliza para notificar a un usuario cuando ha recibido
 * una nueva cotización para su solicitud.
 *
 * Variables disponibles:
 * - $cotizacion_id: ID de la cotización
 * - $solicitud_id: ID de la solicitud relacionada
 * - $user: Objeto usuario destinatario
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

// Obtener información del proveedor
$supplier_id = get_post_field('post_author', $cotizacion_id);
$supplier = get_userdata($supplier_id);
$supplier_name = $supplier ? $supplier->display_name : __('Proveedor', 'rfq-manager-woocommerce');

// Obtener detalles de la cotización
$cotizacion_title = get_the_title($cotizacion_id);
$cotizacion_amount = get_post_meta($cotizacion_id, '_rfq_cotizacion_amount', true);
$formatted_amount = '';
if (!empty($cotizacion_amount)) {
    $formatted_amount = wc_price($cotizacion_amount);
}

// Construir un enlace a la página de detalle de la solicitud
$solicitud_link = home_url('ver-solicitud/' . get_post_field('post_name', $solicitud_id));

// Obtener el número de cotizaciones recibidas
$cotizaciones = get_posts([
    'post_type' => 'rfq_cotizacion',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'meta_query' => [
        [
            'key' => '_rfq_solicitud_id',
            'value' => $solicitud_id,
        ]
    ]
]);
$total_cotizaciones = count($cotizaciones);

// Contenido personalizado para el correo
$content = '<p>' . sprintf(__('Hola %s,', 'rfq-manager-woocommerce'), esc_html($user->display_name)) . '</p>';
$content .= '<p>' . sprintf(__('¡Buenas noticias! Has recibido una nueva cotización para tu solicitud "%s".', 'rfq-manager-woocommerce'), esc_html($solicitud_title)) . '</p>';

$content .= '<div style="background-color: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #2196F3;">';
$content .= '<h3 style="margin-top: 0; color: #2196F3;">' . __('Detalles de la cotización', 'rfq-manager-woocommerce') . '</h3>';
$content .= '<p><strong>' . __('Proveedor:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($supplier_name) . '</p>';
if (!empty($cotizacion_title)) {
    $content .= '<p><strong>' . __('Título de la cotización:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($cotizacion_title) . '</p>';
}
if (!empty($formatted_amount)) {
    $content .= '<p><strong>' . __('Monto ofertado:', 'rfq-manager-woocommerce') . '</strong> ' . $formatted_amount . '</p>';
}
$content .= '<p><strong>' . __('Fecha de recepción:', 'rfq-manager-woocommerce') . '</strong> ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format')) . '</p>';
$content .= '</div>';

if ($total_cotizaciones > 1) {
    $content .= '<p>' . sprintf(__('Hasta ahora has recibido %d cotizaciones para esta solicitud.', 'rfq-manager-woocommerce'), $total_cotizaciones) . '</p>';
    $content .= '<p>' . __('Puedes comparar todas las ofertas y elegir la que mejor se ajuste a tus necesidades.', 'rfq-manager-woocommerce') . '</p>';
} else {
    $content .= '<p>' . __('Esta es la primera cotización que recibes para tu solicitud.', 'rfq-manager-woocommerce') . '</p>';
    $content .= '<p>' . __('Podrías recibir más cotizaciones próximamente, pero siempre puedes aceptar esta si cumple con tus requisitos.', 'rfq-manager-woocommerce') . '</p>';
}

// Agregar enlace a la solicitud
$content .= '<p style="text-align: center; margin-top: 20px;">';
$content .= '<a href="' . esc_url($solicitud_link) . '" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">';
$content .= __('Ver y gestionar cotizaciones', 'rfq-manager-woocommerce');
$content .= '</a></p>';

$content .= '<p>' . __('Recuerda que puedes aceptar la cotización que más te convenga en cualquier momento.', 'rfq-manager-woocommerce') . '</p>';

// Crear la plantilla utilizando el factory
echo NotificationTemplateFactory::create(
    'user',
    __('Has recibido una nueva cotización', 'rfq-manager-woocommerce'),
    $content,
    [
        'footer_text' => __('Gracias por utilizar nuestro sistema de solicitud de cotizaciones.', 'rfq-manager-woocommerce')
    ]
); 