<?php
/**
 * Factory para la generación de plantillas de notificación
 *
 * @package    GiVendor\GiPlugin\Email\Templates
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Email\Templates;

use GiVendor\GiPlugin\Email\Templates\TemplateParser;

class NotificationTemplateFactory {
    /**
     * Directorio base para las plantillas
     * 
     * @since  0.1.0
     * @access protected
     * @var    string
     */
    protected static $base_dir = '';
    
    /**
     * Genera una plantilla HTML para una notificación
     */
    public static function create(string $type, string $subject, string $content, array $args = []): string {
        $data = self::prepareCommonData($type, $args);
        $rendered_subject = TemplateParser::render($subject, $data);
        $rendered_content = TemplateParser::render($content, $data);
        
        $template = self::get_full_html_wrapper_start($rendered_subject, $data);
        $template .= self::get_header($type, $rendered_subject, $data);
        $template .= self::get_content($rendered_content, $data);
        $template .= self::get_footer($type, $data);
        $template .= self::get_full_html_wrapper_end();
        
        return $template;
    }
    
    /**
     * Prepara los datos comunes para todas las plantillas
     */
    protected static function prepareCommonData(string $type, array $args = []): array {
        $data = [
            'site_name' => get_bloginfo('name'),
            'site_url' => get_bloginfo('url'),
            'admin_email' => get_option('admin_email'),
            'fecha' => date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
            'current_year' => date('Y'),
            'user_dashboard_link' => wc_get_page_permalink('myaccount'),
            'supplier_dashboard_link' => TemplateParser::generateSupplierDashboardLink(), // Link general al dashboard de proveedor
            'admin_dashboard_link' => admin_url('admin.php?page=rfq-manager'), // Link general al dashboard de admin de RFQ
            'new_request_link' => home_url('/nueva-solicitud-rfq/'), 
        ];

        if ($type === 'user') {
            $data['user_name'] = $args['user_name'] ?? ($args['nombre'] ?? '');
            $data['user_email'] = $args['user_email'] ?? '';
        }
        if ($type === 'supplier') {
            $data['supplier_name'] = $args['supplier_name'] ?? ($args['nombre'] ?? '');
            $data['supplier_email'] = $args['supplier_email'] ?? '';
        }
        if ($type === 'admin') {
            $data['admin_name'] = $args['admin_name'] ?? ($args['nombre'] ?? __('Administrador', 'rfq-manager-woocommerce'));
        }

        if (!empty($args['solicitud_id'])) {
            $solicitud_id = intval($args['solicitud_id']);
            $solicitud_post = get_post($solicitud_id);
            if ($solicitud_post) {
                $data['request_id'] = $solicitud_id;
                $data['request_uuid'] = get_post_meta($solicitud_id, '_solicitud_uuid', true);
                $data['request_title'] = get_the_title($solicitud_post);
                
                // Estado de la solicitud - asegurar que siempre tenga un valor para mostrar o indique si no está disponible
                $status_value = get_post_meta($solicitud_id, '_rfq_status', true);
                $data['request_status'] = !empty($status_value) ? esc_html($status_value) : __('N/D', 'rfq-manager-woocommerce');
                
                $data['request_description'] = get_post_meta($solicitud_id, '_solicitud_description', true);
                $solicitud_expiry_raw = get_post_meta($solicitud_id, '_solicitud_expiry', true);
                $data['request_expiry'] = $solicitud_expiry_raw ? date_i18n(get_option('date_format'), strtotime($solicitud_expiry_raw)) : __('No especificada', 'rfq-manager-woocommerce');
                $solicitud_slug = $solicitud_post->post_name;

                // Información del cliente (autor de la solicitud)
                $client_id = $solicitud_post->post_author;
                $client = get_userdata($client_id);
                if ($client) {
                    $data['customer_name'] = $client->display_name;
                    $data['customer_email'] = $client->user_email;
                    if ($type === 'admin') { // Para la plantilla de admin que usa {user_name} y {user_email} para el cliente
                        $data['user_name'] = $client->display_name;
                        $data['user_email'] = $client->user_email;
                    }
                    if ($type === 'supplier') { // Si la plantilla de proveedor usa {user_name} para el cliente
                        $data['user_name'] = $client->display_name;
                        // $data['user_email'] si fuera necesario para proveedor
                    }
                } else {
                    $data['customer_name'] = __('Cliente desconocido', 'rfq-manager-woocommerce');
                    $data['customer_email'] = '';
                    if ($type === 'admin') {
                        $data['user_name'] = __('Cliente desconocido', 'rfq-manager-woocommerce');
                        $data['user_email'] = '';
                    }
                    if ($type === 'supplier') {
                        $data['user_name'] = __('Cliente desconocido', 'rfq-manager-woocommerce');
                    }
                }

                // Contar cotizaciones para esta solicitud (para user y admin)
                if ($type === 'user' || $type === 'admin') {
                    $cotizaciones_query = new \WP_Query([
                        'post_type' => 'rfq_cotizacion', // Asegúrate que este es el CPT correcto
                        'post_status' => 'any',
                        'posts_per_page' => -1,
                        'meta_query' => [
                            [
                                'key' => '_rfq_solicitud_id', // Y esta la meta key correcta
                                'value' => $solicitud_id,
                            ]
                        ]
                    ]);
                    $data['quotes_count'] = $cotizaciones_query->found_posts;
                }

                switch ($type) {
                    case 'supplier':
                        $data['request_link'] = home_url('cotizar-solicitud/' . $solicitud_slug);
                        // Para el proveedor, el link de cotizar es el más relevante para una nueva solicitud
                        $data['quote_link'] = $data['request_link']; 
                        break;
                    case 'user':
                        $my_account_url = $data['user_dashboard_link'];
                        // Construir el enlace para ver la solicitud usando el UUID como pide el usuario
                        if (isset($data['request_uuid']) && !empty($data['request_uuid'])) {
                            $data['request_link'] = home_url('ver-solicitud/' . $data['request_uuid']);
                        } else {
                            // Fallback si no hay UUID, usar el slug (aunque el UUID es preferido)
                            $data['request_link'] = home_url('ver-solicitud/' . $solicitud_slug);
                        }
                        // Link al historial, si la página de Mi Cuenta está disponible
                        if ($my_account_url) {
                             $data['history_link'] = add_query_arg(['section' => 'rfq-requests'], $my_account_url);
                        } else {
                            $data['history_link'] = home_url('historial-solicitudes/'); // Fallback general
                        }
                        break;
                    case 'admin':
                        $data['request_link'] = admin_url('admin.php?page=rfq-manager&view=solicitud&id=' . $solicitud_id);
                        break;
                    default:
                        $data['request_link'] = home_url('ver-solicitud/' . $solicitud_slug);
                        break;
                }
            }
        }

        if (!empty($args['cotizacion_id'])) {
            $cotizacion_id = intval($args['cotizacion_id']);
            $cotizacion_post = get_post($cotizacion_id);
            if ($cotizacion_post) {
                $data['quote_id'] = $cotizacion_id;
                $data['quote_title'] = get_the_title($cotizacion_post);
                $quote_total_raw = get_post_meta($cotizacion_id, '_total', true);
                $data['quote_amount'] = wc_price($quote_total_raw);
                $data['total'] = $data['quote_amount']; // Alias para {total} usado en algunas plantillas de proveedor
                $data['quote_date'] = get_the_date(get_option('date_format'), $cotizacion_post);

                switch ($type) {
                    case 'supplier':
                        $data['quote_link'] = TemplateParser::generateSupplierDashboardLink('cotizaciones', ['action' => 'view', 'id' => $cotizacion_id]);
                        break;
                    case 'user':
                        // El link a la cotización para el usuario suele ser dentro de la vista de la solicitud
                        if(isset($data['request_link'])){
                            $data['quote_link'] = add_query_arg(['view_quote' => $cotizacion_id], $data['request_link']);
                        } else if(!empty($args['solicitud_id'])) {
                            // Si request_link no estaba seteado pero tenemos solicitud_id, intentamos generarlo
                            $solicitud_post_for_quote = get_post(intval($args['solicitud_id']));
                            if($solicitud_post_for_quote){
                                $user_req_link = home_url('ver-solicitud/' . $solicitud_post_for_quote->post_name);
                                $data['quote_link'] = add_query_arg(['view_quote' => $cotizacion_id], $user_req_link);
                            }
                        } else {
                             $data['quote_link'] = '#'; // Fallback
                        }
                        break;
                    case 'admin':
                        $data['quote_link'] = admin_url('admin.php?page=rfq-manager&view=cotizacion&id=' . $cotizacion_id);
                        break;
                    default:
                        $data['quote_link'] = '#';
                        break;
                }

                if (($type === 'supplier' || $type === 'admin') && !empty($args['solicitud_id'])) {
                    $client_id = get_post_field('post_author', intval($args['solicitud_id']));
                    $client = get_userdata($client_id);
                    if($client){
                        $data['customer_name'] = $client->display_name;
                        $data['customer_email'] = $client->user_email;
                        // Para plantillas que usan {user_name} para el cliente cuando el destinatario es proveedor
                        if ($type === 'supplier') $data['user_name'] = $client->display_name; 
                    }
                }
                if (($type === 'user' || $type === 'admin') && !empty($args['cotizacion_id'])) {
                    $supplier_id_for_quote = get_post_field('post_author', intval($args['cotizacion_id']));
                    $supplier_user = get_userdata($supplier_id_for_quote);
                    if($supplier_user){
                        $data['supplier_name'] = $supplier_user->display_name;
                        $data['supplier_email'] = $supplier_user->user_email;
                    }
                }
            }
        }
        
        if (isset($args['new_status'])) $data['status_new'] = $args['new_status'];
        if (isset($args['old_status'])) $data['status_old'] = $args['old_status'];
        if (isset($args['porcentaje_ahorro'])) $data['porcentaje_ahorro'] = $args['porcentaje_ahorro'] > 0 ? number_format_i18n(floatval($args['porcentaje_ahorro']), 2) . '%' : '';

        // Alias para productos (si las plantillas usan {items})
        if(isset($args['productos'])) $data['items'] = $args['productos'];
        if(isset($args['productos_cotizados'])) $data['items'] = $args['productos_cotizados'];

        return array_merge($data, $args);
    }

    protected static function get_base_styles(): string {
        return "
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; color: #333; -webkit-text-size-adjust: none; line-height: 1.6; }
        .rfq-email-container { width: 100%; max-width: 700px; margin: 20px auto; background-color: #ffffff; border: 1px solid #e0e0e0; padding: 20px; box-sizing: border-box; }
        .rfq-email-header { padding-bottom: 15px; margin-bottom: 20px; border-bottom: 1px solid #eeeeee; text-align: center; }
        .rfq-email-header h1 { color: #222222; margin: 0 0 5px 0; font-size: 24px; font-weight: bold; }
        .rfq-email-header .rfq-email-subtitle { color: #777777; margin: 0; font-size: 14px; }
        .rfq-email-content { font-size: 16px; }
        .rfq-email-content p { margin: 0 0 15px 0; }
        .rfq-email-content table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .rfq-email-content th, .rfq-email-content td { border: 1px solid #dddddd; padding: 10px; text-align: left; }
        .rfq-email-content th { background-color: #f8f8f8; font-weight: bold; }
        .rfq-email-content .button-container { text-align: center; margin: 25px 0; width: 200px; }
        .rfq-email-content a.button, a.button { background-color: #0073aa; color: white !important; padding: 12px 25px; text-decoration: none !important; border-radius: 5px; display: inline-block; font-size: 16px; font-weight: bold; border:0; cursor:pointer; }
        .rfq-email-content a.button:hover, a.button:hover { background-color: #005a87; }
        .rfq-email-footer { margin-top: 25px; padding-top: 15px; border-top: 1px solid #eeeeee; text-align: center; font-size: 12px; color: #888888; }
        .rfq-email-footer p { margin: 5px 0; }
        .rfq-details-box { background-color: #f9f9f9; border: 1px solid #e5e5e5; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .rfq-details-box h3 { margin-top: 0; margin-bottom: 10px; font-size: 18px; color: #333; }
        .rfq-highlight { color: #0073aa; font-weight: bold; }
        .rfq-alert.info { background-color: #e7f3fe; border-left: 4px solid #2196F3; padding: 15px; margin:15px 0; }
        .rfq-alert.warning { background-color: #fff7e6; border-left: 4px solid #ff9800; padding: 15px; margin:15px 0; }
        .rfq-alert.success { background-color: #e8f5e9; border-left: 4px solid #4CAF50; padding: 15px; margin:15px 0; }
        ";
    }

    protected static function get_full_html_wrapper_start(string $subject, array $data = []): string {
        $lang = get_bloginfo('language');
        return sprintf(
            '<!DOCTYPE html>
            <html lang="%s">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>%s</title>
                <style type="text/css">%s</style>
            </head>
            <body>
                <div class="rfq-email-container">',
            esc_attr(substr($lang, 0, 2)),
            esc_html($subject),
            self::get_base_styles()
        );
    }

    protected static function get_full_html_wrapper_end(): string {
        return '</div></body></html>';
    }
    
    /**
     * Genera el encabezado de la plantilla
     */
    protected static function get_header(string $type, string $subject, array $data = []): string {
        return sprintf(
            '<div class="rfq-email-header">
                <h1>%s</h1>
                <p class="rfq-email-subtitle">%s</p>
            </div>',
            esc_html($subject),
            esc_html(sprintf(__('Notificación de %s', 'rfq-manager-woocommerce'), $data['site_name'] ?? get_bloginfo('name')))
        );
    }
    
    /**
     * Genera el contenido principal de la plantilla
     */
    protected static function get_content(string $content, array $data = []): string {
        return sprintf(
            '<div class="rfq-email-content">%s</div>',
            $content
        );
    }
    
    /**
     * Genera el pie de página de la plantilla
     */
    protected static function get_footer(string $type, array $data = []): string {
        $footer_text_default = sprintf(
            __('Este es un correo automático de %s.', 'rfq-manager-woocommerce'),
            $data['site_name'] ?? get_bloginfo('name')
        );
        $footer_text = $data['footer_text'] ?? $footer_text_default;
        
        return sprintf(
            '<div class="rfq-email-footer">
                <p>%s</p>
                <p>%s</p>
                <p>&copy; %s %s. %s</p>
            </div>',
            esc_html($footer_text),
            sprintf(__('Si tienes alguna pregunta, por favor contacta a <a href="mailto:%s">%s</a>.', 'rfq-manager-woocommerce'), esc_attr($data['admin_email'] ?? get_option('admin_email')), esc_html($data['admin_email'] ?? get_option('admin_email'))),
            esc_html($data['current_year'] ?? date('Y')),
            esc_html($data['site_name'] ?? get_bloginfo('name')),
            __('Todos los derechos reservados.', 'rfq-manager-woocommerce')
        );
    }

    /**
     * Genera un botón de acción para la plantilla
     */
    public static function generateActionButton(string $url, string $text, string $color = '#0073aa'): string {
        return sprintf(
            '<p class="button-container" style="text-align: center; margin: 25px 0; width: 200px;">
                <a href="%s" class="button" style="background-color: %s; color: white !important; padding: 12px 25px; text-decoration: none !important; border-radius: 5px; display: inline-block; font-size: 16px; font-weight: bold;">
                    %s
                </a>
            </p>',
            esc_url($url),
            esc_attr($color),
            esc_html($text)
        );
    }

    // Fallback/original template methods - deben ser revisados para usar los placeholders correctos
    // y pasar los datos necesarios a self::create()

    public static function createSolicitudCreatedTemplate(string $type, int $solicitud_id, $recipient, array $data = []): string {
        $event = 'solicitud_created';
        $notification_manager = \GiVendor\GiPlugin\Email\Notifications\Custom\NotificationManager::getInstance();
        $subject = $notification_manager->getCurrentSubject($type, $event);
        $content = $notification_manager->getCurrentTemplate($type, $event);

        $args = array_merge($data, [
            'solicitud_id' => $solicitud_id,
            'nombre' => $recipient->display_name,
        ]);
        if ($type === 'user') $args['user_name'] = $recipient->display_name;
        if ($type === 'supplier') $args['supplier_name'] = $recipient->display_name;
        if ($type === 'admin') $args['admin_name'] = $recipient->display_name; // O un nombre admin específico

        return self::create($type, $subject, $content, $args);
    }

    public static function createCotizacionTemplate(string $type, int $cotizacion_id, int $solicitud_id, $recipient): string {
        $event = ($type === 'user') ? 'cotizacion_received' : 'cotizacion_submitted'; 
        if ($type === 'admin') $event = 'cotizacion_submitted';

        $notification_manager = \GiVendor\GiPlugin\Email\Notifications\Custom\NotificationManager::getInstance();
        $subject = $notification_manager->getCurrentSubject($type, $event);
        $content = $notification_manager->getCurrentTemplate($type, $event);
        
        $args = [
            'solicitud_id' => $solicitud_id,
            'cotizacion_id' => $cotizacion_id,
            'nombre' => $recipient->display_name,
        ];
        if ($type === 'user') $args['user_name'] = $recipient->display_name;
        if ($type === 'supplier') $args['supplier_name'] = $recipient->display_name;
        if ($type === 'admin') $args['admin_name'] = $recipient->display_name;

        return self::create($type, $subject, $content, $args);
    }

    public static function createCotizacionAceptadaTemplate(string $type, int $cotizacion_id, int $solicitud_id, $recipient): string {
        $event = 'cotizacion_accepted';
        $notification_manager = \GiVendor\GiPlugin\Email\Notifications\Custom\NotificationManager::getInstance();
        $subject = $notification_manager->getCurrentSubject($type, $event);
        $content = $notification_manager->getCurrentTemplate($type, $event);

        $args = [
            'solicitud_id' => $solicitud_id,
            'cotizacion_id' => $cotizacion_id,
            'nombre' => $recipient->display_name,
        ];
        if ($type === 'user') $args['user_name'] = $recipient->display_name;
        if ($type === 'supplier') $args['supplier_name'] = $recipient->display_name;
        if ($type === 'admin') $args['admin_name'] = $recipient->display_name;

        return self::create($type, $subject, $content, $args);
    }

    public static function createStatusChangedTemplate(int $solicitud_id, $recipient, string $new_status, string $old_status, float $porcentaje_ahorro = 0): string {
        $event = 'status_changed'; 
        $notification_manager = \GiVendor\GiPlugin\Email\Notifications\Custom\NotificationManager::getInstance();
        
        $subject_template_key = 'status_changed_' . $new_status;
        $subject = $notification_manager->getCurrentSubject('user', $subject_template_key, false);
        $content = $notification_manager->getCurrentTemplate('user', $event); 

        $args = [
            'solicitud_id' => $solicitud_id,
            'user_name' => $recipient->display_name,
            'nombre' => $recipient->display_name, 
            'new_status' => $new_status,
            'old_status' => $old_status,
            'porcentaje_ahorro' => $porcentaje_ahorro,
        ];
        
        // Si no se encontró un subject específico para el nuevo estado, o el subject es genérico.
        if (!$subject || strpos($subject, '{') !== false ) { 
            $base_subject = '';
            switch($new_status){
                case 'activa': $base_subject = __('Tu solicitud {request_title} ha recibido cotizaciones', 'rfq-manager-woocommerce'); break;
                case 'aceptada': $base_subject = __('Has aceptado una cotización para {request_title}', 'rfq-manager-woocommerce'); break;
                case 'pagada': $base_subject = __('El pago para tu solicitud {request_title} ha sido confirmado', 'rfq-manager-woocommerce'); break;
                case 'historica': $base_subject = __('Tu solicitud {request_title} se movió al historial', 'rfq-manager-woocommerce'); break;
                default: $base_subject = __('El estado de tu solicitud {request_title} es ahora: {new_status}', 'rfq-manager-woocommerce'); break;
            }
            $subject = $base_subject;
        }

        return self::create('user', $subject, $content, $args);
    }
}
