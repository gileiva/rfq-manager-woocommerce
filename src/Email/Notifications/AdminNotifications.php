<?php
/**
 * Gestión de notificaciones para administradores
 *
 * @package    GiVendor\GiPlugin\Email\Notifications
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Email\Notifications;

use GiVendor\GiPlugin\Email\Templates\NotificationTemplateFactory;
use GiVendor\GiPlugin\Email\Notifications\Custom\NotificationManager;
use GiVendor\GiPlugin\Email\Templates\TemplateParser;

/**
 * AdminNotifications - Gestiona las notificaciones por email para administradores
 *
 * Esta clase es responsable de enviar notificaciones por email a los administradores
 * cuando se crean nuevas solicitudes o cuando hay cambios relevantes.
 *
 * @package    GiVendor\GiPlugin\Email\Notifications
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class AdminNotifications {
    
    /**
     * Templates específicos para administradores
     *
     * @since  0.1.0
     * @access protected
     * @var    string
     */
    protected static $template_dir = 'src/Email/Templates/Admin';
    
    /**
     * Inicializa la clase y registra los hooks necesarios
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        // Registrar hooks para enviar notificaciones en diferentes eventos
        add_action('rfq_solicitud_created', [__CLASS__, 'send_solicitud_created_notification'], 10, 2);
        add_action('rfq_cotizacion_submitted', [__CLASS__, 'send_cotizacion_submitted_notification'], 10, 2);
        add_action('rfq_cotizacion_accepted', [__CLASS__, 'send_cotizacion_accepted_notification'], 10, 2);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // error_log('[RFQ-DEBUG] AdminNotifications registrado en hooks');
        }
    }
    
    /**
     * Envía notificación a administradores cuando se crea una solicitud
     *
     * @since  0.1.0
     * @param  int $solicitud_id ID de la solicitud creada
     * @param  array $data Datos de la solicitud
     * @return bool
     */
    public static function send_solicitud_created_notification(int $solicitud_id, array $data): bool {
        $admin_recipients = self::get_admin_recipients('solicitud_created', $solicitud_id);
        if (empty($admin_recipients)) {
            error_log('[RFQ-WARN] No hay destinatarios admin para notificación de solicitud_created: ' . $solicitud_id);
            return true; // No es un error si no hay admins configurados
        }
        
        $notification_manager = NotificationManager::getInstance();
        $subject_template = $notification_manager->getCurrentSubject('admin', 'solicitud_created');
        $content_template = $notification_manager->getCurrentTemplate('admin', 'solicitud_created');

        $user_id_creator = get_post_field('post_author', $solicitud_id);
        $user_creator = get_userdata($user_id_creator);

        $solicitud_items_raw = get_post_meta($solicitud_id, '_solicitud_items', true);
        $solicitud_items = is_string($solicitud_items_raw) ? json_decode($solicitud_items_raw, true) : $solicitud_items_raw;

        $template_args = array_merge($data, [
            'solicitud_id' => $solicitud_id,
            'user_name' => $user_creator ? $user_creator->display_name : __('Usuario Desconocido', 'rfq-manager-woocommerce'),
            'user_email' => $user_creator ? $user_creator->user_email : '',
            'productos' => self::format_items_for_email($solicitud_items ?? []),
            'request_title' => get_the_title($solicitud_id),
            'request_status' => get_post_status($solicitud_id),
            'request_link' => admin_url("admin.php?page=rfq-solicitudes&action=edit&post={$solicitud_id}"),
        ]);
        
        $message = NotificationTemplateFactory::create('admin', $subject_template, $content_template, $template_args);
        
        $headers = self::get_email_headers();
        $result = wp_mail($admin_recipients, TemplateParser::render($subject_template, $template_args), $message, $headers);
        
        self::log_result($result, 'solicitud_created', $admin_recipients, $solicitud_id);
        return $result;
    }
    
    /**
     * Envía notificación a administradores cuando se envía una cotización
     *
     * @since  0.1.0
     * @param  int $cotizacion_id ID de la cotización
     * @param  int $solicitud_id ID de la solicitud relacionada
     * @return bool
     */
    public static function send_cotizacion_submitted_notification(int $cotizacion_id, int $solicitud_id): bool {
        $admin_recipients = self::get_admin_recipients('cotizacion_submitted', $solicitud_id, $cotizacion_id);
        if (empty($admin_recipients)) {
            error_log('[RFQ-WARN] No hay destinatarios admin para notificación de cotizacion_submitted: ' . $cotizacion_id);
            return true;
        }
        
        $notification_manager = NotificationManager::getInstance();
        $subject_template = $notification_manager->getCurrentSubject('admin', 'cotizacion_submitted');
        $content_template = $notification_manager->getCurrentTemplate('admin', 'cotizacion_submitted');

        $supplier_id = get_post_field('post_author', $cotizacion_id);
        $supplier = get_userdata($supplier_id);
        $user_id = get_post_field('post_author', $solicitud_id);
        $user = get_userdata($user_id);
        
        $precio_items_raw = get_post_meta($cotizacion_id, '_precio_items', true);
        $precio_items = is_string($precio_items_raw) ? json_decode($precio_items_raw, true) : $precio_items_raw;
        
        $template_args = [
            'solicitud_id' => $solicitud_id,
            'cotizacion_id' => $cotizacion_id,
            'supplier_name' => $supplier ? $supplier->display_name : __('Proveedor Desconocido', 'rfq-manager-woocommerce'),
            'supplier_email' => $supplier ? $supplier->user_email : '',
            'user_name' => $user ? $user->display_name : __('Cliente Desconocido', 'rfq-manager-woocommerce'),
            'user_email' => $user ? $user->user_email : '',
            'quote_amount' => get_post_meta($cotizacion_id, '_total', true),
            'request_title' => get_the_title($solicitud_id),
            'quote_link' => admin_url("admin.php?page=rfq-cotizaciones&action=edit&post={$cotizacion_id}"),
            'productos_cotizados' => self::format_quoted_items_for_email($precio_items ?? []),
        ];
        
        $message = NotificationTemplateFactory::create('admin', $subject_template, $content_template, $template_args);
        
        $headers = self::get_email_headers();
        $result = wp_mail($admin_recipients, TemplateParser::render($subject_template, $template_args), $message, $headers);
        
        self::log_result($result, 'cotizacion_submitted', $admin_recipients, $cotizacion_id);
        return $result;
    }
    
    /**
     * Envía notificación a administradores cuando se acepta una cotización
     *
     * @since  0.1.0
     * @param  int $cotizacion_id ID de la cotización aceptada
     * @param  int $solicitud_id ID de la solicitud relacionada
     * @return bool
     */
    public static function send_cotizacion_accepted_notification(int $cotizacion_id, int $solicitud_id): bool {
        $admin_recipients = self::get_admin_recipients('cotizacion_accepted', $solicitud_id, $cotizacion_id);
        if (empty($admin_recipients)) {
            error_log('[RFQ-WARN] No hay destinatarios admin para notificación de cotizacion_accepted: ' . $cotizacion_id);
            return true;
        }
        
        $notification_manager = NotificationManager::getInstance();
        $subject_template = $notification_manager->getCurrentSubject('admin', 'cotizacion_accepted');
        $content_template = $notification_manager->getCurrentTemplate('admin', 'cotizacion_accepted');

        $supplier_id = get_post_field('post_author', $cotizacion_id);
        $supplier = get_userdata($supplier_id);
        $user_id = get_post_field('post_author', $solicitud_id);
        $user = get_userdata($user_id);
        
        $precio_items_raw = get_post_meta($cotizacion_id, '_precio_items', true);
        $precio_items = is_string($precio_items_raw) ? json_decode($precio_items_raw, true) : $precio_items_raw;
        
        $template_args = [
            'solicitud_id' => $solicitud_id,
            'cotizacion_id' => $cotizacion_id,
            'supplier_name' => $supplier ? $supplier->display_name : __('Proveedor Desconocido', 'rfq-manager-woocommerce'),
            'supplier_email' => $supplier ? $supplier->user_email : '',
            'user_name' => $user ? $user->display_name : __('Cliente Desconocido', 'rfq-manager-woocommerce'),
            'user_email' => $user ? $user->user_email : '',
            'quote_amount' => get_post_meta($cotizacion_id, '_total', true),
            'request_title' => get_the_title($solicitud_id),
            'quote_link' => admin_url("admin.php?page=rfq-cotizaciones&action=edit&post={$cotizacion_id}"),
            'productos_cotizados' => self::format_quoted_items_for_email($precio_items ?? []),
        ];
        
        $message = NotificationTemplateFactory::create('admin', $subject_template, $content_template, $template_args);
        
        $headers = self::get_email_headers();
        $result = wp_mail($admin_recipients, TemplateParser::render($subject_template, $template_args), $message, $headers);
        
        self::log_result($result, 'cotizacion_accepted', $admin_recipients, $cotizacion_id);
        return $result;
    }
    
    /**
     * Obtiene los destinatarios administrativos para una notificación específica
     *
     * @since  0.1.0
     * @param  string $event_key Tipo de notificación
     * @param  int $solicitud_id ID de la solicitud relacionada
     * @param  int $cotizacion_id ID de la cotización relacionada
     * @return array|string
     */
    protected static function get_admin_recipients(string $event_key, int $solicitud_id = 0, int $cotizacion_id = 0) {
        $default_recipient = get_option('admin_email');
        // Permitir filtrar por IDs específicos si es necesario
        $recipients = apply_filters('rfq_admin_notification_recipients', $default_recipient, $event_key, $solicitud_id, $cotizacion_id);
        
        if (empty($recipients)) {
            return ''; // Devuelve string vacío si no hay destinatarios
        }
        
        // Asegurarse de que sea un array o un string de emails separados por coma
        return $recipients;
    }
    
    /**
     * Obtiene las cabeceras para los emails
     *
     * @since  0.1.0
     * @return string
     */
    protected static function get_email_headers(): string {
        $from_name = apply_filters('rfq_email_from_name', get_bloginfo('name'));
        $from_email = apply_filters('rfq_email_from_address', get_option('admin_email'));
        
        $headers = "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . esc_html($from_name) . " <" . sanitize_email($from_email) . ">\r\n";
        
        return apply_filters('rfq_email_headers', $headers);
    }
    
    /**
     * Verifica si existe un template
     *
     * @since  0.1.0
     * @param  string $template_name Nombre del archivo de template
     * @return bool
     */
    protected static function template_exists(string $template_name): bool {
        // Buscar primero en el tema activo
        $theme_template = locate_template([
            'rfq-manager/emails/admin/' . $template_name,
        ]);
        
        if ($theme_template) {
            return true;
        }
        
        // Buscar en el directorio del plugin
        $plugin_template = RFQ_MANAGER_WOO_PLUGIN_DIR . self::$template_dir . '/' . $template_name;
        
        return file_exists($plugin_template);
    }
    
    /**
     * Obtiene el contenido de un template
     *
     * @since  0.1.0
     * @param  string $template_name Nombre del archivo de template
     * @param  array $args Argumentos para el template
     * @return string
     */
    protected static function get_template_content(string $template_name, array $args = []): string {
        // Extraer argumentos a variables
        extract($args);
        
        ob_start();
        
        // Buscar primero en el tema activo
        $theme_template = locate_template([
            'rfq-manager/emails/admin/' . $template_name,
        ]);
        
        if ($theme_template) {
            include $theme_template;
        } else {
            // Buscar en el directorio del plugin
            $plugin_template = RFQ_MANAGER_WOO_PLUGIN_DIR . self::$template_dir . '/' . $template_name;
            
            if (file_exists($plugin_template)) {
                include $plugin_template;
            } else {
                // Usar template genérico si no existe uno específico
                echo self::get_generic_template_content($args);
            }
        }
        
        $content = ob_get_clean();
        
        return $content;
    }
    
    /**
     * Obtiene el contenido de un template genérico
     *
     * @since  0.1.0
     * @param  array $args Argumentos para el template
     * @return string
     */
    protected static function get_generic_template_content(array $args = []): string {
        // Extraer argumentos a variables
        extract($args);
        
        $site_name = get_bloginfo('name');
        $site_url = get_bloginfo('url');
        
        $content = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
            <title>' . esc_html($site_name) . '</title>
        </head>
        <body style="background-color: #f7f7f7; padding: 20px; font-family: Arial, sans-serif;">
            <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border: 1px solid #e5e5e5;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <h1 style="color: #3c3c3c;">' . esc_html($site_name) . '</h1>
                </div>
                <div style="color: #5d5d5d; font-size: 15px; line-height: 22px;">';
        
        // Contenido específico según el tipo de notificación
        if (isset($cotizacion_id) && isset($solicitud_id) && isset($supplier)) {
            if (isset($accepted)) {
                // Notificación de cotización aceptada
                $solicitud_title = get_the_title($solicitud_id);
                $user_id = get_post_field('post_author', $solicitud_id);
                $user = get_userdata($user_id);
                $user_name = $user ? $user->display_name : __('Cliente desconocido', 'rfq-manager-woocommerce');
                
                $content .= '<p>' . __('Notificación: Cotización Aceptada', 'rfq-manager-woocommerce') . '</p>';
                $content .= '<p>' . sprintf(__('La cotización del proveedor %s para la solicitud "%s" ha sido aceptada por el cliente %s.', 'rfq-manager-woocommerce'), 
                    esc_html($supplier->display_name), esc_html($solicitud_title), esc_html($user_name)) . '</p>';
            } else {
                // Notificación de cotización enviada
                $solicitud_title = get_the_title($solicitud_id);
                
                $content .= '<p>' . __('Notificación: Nueva Cotización', 'rfq-manager-woocommerce') . '</p>';
                $content .= '<p>' . sprintf(__('El proveedor %s ha enviado una cotización para la solicitud "%s".', 'rfq-manager-woocommerce'), 
                    esc_html($supplier->display_name), esc_html($solicitud_title)) . '</p>';
            }
        } elseif (isset($solicitud_id) && isset($data)) {
            // Notificación de solicitud creada
            $solicitud_title = get_the_title($solicitud_id);
            $user_id = get_post_field('post_author', $solicitud_id);
            $user = get_userdata($user_id);
            $user_name = $user ? $user->display_name : __('Cliente desconocido', 'rfq-manager-woocommerce');
            
            $content .= '<p>' . __('Notificación: Nueva Solicitud de Cotización', 'rfq-manager-woocommerce') . '</p>';
            $content .= '<p>' . sprintf(__('El usuario %s ha creado una nueva solicitud de cotización: "%s".', 'rfq-manager-woocommerce'), 
                esc_html($user_name), esc_html($solicitud_title)) . '</p>';
        } else {
            // Template genérico para cualquier otra situación
            $content .= '<p>' . __('Notificación del Sistema RFQ', 'rfq-manager-woocommerce') . '</p>';
            $content .= '<p>' . __('Se ha registrado una nueva actividad en el sistema de solicitudes de cotización.', 'rfq-manager-woocommerce') . '</p>';
        }
        
        $content .= '<p>' . __('Para más detalles, por favor inicie sesión en el panel de administración.', 'rfq-manager-woocommerce') . '</p>';
        $content .= '<div style="margin-top:20px; padding-top:20px; border-top:1px solid #e5e5e5; text-align:center; color:#8a8a8a; font-size:12px;">';
        $content .= '<p>' . sprintf(
            __('Este email fue enviado desde %s (%s)', 'rfq-manager-woocommerce'),
            esc_html($site_name),
            esc_url($site_url)
        ) . '</p>';
        $content .= '</div></div></body></html>';
        
        return $content;
    }

    private static function format_items_for_email($items): string {
        if (empty($items) || !is_array($items)) {
            return '';
        }
        $list_items = [];
        foreach ($items as $item) {
            if (is_array($item) && !empty($item['name'])) {
                $product_name = esc_html($item['name']);
                $quantity = isset($item['quantity']) ? intval($item['quantity']) : (isset($item['qty']) ? intval($item['qty']) : 0);
                $list_items[] = $product_name . ($quantity > 0 ? ' x ' . $quantity : '');
            }
        }
        if(empty($list_items)) return '';
        return '<ul><li>' . implode('</li><li>', $list_items) . '</li></ul>';
    }

    private static function format_quoted_items_for_email($items): string {
        if (empty($items) || !is_array($items)) {
            return '';
        }
        $list_items = [];
        foreach ($items as $item) {
            if (is_array($item) && !empty($item['name'])) {
                $product_name = esc_html($item['name']);
                $quantity = isset($item['quantity']) ? intval($item['quantity']) : (isset($item['qty']) ? intval($item['qty']) : 0);
                $list_items[] = $product_name . ($quantity > 0 ? ' x ' . $quantity : '');
            }
        }
        if(empty($list_items)) return '';
        return '<ul><li>' . implode('</li><li>', $list_items) . '</li></ul>';
    }

    private static function log_result(bool $result, string $notification_type, $recipients, int $post_id): void {
        $recipient_str = is_array($recipients) ? implode(', ', $recipients) : $recipients;
        if (!$result) {
            error_log(sprintf('[RFQ-ERROR] Error al enviar notificación admin de %s a %s para el post #%d', $notification_type, $recipient_str, $post_id));
        } else {
            error_log(sprintf('[RFQ-SUCCESS] Notificación admin de %s enviada a %s para el post #%d', $notification_type, $recipient_str, $post_id));
        }
    }
}
