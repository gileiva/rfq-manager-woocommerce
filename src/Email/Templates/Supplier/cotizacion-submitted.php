<?php
/**
 * Plantilla para la notificación de cotización enviada (Proveedor)
 *
 * Esta plantilla se utiliza para notificar a un proveedor cuando envía una cotización.
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

// Obtener información de la solicitud
$solicitud = get_post($solicitud_id);
$solicitud_title = $solicitud ? get_the_title($solicitud) : __('Solicitud', 'rfq-manager-woocommerce');
$solicitud_slug = get_post_field('post_name', $solicitud_id);
$solicitud_expiry = get_post_meta($solicitud_id, '_solicitud_expiry', true);
$formatted_expiry = $solicitud_expiry ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($solicitud_expiry)) : '';

// Obtener información de la cotización
$precio_items = get_post_meta($cotizacion_id, '_precio_items', true);
$total = get_post_meta($cotizacion_id, '_total', true);
$formatted_total = wc_price($total);
$submission_date = get_the_date(get_option('date_format') . ' ' . get_option('time_format'), $cotizacion_id);

// Obtener datos del cliente
$client_id = get_post_field('post_author', $solicitud_id);
$client = get_userdata($client_id);
$client_name = $client ? $client->display_name : __('Cliente', 'rfq-manager-woocommerce');

// Construir un enlace a la página de cotización
$cotizar_link = home_url('cotizar-solicitud/' . get_post_field('post_name', $solicitud_id));

// Contenido personalizado para el correo
$content = '<p>' . sprintf(__('Hola %s,', 'rfq-manager-woocommerce'), esc_html($supplier->display_name)) . '</p>';
$content .= '<p>' . sprintf(__('Tu cotización para la solicitud "%s" ha sido enviada correctamente.', 'rfq-manager-woocommerce'), esc_html($solicitud_title)) . '</p>';

// Información de cotización
$content .= '<div style="background-color: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #2196F3;">';
$content .= '<h3 style="margin-top: 0; color: #2196F3;">' . __('Detalles de la cotización', 'rfq-manager-woocommerce') . '</h3>';
$content .= '<p><strong>' . __('Solicitud:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($solicitud_title) . '</p>';
$content .= '<p><strong>' . __('Cliente:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($client_name) . '</p>';
$content .= '<p><strong>' . __('Fecha de envío:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($submission_date) . '</p>';
if ($formatted_expiry) {
    $content .= '<p><strong>' . __('Fecha de expiración de la solicitud:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($formatted_expiry) . '</p>';
}
$content .= '<p><strong>' . __('Total cotizado:', 'rfq-manager-woocommerce') . '</strong> ' . $formatted_total . '</p>';
$content .= '</div>';

// Explicación del proceso
$content .= '<p>' . __('El cliente ha sido notificado sobre tu cotización y podrá revisarla en su panel de usuario. Te informaremos si tu cotización es aceptada.', 'rfq-manager-woocommerce') . '</p>';

// Lista de productos cotizados
if (!empty($precio_items) && is_array($precio_items)) {
    $content .= '<h3>' . __('Productos cotizados:', 'rfq-manager-woocommerce') . '</h3>';
    $content .= '<table style="width: 100%; border-collapse: collapse; margin: 15px 0;">';
    $content .= '<tr>';
    $content .= '<th style="text-align: left; background-color: #f2f2f2; padding: 8px; border: 1px solid #ddd;">' . __('Producto', 'rfq-manager-woocommerce') . '</th>';
    $content .= '<th style="text-align: left; background-color: #f2f2f2; padding: 8px; border: 1px solid #ddd;">' . __('Cantidad', 'rfq-manager-woocommerce') . '</th>';
    $content .= '<th style="text-align: left; background-color: #f2f2f2; padding: 8px; border: 1px solid #ddd;">' . __('Precio Unitario', 'rfq-manager-woocommerce') . '</th>';
    $content .= '<th style="text-align: left; background-color: #f2f2f2; padding: 8px; border: 1px solid #ddd;">' . __('IVA', 'rfq-manager-woocommerce') . '</th>';
    $content .= '<th style="text-align: left; background-color: #f2f2f2; padding: 8px; border: 1px solid #ddd;">' . __('Subtotal', 'rfq-manager-woocommerce') . '</th>';
    $content .= '</tr>';
    
    foreach ($precio_items as $product_id => $item) {
        $product = wc_get_product($product_id);
        if (!$product) continue;
        
        $subtotal = $item['precio'] * $item['qty'];
        
        $content .= '<tr>';
        $content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($product->get_name()) . '</td>';
        $content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($item['qty']) . '</td>';
        $content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . wc_price($item['precio']) . '</td>';
        $content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($item['iva']) . '%</td>';
        $content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . wc_price($subtotal) . '</td>';
        $content .= '</tr>';
    }
    
    $content .= '<tr style="background-color: #f9f9f9; font-weight: bold;">';
    $content .= '<td colspan="4" style="padding: 8px; border: 1px solid #ddd; text-align: right;">' . __('Total:', 'rfq-manager-woocommerce') . '</td>';
    $content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . $formatted_total . '</td>';
    $content .= '</tr>';
    
    $content .= '</table>';
}

// Información adicional
$content .= '<p>' . __('Recuerda que puedes revisar y gestionar todas tus cotizaciones en tu panel de proveedor.', 'rfq-manager-woocommerce') . '</p>';

// Agregar enlace para ver la cotización
$content .= '<p style="text-align: center; margin-top: 20px;">';
$content .= '<a href="' . esc_url($cotizar_link) . '" style="background-color: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">';
$content .= __('Ver cotización', 'rfq-manager-woocommerce');
$content .= '</a></p>';

$content .= '<p>' . __('Gracias por utilizar nuestro sistema de cotizaciones.', 'rfq-manager-woocommerce') . '</p>';

// Crear la plantilla utilizando el factory
echo NotificationTemplateFactory::create(
    'supplier',
    __('Tu cotización ha sido enviada', 'rfq-manager-woocommerce'),
    $content,
    [
        'footer_text' => __('Gracias por ser parte de nuestro equipo de proveedores.', 'rfq-manager-woocommerce')
    ]
); 