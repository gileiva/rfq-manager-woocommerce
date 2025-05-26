<?php
/**
 * TemplateManager - Clase para manejar las plantillas del plugin
 *
 * @package    GiVendor\GiPlugin\Templates
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Templates;

/**
 * TemplateManager - Clase que maneja la carga de plantillas y estilos
 *
 * @package    GiVendor\GiPlugin\Templates
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class TemplateManager {
    
    /**
     * Inicializa el manejador de plantillas
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        add_filter('single_template', [self::class, 'load_single_template'], 10);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_styles']);
        add_action('init', [self::class, 'add_rewrite_rules']);
        add_filter('query_vars', [self::class, 'add_query_vars']);
        add_action('template_redirect', [self::class, 'handle_payment_template']);
    }
    
    /**
     * Agrega las reglas de rewrite para URLs personalizadas
     *
     * @since  0.1.0
     * @return void
     */
    public static function add_rewrite_rules(): void {
        // Regla para la página de pago de cotizaciones
        add_rewrite_rule(
            '^pagar-cotizacion/([0-9]+)/?$',
            'index.php?rfq_payment_page=1&cotizacion_id=$matches[1]',
            'top'
        );
    }
    
    /**
     * Agrega variables de consulta personalizadas
     *
     * @since  0.1.0
     * @param  array $vars Variables existentes
     * @return array Variables modificadas
     */
    public static function add_query_vars(array $vars): array {
        $vars[] = 'rfq_payment_page';
        $vars[] = 'cotizacion_id';
        return $vars;
    }
    
    /**
     * Maneja la carga de la plantilla de pago
     *
     * @since  0.1.0
     * @return void
     */
    public static function handle_payment_template(): void {
        if (get_query_var('rfq_payment_page')) {
            $cotizacion_id = get_query_var('cotizacion_id');
            
            if ($cotizacion_id) {
                // Verificar si existe una plantilla en el tema
                $theme_template = get_stylesheet_directory() . '/rfq-payment-cotizacion.php';
                
                if (file_exists($theme_template)) {
                    include $theme_template;
                    exit;
                }
                
                // Si no existe en el tema, usar la del plugin
                $plugin_template = RFQ_MANAGER_WOO_PLUGIN_DIR . 'templates/payment-cotizacion.php';
                
                if (file_exists($plugin_template)) {
                    include $plugin_template;
                    exit;
                }
            }
            
            // Si no se encontró la plantilla o el ID, mostrar 404
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
        }
    }
    
    /**
     * Carga la plantilla single apropiada
     *
     * @since  0.1.0
     * @param  string $template La plantilla actual
     * @return string La plantilla a cargar
     */
    public static function load_single_template(string $template): string {
        global $post;
        
        if (!$post) {
            return $template;
        }
        
        // Verificar si existe una plantilla en el tema
        $theme_template = '';
        
        if ($post->post_type === 'solicitud') {
            $theme_template = get_stylesheet_directory() . '/single-solicitud.php';
        } elseif ($post->post_type === 'cotizacion') {
            $theme_template = get_stylesheet_directory() . '/single-cotizacion.php';
        }
        
        if ($theme_template && file_exists($theme_template)) {
            return $theme_template;
        }
        
        // Si no existe en el tema, usar la del plugin
        $plugin_template = '';
        
        if ($post->post_type === 'solicitud') {
            $plugin_template = RFQ_MANAGER_WOO_PLUGIN_DIR . 'templates/single-solicitud.php';
        } elseif ($post->post_type === 'cotizacion') {
            $plugin_template = RFQ_MANAGER_WOO_PLUGIN_DIR . 'templates/single-cotizacion.php';
        }
        
        if ($plugin_template && file_exists($plugin_template)) {
            return $plugin_template;
        }
        
        return $template;
    }
    
    /**
     * Encola los estilos CSS
     *
     * @since  0.1.0
     * @return void
     */
    public static function enqueue_styles(): void {
        if (is_singular(['solicitud', 'cotizacion']) || get_query_var('rfq_payment_page')) {
            wp_enqueue_style(
                'rfq-single-styles',
                RFQ_MANAGER_WOO_PLUGIN_URL . 'assets/css/single-templates.css',
                [],
                RFQ_MANAGER_WOO_VERSION
            );
        }
    }
} 