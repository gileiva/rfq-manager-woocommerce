<?php
/**
 * Motor de render central para plantillas de email
 * 
 * Responsabilidad única: renderizar plantillas HTML con variables y pie legal,
 * y generar versiones de texto plano derivadas.
 *
 * @package    GiVendor\GiPlugin\Email\Templates
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Email\Templates;

use GiVendor\GiPlugin\Utils\RfqLogger;

/**
 * TemplateRenderer - Motor central de renderizado de plantillas
 *
 * @package    GiVendor\GiPlugin\Email\Templates
 * @since      0.1.0
 */
final class TemplateRenderer {
    
    /**
     * Renderiza una plantilla HTML con variables y pie legal
     *
     * @since  0.1.0
     * @param  string $template_html     Cuerpo HTML de la plantilla
     * @param  array  $variables         Array escalar de placeholders => valores 
     * @param  string $legal_footer_html HTML del pie legal
     * @param  array  $context           Info adicional para logs
     * @return string HTML final renderizado
     */
    public static function render_html(string $template_html, array $variables, string $legal_footer_html = '', array $context = []): string {
        // Logging de diagnóstico para placeholders
        if (defined('WP_DEBUG') && WP_DEBUG) {
            RfqLogger::debug('[TemplateRenderer] Variables antes de normalizar', [
                'keys' => array_keys($variables),
                'has_first_name' => array_key_exists('first_name', $variables),
                'has_last_name' => array_key_exists('last_name', $variables),
                'first_name_value' => $variables['first_name'] ?? 'NO_SET',
                'last_name_value' => $variables['last_name'] ?? 'NO_SET',
                'notification_type' => $context['notification_type'] ?? 'unknown'
            ]);
        }
        
        // Normalizar variables (convertir arrays a string si existen)
        $normalized_variables = self::normalize_variables($variables);
        
        // Usar el parser actual para reemplazar placeholders
        $rendered_content = TemplateParser::render($template_html, $normalized_variables);
        
        // Inyectar pie legal si existe
        $final_html = self::inject_legal_footer($rendered_content, $legal_footer_html);
        
        // Log mínimo para depuración
        RfqLogger::debug("Template renderizado", [
            'template_len' => strlen($template_html),
            'vars_count' => count($variables),
            'context' => $context
        ]);
        
        return $final_html;
    }
    
    /**
     * Genera versión de texto plano a partir del HTML renderizado
     *
     * @since  0.1.0
     * @param  string $html HTML renderizado
     * @return string Versión de texto plano
     */
    public static function render_text(string $html): string {
        // Convertir HTML a texto plano
        $text = wp_strip_all_tags($html, true);
        
        // Normalizar saltos de línea
        $text = preg_replace('/\r\n|\r|\n/', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text); // Máximo 2 saltos consecutivos
        
        return trim($text);
    }
    
    /**
     * Normaliza variables para el template (convierte arrays a string)
     *
     * @since  0.1.0
     * @param  array $variables Variables originales
     * @return array Variables normalizadas (solo escalares)
     */
    private static function normalize_variables(array $variables): array {
        $normalized = [];
        
        foreach ($variables as $key => $value) {
            if (is_array($value)) {
                // Si es array vacío
                if (empty($value)) {
                    $normalized[$key] = '';
                    continue;
                }
                
                // Si es array simple (valores indexados)
                if (array_values($value) === $value) {
                    $normalized[$key] = implode(', ', array_map('strval', $value));
                } else {
                    // Array asociativo - convertir a "key: value" formato
                    $pairs = [];
                    foreach ($value as $k => $v) {
                        if (is_scalar($v)) {
                            $pairs[] = $k . ': ' . $v;
                        } elseif (is_array($v)) {
                            $pairs[] = $k . ': ' . implode(', ', array_map('strval', $v));
                        } else {
                            $pairs[] = $k . ': ' . (string)$v;
                        }
                    }
                    $normalized[$key] = implode('; ', $pairs);
                }
            } elseif (is_object($value)) {
                // Si el objeto tiene __toString(), usarlo
                if (method_exists($value, '__toString')) {
                    $normalized[$key] = (string)$value;
                } else {
                    // Intentar extraer propiedades conocidas
                    if (isset($value->name)) {
                        $normalized[$key] = (string)$value->name;
                    } elseif (isset($value->title)) {
                        $normalized[$key] = (string)$value->title;
                    } else {
                        // Último recurso: JSON encode
                        $normalized[$key] = wp_json_encode($value);
                    }
                }
            } elseif (is_null($value)) {
                $normalized[$key] = '';
            } else {
                $normalized[$key] = (string)$value;
            }
        }
        
        // Guard final: verificar que no quedó ningún array/objeto
        foreach ($normalized as $k => $v) {
            if (!is_scalar($v)) {
                $normalized[$k] = is_array($v)
                    ? implode(', ', array_map('strval', $v))
                    : (method_exists($v, '__toString') ? (string)$v : wp_json_encode($v));
            }
        }
        
        return $normalized;
    }
    
    /**
     * Inyecta el pie legal al final del HTML
     *
     * @since  0.1.0
     * @param  string $html              HTML del contenido
     * @param  string $legal_footer_html Pie legal en HTML
     * @return string HTML con pie legal inyectado
     */
    private static function inject_legal_footer(string $html, string $legal_footer_html): string {
        if (empty($legal_footer_html)) {
            return $html;
        }
        
        // Sanitizar pie legal
        $clean_footer = wp_kses_post($legal_footer_html);
        
        if (empty($clean_footer)) {
            return $html;
        }
        
        // Buscar si ya existe un footer o estructura similar
        // Si el HTML tiene estructura completa con </body>, insertar antes
        if (strpos($html, '</body>') !== false) {
            $footer_section = "\n<hr style=\"margin: 30px 0; border: none; border-top: 1px solid #ddd;\">\n";
            $footer_section .= "<div style=\"font-size: 11px; color: #666; line-height: 1.4; margin-top: 20px;\">\n";
            $footer_section .= $clean_footer;
            $footer_section .= "\n</div>\n";
            
            return str_replace('</body>', $footer_section . '</body>', $html);
        }
        
        // Si no tiene estructura completa, agregar al final con separador
        $footer_section = "\n\n<hr style=\"margin: 30px 0; border: none; border-top: 1px solid #ddd;\">\n";
        $footer_section .= "<div style=\"font-size: 11px; color: #666; line-height: 1.4; margin-top: 20px;\">\n";
        $footer_section .= $clean_footer;
        $footer_section .= "\n</div>";
        
        return $html . $footer_section;
    }
    
    /**
     * Obtiene el pie legal desde las opciones
     *
     * @since  0.1.0
     * @return string Pie legal HTML
     */
    public static function get_legal_footer(): string {
        $footer = get_option('rfq_email_legal_footer', '');
        
        // Si no existe la opción, usar el texto por defecto
        if (empty($footer)) {
            $footer = self::get_default_legal_footer();
        }
        
        return $footer;
    }
    
    /**
     * Devuelve el texto legal por defecto
     *
     * @since  0.1.0
     * @return string Texto legal por defecto en HTML
     */
    private static function get_default_legal_footer(): string {
        return '<p><strong>Aviso legal:</strong></p>
<p>Este correo electrónico y cualquier fichero adjunto a él son confidenciales y han sido creados exclusivamente para el uso de la persona a la que están dirigidos. Por ello, se informa a quien lo reciba por error que la información contenida en el mismo es reservada y su uso no autorizado está prohibido legalmente, por lo que en tal caso le rogamos nos lo comunique por la misma vía y proceda a borrarlo de inmediato. Cualquier punto de vista u opinión expresados en él son propios de su autor y no representan necesariamente los del responsable de su tratamiento. La publicación, uso, diseminación, reenvío, impresión o copia no autorizados de este correo electrónico o cualquiera de los ficheros adjuntos al mismo, están estrictamente prohibidos.</p>
<p>Se comunica al destinatario de este correo que los datos personales en él contenidos son tratados por <strong>DENTAL MARKET SOLUTIONS, S.L.</strong> en concepto de Responsable del Tratamiento, para la prestación del servicio contratado por el cliente, para la gestión de cualquier petición, reclamación, queja o sugerencia que se realice ante el mismo así como para la gestión administrativa, contable y fiscal de la actividad desarrollada por el Responsable del Tratamiento, en base a la relación contractual –arrendamiento de servicios- mantenida entre el interesado y el Responsable del Tratamiento y que no serán cedidos a terceros salvo obligación legal o consentimiento expreso del interesado.</p>
<p>Para ejercitar los derechos de acceso, rectificación o supresión, limitación de su tratamiento, oposición al tratamiento así como el derecho a la portabilidad de los datos, deberán dirigir escrito al Responsable del Tratamiento, a la siguiente dirección postal: <strong>Avda. de los Boliches nº 87, 29640 Fuengirola (Málaga)</strong> o a la siguiente dirección de correo electrónico: <strong>protecciondedatos@thecleverdentist.com</strong></p>
<p>El escrito deberá estar firmado por el interesado o su representante legal y contener: nombre y apellidos del interesado; fotocopia de su documento nacional de identidad, o de su pasaporte u otro documento válido que lo identifique y, en su caso, de la persona que lo represente, o instrumentos electrónicos equivalentes; documento o instrumento electrónico acreditativo de tal representación. La utilización de firma electrónica identificativa del solicitante exime de la presentación de las fotocopias del DNI o documento equivalente. También puede presentar una reclamación ante la <strong>Agencia Española de Protección de Datos</strong>, a través de la página web oficial de la Agencia.</p>';
    }
}
