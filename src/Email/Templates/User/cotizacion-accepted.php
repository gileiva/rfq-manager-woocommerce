<?php
/**
 * Plantilla para la notificación de cotización aceptada (Usuario)
 *
 * Esta plantilla se utiliza para notificar a un usuario cuando acepta una cotización.
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

// Obtener información de la solicitud
$solicitud = get_post($solicitud_id);
$solicitud_title = $solicitud ? get_the_title($solicitud) : __('Solicitud', 'rfq-manager-woocommerce');
$solicitud_slug = get_post_field('post_name', $solicitud_id);

// Obtener información de la cotización
$cotizacion = get_post($cotizacion_id);
$precio_items = get_post_meta($cotizacion_id, '_precio_items', true);
$total = get_post_meta($cotizacion_id, '_total', true);
$formatted_total = wc_price($total);
$fecha_aceptacion = get_post_meta($cotizacion_id, '_fecha_aceptacion', true);
$formatted_date = $fecha_aceptacion ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($fecha_aceptacion)) : date_i18n(get_option('date_format') . ' ' . get_option('time_format'));

// Obtener información del proveedor
$supplier_id = get_post_field('post_author', $cotizacion_id);
$supplier = get_userdata($supplier_id);
$supplier_name = $supplier ? $supplier->display_name : __('Proveedor', 'rfq-manager-woocommerce');
$supplier_email = $supplier ? $supplier->user_email : '';

// URL para ver la solicitud
$view_url = home_url('ver-solicitud/' . $solicitud_slug);

// URL de pago (si existe)
$payment_url = home_url('pagar-cotizacion/' . $solicitud_slug);

// Contenido personalizado para el correo
$content = '<p>' . sprintf(__('Hola %s,', 'rfq-manager-woocommerce'), esc_html($user->display_name)) . '</p>';
$content .= '<p>' . sprintf(__('Has aceptado la cotización del proveedor %s para tu solicitud "%s".', 'rfq-manager-woocommerce'), 
    esc_html($supplier_name), esc_html($solicitud_title)) . '</p>';

// Calcular el porcentaje de ahorro
$items = json_decode(get_post_meta($solicitud_id, '_solicitud_items', true), true);
$gran_total = 0;
foreach ($items as $item) {
    $gran_total += floatval($item['subtotal']);
}

$porcentaje_ahorro = 0;
if ($gran_total > 0) {
    $porcentaje_ahorro = (($gran_total - $total) / $gran_total) * 100;
    $porcentaje_ahorro = round($porcentaje_ahorro, 2);
}

// Mostrar mensaje de ahorro si es positivo
if ($porcentaje_ahorro > 0) {
    $content .= '<div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0;">';
    $content .= '<p style="margin: 0;">' . sprintf(__('¡Enhorabuena! Gracias a The Clever Dentist has conseguido un %s%% de descuento sobre el precio medio del mercado.', 'rfq-manager-woocommerce'), number_format($porcentaje_ahorro, 2)) . '</p>';
    $content .= '</div>';
}

// Detalles de la cotización
$content .= '<div style="background-color: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #4CAF50;">';
$content .= '<h3 style="margin-top: 0; color: #4CAF50;">' . __('Detalles de la cotización aceptada', 'rfq-manager-woocommerce') . '</h3>';
$content .= '<p><strong>' . __('Solicitud:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($solicitud_title) . '</p>';
$content .= '<p><strong>' . __('Proveedor:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($supplier_name) . '</p>';
if ($supplier_email) {
    $content .= '<p><strong>' . __('Email del proveedor:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($supplier_email) . '</p>';
}
$content .= '<p><strong>' . __('Fecha de aceptación:', 'rfq-manager-woocommerce') . '</strong> ' . esc_html($formatted_date) . '</p>';
$content .= '<p><strong>' . __('Total a pagar:', 'rfq-manager-woocommerce') . '</strong> ' . $formatted_total . '</p>';
$content .= '</div>';

// Instrucciones de pago
$content .= '<div style="background-color: #fff7e6; padding: 15px; margin: 15px 0; border-left: 4px solid #ff9800;">';
$content .= '<h3 style="margin-top: 0; color: #ff9800;">' . __('Información de pago', 'rfq-manager-woocommerce') . '</h3>';
$content .= '<p>' . __('Para completar el proceso, es necesario realizar el pago correspondiente a la cotización aceptada.', 'rfq-manager-woocommerce') . '</p>';
$content .= '<p>' . __('Una vez realizado el pago, el proveedor será notificado y procederá con el servicio o entrega del producto.', 'rfq-manager-woocommerce') . '</p>';
$content .= '</div>';

// Siguientes pasos
$content .= '<p>' . __('El proveedor ha sido notificado de tu aceptación y está a la espera de tu pago para proceder.', 'rfq-manager-woocommerce') . '</p>';

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

// Agregar botones para ver la solicitud y realizar el pago
$content .= '<div style="text-align: center; margin-top: 20px;">';

if ($view_url) {
    $content .= '<a href="' . esc_url($view_url) . '" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;">' . __('Ver detalles', 'rfq-manager-woocommerce') . '</a>';
}

if ($payment_url) {
    $content .= '<a href="' . esc_url($payment_url) . '" style="background-color: #ff9800; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">' . __('Realizar pago', 'rfq-manager-woocommerce') . '</a>';
}

$content .= '</div>';

// Información adicional
$content .= '<p>' . __('Tu solicitud ha sido marcada como "Aceptada" y ya no recibirás más cotizaciones para ella.', 'rfq-manager-woocommerce') . '</p>';
$content .= '<p>' . __('Si tienes alguna pregunta o necesitas asistencia, no dudes en contactarnos.', 'rfq-manager-woocommerce') . '</p>';

// Crear la plantilla utilizando el factory
echo NotificationTemplateFactory::create(
    'user',
    __('Has aceptado una cotización - Información de pago', 'rfq-manager-woocommerce'),
    $content,
    [
        'footer_text' => __('Gracias por utilizar nuestro sistema de cotizaciones.', 'rfq-manager-woocommerce')
    ]
); 