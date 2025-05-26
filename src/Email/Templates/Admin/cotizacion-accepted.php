<?php
/**
 * Plantilla para la notificación de cotización aceptada (Administrador)
 *
 * Esta plantilla se utiliza para notificar a los administradores cuando un cliente
 * acepta una cotización.
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
$cotizacion_amount = get_post_meta($cotizacion_id, '_total', true);
$formatted_amount = '';
if (!empty($cotizacion_amount)) {
    $formatted_amount = wc_price($cotizacion_amount);
}

// Construir enlaces al panel de administración
$admin_url_solicitud = admin_url('admin.php?page=rfq-manager&view=solicitud&id=' . $solicitud_id);
$admin_url_cotizacion = admin_url('admin.php?page=rfq-manager&view=cotizacion&id=' . $cotizacion_id);

// Fecha de aceptación
$acceptance_date = get_post_meta($cotizacion_id, '_fecha_aceptacion', true);
if (empty($acceptance_date)) {
    $acceptance_date = current_time('mysql');
}
$formatted_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($acceptance_date));

// Contenido personalizado para el correo
$content = '<p><strong>' . __('Notificación del Sistema RFQ Manager:', 'rfq-manager-woocommerce') . '</strong></p>';
$content .= '<p>' . __('Se ha completado una etapa importante en el sistema de solicitudes de cotización.', 'rfq-manager-woocommerce') . '</p>';
$content .= '<p>' . sprintf(__('El cliente %s ha aceptado la cotización enviada por el proveedor %s.', 'rfq-manager-woocommerce'), 
    '<strong>' . esc_html($client_name) . '</strong>', 
    '<strong>' . esc_html($supplier_name) . '</strong>') . '</p>';

$content .= '<div style="background-color: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #FF5722;">';
$content .= '<h3 style="margin-top: 0;">' . __('Detalles de la transacción', 'rfq-manager-woocommerce') . '</h3>';
$content .= '<p><strong>' . __('Solicitud:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($solicitud_title) . ' (ID: ' . $solicitud_id . ')</p>';
if (!empty($cotizacion_title)) {
    $content .= '<p><strong>' . __('Cotización:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($cotizacion_title) . ' (ID: ' . $cotizacion_id . ')</p>';
}
if (!empty($formatted_amount)) {
    $content .= '<p><strong>' . __('Monto pendiente de pago:', 'rfq-manager-woocommerce') . '</strong> ' . $formatted_amount . '</p>';
}
$content .= '<p><strong>' . __('Fecha de aceptación:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($formatted_date) . '</p>';
$content .= '</div>';

// Información sobre el proceso de pago
$content .= '<div style="background-color: #fff7e6; padding: 15px; margin: 15px 0; border-left: 4px solid #ff9800;">';
$content .= '<h3 style="margin-top: 0; color: #ff9800;">' . __('Estado de pago: PENDIENTE', 'rfq-manager-woocommerce') . '</h3>';
$content .= '<p>' . __('El cliente ha sido notificado para realizar el pago correspondiente a esta cotización.', 'rfq-manager-woocommerce') . '</p>';
$content .= '<p>' . __('El proveedor ha sido notificado para esperar la confirmación del pago antes de proceder.', 'rfq-manager-woocommerce') . '</p>';
$content .= '<p>' . __('El sistema notificará automáticamente a ambas partes cuando el pago sea procesado.', 'rfq-manager-woocommerce') . '</p>';
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

$content .= '<p>' . __('La solicitud ha cambiado automáticamente a estado "Aceptada" y está pendiente de pago.', 'rfq-manager-woocommerce') . '</p>';
$content .= '<p>' . __('Es posible que se requiera su intervención en caso de problemas con el proceso de pago.', 'rfq-manager-woocommerce') . '</p>';

// Crear la plantilla utilizando el factory
echo NotificationTemplateFactory::create(
    'admin',
    sprintf(__('Cotización aceptada - Pago pendiente - Solicitud #%d', 'rfq-manager-woocommerce'), $solicitud_id),
    $content,
    [
        'footer_text' => __('Este es un mensaje informativo del sistema RFQ Manager.', 'rfq-manager-woocommerce')
    ]
); 