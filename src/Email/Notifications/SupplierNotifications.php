<?php
/**
 * Gestión de notificaciones para proveedores
 *
 * @package    GiVendor\GiPlugin\Email\Notifications
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Email\Notifications;

use GiVendor\GiPlugin\Email\Templates\NotificationTemplateFactory;
use GiVendor\GiPlugin\Email\Templates\TemplateRenderer;
use GiVendor\GiPlugin\Email\Notifications\Custom\NotificationManager;
use GiVendor\GiPlugin\Email\Templates\TemplateParser;
use GiVendor\GiPlugin\Utils\RfqLogger;
use GiVendor\GiPlugin\Email\EmailManager;

/**
 * SupplierNotifications - Gestiona las notificaciones por email para proveedores
 *
 * Esta clase es responsable de enviar notificaciones por email a los proveedores
 * cuando se crean nuevas solicitudes o cuando hay cambios relevantes.
 *
 * @package    GiVendor\GiPlugin\Email\Notifications
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class SupplierNotifications {
    
    /**
     * Templates específicos para proveedores
     *
     * @since  0.1.0
     * @access protected
     * @var    string
     */
    protected static $template_dir = 'src/Email/Templates/Supplier';
    
    /**
     * Inicializa la clase y registra los hooks necesarios
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        // Registrar hooks para enviar notificaciones en diferentes eventos
        add_action('rfq_solicitud_created', [__CLASS__, 'send_solicitud_created_notification_to_suppliers'], 10, 2);
        add_action('rfq_cotizacion_accepted', [__CLASS__, 'send_cotizacion_accepted_notification'], 10, 2);
        add_action('rfq_cotizacion_submitted', [__CLASS__, 'send_cotizacion_submitted_notification'], 10, 2);
        
        // Log de inicialización
        add_action('init', function() {
            // error_log('RFQ Manager - SupplierNotifications inicializado');
        }, 999);
    }
    
    /**
     * Envía notificación a proveedores cuando se crea una solicitud
     *
     * @since  0.1.0
     * @param  int $solicitud_id ID de la solicitud creada
     * @param  array $data Datos adicionales
     * @return bool
     */
    public static function send_solicitud_created_notification_to_suppliers(int $solicitud_id, array $data): bool {
        $success_all = true;
        $suppliers = self::get_suppliers_for_notification($solicitud_id);
    
        if (empty($suppliers)) {
            RfqLogger::info("No hay proveedores activos para notificar solicitud {$solicitud_id}");
            return true;
        }
    
        // Datos comunes para el contexto
        $raw_items = get_post_meta($solicitud_id, '_solicitud_items', true);
        $items = is_string($raw_items) ? json_decode($raw_items, true) : $raw_items;
        
        // Partir en batches de 20
        $batches = array_chunk($suppliers, 20);
    
        foreach ($batches as $batch) {
            // Construir lista de BCC
            $bcc_list = [];
            foreach ($batch as $supplier) {
                if (! ($supplier instanceof \WP_User)) {
                    continue;
                }
                $email = apply_filters(
                    'rfq_supplier_notification_recipient_solicitud_created',
                    $supplier->user_email,
                    $solicitud_id,
                    $supplier,
                    $data
                );
                if (! empty($email)) {
                    $bcc_list[] = $email;
                }
            }

            if (empty($bcc_list)) {
                RfqLogger::warn("Ningún email válido en batch de solicitud {$solicitud_id}");
                continue;
            }

            // 1. Construir contexto para el pipeline consolidado
            $context = [
                'role' => 'supplier',
                'event' => 'solicitud_created',
                'solicitud_id' => $solicitud_id,
                'productos' => self::format_items_for_email($items ?? []),
                'extra_headers' => [
                    'Bcc' => implode(',', array_unique(array_filter(array_map('sanitize_email', $bcc_list))))
                ]
            ];
            
            // Mergear datos adicionales
            $context = array_merge($context, $data);

            // 2. Preparar mensaje con el pipeline consolidado
            $message = NotificationManager::prepare_message('supplier_solicitud_created', $context);

            // 3. Resolver destinatario genérico para el batch
            $to = apply_filters(
                'rfq_supplier_notification_batch_to',
                get_option('admin_email'),
                $solicitud_id,
                $batch,
                $data
            );

            // 4. Enviar usando el pipeline consolidado
            $result = EmailManager::send($to, $message['subject'], $message['html'], $message['text'], $message['headers']);

            // Log usando el nuevo sistema con contexto estructurado
            $context = [
                'batch_size' => count($bcc_list),
                'solicitud_id' => $solicitud_id,
                'recipients' => $bcc_list
            ];
            
            if ($result) {
                RfqLogger::email("solicitud_created BCC enviada a {$context['batch_size']} proveedores", RfqLogger::LEVEL_SUCCESS, $context);
            } else {
                RfqLogger::email("Error enviando solicitud_created BCC a {$context['batch_size']} proveedores", RfqLogger::LEVEL_ERROR, $context);
                $success_all = false;
            }
        }
    
        return $success_all;
    }
    
    
    /**
     * Envía notificación al proveedor cuando su cotización es aceptada
     *
     * @since  0.1.0
     * @param  int $cotizacion_id ID de la cotización aceptada
     * @param  int $solicitud_id ID de la solicitud relacionada
     * @return bool
     */
    public static function send_cotizacion_accepted_notification(int $cotizacion_id, int $solicitud_id): bool {
        $supplier_id = get_post_field('post_author', $cotizacion_id);
        $supplier = get_userdata($supplier_id);
        
        if (!$supplier) {
            error_log('[RFQ-ERROR] Proveedor no encontrado para cotizacion_accepted ' . $cotizacion_id);
            return false;
        }
        
        $to = apply_filters('rfq_supplier_notification_recipient_cotizacion_accepted', $supplier->user_email, $cotizacion_id, $solicitud_id, $supplier);
        if (empty($to)) {
            error_log('[RFQ-ERROR] No se envió notificación: Destinatario vacío para cotizacion_accepted ' . $cotizacion_id);
            return false;
        }
        
        $notification_manager = NotificationManager::getInstance();
        $subject_template = $notification_manager->getCurrentSubject('supplier', 'cotizacion_accepted');
        $content_template = $notification_manager->getCurrentTemplate('supplier', 'cotizacion_accepted');

        $precio_items_raw = get_post_meta($cotizacion_id, '_precio_items', true);
        $precio_items = is_string($precio_items_raw) ? json_decode($precio_items_raw, true) : $precio_items_raw;
        
        // Resolver first_name y last_name del proveedor
        $names = NotificationManager::resolve_user_names($supplier->ID);
        
        $template_args = [
            'solicitud_id' => $solicitud_id,
            'cotizacion_id' => $cotizacion_id,
            'supplier_id' => $supplier->ID,
            'supplier_name' => $supplier->display_name,
            'supplier_email' => $supplier->user_email,
            'first_name' => $names['first_name'] ?: '',
            'last_name' => $names['last_name'] ?: '',
            'nombre' => $supplier->display_name,
            'productos_cotizados' => self::format_quoted_items_for_email($precio_items ?? []),
        ];
        
        // Usar TemplateRenderer para generar HTML con pie legal
        $legal_footer = get_option('rfq_email_legal_footer', '');
        $legal_footer = wp_kses_post($legal_footer);
        $message = TemplateRenderer::render_html(
            $content_template, 
            $template_args, 
            $legal_footer,
            ['notification_type' => 'supplier_cotizacion_accepted', 'supplier_id' => $supplier->ID, 'cotizacion_id' => $cotizacion_id]
        );
        
        $headers = EmailManager::build_headers();
        $result = wp_mail($to, TemplateParser::render($subject_template, $template_args), $message, $headers);
        
        self::log_result($result, 'cotizacion_accepted', $supplier, $cotizacion_id);
        return $result;
    }
    
    /**
     * Envía notificación al proveedor cuando envía una cotización
     *
     * @since  0.1.0
     * @param  int $cotizacion_id ID de la cotización enviada
     * @param  int $solicitud_id ID de la solicitud relacionada
     * @return bool
     */
    public static function send_cotizacion_submitted_notification(int $cotizacion_id, int $solicitud_id): bool {
        $supplier_id = get_post_field('post_author', $cotizacion_id);
        $supplier = get_userdata($supplier_id);
        
        if (!$supplier) {
            error_log('[RFQ-ERROR] Proveedor no encontrado para cotizacion_submitted ' . $cotizacion_id);
            return false;
        }
        
        $to = apply_filters('rfq_supplier_notification_recipient_cotizacion_submitted', $supplier->user_email, $cotizacion_id, $solicitud_id, $supplier);
        if (empty($to)) {
            error_log('[RFQ-ERROR] No se envió notificación: Destinatario vacío para cotizacion_submitted ' . $cotizacion_id);
            return false;
        }
        
        $notification_manager = NotificationManager::getInstance();
        $subject_template = $notification_manager->getCurrentSubject('supplier', 'cotizacion_submitted');
        $content_template = $notification_manager->getCurrentTemplate('supplier', 'cotizacion_submitted');

        // Obtener y validar los items cotizados
        $precio_items_raw = get_post_meta($cotizacion_id, '_precio_items', true);
        error_log('[RFQ-DEBUG] send_cotizacion_submitted_notification: precio_items_raw = ' . print_r($precio_items_raw, true));
        
        $precio_items = [];
        if (is_string($precio_items_raw)) {
            $precio_items = json_decode($precio_items_raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('[RFQ-ERROR] Error al decodificar JSON de precio_items: ' . json_last_error_msg());
                $precio_items = [];
            }
        } elseif (is_array($precio_items_raw)) {
            $precio_items = $precio_items_raw;
        }

        // Si no hay items en _precio_items, intentar obtenerlos de _line_items
        if (empty($precio_items)) {
            $line_items_raw = get_post_meta($cotizacion_id, '_line_items', true);
            if (is_string($line_items_raw)) {
                $line_items = json_decode($line_items_raw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $precio_items = $line_items;
                }
            } elseif (is_array($line_items_raw)) {
                $precio_items = $line_items_raw;
            }
        }

        error_log('[RFQ-DEBUG] send_cotizacion_submitted_notification: precio_items procesados = ' . print_r($precio_items, true));

        // Resolver first_name y last_name del proveedor
        $names = NotificationManager::resolve_user_names($supplier->ID);

        $template_args = [
            'solicitud_id' => $solicitud_id,
            'cotizacion_id' => $cotizacion_id,
            'supplier_id' => $supplier->ID,
            'supplier_name' => $supplier->display_name,
            'supplier_email' => $supplier->user_email,
            'first_name' => $names['first_name'] ?: '',
            'last_name' => $names['last_name'] ?: '',
            'nombre' => $supplier->display_name,
            'productos_cotizados' => self::format_quoted_items_for_email($precio_items),
        ];
        
        // Usar TemplateRenderer para generar HTML con pie legal
        $legal_footer = get_option('rfq_email_legal_footer', '');
        $legal_footer = wp_kses_post($legal_footer);
        $message = TemplateRenderer::render_html(
            $content_template, 
            $template_args, 
            $legal_footer,
            ['notification_type' => 'supplier_cotizacion_submitted', 'supplier_id' => $supplier->ID, 'cotizacion_id' => $cotizacion_id]
        );
        
        $headers = EmailManager::build_headers();
        $result = wp_mail($to, TemplateParser::render($subject_template, $template_args), $message, $headers);
        
        self::log_result($result, 'cotizacion_submitted', $supplier, $cotizacion_id);
        return $result;
    }
    
    /**
     * Obtiene los proveedores asignados a una solicitud
     *
     * @since  0.1.0
     * @param  int $solicitud_id ID de la solicitud
     * @return array
     */
    protected static function get_suppliers_for_notification(int $solicitud_id = 0): array {
        $supplier_ids = [];
        // Lógica para obtener proveedores: asignados a la solicitud, o todos los activos, o por categoría, etc.
        // Ejemplo: obtener todos los proveedores con un rol específico y que estén activos/verificados.
        $args = [
            'role' => 'proveedor', // Asegúrate que 'proveedor' es el rol correcto
            // 'meta_query' => [ [ 'key' => 'account_status', 'value' => 'approved' ] ] // Ejemplo si tienes un estado
        ];
        $suppliers = get_users($args);
        
        return apply_filters('rfq_suppliers_for_notification', $suppliers, $solicitud_id);
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
            'rfq-manager/emails/supplier/' . $template_name,
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
            'rfq-manager/emails/supplier/' . $template_name,
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
        if (isset($cotizacion_id) && isset($solicitud_id)) {
            // Notificación de cotización aceptada
            $solicitud_title = get_the_title($solicitud_id);
            $content .= '<p>' . sprintf(__('Hola %s,', 'rfq-manager-woocommerce'), esc_html($supplier->display_name)) . '</p>';
            $content .= '<p>' . sprintf(__('Tu cotización para la solicitud "%s" ha sido aceptada.', 'rfq-manager-woocommerce'), 
                esc_html($solicitud_title)) . '</p>';
            $content .= '<p>' . __('Felicidades! El cliente se pondrá en contacto contigo para coordinar los detalles.', 'rfq-manager-woocommerce') . '</p>';
        } elseif (isset($solicitud_id) && isset($data)) {
            // Notificación de solicitud creada
            $solicitud_title = get_the_title($solicitud_id);
            $content .= '<p>' . sprintf(__('Hola %s,', 'rfq-manager-woocommerce'), esc_html($supplier->display_name)) . '</p>';
            $content .= '<p>' . sprintf(__('Hay una nueva solicitud de cotización "%s" disponible para que puedas ofertar.', 'rfq-manager-woocommerce'), 
                esc_html($solicitud_title)) . '</p>';
            $content .= '<p>' . __('Por favor, ingresa a tu panel de proveedor para ver los detalles y enviar tu cotización.', 'rfq-manager-woocommerce') . '</p>';
        } else {
            // Template genérico para cualquier otra situación
            $content .= '<p>' . sprintf(__('Hola %s,', 'rfq-manager-woocommerce'), esc_html($supplier->display_name)) . '</p>';
            $content .= '<p>' . __('Hay una actualización importante en el sistema de solicitudes de cotización.', 'rfq-manager-woocommerce') . '</p>';
            $content .= '<p>' . __('Por favor, ingresa a tu panel de proveedor para más detalles.', 'rfq-manager-woocommerce') . '</p>';
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

    private static function format_quoted_items_for_email($quoted_items): string {
        if (empty($quoted_items) || !is_array($quoted_items)) {
            error_log('[RFQ-DEBUG] format_quoted_items_for_email: Items vacíos o no es array');
            return '';
        }

        error_log('[RFQ-DEBUG] format_quoted_items_for_email: Estructura de items: ' . print_r($quoted_items, true));
        
        $list_items = [];
        foreach ($quoted_items as $product_id => $item) {
            if (!is_array($item)) {
                error_log('[RFQ-DEBUG] format_quoted_items_for_email: Item no es array para product_id ' . $product_id);
                continue;
            }

            // Intentar obtener el nombre del producto de diferentes formas
            $product_name = '';
            if (!empty($item['name'])) {
                $product_name = $item['name'];
            } elseif (!empty($item['product_name'])) {
                $product_name = $item['product_name'];
            } elseif (!empty($item['title'])) {
                $product_name = $item['title'];
            } else {
                // Si no hay nombre, intentar obtenerlo del producto de WooCommerce
                $product = wc_get_product($product_id);
                if ($product) {
                    $product_name = $product->get_name();
                }
            }

            if (empty($product_name)) {
                error_log('[RFQ-DEBUG] format_quoted_items_for_email: No se pudo obtener nombre para product_id ' . $product_id);
                continue;
            }

            // Intentar obtener la cantidad de diferentes formas
            $quantity = 0;
            if (isset($item['qty'])) {
                $quantity = intval($item['qty']);
            } elseif (isset($item['quantity'])) {
                $quantity = intval($item['quantity']);
            }

            // Intentar obtener el precio de diferentes formas
            $price = '';
            if (isset($item['precio'])) {
                $price = wc_price($item['precio']);
            } elseif (isset($item['price'])) {
                $price = wc_price($item['price']);
            } elseif (isset($item['line_total'])) {
                $price = wc_price($item['line_total']);
            }

            $line = esc_html($product_name);
            if ($quantity > 0) {
                $line .= ' x ' . $quantity;
            }
            if ($price) {
                $line .= ' (' . $price . ' c/u)';
            }
            $list_items[] = $line;
        }

        if (empty($list_items)) {
            error_log('[RFQ-DEBUG] format_quoted_items_for_email: No se generaron items para la lista');
            return '';
        }

        return '<ul><li>' . implode('</li><li>', $list_items) . '</li></ul>';
    }

    private static function log_result(bool $result, string $notification_type, \WP_User $user, int $post_id): void {
        $context = [
            'notification_type' => $notification_type,
            'supplier_name' => $user->display_name,
            'supplier_email' => $user->user_email,
            'supplier_id' => $user->ID,
            'post_id' => $post_id
        ];
        
        if (!$result) {
            RfqLogger::email("Error enviando notificación {$notification_type} a proveedor {$user->display_name}", RfqLogger::LEVEL_ERROR, $context);
        } else {
            RfqLogger::email("Notificación {$notification_type} enviada exitosamente a proveedor {$user->display_name}", RfqLogger::LEVEL_SUCCESS, $context);
        }
    }
}
