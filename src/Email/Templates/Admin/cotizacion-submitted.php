<?php
/**
 * Plantilla para la notificación de cotización enviada (Administrador)
 *
 * Esta plantilla se utiliza para notificar a los administradores cuando un proveedor
 * envía una nueva cotización.
 *
 * Variables disponibles:
 * - $cotizacion_id: ID de la cotización
 * - $solicitud_id: ID de la solicitud relacionada
 * - $supplier: Objeto usuario proveedor
 *
 * @package    GiVendor\GiPlugin\Email\Templates
 * @since      0.1.0
 */

// Seguridad: Evitar acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

use GiVendor\GiPlugin\Email\Templates\NotificationTemplateFactory;

// Obtener títulos
$solicitud_title = get_the_title($solicitud_id);
$cotizacion_title = get_the_title($cotizacion_id);

// Obtener información del cliente
$client_id = get_post_field('post_author', $solicitud_id);
$client = get_userdata($client_id);
$client_name = $client ? $client->display_name : __('Cliente desconocido', 'rfq-manager-woocommerce');
$client_email = $client ? $client->user_email : '';

// Obtener información del proveedor
$supplier_name = $supplier ? $supplier->display_name : __('Proveedor desconocido', 'rfq-manager-woocommerce');
$supplier_email = $supplier ? $supplier->user_email : '';

// Obtener monto de la cotización
$cotizacion_amount = get_post_meta($cotizacion_id, '_rfq_cotizacion_amount', true);
$formatted_amount = '';
if (!empty($cotizacion_amount)) {
    $formatted_amount = wc_price($cotizacion_amount);
}

// Construir enlaces al panel de administración
$admin_url_solicitud = admin_url('admin.php?page=rfq-manager&view=solicitud&id=' . $solicitud_id);
$admin_url_cotizacion = admin_url('admin.php?page=rfq-manager&view=cotizacion&id=' . $cotizacion_id);

// Fecha de envío
$submission_date = get_the_date(get_option('date_format') . ' ' . get_option('time_format'), $cotizacion_id);

// Obtener el número de cotizaciones para esta solicitud
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
$content = '<p><strong>' . __('Notificación del Sistema RFQ Manager:', 'rfq-manager-woocommerce') . '</strong></p>';
$content .= '<p>' . __('Se ha recibido una nueva cotización en el sistema.', 'rfq-manager-woocommerce') . '</p>';
$content .= '<p>' . sprintf(__('El proveedor %s ha enviado una cotización para la solicitud "%s".', 'rfq-manager-woocommerce'), 
    '<strong>' . esc_html($supplier_name) . '</strong>', 
    '<strong>' . esc_html($solicitud_title) . '</strong>') . '</p>';

$content .= '<div style="background-color: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #FF5722;">';
$content .= '<h3 style="margin-top: 0;">' . __('Detalles de la cotización', 'rfq-manager-woocommerce') . '</h3>';
$content .= '<p><strong>' . __('Solicitud:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($solicitud_title) . ' (ID: ' . $solicitud_id . ')</p>';
if (!empty($cotizacion_title)) {
    $content .= '<p><strong>' . __('Título de cotización:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($cotizacion_title) . ' (ID: ' . $cotizacion_id . ')</p>';
}
if (!empty($formatted_amount)) {
    $content .= '<p><strong>' . __('Monto ofertado:', 'rfq-manager-woocommerce') . '</strong> ' . $formatted_amount . '</p>';
}
$content .= '<p><strong>' . __('Fecha de envío:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($submission_date) . '</p>';
$content .= '<p><strong>' . __('Cotizaciones recibidas:', 'rfq-manager-woocommerce') . '</strong> ' . $total_cotizaciones . '</p>';
$content .= '</div>';

$content .= '<div style="background-color: #f0f8ff; padding: 15px; margin: 15px 0; border-left: 4px solid #007bff;">';
$content .= '<h3 style="margin-top: 0;">' . __('Información de contacto', 'rfq-manager-woocommerce') . '</h3>';
$content .= '<p><strong>' . __('Cliente:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($client_name) . 
    ($client_email ? ' (' . esc_html($client_email) . ')' : '') . '</p>';
$content .= '<p><strong>' . __('Proveedor:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($supplier_name) . 
    ($supplier_email ? ' (' . esc_html($supplier_email) . ')' : '') . '</p>';
$content .= '</div>';

// Agregar enlaces al panel de administración
$content .= '<div style="text-align: center; margin-top: 20px;">';
$content .= '<a href="' . esc_url($admin_url_solicitud) . '" style="background-color: #FF5722; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;">';
$content .= __('Ver solicitud', 'rfq-manager-woocommerce');
$content .= '</a>';
$content .= '<a href="' . esc_url($admin_url_cotizacion) . '" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">';
$content .= __('Ver cotización', 'rfq-manager-woocommerce');
$content .= '</a>';
$content .= '</div>';

// Si es la primera cotización, indicar que la solicitud cambiará a estado "Activa"
if ($total_cotizaciones === 1) {
    $content .= '<p>' . __('Esta es la primera cotización para esta solicitud. El estado de la solicitud cambiará automáticamente a "Activa".', 'rfq-manager-woocommerce') . '</p>';
}

// Crear la plantilla utilizando el factory
echo NotificationTemplateFactory::create(
    'admin',
    sprintf(__('Nueva cotización - Solicitud #%d', 'rfq-manager-woocommerce'), $solicitud_id),
    $content,
    [
        'footer_text' => __('Este es un mensaje informativo del sistema RFQ Manager.', 'rfq-manager-woocommerce')
    ]
); 