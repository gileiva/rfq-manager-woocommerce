<?php
/**
 * Plantilla para la notificación de cambio a estado Activa (Usuario)
 *
 * Esta plantilla se utiliza para notificar a un usuario cuando el estado de su solicitud
 * cambia a "activa" al recibir cotizaciones de proveedores.
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
$content .= '<p>' . sprintf(__('¡Buenas noticias! Tu solicitud "%s" ha recibido cotizaciones y ahora está activa.', 'rfq-manager-woocommerce'), esc_html($solicitud_title)) . '</p>';

$content .= '<div style="background-color: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #2196F3;">';
$content .= '<h3 style="margin-top: 0; color: #2196F3;">' . __('Información de la solicitud', 'rfq-manager-woocommerce') . '</h3>';
$content .= '<p><strong>' . __('Solicitud:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($solicitud_title) . '</p>';
$content .= '<p><strong>' . __('Estado anterior:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($old_status) . '</p>';
$content .= '<p><strong>' . __('Nuevo estado:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($new_status) . '</p>';
$content .= '<p><strong>' . __('Cotizaciones recibidas:', 'rfq-manager-woocommerce') . '</strong> ' . $total_cotizaciones . '</p>';
$content .= '<p><strong>' . __('Fecha de actualización:', 'rfq-manager-woocommerce') . '</strong> ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format')) . '</p>';
$content .= '</div>';

$content .= '<p>' . __('Ahora puedes revisar todas las cotizaciones recibidas y seleccionar la que mejor se adapte a tus necesidades.', 'rfq-manager-woocommerce') . '</p>';
$content .= '<p>' . __('Recuerda que puedes recibir más cotizaciones en el futuro, así que te recomendamos revisar periódicamente tu solicitud.', 'rfq-manager-woocommerce') . '</p>';

// Agregar enlace a la solicitud
$content .= '<p style="text-align: center; margin-top: 20px;">';
$content .= '<a href="' . esc_url($solicitud_link) . '" style="background-color: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">';
$content .= __('Ver y comparar cotizaciones', 'rfq-manager-woocommerce');
$content .= '</a></p>';

$content .= '<p>' . __('Al comparar las cotizaciones, considera factores como el precio, los plazos de entrega y la reputación del proveedor.', 'rfq-manager-woocommerce') . '</p>';

// Crear la plantilla utilizando el factory
echo NotificationTemplateFactory::create(
    'user',
    __('Tu solicitud ha recibido cotizaciones', 'rfq-manager-woocommerce'),
    $content,
    [
        'footer_text' => __('Gracias por utilizar nuestro sistema de solicitud de cotizaciones.', 'rfq-manager-woocommerce')
    ]
); 