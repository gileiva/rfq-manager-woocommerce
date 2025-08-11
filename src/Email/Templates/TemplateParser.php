<?php
/**
 * TemplateParser - Renderiza plantillas con placeholders
 *
 * @package    GiVendor\GiPlugin\Email\Templates
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Email\Templates;

class TemplateParser {
    /**
     * Renderiza una plantilla con los datos proporcionados
     *
     * @param string $template La plantilla a renderizar
     * @param array $data Los datos para reemplazar en la plantilla
     * @return string La plantilla renderizada
     */
    public static function render(string $template, array $data): string {
        // Asegurar que los datos básicos estén disponibles
        $data = array_merge([
            'site_name' => get_bloginfo('name'),
            'site_url' => get_bloginfo('url'),
            'admin_email' => get_option('admin_email'),
            'fecha' => date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
        ], $data);

        // Procesar condicionales
        $template = self::processConditionals($template, $data);

        // Reemplazar placeholders con datos
        $placeholders = [
            // Placeholders básicos
            '{site_name}' => (string)($data['site_name'] ?? ''),
            '{site_url}' => (string)($data['site_url'] ?? ''),
            '{admin_email}' => (string)($data['admin_email'] ?? ''),
            '{fecha}' => (string)($data['fecha'] ?? ''),
            
            // Placeholders de usuario
            '{user_name}' => (string)($data['nombre'] ?? $data['user_name'] ?? ''),
            '{supplier_name}' => (string)($data['nombre'] ?? $data['supplier_name'] ?? ''),
            '{admin_name}' => (string)($data['nombre'] ?? $data['admin_name'] ?? ''),
            '{customer_name}' => (string)($data['customer_name'] ?? ''),
            '{customer_email}' => (string)($data['customer_email'] ?? ''),
            '{first_name}' => (string)($data['first_name'] ?? ''),
            '{last_name}' => (string)($data['last_name'] ?? ''),
            '{customer_first_name}' => (string)($data['customer_first_name'] ?? $data['first_name'] ?? ''),
            '{customer_last_name}' => (string)($data['customer_last_name'] ?? $data['last_name'] ?? ''),
            '{supplier_first_name}' => (string)($data['supplier_first_name'] ?? ''),
            '{supplier_last_name}' => (string)($data['supplier_last_name'] ?? ''),
            
            // Placeholders de solicitud
            '{request_id}' => (string)($data['request_id'] ?? $data['solicitud_id'] ?? ''),
            '{request_title}' => (string)($data['request_title'] ?? $data['solicitud_title'] ?? ''),
            '{request_description}' => (string)($data['request_description'] ?? ''),
            '{request_expiry}' => (string)($data['request_expiry'] ?? ''),
            '{request_link}' => (string)($data['request_link'] ?? ''),
            '{request_details}' => self::normalize_for_template($data['request_details'] ?? $data['productos'] ?? ''),
            '{request_status}' => (string)($data['request_status'] ?? ''),
            '{productos}' => self::normalize_for_template($data['productos'] ?? $data['items'] ?? ''),
            '{productos_cotizados}' => self::normalize_for_template($data['productos_cotizados'] ?? $data['items'] ?? ''),
            
            // Placeholders de cotización
            '{quote_id}' => (string)($data['quote_id'] ?? $data['cotizacion_id'] ?? ''),
            '{quote_title}' => (string)($data['quote_title'] ?? ''),
            '{quote_amount}' => (string)($data['quote_amount'] ?? ''),
            '{quote_details}' => (string)($data['quote_details'] ?? ''),
            '{quote_link}' => (string)($data['quote_link'] ?? ''),
            
            // Placeholders de orden
            '{order_id}' => (string)($data['order_id'] ?? ''),
            '{order_date}' => (string)($data['order_date'] ?? ''),
            '{order_status}' => (string)($data['order_status'] ?? ''),
            
            // Placeholders de estado
            '{status_old}' => (string)($data['status_old'] ?? $data['estado_anterior'] ?? ''),
            '{status_new}' => (string)($data['status_new'] ?? $data['estado_nuevo'] ?? ''),
            '{porcentaje_ahorro}' => (string)($data['porcentaje_ahorro'] ?? ''),
        ];

        // Reemplazar placeholders
        $content = str_replace(
            array_keys($placeholders),
            array_values($placeholders),
            $template
        );

        // Debug temporal: verificar si quedan placeholders sin reemplazar
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (strpos($content, '{first_name}') !== false || strpos($content, '{last_name}') !== false) {
                RfqLogger::debug('[TemplateParser] Placeholders sin reemplazar detectados', [
                    'has_first_name_data' => isset($data['first_name']),
                    'has_last_name_data' => isset($data['last_name']),
                    'first_name_value' => $data['first_name'] ?? 'NOT_SET',
                    'last_name_value' => $data['last_name'] ?? 'NOT_SET',
                    'template_excerpt' => substr($template, 0, 100)
                ]);
            }
        }

        // Sanitizar el contenido
        $content = wp_kses_post($content);

        // Convertir saltos de línea a <br>
        $content = nl2br($content);

        return $content;
    }

    /**
     * Procesa las condiciones en la plantilla
     *
     * @param string $template La plantilla a procesar
     * @param array $data Los datos disponibles
     * @return string La plantilla procesada
     */
    private static function processConditionals(string $template, array $data): string {
        // Procesar condicionales {if variable} ... {/if}
        preg_match_all('/\{if\s+([^}]+)\}(.*?)\{\/if\}/s', $template, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $condition = trim($match[1]);
            $content = $match[2];
            
            // Verificar si la condición es verdadera
            $is_true = false;
            if (isset($data[$condition])) {
                $is_true = !empty($data[$condition]);
            }
            
            // Reemplazar el bloque condicional
            $template = str_replace(
                $match[0],
                $is_true ? $content : '',
                $template
            );
        }
        
        return $template;
    }

    /**
     * Genera un enlace para una solicitud
     *
     * @param int $solicitud_id ID de la solicitud
     * @param string $type Tipo de enlace (view, edit, etc)
     * @return string URL generada
     */
    public static function generateRequestLink(int $solicitud_id, string $type = 'view'): string {
        $solicitud_slug = get_post_field('post_name', $solicitud_id);
        
        switch ($type) {
            case 'view':
                return home_url('ver-solicitud/' . $solicitud_slug);
            case 'edit':
                return home_url('editar-solicitud/' . $solicitud_slug);
            case 'admin':
                return admin_url('admin.php?page=rfq-manager&view=solicitud&id=' . $solicitud_id);
            default:
                return home_url('ver-solicitud/' . $solicitud_slug);
        }
    }

    /**
     * Genera un enlace para una cotización
     *
     * @param int $cotizacion_id ID de la cotización
     * @param string $type Tipo de enlace (view, edit, etc)
     * @return string URL generada
     */
    public static function generateQuoteLink(int $cotizacion_id, string $type = 'view'): string {
        switch ($type) {
            case 'view':
                return home_url('ver-cotizacion/' . $cotizacion_id);
            case 'edit':
                return home_url('editar-cotizacion/' . $cotizacion_id);
            case 'admin':
                return admin_url('admin.php?page=rfq-manager&view=cotizacion&id=' . $cotizacion_id);
            case 'payment':
                return home_url('pagar-cotizacion/' . $cotizacion_id);
            default:
                return home_url('ver-cotizacion/' . $cotizacion_id);
        }
    }

    /**
     * Genera un enlace para el panel de proveedor
     *
     * @param string $section Sección específica del panel
     * @param array $args Argumentos adicionales
     * @return string URL generada
     */
    public static function generateSupplierDashboardLink(string $section = '', array $args = []): string {
        $base_url = home_url('proveedor-dashboard/');
        
        if (!empty($section)) {
            $args['section'] = $section;
        }
        
        return add_query_arg($args, $base_url);
    }

    /**
     * Valida una plantilla contra los placeholders requeridos
     *
     * @param string $template La plantilla a validar
     * @param array $required_placeholders Los placeholders requeridos
     * @return bool True si la plantilla es válida
     */
    public static function validate(string $template, array $required_placeholders): bool {
        foreach ($required_placeholders as $placeholder) {
            if (strpos($template, $placeholder) === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * Obtiene los placeholders disponibles en una plantilla
     *
     * @param string $template La plantilla a analizar
     * @return array Los placeholders encontrados
     */
    public static function getPlaceholders(string $template): array {
        preg_match_all('/\{([^}]+)\}/', $template, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Limpia una plantilla de placeholders no utilizados
     *
     * @param string $template La plantilla a limpiar
     * @param array $used_placeholders Los placeholders utilizados
     * @return string La plantilla limpia
     */
    public static function clean(string $template, array $used_placeholders): string {
        $all_placeholders = self::getPlaceholders($template);
        $unused_placeholders = array_diff($all_placeholders, $used_placeholders);

        foreach ($unused_placeholders as $placeholder) {
            $template = str_replace('{' . $placeholder . '}', '', $template);
        }

        return $template;
    }
    
    /**
     * Normaliza un valor para usar en plantillas (convierte arrays a string)
     *
     * @since  0.1.0
     * @param  mixed $value El valor a normalizar
     * @return string El valor como string
     */
    private static function normalize_for_template($value): string {
        if (is_array($value)) {
            // Si es un array de strings simples, unir con comas
            if (!empty($value) && array_values($value) === $value) {
                return implode(', ', array_map('strval', $value));
            }
            // Si es un array asociativo o complejo, puede que ya esté formateado como HTML
            return '';
        }
        
        return (string)$value;
    }
} 