<?php
namespace GiVendor\GiPlugin\Notifications;

use GiVendor\GiPlugin\Utils\RfqLogger;

class WhatsAppNotifier {
    private const TIMEOUT = 15;
    private const API_VERSION = 'v18.0';
    
    /**
     * Envía un mensaje de texto por WhatsApp
     * 
     * @param string $phone Número en formato E.164
     * @param string $text Texto del mensaje
     * @return bool True si se envió exitosamente, false si no
     */
    public static function send_message(string $phone, string $text): bool {
        // Validar configuración
        $api_key = get_option('rfq_whatsapp_api_key', '');
        $sender = get_option('rfq_whatsapp_sender', '');
        
        if (empty($api_key) || empty($sender)) {
            RfqLogger::warn('[whatsapp] Falta configuración API - api_key o sender vacío');
            return false;
        }
        
        // Validar inputs
        if (!WhatsAppPhone::is_valid($phone)) {
            RfqLogger::warn('[whatsapp] Teléfono inválido para envío', ['phone' => $phone]);
            return false;
        }
        
        if (empty(trim($text))) {
            RfqLogger::warn('[whatsapp] Texto vacío para envío');
            return false;
        }
        
        // Preparar endpoint
        $endpoint = apply_filters('rfq_whatsapp_endpoint', 
            'https://graph.facebook.com/' . self::API_VERSION . '/' . $sender . '/messages'
        );
        
        // Preparar payload
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => [
                'body' => $text
            ]
        ];
        
        // Preparar headers
        $headers = [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ];
        
        // Configurar request
        $timeout = apply_filters('rfq_whatsapp_timeout', self::TIMEOUT);
        $args = [
            'method' => 'POST',
            'headers' => $headers,
            'body' => wp_json_encode($payload),
            'timeout' => $timeout,
            'blocking' => true,
            'data_format' => 'body'
        ];
        
        // Rate limit hook
        do_action('rfq_whatsapp_rate_limit', $phone, $text);
        
        // Enviar request
        $response = wp_remote_request($endpoint, $args);
        
        // Manejar respuesta
        if (is_wp_error($response)) {
            RfqLogger::warn('[whatsapp] Error en request', [
                'phone' => $phone,
                'error' => $response->get_error_message()
            ]);
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($status_code >= 200 && $status_code < 300) {
            // Éxito
            $response_data = json_decode($response_body, true);
            $message_id = $response_data['messages'][0]['id'] ?? 'unknown';
            
            RfqLogger::info('[whatsapp] Mensaje enviado exitosamente', [
                'phone' => $phone,
                'message_id' => $message_id,
                'status' => $status_code
            ]);
            
            return true;
        } else {
            // Error HTTP
            $error_info = [
                'phone' => $phone,
                'status' => $status_code
            ];
            
            // Solo loguear un extracto del error en producción
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $error_info['response'] = $response_body;
            } else {
                // Extracto seguro del error
                $response_data = json_decode($response_body, true);
                if (isset($response_data['error']['message'])) {
                    $error_info['error_message'] = $response_data['error']['message'];
                }
            }
            
            RfqLogger::warn('[whatsapp] Error en envío', $error_info);
            return false;
        }
    }
    
    /**
     * Envía un mensaje usando plantilla oficial de WhatsApp Business (HSM)
     * Implementación futura para plantillas pre-aprobadas
     * 
     * @param string $phone Número en formato E.164
     * @param string $template Nombre de la plantilla
     * @param array $vars Variables para la plantilla
     * @param array $components Componentes de la plantilla
     * @param string $lang Idioma de la plantilla
     * @return bool True si se envió exitosamente, false si no
     */
    public static function send_template(
        string $phone, 
        string $template, 
        array $vars = [], 
        array $components = [], 
        string $lang = 'es'
    ): bool {
        // Stub para implementación futura
        RfqLogger::info('[whatsapp] Envío de plantilla no implementado aún', [
            'phone' => $phone,
            'template' => $template,
            'lang' => $lang
        ]);
        
        return false;
    }
    
    /**
     * Verifica si WhatsApp está habilitado y configurado
     * 
     * @return bool True si está listo para usar, false si no
     */
    public static function is_enabled(): bool {
        if (get_option('rfq_whatsapp_enabled') !== 'yes') {
            return false;
        }
        
        $api_key = get_option('rfq_whatsapp_api_key', '');
        $sender = get_option('rfq_whatsapp_sender', '');
        
        return !empty($api_key) && !empty($sender);
    }
}
