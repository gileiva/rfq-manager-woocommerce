<?php
namespace GiVendor\GiPlugin\Notifications;

class WhatsAppPhone {
    
    /**
     * Normaliza un teléfono al formato E.164
     * 
     * @param string|null $raw_phone El número de teléfono en cualquier formato
     * @return string|null El número normalizado en formato E.164, o null si no es válido
     */
    public static function normalize(?string $raw_phone): ?string {
        if (empty($raw_phone)) {
            return null;
        }
        
        // Remover todo excepto dígitos y +
        $clean = preg_replace('/[^\d+]/', '', trim($raw_phone));
        
        if (empty($clean)) {
            return null;
        }
        
        // Asegurar que empiece con +
        if (!str_starts_with($clean, '+')) {
            $clean = '+' . $clean;
        }
        
        // Validar longitud E.164 (8-15 dígitos después del +)
        $digits_only = substr($clean, 1); // Quitar el +
        $length = strlen($digits_only);
        
        if ($length < 8 || $length > 15) {
            return null;
        }
        
        // Validar que solo contenga dígitos después del +
        if (!ctype_digit($digits_only)) {
            return null;
        }
        
        return $clean;
    }
    
    /**
     * Verifica si un número de teléfono es válido en formato E.164
     * 
     * @param string|null $phone El número a validar
     * @return bool True si es válido, false si no
     */
    public static function is_valid(?string $phone): bool {
        return self::normalize($phone) !== null;
    }
    
    /**
     * Obtiene el número de WhatsApp normalizado para un usuario
     * 
     * @param int $user_id ID del usuario
     * @return string|null Número normalizado o null si no válido/disponible
     */
    public static function get_user_phone(int $user_id): ?string {
        // Permitir override via filtro
        $override_phone = apply_filters('rfq_whatsapp_phone_for_user', null, $user_id);
        if (!empty($override_phone)) {
            return self::normalize($override_phone);
        }
        
        // Buscar en el meta del plugin gi-user-register
        $raw_phone = get_user_meta($user_id, 'gireg_phone', true);
        
        return self::normalize($raw_phone);
    }
}
