<?php
/**
 * Plantilla para la notificación de cambio a estado Histórica (Usuario)
 *
 * Esta plantilla se utiliza para notificar a un usuario cuando el estado de su solicitud
 * cambia a "historica" por haber vencido sin aceptar ninguna cotización.
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

// Construir un enlace a la página de detalle de la solicitud
$solicitud_link = '';
$my_account_url = wc_get_page_permalink('myaccount');
if ($my_account_url) {
    $solicitud_link = add_query_arg([
        'section' => 'rfq-requests',
        'view' => 'detail',
        'id' => $solicitud_id
    ], $my_account_url);
}

// Construir un enlace para crear una nueva solicitud
$new_request_link = '';
if ($my_account_url) {
    $new_request_link = add_query_arg([
        'section' => 'rfq-requests',
        'action' => 'new'
    ], $my_account_url);
}

// Obtener fecha de vencimiento
$expiry_date = get_post_meta($solicitud_id, '_solicitud_expiry', true);
$formatted_expiry = $expiry_date ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($expiry_date)) : '';

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
$content .= '<p>' . sprintf(__('Tu solicitud "%s" ha pasado al historial.', 'rfq-manager-woocommerce'), esc_html($solicitud_title)) . '</p>';

$content .= '<div style="background-color: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #9E9E9E;">';
$content .= '<h3 style="margin-top: 0; color: #9E9E9E;">' . __('Información de la solicitud', 'rfq-manager-woocommerce') . '</h3>';
$content .= '<p><strong>' . __('Solicitud:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($solicitud_title) . '</p>';
$content .= '<p><strong>' . __('Estado anterior:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($old_status) . '</p>';
$content .= '<p><strong>' . __('Nuevo estado:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($new_status) . '</p>';
if ($formatted_expiry) {
    $content .= '<p><strong>' . __('Fecha de vencimiento:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($formatted_expiry) . '</p>';
}
if ($total_cotizaciones > 0) {
    $content .= '<p><strong>' . __('Cotizaciones recibidas:', 'rfq-manager-woocommerce') . '</strong> ' . $total_cotizaciones . '</p>';
}
$content .= '</div>';

if ($total_cotizaciones == 0) {
    $content .= '<p>' . __('Tu solicitud ha pasado al historial debido a que ha vencido el plazo sin recibir ninguna cotización.', 'rfq-manager-woocommerce') . '</p>';
} else {
    $content .= '<p>' . __('Tu solicitud ha pasado al historial debido a que ha vencido el plazo sin que se haya aceptado ninguna cotización.', 'rfq-manager-woocommerce') . '</p>';
}

$content .= '<p>' . __('Si aún estás interesado en recibir cotizaciones para este producto o servicio, te recomendamos crear una nueva solicitud.', 'rfq-manager-woocommerce') . '</p>';

// Agregar enlaces
$content .= '<p style="text-align: center; margin-top: 20px;">';
if (!empty($solicitud_link)) {
    $content .= '<a href="' . esc_url($solicitud_link) . '" style="background-color: #9E9E9E; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;">';
    $content .= __('Ver solicitud en historial', 'rfq-manager-woocommerce');
    $content .= '</a>';
}
if (!empty($new_request_link)) {
    $content .= '<a href="' . esc_url($new_request_link) . '" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">';
    $content .= __('Crear nueva solicitud', 'rfq-manager-woocommerce');
    $content .= '</a>';
}
$content .= '</p>';

$content .= '<p>' . __('Si tienes alguna pregunta o necesitas más información, no dudes en contactarnos.', 'rfq-manager-woocommerce') . '</p>';

// Crear la plantilla utilizando el factory
echo NotificationTemplateFactory::create(
    'user',
    __('Tu solicitud ha pasado al historial', 'rfq-manager-woocommerce'),
    $content,
    [
        'footer_text' => __('Gracias por utilizar nuestro sistema de solicitud de cotizaciones.', 'rfq-manager-woocommerce')
    ]
); 