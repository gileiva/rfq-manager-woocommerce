<?php
/**
 * Gestión de notificaciones para usuarios
 *
 * @package    GiVendor\GiPlugin\Email\Notifications
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Email\Notifications;

use GiVendor\GiPlugin\Email\Templates\NotificationTemplateFactory;
use GiVendor\GiPlugin\Email\Notifications\Custom\NotificationManager;
use GiVendor\GiPlugin\Email\Templates\TemplateParser;
use GiVendor\GiPlugin\Email\Templates\TemplateRenderer;
use GiVendor\GiPlugin\Utils\RfqLogger;
use GiVendor\GiPlugin\Email\EmailManager;

/**
 * UserNotifications - Gestiona las notificaciones por email para usuarios
 *
 * Esta clase es responsable de enviar notificaciones por email a los usuarios
 * en respuesta a diferentes acciones relacionadas con las solicitudes y cotizaciones.
 *
 * @package    GiVendor\GiPlugin\Email\Notifications
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class UserNotifications {
    
    /**
     * Templates específicos para usuarios
     *
     * @since  0.1.0
     * @access protected
     * @var    string
     */
    protected static $template_dir = 'src/Email/Templates/User';
    
    /**
     * Inicializa la clase y registra los hooks necesarios
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        // Registrar hooks para enviar notificaciones en diferentes eventos
        add_action('rfq_solicitud_created', [__CLASS__, 'send_solicitud_created_notification'], 10, 2);
        add_action('rfq_cotizacion_submitted', [__CLASS__, 'send_cotizacion_received_notification'], 10, 2);
        add_action('rfq_solicitud_status_changed', [__CLASS__, 'send_status_changed_notification'], 10, 3);
        add_action('rfq_cotizacion_accepted_by_user', [__CLASS__, 'send_cotizacion_accepted_notification'], 10, 2);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // error_log('[RFQ-DEBUG] UserNotifications registrado en hooks');
        }
    }
    
    /**
     * Envía notificación al usuario cuando se crea una solicitud
     *
     * @since  0.1.0
     * @param  int $solicitud_id ID de la solicitud creada
     * @param  array $data Datos de la solicitud
     * @return bool
     */
    public static function send_solicitud_created_notification(int $solicitud_id, array $data): bool {
        $user_id = get_post_field('post_author', $solicitud_id);
        $user = get_userdata($user_id);
        
        if (!$user) {
            error_log('[RFQ-ERROR] Usuario no encontrado para solicitud ' . $solicitud_id);
            return false;
        }
        
        $to = apply_filters('rfq_user_notification_recipient_solicitud_created', $user->user_email, $solicitud_id, $user);
        if (empty($to)) {
            error_log('[RFQ-ERROR] No se envió notificación: Destinatario vacío para solicitud_created ' . $solicitud_id);
            return false;
        }
        
        $notification_manager = NotificationManager::getInstance();
        $subject_template = $notification_manager->getCurrentSubject('user', 'solicitud_created');
        $content_template = $notification_manager->getCurrentTemplate('user', 'solicitud_created');
        
        // Resolver first_name y last_name
        $names = NotificationManager::resolve_user_names($user_id);
        
        $template_args = array_merge($data, [
            'solicitud_id' => $solicitud_id,
            'user_id' => $user_id,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'first_name' => $names['first_name'] ?: '',
            'last_name' => $names['last_name'] ?: '',
            'nombre' => $user->display_name, // Por compatibilidad con placeholders antiguos
            'productos' => self::format_items_for_email($data['items'] ?? []),
        ]);
        
        // Usar TemplateRenderer para generar HTML con pie legal
        $legal_footer = get_option('rfq_email_legal_footer', '');
        $legal_footer = wp_kses_post($legal_footer);
        $message = TemplateRenderer::render_html(
            $content_template, 
            $template_args, 
            $legal_footer,
            ['notification_type' => 'solicitud_created', 'user_id' => $user_id]
        );
        
        $headers = EmailManager::build_headers();
        $result = wp_mail($to, TemplateParser::render($subject_template, $template_args), $message, $headers);
        
        self::log_result($result, 'solicitud_created', $user, $solicitud_id);
        return $result;
    }
    
    /**
     * Formatea los items para la plantilla
     */
    private static function format_items_for_email($items): string {
        if (empty($items) || !is_array($items)) {
            return '';
        }
        $list_items = [];
        foreach ($items as $item) {
            if (is_array($item) && !empty($item['name'])) {
                $list_items[] = esc_html($item['name']) . (isset($item['quantity']) ? ' x ' . intval($item['quantity']) : (isset($item['qty']) ? ' x ' . intval($item['qty']) : ''));
            }
        }
        if(empty($list_items)) return '';
        return '<ul><li>' . implode('</li><li>', $list_items) . '</li></ul>';
    }
    
    /**
     * Envía notificación al usuario cuando recibe una cotización
     *
     * @since  0.1.0
     * @param  int $cotizacion_id ID de la cotización
     * @param  int $solicitud_id ID de la solicitud relacionada
     * @return bool
     */
    public static function send_cotizacion_received_notification(int $cotizacion_id, int $solicitud_id): bool {
        $user_id = get_post_field('post_author', $solicitud_id);
        $user = get_userdata($user_id);
        
        if (!$user) {
            error_log('[RFQ-ERROR] Usuario no encontrado para cotizacion_received (solicitud ' . $solicitud_id . ')');
            return false;
        }
        
        $to = apply_filters('rfq_user_notification_recipient_cotizacion_received', $user->user_email, $cotizacion_id, $solicitud_id, $user);
        if (empty($to)) {
            error_log('[RFQ-ERROR] No se envió notificación: Destinatario vacío para cotizacion_received ' . $cotizacion_id);
            return false;
        }
        
        $notification_manager = NotificationManager::getInstance();
        // Para el usuario, el evento es 'cotizacion_received'
        $subject_template = $notification_manager->getCurrentSubject('user', 'cotizacion_received'); 
        $content_template = $notification_manager->getCurrentTemplate('user', 'cotizacion_received');

        $solicitud_items = get_post_meta($solicitud_id, '_solicitud_items', true);
        if (is_string($solicitud_items)) $solicitud_items = json_decode($solicitud_items, true);
        
        // Resolver first_name y last_name
        $names = NotificationManager::resolve_user_names($user_id);
        
        $template_args = [
            'solicitud_id' => $solicitud_id,
            'cotizacion_id' => $cotizacion_id,
            'user_id' => $user_id,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'first_name' => $names['first_name'] ?: '',
            'last_name' => $names['last_name'] ?: '',
            'nombre' => $user->display_name,
            'productos' => self::format_items_for_email($solicitud_items ?? []),
        ];
        
        // Usar TemplateRenderer para generar HTML con pie legal
        $legal_footer = get_option('rfq_email_legal_footer', '');
        $legal_footer = wp_kses_post($legal_footer);
        $message = TemplateRenderer::render_html(
            $content_template, 
            $template_args, 
            $legal_footer,
            ['notification_type' => 'cotizacion_received', 'user_id' => $user_id]
        );
        
        $headers = EmailManager::build_headers();
        $result = wp_mail($to, TemplateParser::render($subject_template, $template_args), $message, $headers);
        
        self::log_result($result, 'cotizacion_received', $user, $cotizacion_id);
        return $result;
    }
    
    /**
     * Envía notificación al usuario cuando cambia el estado de su solicitud
     *
     * @since  0.1.0
     * @param  int $solicitud_id ID de la solicitud
     * @param  string $new_status Nuevo estado
     * @param  string $old_status Estado anterior
     * @return bool
     */
    public static function send_status_changed_notification(int $solicitud_id, string $new_status, string $old_status): bool {
        $user_id = get_post_field('post_author', $solicitud_id);
        $user = get_userdata($user_id);

        if (!$user) {
            error_log("[RFQ-ERROR] Usuario no encontrado para solicitud_status_changed {$solicitud_id}");
            return false;
        }

        $notify_status_changes = apply_filters('rfq_user_notify_status_changes', ['activa', 'aceptada', 'historica', 'pagada'], $solicitud_id, $new_status, $old_status);
        if (!in_array($new_status, $notify_status_changes)) {
            error_log("[RFQ-FLOW] No se notificará cambio de estado a: {$new_status}");
            return true; 
        }

        // Si es 'aceptada' y es activado por rfq_cotizacion_accepted_by_user, se maneja en send_cotizacion_accepted_notification.
        // Aquí solo notificamos si el estado cambia a 'aceptada' por otros medios (ej. admin) y no hay cotización explícita.
        if ($new_status === 'aceptada') {
            $is_accepted_by_user_hook = current_filter() === 'rfq_cotizacion_accepted_by_user';
            if ($is_accepted_by_user_hook) {
                 error_log("[RFQ-FLOW] Omitiendo notificación de status_changed a 'aceptada' vía rfq_cotizacion_accepted_by_user. Será manejada por send_cotizacion_accepted_notification.");
                return true;
            }
            // Si no es por el hook 'rfq_cotizacion_accepted_by_user', podría ser un cambio manual del admin.
            // La notificación de 'cotizacion_accepted' es más rica en detalles si existe una cotización asociada.
            $accepted_cotizacion_id = get_post_meta($solicitud_id, '_rfq_accepted_cotizacion', true);
            if ($accepted_cotizacion_id) {
                error_log("[RFQ-FLOW] Omitiendo notificación de status_changed a 'aceptada'. La notificación de cotización aceptada (ID: {$accepted_cotizacion_id}) es preferible.");
                //return self::send_cotizacion_accepted_notification(intval($accepted_cotizacion_id), $solicitud_id); // Podríamos forzarla, pero es mejor que el hook original lo maneje.
                return true; 
            }
        }
        
        $to = apply_filters('rfq_user_notification_recipient_status_changed', $user->user_email, $solicitud_id, $new_status, $old_status, $user);
        if (empty($to)) {
            error_log("[RFQ-ERROR] No se envió notificación: Destinatario vacío para status_changed {$solicitud_id}");
            return false;
        }
        
        $notification_manager = NotificationManager::getInstance();
        $event_key = 'status_changed_' . $new_status; // ej. status_changed_activa
        $subject_template = $notification_manager->getCurrentSubject('user', $event_key, false);
        $content_template = $notification_manager->getCurrentTemplate('user', $event_key, false);

        // Fallback si no hay plantilla/asunto específico para el nuevo estado
        if (!$subject_template || !$content_template) {
            $subject_template = $notification_manager->getCurrentSubject('user', 'status_changed');
            $content_template = $notification_manager->getCurrentTemplate('user', 'status_changed');
        }
        
        $porcentaje_ahorro = 0;
        if ($new_status === 'aceptada' || $new_status === 'pagada') { // 'pagada' implica que fue aceptada antes
            $cotizacion_id_ahorro = get_post_meta($solicitud_id, '_rfq_accepted_cotizacion', true);
            if ($cotizacion_id_ahorro) {
                $total_cotizacion = get_post_meta($cotizacion_id_ahorro, '_total', true);
                $items_solicitud_raw = get_post_meta($solicitud_id, '_solicitud_items', true);
                $items_solicitud = is_string($items_solicitud_raw) ? json_decode($items_solicitud_raw, true) : $items_solicitud_raw;
                $gran_total_solicitud = 0;
                if (is_array($items_solicitud)) {
                    foreach ($items_solicitud as $item) {
                        $gran_total_solicitud += floatval($item['subtotal'] ?? 0); 
                    }
                }
                if ($gran_total_solicitud > 0 && $total_cotizacion > 0) {
                    $porcentaje_ahorro = (($gran_total_solicitud - floatval($total_cotizacion)) / $gran_total_solicitud) * 100;
                }
            }
        }

        // Resolver first_name y last_name
        $names = NotificationManager::resolve_user_names($user_id);
        
        $template_args = [
            'solicitud_id' => $solicitud_id,
            'user_id' => $user_id,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'first_name' => $names['first_name'] ?: '',
            'last_name' => $names['last_name'] ?: '',
            'nombre' => $user->display_name,
            'new_status' => $new_status, 
            'old_status' => $old_status,
            'porcentaje_ahorro' => $porcentaje_ahorro, 
        ];
        
        // Si el subject_template todavía es genérico o es un placeholder, intentamos hacerlo más específico
        $rendered_subject_check = TemplateParser::render($subject_template, $template_args);
        if (strpos($subject_template, '{new_status}') !== false || strpos($rendered_subject_check, $new_status) === false) {
             $status_labels = [
                'activa' => __('Tu solicitud {request_title} ha recibido cotizaciones', 'rfq-manager-woocommerce'),
                'aceptada' => __('Has aceptado una cotización para {request_title}', 'rfq-manager-woocommerce'),
                'historica' => __('Tu solicitud {request_title} ha pasado al historial', 'rfq-manager-woocommerce'),
                'pagada' => __('El pago para {request_title} ha sido confirmado', 'rfq-manager-woocommerce'),
            ];
            $subject_template = $status_labels[$new_status] ?? sprintf(__('El estado de tu solicitud {request_title} es ahora: %s', 'rfq-manager-woocommerce'), $new_status);
        }

        // Usar TemplateRenderer para generar HTML con pie legal
        $legal_footer = get_option('rfq_email_legal_footer', '');
        $legal_footer = wp_kses_post($legal_footer);
        $message = TemplateRenderer::render_html(
            $content_template, 
            $template_args, 
            $legal_footer,
            ['notification_type' => 'status_change', 'user_id' => $user_id, 'new_status' => $new_status]
        );
        
        $headers = EmailManager::build_headers();
        $final_subject = TemplateParser::render($subject_template, $template_args);
        $result = wp_mail($to, $final_subject, $message, $headers);
        
        self::log_result($result, 'status_changed_to_' . $new_status, $user, $solicitud_id);
        return $result;
    }
    
    /**
     * Envía notificación al usuario cuando acepta una cotización
     *
     * @since  0.1.0
     * @param  int $cotizacion_id ID de la cotización aceptada
     * @param  int $solicitud_id ID de la solicitud relacionada
     * @return bool
     */
    public static function send_cotizacion_accepted_notification(int $cotizacion_id, int $solicitud_id): bool {
        $user_id = get_post_field('post_author', $solicitud_id);
        $user = get_userdata($user_id);
        
        if (!$user) {
            error_log("[RFQ-ERROR] Usuario no encontrado para cotizacion_accepted (solicitud {$solicitud_id})");
            return false;
        }
        
        $to = apply_filters('rfq_user_notification_recipient_cotizacion_accepted', $user->user_email, $cotizacion_id, $solicitud_id, $user);
        if (empty($to)) {
            error_log("[RFQ-ERROR] No se envió notificación: Destinatario vacío para cotizacion_accepted {$cotizacion_id}");
            return false;
        }
        
        $notification_manager = NotificationManager::getInstance();
        $subject_template = $notification_manager->getCurrentSubject('user', 'cotizacion_accepted');
        $content_template = $notification_manager->getCurrentTemplate('user', 'cotizacion_accepted');
        
        $porcentaje_ahorro = 0;
        $total_cotizacion = get_post_meta($cotizacion_id, '_total', true);
        $items_solicitud_raw = get_post_meta($solicitud_id, '_solicitud_items', true);
        $items_solicitud = is_string($items_solicitud_raw) ? json_decode($items_solicitud_raw, true) : $items_solicitud_raw;
        $gran_total_solicitud = 0;
        if (is_array($items_solicitud)) {
            foreach ($items_solicitud as $item) {
                $gran_total_solicitud += floatval($item['subtotal'] ?? 0);
            }
        }
        if ($gran_total_solicitud > 0 && $total_cotizacion > 0) {
            $porcentaje_ahorro = (($gran_total_solicitud - floatval($total_cotizacion)) / $gran_total_solicitud) * 100;
        }
        
        // Resolver first_name y last_name
        $names = NotificationManager::resolve_user_names($user_id);
        
        $template_args = [
            'solicitud_id' => $solicitud_id,
            'cotizacion_id' => $cotizacion_id,
            'user_id' => $user_id,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'first_name' => $names['first_name'] ?: '',
            'last_name' => $names['last_name'] ?: '',
            'nombre' => $user->display_name,
            'productos' => self::format_items_for_email($items_solicitud ?? []),
            'porcentaje_ahorro' => $porcentaje_ahorro,
            // El proveedor se añade en prepareCommonData si cotizacion_id está presente
        ];
        
        // Usar TemplateRenderer para generar HTML con pie legal
        $legal_footer = get_option('rfq_email_legal_footer', '');
        $legal_footer = wp_kses_post($legal_footer);
        $message = TemplateRenderer::render_html(
            $content_template, 
            $template_args, 
            $legal_footer,
            ['notification_type' => 'cotizacion_accepted', 'user_id' => $user_id]
        );
        
        $headers = EmailManager::build_headers();
        $result = wp_mail($to, TemplateParser::render($subject_template, $template_args), $message, $headers);
        
        self::log_result($result, 'cotizacion_accepted', $user, $cotizacion_id);
        return $result;
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
            'rfq-manager/emails/user/' . $template_name,
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
            'rfq-manager/emails/user/' . $template_name,
        ]);
        
        if ($theme_template) {
            include $theme_template;
            error_log("[RFQ-FLOW] Usando template del tema: {$theme_template}");
        } else {
            // Buscar en el directorio del plugin
            $plugin_template = RFQ_MANAGER_WOO_PLUGIN_DIR . self::$template_dir . '/' . $template_name;
            
            if (file_exists($plugin_template)) {
                include $plugin_template;
                error_log("[RFQ-FLOW] Usando template del plugin: {$plugin_template}");
            } else {
                // Usar template genérico si no existe uno específico
                error_log("[RFQ-FLOW] Template no encontrado, usando contenido genérico para: {$template_name}");
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
        if (isset($solicitud_id) && isset($new_status) && isset($old_status)) {
            // Notificación de cambio de estado
            $solicitud_title = get_the_title($solicitud_id);
            $content .= '<p>' . sprintf(__('Hola %s,', 'rfq-manager-woocommerce'), esc_html($user->display_name)) . '</p>';
            $content .= '<p>' . sprintf(__('El estado de tu solicitud "%s" ha cambiado de "%s" a "%s".', 'rfq-manager-woocommerce'), 
                esc_html($solicitud_title), esc_html($old_status), esc_html($new_status)) . '</p>';
        } elseif (isset($cotizacion_id) && isset($solicitud_id)) {
            // Notificación de cotización recibida
            $solicitud_title = get_the_title($solicitud_id);
            $content .= '<p>' . sprintf(__('Hola %s,', 'rfq-manager-woocommerce'), esc_html($user->display_name)) . '</p>';
            $content .= '<p>' . sprintf(__('Has recibido una nueva cotización para tu solicitud "%s".', 'rfq-manager-woocommerce'), 
                esc_html($solicitud_title)) . '</p>';
        } elseif (isset($solicitud_id) && isset($data)) {
            // Notificación de solicitud creada
            $solicitud_title = get_the_title($solicitud_id);
            $content .= '<p>' . sprintf(__('Hola %s,', 'rfq-manager-woocommerce'), esc_html($user->display_name)) . '</p>';
            $content .= '<p>' . sprintf(__('Tu solicitud de cotización "%s" ha sido recibida y está siendo procesada.', 'rfq-manager-woocommerce'), 
                esc_html($solicitud_title)) . '</p>';
        } else {
            // Template genérico para cualquier otra situación
            $content .= '<p>' . sprintf(__('Hola %s,', 'rfq-manager-woocommerce'), esc_html($user->display_name)) . '</p>';
            $content .= '<p>' . __('Gracias por usar nuestro sistema de solicitudes de cotización.', 'rfq-manager-woocommerce') . '</p>';
        }
        
        $content .= '
                </div>
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5; text-align: center; color: #8a8a8a; font-size: 12px;">
                    <p>' . sprintf(__('Este email fue enviado desde %s (%s)', 'rfq-manager-woocommerce'), esc_html($site_name), esc_url($site_url)) . '</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $content;
    }
    
    private static function log_result(bool $result, string $notification_type, \WP_User $user, int $post_id): void {
        $context = [
            'notification_type' => $notification_type,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'user_id' => $user->ID,
            'post_id' => $post_id
        ];
        
        if (!$result) {
            RfqLogger::email("Error enviando notificación {$notification_type} a usuario {$user->display_name}", RfqLogger::LEVEL_ERROR, $context);
        } else {
            RfqLogger::email("Notificación {$notification_type} enviada exitosamente a usuario {$user->display_name}", RfqLogger::LEVEL_SUCCESS, $context);
        }
    }
}
