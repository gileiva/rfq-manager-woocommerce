<?php
/**
 * Plantilla para la notificación de solicitud creada (Administrador)
 *
 * Esta plantilla se utiliza para notificar a los administradores cuando se crea
 * una nueva solicitud de cotización en el sistema.
 *
 * Variables disponibles:
 * - $solicitud_id: ID de la solicitud
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

// Obtener información del usuario que creó la solicitud
$user_id = get_post_field('post_author', $solicitud_id);
$user = get_userdata($user_id);
$user_name = $user ? $user->display_name : __('Usuario desconocido', 'rfq-manager-woocommerce');
$user_email = $user ? $user->user_email : '';

// Construir un enlace al panel de administración para ver la solicitud
$admin_url = admin_url('admin.php?page=rfq-manager&view=solicitud&id=' . $solicitud_id);

// Fecha de creación
$created_date = get_the_date(get_option('date_format') . ' ' . get_option('time_format'), $solicitud_id);

// Contenido personalizado para el correo
$content = '<p><strong>' . __('Notificación del Sistema RFQ Manager:', 'rfq-manager-woocommerce') . '</strong></p>';
$content .= '<p>' . sprintf(__('Se ha creado una nueva solicitud de cotización con ID: #%d', 'rfq-manager-woocommerce'), $solicitud_id) . '</p>';

$content .= '<div style="background-color: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #FF5722;">';
$content .= '<h3 style="margin-top: 0;">' . __('Información de la solicitud', 'rfq-manager-woocommerce') . '</h3>';
$content .= '<p><strong>' . __('Título:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($solicitud_title) . '</p>';
$content .= '<p><strong>' . __('Creada por:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($user_name) . ($user_email ? ' (' . esc_html($user_email) . ')' : '') . '</p>';
$content .= '<p><strong>' . __('Fecha de creación:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($created_date) . '</p>';

// Estado actual
$current_status = get_post_meta($solicitud_id, '_rfq_status', true);
if ($current_status) {
    $content .= '<p><strong>' . __('Estado actual:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($current_status) . '</p>';
}

$content .= '</div>';

// Si hay artículos en la solicitud, mostrarlos
if (!empty($data['items'])) {
    $content .= '<p><strong>' . __('Artículos solicitados:', 'rfq-manager-woocommerce') . '</strong></p>';
    $content .= '<table style="width: 100%; border-collapse: collapse; margin: 15px 0;">';
    $content .= '<thead><tr>';
    $content .= '<th style="text-align: left; padding: 8px; border: 1px solid #ddd; background-color: #f2f2f2;">' . __('Producto', 'rfq-manager-woocommerce') . '</th>';
    $content .= '<th style="text-align: left; padding: 8px; border: 1px solid #ddd; background-color: #f2f2f2;">' . __('Cantidad', 'rfq-manager-woocommerce') . '</th>';
    $content .= '</tr></thead><tbody>';
    
    foreach ($data['items'] as $item) {
        $content .= '<tr>';
        $content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($item['name']) . '</td>';
        $content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($item['quantity']) . '</td>';
        $content .= '</tr>';
    }
    
    $content .= '</tbody></table>';
}

// Información adicional si está disponible
if (!empty($data['notes'])) {
    $content .= '<p><strong>' . __('Notas del cliente:', 'rfq-manager-woocommerce') . '</strong></p>';
    $content .= '<p>' . esc_html($data['notes']) . '</p>';
}

// Agregar enlace al panel de administración
$content .= '<p style="text-align: center; margin-top: 20px;">';
$content .= '<a href="' . esc_url($admin_url) . '" style="background-color: #FF5722; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">';
$content .= __('Ver solicitud en el panel de administración', 'rfq-manager-woocommerce');
$content .= '</a></p>';

// Crear la plantilla utilizando el factory
echo NotificationTemplateFactory::create(
    'admin',
    sprintf(__('Nueva solicitud de cotización: #%d', 'rfq-manager-woocommerce'), $solicitud_id),
    $content,
    [
        'footer_text' => __('Este es un mensaje informativo del sistema RFQ Manager.', 'rfq-manager-woocommerce')
    ]
); 