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
use GiVendor\GiPlugin\Utils\RfqLogger;


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
            // error_log('[RFQ-DEBUG] EmailManager - Sistema de notificaciones inicializado');
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
        
        // Inicializar componentes de WhatsApp
        if (class_exists('GiVendor\GiPlugin\Notifications\WhatsAppUserProfile')) {
            \GiVendor\GiPlugin\Notifications\WhatsAppUserProfile::init();
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
        
        // Pie legal para emails
        register_setting('rfq_email_settings', 'rfq_email_legal_footer', [
            'type' => 'string',
            'description' => __('Pie legal para emails (HTML)', 'rfq-manager-woocommerce'),
            'sanitize_callback' => 'wp_kses_post',
            'default' => '',
        ]);
    }
    
    /**
     * Construye headers centralizados para emails
     *
     * @since  0.1.0
     * @param  array $extra Headers adicionales
     * @return array Array de strings listos para wp_mail()
     */
    public static function build_headers(array $extra = []): array {
        $headers = [];
        
        // Content-Type obligatorio
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        
        // From header con validación
        $from_name = sanitize_text_field(
            get_option('rfq_manager_from_name', get_bloginfo('name'))
        );
        
        // Usar el remitente global configurado en el backend o fallback
        $from_email_global = get_option('rfq_email_from_global', '');
        if (!empty($from_email_global) && is_email($from_email_global)) {
            $from_email = $from_email_global;
        } else {
            $from_email = sanitize_email(
                get_option('rfq_manager_from_email', get_option('admin_email'))
            );
        }
        
        // Fallback a filtros WP si faltan configuraciones
        if (empty($from_name)) {
            $from_name = apply_filters('wp_mail_from_name', get_bloginfo('name'));
        }
        if (empty($from_email) || !is_email($from_email)) {
            $from_email = apply_filters('wp_mail_from', get_option('admin_email'));
        }
        
        $headers[] = "From: " . esc_html($from_name) . " <" . sanitize_email($from_email) . ">";
        
        // Procesar BCC global
        $bcc_emails = self::process_global_bcc($extra);
        if (!empty($bcc_emails)) {
            $headers[] = 'Bcc: ' . implode(',', $bcc_emails);
        }
        
        // Mezclar headers adicionales (excluyendo Bcc que ya se procesó)
        foreach ($extra as $key => $value) {
            if (is_string($key)) {
                // Skip Bcc porque ya se procesó en process_global_bcc()
                if (strtolower($key) === 'bcc') {
                    continue;
                }
                // Header con clave (ej: 'Reply-To' => 'email@domain.com')
                $headers[] = sanitize_text_field($key) . ': ' . sanitize_text_field($value);
            } else {
                // Header directo (ej: 'Reply-To: email@domain.com')
                $headers[] = sanitize_text_field($value);
            }
        }
        
        // Aplicar filtro para personalización
        return apply_filters('rfq_email_headers', $headers);
    }

    /**
     * Procesa el BCC global y lo combina con BCC específicos
     *
     * @since  0.2.0
     * @param  array $extra Headers adicionales que pueden contener Bcc
     * @return array Array de emails BCC válidos y deduplicados
     */
    private static function process_global_bcc(array $extra = []): array {
        // 1. Obtener BCC global de configuración
        $bcc_global_raw = get_option('rfq_email_bcc_global', '');
        $bcc_global_raw = apply_filters('rfq_email_bcc_global', $bcc_global_raw);
        
        // 2. Parsear BCC global (CSV a array)
        $bcc_global = [];
        if (!empty($bcc_global_raw)) {
            $bcc_global = array_map('trim', explode(',', $bcc_global_raw));
        }
        
        // 3. Obtener BCC específico de $extra si existe
        $bcc_specific = [];
        if (isset($extra['Bcc'])) {
            if (is_string($extra['Bcc'])) {
                $bcc_specific = array_map('trim', explode(',', $extra['Bcc']));
            } elseif (is_array($extra['Bcc'])) {
                $bcc_specific = $extra['Bcc'];
            }
        } elseif (isset($extra['bcc'])) {
            if (is_string($extra['bcc'])) {
                $bcc_specific = array_map('trim', explode(',', $extra['bcc']));
            } elseif (is_array($extra['bcc'])) {
                $bcc_specific = $extra['bcc'];
            }
        }
        
        // 4. Combinar ambos arrays
        $all_bcc = array_merge($bcc_global, $bcc_specific);
        
        // 5. Validar emails y descartar inválidos
        $valid_bcc = [];
        foreach ($all_bcc as $email) {
            $email = trim(strtolower($email));
                if (!empty($email) && is_email($email)) {
                    $valid_bcc[] = $email;
                } elseif (!empty($email)) {
                    // Log warning para emails inválidos
                    if (class_exists('GiVendor\\GiPlugin\\Utils\\RfqLogger')) {
                        \GiVendor\GiPlugin\Utils\RfqLogger::warn(
                            'BCC email inválido descartado: ' . $email,
                            ['context' => 'EmailManager::process_global_bcc']
                        );
                    }
                }
            }        // 6. Deduplicar
        $valid_bcc = array_unique($valid_bcc);
        
        // 7. Aplicar filtro final
        $final_bcc = apply_filters('rfq_email_bcc_recipients', $valid_bcc);
        
        // 8. Log resultado final (solo cantidad para no exponer emails)
        if (!empty($final_bcc) && class_exists('GiVendor\\GiPlugin\\Utils\\RfqLogger')) {
            \GiVendor\GiPlugin\Utils\RfqLogger::info(
                'BCC global procesado: ' . count($final_bcc) . ' destinatarios',
                ['context' => 'EmailManager::process_global_bcc']
            );
        }
        
        return array_values($final_bcc);
    }
    
    /**
     * Pipeline consolidado: envío único de emails
     *
     * @since  0.2.0
     * @param  string|array $to      Destinatario(s) - string o array
     * @param  string       $subject Asunto del email
     * @param  string       $html    Contenido HTML
     * @param  string       $text    Contenido texto plano (preparado para futuro)
     * @param  array        $headers Headers del email (array de strings)
     * @return bool Resultado del envío
     */
    public static function send($to, string $subject, string $html, string $text, array $headers): bool {
        // Normalizar destinatarios
        $recipients = is_array($to) ? $to : [$to];
        $valid_recipients = [];
        
        foreach ($recipients as $recipient) {
            $clean_email = sanitize_email(trim($recipient));
            if (!empty($clean_email) && is_email($clean_email)) {
                $valid_recipients[] = $clean_email;
            } else {
                RfqLogger::warn('[send] Destinatario inválido descartado: ' . $recipient);
            }
        }
        
        if (empty($valid_recipients)) {
            RfqLogger::warn('[send] Sin destinatarios válidos para envío', [
                'original_to' => $to,
                'subject' => $subject
            ]);
            return false;
        }
        
        // Aplicar filtros antes del envío
        $filtered_to = apply_filters('rfq_before_send_email', $valid_recipients, $subject, $html, $text, $headers);
        $filtered_subject = apply_filters('rfq_before_send_email_subject', $subject, $valid_recipients, $html, $text, $headers);
        $filtered_html = apply_filters('rfq_before_send_email_html', $html, $valid_recipients, $subject, $text, $headers);
        $filtered_headers = apply_filters('rfq_before_send_email_headers', $headers, $valid_recipients, $subject, $html, $text);
        
        // Log intento de envío
        RfqLogger::info('[send] intento', [
            'to_count' => count($filtered_to),
            'len_html' => strlen($filtered_html),
            'subject_preview' => substr($filtered_subject, 0, 50)
        ]);
        
        // Envío (por ahora solo HTML como indica la fase 3)
        $result = wp_mail($filtered_to, $filtered_subject, $filtered_html, $filtered_headers);
        
        // Log resultado
        if (!$result) {
            RfqLogger::warn('[send] Error en wp_mail', [
                'to_count' => count($filtered_to),
                'subject' => $filtered_subject,
                'wp_mail_error' => 'wp_mail devolvió false'
            ]);
        } else {
            RfqLogger::info('[send] exitoso', [
                'to_count' => count($filtered_to),
                'subject_preview' => substr($filtered_subject, 0, 50)
            ]);
        }
        
        // Aplicar filtro post-envío
        do_action('rfq_after_send_email', $result, $filtered_to, $filtered_subject, $filtered_html, $filtered_headers);
        
        return $result;
    }
    
}