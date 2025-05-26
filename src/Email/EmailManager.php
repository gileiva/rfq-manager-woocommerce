<?php
/**
 * Gestor de emails de WooCommerce
 *
 * @package    GiVendor\GiPlugin\Email
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Email;

use WC_Order;
use GiVendor\GiPlugin\Email\Notifications\UserNotifications;
use GiVendor\GiPlugin\Email\Notifications\SupplierNotifications;
use GiVendor\GiPlugin\Email\Notifications\AdminNotifications;
use GiVendor\GiPlugin\Email\Notifications\Custom\NotificationManager;


/**
 * EmailManager - Gestiona los emails de WooCommerce
 *
 * Esta clase es responsable de prevenir los emails automáticos
 * de WooCommerce para órdenes convertidas a solicitudes y gestionar
 * el sistema de notificaciones del plugin.
 *
 * @package    GiVendor\GiPlugin\Email
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class EmailManager {
    
    private static $initialized = false;

    /**
     * Inicializa los hooks relacionados con emails
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        if (self::$initialized) {
            return;
        }

        // Inicializar las clases de notificaciones una sola vez
        self::init_notification_classes();

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
        
        // Crear directorios de template en la inicialización
        self::create_template_directories();
        
        // Marcar como inicializado
        self::$initialized = true;
        
        // Log de inicialización
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[RFQ-DEBUG] EmailManager - Sistema de notificaciones inicializado');
        }
    }

    /**
     * Inicializa las clases de notificaciones
     *
     * @since  0.1.0
     * @return void
     */
    protected static function init_notification_classes(): void {
        // Cargar dependencias primero
        self::load_notification_dependencies();
        
        // Verificar que las clases existan antes de inicializarlas
        if (class_exists('GiVendor\GiPlugin\Email\Notifications\UserNotifications')) {
            UserNotifications::init();
        }
        
        if (class_exists('GiVendor\GiPlugin\Email\Notifications\SupplierNotifications')) {
            SupplierNotifications::init();
        }
        
        if (class_exists('GiVendor\GiPlugin\Email\Notifications\AdminNotifications')) {
            AdminNotifications::init();
        }
        
        if (class_exists('GiVendor\GiPlugin\Email\Notifications\Custom\NotificationManager')) {
            NotificationManager::init();
        }
    }
    
    /**
     * Carga las dependencias necesarias para el sistema de notificaciones
     *
     * @since  0.1.0
     * @return void
     */
    protected static function load_notification_dependencies(): void {
        // Verificar si las clases ya están cargadas
        if (!class_exists('GiVendor\GiPlugin\Email\Notifications\UserNotifications')) {
            require_once RFQ_MANAGER_WOO_PLUGIN_DIR . 'src/Email/Notifications/UserNotifications.php';
        }
        
        if (!class_exists('GiVendor\GiPlugin\Email\Notifications\SupplierNotifications')) {
            require_once RFQ_MANAGER_WOO_PLUGIN_DIR . 'src/Email/Notifications/SupplierNotifications.php';
        }
        
        if (!class_exists('GiVendor\GiPlugin\Email\Notifications\AdminNotifications')) {
            require_once RFQ_MANAGER_WOO_PLUGIN_DIR . 'src/Email/Notifications/AdminNotifications.php';
        }

        if ( file_exists( RFQ_MANAGER_WOO_PLUGIN_DIR . 'src/Email/Notifications/Custom/NotificationManager.php' ) ) {
            require_once RFQ_MANAGER_WOO_PLUGIN_DIR . 'src/Email/Notifications/Custom/NotificationManager.php';
        }
        
    }

    /**
     * Previene el envío de emails de WooCommerce para órdenes interceptadas
     *
     * @since  0.1.0
     * @param  bool $enabled Si el email está habilitado
     * @param  WC_Order|null $order La orden
     * @return bool
     */
    public static function prevent_emails($enabled, $order): bool {
        // Verificar que la orden sea válida
        if (!$order || !is_a($order, 'WC_Order')) {
            return $enabled;
        }

        // Si estamos interceptando esta orden, no queremos que WooCommerce envíe emails
        $processed = get_post_meta($order->get_id(), '_rfq_processed', true);
        if ($processed) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[RFQ-DEBUG] Email bloqueado para orden ' . $order->get_id());
            }
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
                return '';  // Devolver cadena vacía para eliminar destinatarios
            }
        }
        return $recipient;
    }
    
    /**
     * Comprueba si una dirección de email es válida
     *
     * @since  0.1.0
     * @param  string $email Dirección de email a verificar
     * @return bool
     */
    public static function is_valid_email(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Crea las carpetas de templates si no existen
     *
     * @since  0.1.0
     * @return void
     */
    public static function create_template_directories(): void {
        $dirs = [
            'src/Email/Templates/User',
            'src/Email/Templates/Supplier',
            'src/Email/Templates/Admin'
        ];
        
        foreach ($dirs as $dir) {
            $full_path = RFQ_MANAGER_WOO_PLUGIN_DIR . $dir;
            if (!file_exists($full_path)) {
                $result = wp_mkdir_p($full_path);
                if (!$result && defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[RFQ-ERROR] Error al crear directorio de templates: ' . $full_path);
                }
            }
        }
    }
    
    /**
     * Registra las opciones de configuración para emails
     *
     * Este método será utilizado cuando se implemente la configuración
     * a través de la Settings API.
     *
     * @since  0.1.0
     * @return void
     */
    public static function register_settings(): void {
        // Configuraciones globales de email
        register_setting('rfq_email_settings', 'rfq_email_from_name', [
            'type' => 'string',
            'description' => __('Nombre del remitente para los emails', 'rfq-manager-woocommerce'),
            'sanitize_callback' => 'sanitize_text_field',
            'default' => get_bloginfo('name'),
        ]);
        
        register_setting('rfq_email_settings', 'rfq_email_from_address', [
            'type' => 'string',
            'description' => __('Email del remitente', 'rfq-manager-woocommerce'),
            'sanitize_callback' => 'sanitize_email',
            'default' => get_option('admin_email'),
        ]);
        
        // Opciones para activar/desactivar tipos específicos de notificaciones según el nuevo flujo de trabajo
        $notification_types = [
            // Notificaciones a usuario
            'user_solicitud_created' => __('Usuario - Solicitud creada', 'rfq-manager-woocommerce'),
            'user_cotizacion_received' => __('Usuario - Cotización recibida', 'rfq-manager-woocommerce'),
            'user_status_changed_activa' => __('Usuario - Solicitud activa', 'rfq-manager-woocommerce'),
            'user_status_changed_aceptada' => __('Usuario - Solicitud aceptada', 'rfq-manager-woocommerce'),
            'user_status_changed_historica' => __('Usuario - Solicitud histórica', 'rfq-manager-woocommerce'),
            
            // Notificaciones a proveedor
            'supplier_solicitud_created' => __('Proveedor - Nueva solicitud', 'rfq-manager-woocommerce'),
            'supplier_cotizacion_accepted' => __('Proveedor - Cotización aceptada', 'rfq-manager-woocommerce'),
            
            // Notificaciones a administrador
            'admin_solicitud_created' => __('Admin - Solicitud creada', 'rfq-manager-woocommerce'),
            'admin_cotizacion_submitted' => __('Admin - Cotización enviada', 'rfq-manager-woocommerce'),
            'admin_cotizacion_accepted' => __('Admin - Cotización aceptada', 'rfq-manager-woocommerce'),
        ];
        
        foreach ($notification_types as $type => $label) {
            register_setting('rfq_email_settings', 'rfq_enable_' . $type, [
                'type' => 'boolean',
                'description' => sprintf(__('Activar notificación: %s', 'rfq-manager-woocommerce'), $label),
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => true,
            ]);
        }
        
        // Configuración de destinatarios administrativos
        register_setting('rfq_email_settings', 'rfq_admin_notification_recipients', [
            'type' => 'string',
            'description' => __('Destinatarios administrativos para notificaciones (separados por comas)', 'rfq-manager-woocommerce'),
            'sanitize_callback' => 'sanitize_text_field',
            'default' => get_option('admin_email'),
        ]);
    }
    
}