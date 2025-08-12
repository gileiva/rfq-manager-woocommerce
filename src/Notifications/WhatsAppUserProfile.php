<?php
namespace GiVendor\GiPlugin\Notifications;

/**
 * Integración de WhatsApp con perfiles de usuario
 * Extiende el perfil de usuario para normalizar teléfonos E.164
 */
class WhatsAppUserProfile {
    
    /**
     * Inicializa los hooks
     */
    public static function init(): void {
        // Hook adicional para normalizar teléfonos al guardar
        add_action('personal_options_update', [self::class, 'normalize_phone_on_save'], 20);
        add_action('edit_user_profile_update', [self::class, 'normalize_phone_on_save'], 20);
        
        // Mostrar información adicional de WhatsApp en el perfil
        add_action('show_user_profile', [self::class, 'show_whatsapp_status'], 25);
        add_action('edit_user_profile', [self::class, 'show_whatsapp_status'], 25);
    }
    
    /**
     * Normaliza el teléfono al formato E.164 después de guardarlo
     * 
     * @param int $user_id
     */
    public static function normalize_phone_on_save(int $user_id): void {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        
        // Obtener el teléfono guardado
        $raw_phone = get_user_meta($user_id, 'gireg_phone', true);
        
        if (!empty($raw_phone)) {
            $normalized_phone = WhatsAppPhone::normalize($raw_phone);
            
            if ($normalized_phone && $normalized_phone !== $raw_phone) {
                // Actualizar con el formato normalizado
                update_user_meta($user_id, 'gireg_phone', $normalized_phone);
                
                // Mostrar mensaje de normalización
                if (is_admin()) {
                    $message = sprintf(
                        __('Teléfono normalizado a formato E.164: %s', 'rfq-manager-woocommerce'),
                        $normalized_phone
                    );
                    add_action('admin_notices', function() use ($message) {
                        echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($message) . '</p></div>';
                    });
                }
            } elseif (!$normalized_phone) {
                // Teléfono inválido - mostrar advertencia
                if (is_admin()) {
                    $message = sprintf(
                        __('El teléfono ingresado no es válido para WhatsApp: %s. Por favor usa formato E.164 (+123456789).', 'rfq-manager-woocommerce'),
                        $raw_phone
                    );
                    add_action('admin_notices', function() use ($message) {
                        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html($message) . '</p></div>';
                    });
                }
            }
        }
    }
    
    /**
     * Muestra el estado de WhatsApp en el perfil de usuario
     * 
     * @param \WP_User $user
     */
    public static function show_whatsapp_status(\WP_User $user): void {
        $has_optin = get_user_meta($user->ID, 'whatsapp_notifications', true);
        $phone = get_user_meta($user->ID, 'gireg_phone', true);
        $phone_valid = WhatsAppPhone::is_valid($phone);
        $whatsapp_enabled = get_option('rfq_whatsapp_enabled') === 'yes';
        
        echo '<h2>' . esc_html__('Estado WhatsApp', 'rfq-manager-woocommerce') . '</h2>';
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th>' . esc_html__('Estado del Sistema', 'rfq-manager-woocommerce') . '</th>';
        echo '<td>';
        if ($whatsapp_enabled) {
            echo '<span style="color: green;">✅ ' . esc_html__('WhatsApp habilitado', 'rfq-manager-woocommerce') . '</span>';
        } else {
            echo '<span style="color: orange;">⚠️ ' . esc_html__('WhatsApp deshabilitado en configuración', 'rfq-manager-woocommerce') . '</span>';
        }
        echo '</td></tr>';
        
        echo '<tr>';
        echo '<th>' . esc_html__('Opt-in Usuario', 'rfq-manager-woocommerce') . '</th>';
        echo '<td>';
        if ($has_optin === '1') {
            echo '<span style="color: green;">✅ ' . esc_html__('Usuario acepta notificaciones WhatsApp', 'rfq-manager-woocommerce') . '</span>';
        } else {
            echo '<span style="color: red;">❌ ' . esc_html__('Usuario NO acepta notificaciones WhatsApp', 'rfq-manager-woocommerce') . '</span>';
        }
        echo '</td></tr>';
        
        echo '<tr>';
        echo '<th>' . esc_html__('Teléfono', 'rfq-manager-woocommerce') . '</th>';
        echo '<td>';
        if (empty($phone)) {
            echo '<span style="color: red;">❌ ' . esc_html__('Sin teléfono registrado', 'rfq-manager-woocommerce') . '</span>';
        } elseif ($phone_valid) {
            echo '<span style="color: green;">✅ ' . esc_html($phone) . ' ' . esc_html__('(formato válido E.164)', 'rfq-manager-woocommerce') . '</span>';
        } else {
            echo '<span style="color: red;">❌ ' . esc_html($phone) . ' ' . esc_html__('(formato inválido para WhatsApp)', 'rfq-manager-woocommerce') . '</span>';
        }
        echo '</td></tr>';
        
        echo '<tr>';
        echo '<th>' . esc_html__('Puede recibir WhatsApp', 'rfq-manager-woocommerce') . '</th>';
        echo '<td>';
        if ($whatsapp_enabled && $has_optin === '1' && $phone_valid) {
            echo '<span style="color: green; font-weight: bold;">✅ ' . esc_html__('SÍ - Recibirá notificaciones por WhatsApp', 'rfq-manager-woocommerce') . '</span>';
        } else {
            echo '<span style="color: red; font-weight: bold;">❌ ' . esc_html__('NO - Revisar requisitos arriba', 'rfq-manager-woocommerce') . '</span>';
        }
        echo '</td></tr>';
        
        echo '</table>';
    }
}
