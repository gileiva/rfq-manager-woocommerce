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
        add_action('rfq_solicitud_cancelada', [__CLASS__, 'send_solicitud_cancelada_notification'], 10, 3);
        
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
            RfqLogger::warn('Usuario no encontrado para solicitud ' . $solicitud_id);
            return false;
        }

        // 1. Construir contexto para el pipeline consolidado
        $context = [
            'role' => 'user',
            'event' => 'solicitud_created',
            'solicitud_id' => $solicitud_id,
            'user_id' => $user_id,
            'recipient_user_id' => $user_id,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'productos' => self::format_items_for_email($data['items'] ?? []),
        ];
        
        // Mergear datos adicionales del evento
        $context = array_merge($context, $data);

        // 2. Resolver destinatario con filtro específico
        $to = apply_filters('rfq_user_notification_recipient_solicitud_created', $user->user_email, $solicitud_id, $user);
        if (empty($to)) {
            RfqLogger::warn('No se envió notificación: Destinatario vacío para solicitud_created ' . $solicitud_id);
            return false;
        }

        // 3. Preparar mensaje y enviar usando método central
        $result = NotificationManager::send_notification('user_solicitud_created', $context, $to);
        
        // 4. Log resultado
        if ($result) {
            RfqLogger::info('Notificación user solicitud_created enviada exitosamente', [
                'solicitud_id' => $solicitud_id,
                'user_id' => $user_id
            ]);
        } else {
            RfqLogger::warn('Error enviando notificación user solicitud_created', [
                'solicitud_id' => $solicitud_id,
                'user_id' => $user_id
            ]);
        }

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
            RfqLogger::warn('Usuario no encontrado para cotizacion_received (solicitud ' . $solicitud_id . ')');
            return false;
        }

        // 1. Construir contexto para el pipeline consolidado
        $solicitud_items = get_post_meta($solicitud_id, '_solicitud_items', true);
        if (is_string($solicitud_items)) $solicitud_items = json_decode($solicitud_items, true);
        
        $context = [
            'role' => 'user',
            'event' => 'cotizacion_received',
            'solicitud_id' => $solicitud_id,
            'cotizacion_id' => $cotizacion_id,
            'user_id' => $user_id,
            'recipient_user_id' => $user_id,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'productos' => self::format_items_for_email($solicitud_items ?? []),
        ];

        // 2. Resolver destinatario
        $to = apply_filters('rfq_user_notification_recipient_cotizacion_received', $user->user_email, $cotizacion_id, $solicitud_id, $user);
        if (empty($to)) {
            RfqLogger::warn('No se envió notificación: Destinatario vacío para cotizacion_received ' . $cotizacion_id);
            return false;
        }

        // 3. Preparar mensaje y enviar usando método central
        $result = NotificationManager::send_notification('user_cotizacion_submitted', $context, $to);
        
        // 4. Log resultado
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

        // Solo notificar cambios de estado a 'historica' (equivalente a post_status 'rfq-historic')
        if ($new_status !== 'historica') {
            error_log("[RFQ-FLOW] No se notificará cambio de estado a: {$new_status}. Solo se notifica cambio a 'historica'");
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
        
        // Calcular porcentaje ahorro para estados aceptada/pagada
        $porcentaje_ahorro = 0;
        if ($new_status === 'aceptada' || $new_status === 'pagada') {
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

        // Construir contexto para el pipeline
        $context = [
            'role' => 'user',
            'event' => 'status_changed',
            'solicitud_id' => $solicitud_id,
            'request_id' => $solicitud_id,
            'request_title' => get_the_title($solicitud_id),
            'request_link' => get_permalink($solicitud_id) ?: admin_url("post.php?post={$solicitud_id}&action=edit"),
            'site_name' => get_bloginfo('name'),
            'user_id' => $user_id,
            'recipient_user_id' => $user_id,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'new_status' => $new_status, 
            'old_status' => $old_status,
            'porcentaje_ahorro' => $porcentaje_ahorro,
        ];
        
        // Usar el pipeline consolidado
        $result = NotificationManager::send_notification('user_status_changed', $context, $to);
        
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
        
        // Calcular porcentaje ahorro
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
        
        // Construir contexto para el pipeline
        $context = [
            'role' => 'user',
            'event' => 'cotizacion_accepted',
            'solicitud_id' => $solicitud_id,
            'cotizacion_id' => $cotizacion_id,
            'user_id' => $user_id,
            'recipient_user_id' => $user_id,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'productos' => self::format_items_for_email($items_solicitud ?? []),
            'porcentaje_ahorro' => $porcentaje_ahorro,
        ];
        
        // Usar el pipeline consolidado
        $result = NotificationManager::send_notification('user_cotizacion_accepted', $context, $to);
        
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
    
    /**
     * Envía notificación al usuario cuando cancela una solicitud
     *
     * @since  0.1.0
     * @param  int $solicitud_id ID de la solicitud cancelada
     * @param  int $user_id ID del usuario que canceló
     * @param  string $cancel_reason Motivo de la cancelación (opcional)
     * @return bool
     */
    public static function send_solicitud_cancelada_notification(int $solicitud_id, int $user_id, string $cancel_reason = ''): bool {
        $user = get_userdata($user_id);
        
        if (!$user) {
            error_log("[RFQ-ERROR] Usuario no encontrado para solicitud_cancelada {$solicitud_id}");
            return false;
        }
        
        // 1. Construir contexto para el pipeline
        $context = [
            'role' => 'user',
            'event' => 'solicitud_cancelada',
            'solicitud_id' => $solicitud_id,
            'request_id' => $solicitud_id,
            'request_title' => get_the_title($solicitud_id),
            'request_link' => get_permalink($solicitud_id) ?: admin_url("post.php?post={$solicitud_id}&action=edit"),
            'site_name' => get_bloginfo('name'),
            'user_id' => $user_id,
            'recipient_user_id' => $user_id,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
        ];
        
        // 2. Resolver destinatario
        $to = apply_filters('rfq_user_notification_recipient_solicitud_cancelada', $user->user_email, $solicitud_id, $user_id, $user);
        if (empty($to)) {
            error_log("[RFQ-ERROR] No se envió notificación: Destinatario vacío para solicitud_cancelada {$solicitud_id}");
            return false;
        }
        
        // 3. Usar el pipeline consolidado
        $result = NotificationManager::send_notification('user_solicitud_cancelada', $context, $to);
        
        // 4. Log resultado
        self::log_result($result, 'solicitud_cancelada', $user, $solicitud_id);
        return $result;
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
