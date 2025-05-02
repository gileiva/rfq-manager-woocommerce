<?php
/**
 * Gestor de emails de WooCommerce
 *
 * @package    GiVendor\GiPlugin\Email
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Email;

use WC_Order;

/**
 * EmailManager - Gestiona los emails de WooCommerce
 *
 * Esta clase es responsable de prevenir los emails automáticos
 * de WooCommerce para órdenes convertidas a solicitudes.
 *
 * @package    GiVendor\GiPlugin\Email
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class EmailManager {
    
    /**
     * Inicializa los hooks relacionados con emails
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        // Hooks para prevenir todos los emails de WooCommerce para órdenes interceptadas
        add_filter('woocommerce_email_enabled_new_order', [__CLASS__, 'prevent_emails'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_processing_order', [__CLASS__, 'prevent_emails'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_completed_order', [__CLASS__, 'prevent_emails'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_on_hold_order', [__CLASS__, 'prevent_emails'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_invoice', [__CLASS__, 'prevent_emails'], 10, 2);
        add_filter('woocommerce_email_enabled_cancelled_order', [__CLASS__, 'prevent_emails'], 10, 2);
        add_filter('woocommerce_email_enabled_failed_order', [__CLASS__, 'prevent_emails'], 10, 2);
        
        // Hook para desactivar todas las notificaciones de WooCommerce
        add_filter('woocommerce_email_recipient_', [__CLASS__, 'prevent_email_recipient'], 10, 3);
        
        // Hook de depuración
        add_action('init', function() {
            // error_log('RFQ Manager - EmailManager hooks inicializados');
        }, 999);
    }

    /**
     * Previene el envío de emails de WooCommerce para órdenes interceptadas
     *
     * @since  0.1.0
     * @param  bool $enabled Si el email está habilitado
     * @param  WC_Order $order La orden
     * @return bool
     */
    public static function prevent_emails($enabled, $order): bool {
        // Si estamos interceptando esta orden, no queremos que WooCommerce envíe emails
        $processed = get_post_meta($order->get_id(), '_rfq_processed', true);
        if ($processed) {
            error_log('RFQ Manager - Email bloqueado para orden ' . $order->get_id());
            return false;
        }
        return $enabled;
    }
    
    /**
     * Previene el envío de emails a cualquier destinatario para órdenes interceptadas
     * 
     * @since 0.1.0
     * @param string $recipient El destinatario del email
     * @param object $object El objeto asociado al email (normalmente una orden)
     * @param object $email El objeto del email
     * @return string Destinatario vacío si la orden ha sido procesada, original en caso contrario
     */
    public static function prevent_email_recipient($recipient, $object, $email): string {
        // Verificar si es una orden
        if ($object instanceof WC_Order) {
            $processed = get_post_meta($object->get_id(), '_rfq_processed', true);
            if ($processed) {
                error_log('RFQ Manager - Destinatario de email bloqueado para orden ' . $object->get_id());
                return '';  // Devolver cadena vacía para eliminar destinatarios
            }
        }
        return $recipient;
    }
}