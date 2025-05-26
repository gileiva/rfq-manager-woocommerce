<?php
/**
 * Plantilla para la notificación de cambio de estado (Usuario)
 *
 * Esta plantilla se utiliza para notificar a un usuario cuando el estado de su solicitud
 * ha cambiado.
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

// Construir un enlace a la página de detalle de la solicitud (si existe)
$solicitud_link = '';
$my_account_url = wc_get_page_permalink('myaccount');
if ($my_account_url) {
    $solicitud_link = add_query_arg([
        'section' => 'rfq-requests',
        'view' => 'detail',
        'id' => $solicitud_id
    ], $my_account_url);
}

// Determinar el asunto y la descripción según el estado
$subject = '';
$description = '';
$additional_info = '';

switch ($new_status) {
    case 'activa':
        $subject = __('Tu solicitud ha recibido cotizaciones', 'rfq-manager-woocommerce');
        $description = sprintf(__('Tu solicitud "%s" ha recibido cotizaciones y ahora está activa.', 'rfq-manager-woocommerce'), esc_html($solicitud_title));
        $additional_info = __('Ya puedes revisar las cotizaciones recibidas y decidir cuál aceptar.', 'rfq-manager-woocommerce');
        break;
        
    case 'aceptada':
        $subject = __('Tu solicitud ha sido completada', 'rfq-manager-woocommerce');
        $description = sprintf(__('Tu solicitud "%s" ha sido completada tras aceptar una cotización.', 'rfq-manager-woocommerce'), esc_html($solicitud_title));
        $additional_info = __('El proveedor se pondrá en contacto contigo próximamente para coordinar los detalles.', 'rfq-manager-woocommerce');
        break;
        
    case 'historica':
        $subject = __('Tu solicitud ha pasado al historial', 'rfq-manager-woocommerce');
        $description = sprintf(__('Tu solicitud "%s" ha pasado al historial.', 'rfq-manager-woocommerce'), esc_html($solicitud_title));
        $additional_info = __('Esto puede ser debido a que ha vencido el plazo sin aceptar ninguna cotización o porque el proceso ha finalizado.', 'rfq-manager-woocommerce');
        break;
        
    default:
        $subject = sprintf(__('El estado de tu solicitud ha cambiado: %s', 'rfq-manager-woocommerce'), $new_status);
        $description = sprintf(__('El estado de tu solicitud "%s" ha cambiado de "%s" a "%s".', 'rfq-manager-woocommerce'), 
            esc_html($solicitud_title), esc_html($old_status), esc_html($new_status));
        $additional_info = __('Por favor, ingresa a tu cuenta para más detalles.', 'rfq-manager-woocommerce');
}

// Color de fondo según el estado
$status_color = '#4CAF50'; // Verde por defecto
switch ($new_status) {
    case 'activa':
        $status_color = '#2196F3'; // Azul
        break;
    case 'aceptada':
        $status_color = '#4CAF50'; // Verde
        break;
    case 'historica':
        $status_color = '#9E9E9E'; // Gris
        break;
}

// Contenido personalizado para el correo
$content = '<p>' . sprintf(__('Hola %s,', 'rfq-manager-woocommerce'), esc_html($user->display_name)) . '</p>';
$content .= '<p>' . $description . '</p>';

$content .= '<div style="background-color: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid ' . $status_color . ';">';
$content .= '<h3 style="margin-top: 0; color: ' . $status_color . ';">' . __('Información de estado', 'rfq-manager-woocommerce') . '</h3>';
$content .= '<p><strong>' . __('Solicitud:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($solicitud_title) . '</p>';
$content .= '<p><strong>' . __('Estado anterior:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($old_status) . '</p>';
$content .= '<p><strong>' . __('Nuevo estado:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($new_status) . '</p>';
$content .= '<p><strong>' . __('Fecha de cambio:', 'rfq-manager-woocommerce') . '</strong> ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format')) . '</p>';
$content .= '</div>';

if (!empty($additional_info)) {
    $content .= '<p>' . $additional_info . '</p>';
}

// Agregar enlace a la solicitud si está disponible
if (!empty($solicitud_link)) {
    $content .= '<p style="text-align: center; margin-top: 20px;">';
    $content .= '<a href="' . esc_url($solicitud_link) . '" style="background-color: ' . $status_color . '; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">';
    $content .= __('Ver solicitud', 'rfq-manager-woocommerce');
    $content .= '</a></p>';
}

// Mensajes específicos según el estado
if ($new_status === 'historica') {
    $content .= '<p>' . __('Si aún necesitas este servicio o producto, te recomendamos crear una nueva solicitud.', 'rfq-manager-woocommerce') . '</p>';
} elseif ($new_status === 'activa') {
    $content .= '<p>' . __('Recuerda que puedes revisar y comparar todas las cotizaciones recibidas antes de tomar una decisión.', 'rfq-manager-woocommerce') . '</p>';
}

// Crear la plantilla utilizando el factory
echo NotificationTemplateFactory::create(
    'user',
    $subject,
    $content,
    [
        'footer_text' => __('Gracias por utilizar nuestro sistema de solicitud de cotizaciones.', 'rfq-manager-woocommerce')
    ]
); 