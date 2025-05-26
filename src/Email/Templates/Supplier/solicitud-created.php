<?php
/**
 * Plantilla para la notificación de solicitud creada (Proveedor)
 *
 * Esta plantilla se utiliza para notificar a un proveedor cuando hay una nueva
 * solicitud de cotización disponible.
 *
 * Variables disponibles:
 * - $solicitud_id: ID de la solicitud
 * - $supplier: Objeto usuario proveedor destinatario
 * - $data: Datos adicionales de la solicitud
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

// Construir un enlace a la página de cotización
$cotizar_link = home_url('cotizar-solicitud/' . get_post_field('post_name', $solicitud_id));

// Fecha de vencimiento (si está disponible)
$expiration_date = get_post_meta($solicitud_id, '_rfq_expiration_date', true);
$expiration_text = '';
if (!empty($expiration_date)) {
    $formatted_date = date_i18n(get_option('date_format'), strtotime($expiration_date));
    $expiration_text = sprintf(__('Fecha límite para enviar cotización: %s', 'rfq-manager-woocommerce'), $formatted_date);
}

// Contenido personalizado para el correo
$content = '<p>' . sprintf(__('Hola %s,', 'rfq-manager-woocommerce'), esc_html($supplier->display_name)) . '</p>';
$content .= '<p>' . sprintf(__('Hay una nueva solicitud de cotización disponible: <strong>%s</strong>', 'rfq-manager-woocommerce'), esc_html($solicitud_title)) . '</p>';

// Si hay fecha de vencimiento, mostrarla
if (!empty($expiration_text)) {
    $content .= '<p><strong>' . $expiration_text . '</strong></p>';
}

// Si hay artículos en la solicitud, mostrarlos
if (!empty($data['items'])) {
    $content .= '<div style="background-color: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #2196F3;">';
    $content .= '<h3 style="margin-top: 0; color: #2196F3;">' . __('Artículos solicitados:', 'rfq-manager-woocommerce') . '</h3>';
    $content .= '<ul>';
    foreach ($data['items'] as $item) {
        $content .= '<li>' . esc_html($item['name']) . ($item['quantity'] > 1 ? ' (' . $item['quantity'] . ')' : '') . '</li>';
    }
    $content .= '</ul>';
    $content .= '</div>';
}

// Información adicional si está disponible
if (!empty($data['notes'])) {
    $content .= '<div style="background-color: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #2196F3;">';
    $content .= '<h3 style="margin-top: 0; color: #2196F3;">' . __('Información adicional:', 'rfq-manager-woocommerce') . '</h3>';
    $content .= '<p>' . esc_html($data['notes']) . '</p>';
    $content .= '</div>';
}

// Agregar enlace para cotizar
$content .= '<p style="text-align: center; margin-top: 20px;">';
$content .= '<a href="' . esc_url($cotizar_link) . '" style="background-color: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">';
$content .= __('Enviar cotización', 'rfq-manager-woocommerce');
$content .= '</a></p>';

// Mostrar mensajes adicionales
$content .= '<p>' . __('Te recomendamos revisar esta solicitud y enviar tu cotización lo antes posible.', 'rfq-manager-woocommerce') . '</p>';

// Crear la plantilla utilizando el factory
echo NotificationTemplateFactory::create(
    'supplier',
    __('Nueva solicitud de cotización disponible', 'rfq-manager-woocommerce'),
    $content,
    [
        'footer_text' => __('Recuerda responder rápidamente para aumentar tus posibilidades de ser seleccionado.', 'rfq-manager-woocommerce')
    ]
); 