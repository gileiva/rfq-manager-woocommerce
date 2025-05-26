<?php
/**
 * Plantilla para la notificación de cotización aceptada (Proveedor)
 *
 * Esta plantilla se utiliza para notificar a un proveedor cuando su cotización
 * ha sido aceptada por un cliente.
 *
 * Variables disponibles:
 * - $cotizacion_id: ID de la cotización
 * - $solicitud_id: ID de la solicitud relacionada
 * - $supplier: Objeto usuario proveedor destinatario
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
$solicitud_slug = get_post_field('post_name', $solicitud_id);

// Obtener información del cliente
$client_id = get_post_field('post_author', $solicitud_id);
$client = get_userdata($client_id);
$client_name = $client ? $client->display_name : __('Cliente', 'rfq-manager-woocommerce');

// Obtener detalles de la cotización
$precio_items = get_post_meta($cotizacion_id, '_precio_items', true);
$total = get_post_meta($cotizacion_id, '_total', true);
$formatted_total = wc_price($total);

// URL para ver la solicitud cotizada
$view_url = home_url('historial-cotizaciones');

// Fecha de aceptación
$fecha_aceptacion = get_post_meta($cotizacion_id, '_fecha_aceptacion', true);
$formatted_date = $fecha_aceptacion ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($fecha_aceptacion)) : date_i18n(get_option('date_format') . ' ' . get_option('time_format'));

// Contenido personalizado para el correo
$content = '<p>' . sprintf(__('¡Felicitaciones %s!', 'rfq-manager-woocommerce'), esc_html($supplier->display_name)) . '</p>';
$content .= '<p>' . sprintf(__('Tu cotización para la solicitud <strong>%s</strong> ha sido aceptada por el cliente.', 'rfq-manager-woocommerce'), esc_html($solicitud_title)) . '</p>';

$content .= '<div style="background-color: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #2196F3;">';
$content .= '<h3 style="margin-top: 0;">' . __('Detalles de la cotización aceptada', 'rfq-manager-woocommerce') . '</h3>';
$content .= '<p><strong>' . __('Solicitud:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($solicitud_title) . '</p>';
$content .= '<p><strong>' . __('Cliente:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($client_name) . '</p>';
$content .= '<p><strong>' . __('Fecha de aceptación:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($formatted_date) . '</p>';
$content .= '<p><strong>' . __('Total cotizado:', 'rfq-manager-woocommerce') . '</strong> ' . $formatted_total . '</p>';
$content .= '</div>';

// Información sobre proceso de pago
$content .= '<div style="background-color: #fff7e6; padding: 15px; margin: 15px 0; border-left: 4px solid #ff9800;">';
$content .= '<h3 style="margin-top: 0; color: #ff9800;">' . __('Información importante sobre el pago', 'rfq-manager-woocommerce') . '</h3>';
$content .= '<p>' . __('El cliente ha sido notificado para que realice el pago correspondiente a esta cotización.', 'rfq-manager-woocommerce') . '</p>';
$content .= '<p>' . __('Por favor, espera a recibir la confirmación de pago antes de proceder con el servicio o la entrega del producto.', 'rfq-manager-woocommerce') . '</p>';
$content .= '<p>' . __('Recibirás una nueva notificación cuando el pago haya sido completado.', 'rfq-manager-woocommerce') . '</p>';
$content .= '</div>';

$content .= '<p>' . __('Una vez que el cliente realice el pago, deberás ponerte en contacto con él para coordinar los detalles de entrega y servicio.', 'rfq-manager-woocommerce') . '</p>';

// Lista de productos cotizados
if (!empty($precio_items) && is_array($precio_items)) {
    $content .= '<h3>' . __('Productos cotizados:', 'rfq-manager-woocommerce') . '</h3>';
    $content .= '<table style="width: 100%; border-collapse: collapse; margin: 15px 0;">';
    $content .= '<tr>';
    $content .= '<th style="text-align: left; background-color: #f2f2f2; padding: 8px; border: 1px solid #ddd;">' . __('Producto', 'rfq-manager-woocommerce') . '</th>';
    $content .= '<th style="text-align: left; background-color: #f2f2f2; padding: 8px; border: 1px solid #ddd;">' . __('Cantidad', 'rfq-manager-woocommerce') . '</th>';
    $content .= '<th style="text-align: left; background-color: #f2f2f2; padding: 8px; border: 1px solid #ddd;">' . __('Precio Unitario', 'rfq-manager-woocommerce') . '</th>';
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
        $content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . wc_price($subtotal) . '</td>';
        $content .= '</tr>';
    }
    
    $content .= '<tr style="background-color: #f9f9f9; font-weight: bold;">';
    $content .= '<td colspan="3" style="padding: 8px; border: 1px solid #ddd; text-align: right;">' . __('Total:', 'rfq-manager-woocommerce') . '</td>';
    $content .= '<td style="padding: 8px; border: 1px solid #ddd;">' . $formatted_total . '</td>';
    $content .= '</tr>';
    
    $content .= '</table>';
}

// Agregar enlace al historial de cotizaciones
if ($view_url) {
    $content .= '<p style="text-align: center; margin-top: 20px;">';
    $content .= '<a href="' . esc_url($view_url) . '" style="background-color: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">';
    $content .= __('Ver historial de cotizaciones', 'rfq-manager-woocommerce');
    $content .= '</a></p>';
}

$content .= '<p>' . __('Si tienes alguna pregunta, por favor contacta al administrador del sistema.', 'rfq-manager-woocommerce') . '</p>';

// Crear la plantilla utilizando el factory
echo NotificationTemplateFactory::create(
    'supplier',
    __('¡Tu cotización ha sido aceptada! Pendiente de pago', 'rfq-manager-woocommerce'),
    $content,
    [
        'footer_text' => __('Recuerda cumplir con los términos y condiciones acordados en tu cotización.', 'rfq-manager-woocommerce')
    ]
); 