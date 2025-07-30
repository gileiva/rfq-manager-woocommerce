<?php
/**
 * Main plugin handler class
 *
 * @package    GiVendor\GiPlugin
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin;

use GiVendor\GiPlugin\Services\PriceManager;
use GiVendor\GiPlugin\Services\PaymentManager;
use GiVendor\GiPlugin\Solicitud\Admin\SolicitudView;
use GiVendor\GiPlugin\Solicitud\Scheduler\StatusScheduler;
use GiVendor\GiPlugin\PostType\SolicitudPostType;
use GiVendor\GiPlugin\PostType\CotizacionPostType;
use GiVendor\GiPlugin\Shortcode\SolicitudShortcodes;
use GiVendor\GiPlugin\Shortcode\CotizacionShortcodes;
use GiVendor\GiPlugin\Cotizacion\CotizacionHandler;
use GiVendor\GiPlugin\Solicitud\SolicitudStatusHandler;

/**
 * GiHandler - Main plugin class that handles plugin initialization
 *
 * This class is responsible for:
 * - Loading dependencies
 * - Defining internationalization
 * - Registering admin and public hooks
 * - Registering custom post types and taxonomies
 * - Initializing controllers and services
 *
 * @package    GiVendor\GiPlugin
 * @author     Gi Leiva <info@gileiva.com>
 * @since      0.1.0
 */
class GiHandler {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power the plugin.
     *
     * @since    0.1.0
     * @access   protected
     * @var      \GiVendor\GiPlugin\Core\Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected static $loader;
    private $rfq_gateway_filters;

    /**
     * The unique identifier of this plugin.
     *
     * @since    0.1.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected static $plugin_name = 'rfq-manager-woocommerce';

    /**
     * The current version of the plugin.
     *
     * @since    0.1.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected static $version;

    /**
     * Initialize the plugin
     *
     * @since    0.1.0
     * @return   void
     */
    public static function run() {
        self::$version = RFQ_MANAGER_WOO_VERSION;
        
        self::loadDependencies();
        self::defineHooks();
        self::initializeComponents();
        self::create_required_pages();
        
        // Execute the loader to register all the hooks with WordPress
        self::$loader->run();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    0.1.0
     * @access   private
     * @return   void
     */
    private static function loadDependencies(): void {
        // Carga automática de todas las librerías instaladas por Composer
        require_once RFQ_MANAGER_WOO_PLUGIN_DIR . 'vendor/autoload.php';

        // Core dependencies
        require_once RFQ_MANAGER_WOO_PLUGIN_DIR . 'src/Core/Loader.php';
        
        // Helpers
        require_once RFQ_MANAGER_WOO_PLUGIN_DIR . 'src/Helpers/RequestHelper.php';
        
        // Templates
        require_once RFQ_MANAGER_WOO_PLUGIN_DIR . 'src/Templates/TemplateManager.php';
        require_once RFQ_MANAGER_WOO_PLUGIN_DIR . 'src/Email/Templates/TemplateParser.php';
        
        // Shortcodes
        require_once RFQ_MANAGER_WOO_PLUGIN_DIR . 'src/Shortcode/SolicitudShortcodes.php';
        require_once RFQ_MANAGER_WOO_PLUGIN_DIR . 'src/Shortcode/CotizacionShortcodes.php';
        
        // Cotizacion
        require_once RFQ_MANAGER_WOO_PLUGIN_DIR . 'src/Cotizacion/CotizacionHandler.php';

        // WooCommerce integrations
        require_once RFQ_MANAGER_WOO_PLUGIN_DIR . 'src/Woocommerce/RFQPurchasableOverride.php';
        require_once RFQ_MANAGER_WOO_PLUGIN_DIR . 'src/WooCommerce/CartShortcode.php';
        require_once RFQ_MANAGER_WOO_PLUGIN_DIR . 'src/WooCommerce/AjaxCart.php';
        
        // Initialize the loader
        self::$loader = new Core\Loader();
    }

    /**
     * Register all of the hooks related to the plugin functionality
     *
     * @since    0.1.0
     * @access   private
     * @return   void
     */
    private static function defineHooks() {
        // Registrar hooks de activación/desactivación
        PostType\CotizacionPostType::register_activation_hooks();
        
        // Flush rewrite rules on plugin activation
        register_activation_hook(RFQ_MANAGER_WOO_PLUGIN_BASENAME, function() {
            flush_rewrite_rules();
        });
        
                
        // Add custom rewrite rules
        add_action('init', function() {
            // Regla para cotizar-solicitud/[slug] que apunta a la página base y pasa el slug como query var
            add_rewrite_rule(
                '^cotizar-solicitud/([^/]+)/?$',
                'index.php?pagename=cotizar-solicitud&rfq_cotizacion_slug=$matches[1]',
                'top'
            );

            // Regla para ver-solicitud/[slug] que apunta a la página base y pasa el slug como query var
            add_rewrite_rule(
                '^ver-solicitud/([^/]+)/?$',
                'index.php?pagename=ver-solicitud&rfq_slug=$matches[1]',
                'top'
            );

            // Agregar query vars
            add_filter('query_vars', function($vars) {
                $vars[] = 'post_type';
                $vars[] = 'name';
                $vars[] = 'rfq_slug';
                $vars[] = 'rfq_cotizacion_slug';
                return $vars;
            });

            // Forzar flush de reglas
            if (get_option('rfq_flush_rewrite_rules', false)) {
                flush_rewrite_rules();
                delete_option('rfq_flush_rewrite_rules');
            }
        }, 10);

        // Flush rewrite rules when needed
        add_action('after_switch_theme', 'flush_rewrite_rules');
        add_action('wp_loaded', function() {
            if (get_option('rfq_flush_rewrite_rules', false)) {
                flush_rewrite_rules();
                delete_option('rfq_flush_rewrite_rules');
            }
        });


        // Forzar plantilla de la página base en /ver-solicitud/{slug}/ y /cotizar-solicitud/{slug}/
        add_filter('template_include', function($template) {
            global $wp;
            $request_path = $wp->request;
            
            // Para /ver-solicitud/{slug}/
            if (preg_match('#^ver-solicitud/([^/]+)/?$#', $request_path, $matches)) {
                $page = get_page_by_path('ver-solicitud');
                if ($page) {
                    // Forzar el post global a la página base
                    setup_postdata($page);
                    $GLOBALS['post'] = $page;
                    // Obtener plantilla personalizada si existe
                    $page_template = get_page_template_slug($page->ID);
                    if ($page_template) {
                        $located = locate_template($page_template);
                        if ($located) {
                            return $located;
                        }
                    }
                    // Si no hay plantilla personalizada, usar page.php o fallback
                    $default = get_query_template('page');
                    if ($default) {
                        return $default;
                    }
                }
            }
            
            // Para /cotizar-solicitud/{slug}/
            if (preg_match('#^cotizar-solicitud/([^/]+)/?$#', $request_path, $matches)) {
                $page = get_page_by_path('cotizar-solicitud');
                if ($page) {
                    // Forzar el post global a la página base
                    setup_postdata($page);
                    $GLOBALS['post'] = $page;
                    // Obtener plantilla personalizada si existe
                    $page_template = get_page_template_slug($page->ID);
                    if ($page_template) {
                        $located = locate_template($page_template);
                        if ($located) {
                            return $located;
                        }
                    }
                    // Si no hay plantilla personalizada, usar page.php o fallback
                    $default = get_query_template('page');
                    if ($default) {
                        return $default;
                    }
                }
            }
            
            return $template;
        }, 99);

        // Forzar a Elementor a renderizar su layout visual en /ver-solicitud/{slug}/ y /cotizar-solicitud/{slug}/
        add_filter('elementor/frontend/the_content', function($content) {
            global $wp;
            $request_path = isset($wp->request) ? $wp->request : '';
            
            // Para /ver-solicitud/{slug}/
            if (preg_match('#^ver-solicitud/([^/]+)/?$#', $request_path)) {
                $page = get_page_by_path('ver-solicitud');
                if ($page && isset($GLOBALS['post']) && $GLOBALS['post']->ID === $page->ID) {
                    // Forzar a Elementor a pensar que está en la URL canónica
                    add_filter('elementor/utils/is_preview', '__return_true', 99);
                }
            }
            
            // Para /cotizar-solicitud/{slug}/
            if (preg_match('#^cotizar-solicitud/([^/]+)/?$#', $request_path)) {
                $page = get_page_by_path('cotizar-solicitud');
                if ($page && isset($GLOBALS['post']) && $GLOBALS['post']->ID === $page->ID) {
                    // Forzar a Elementor a pensar que está en la URL canónica
                    add_filter('elementor/utils/is_preview', '__return_true', 99);
                }
            }
            
            return $content;
        }, 0);

        // Forzar que la query principal trate /ver-solicitud/{slug}/ y /cotizar-solicitud/{slug}/ como páginas reales
        add_action('pre_get_posts', function($query) {
            if (!is_admin() && $query->is_main_query()) {
                global $wp;
                $request_path = $wp->request;

                // Para /ver-solicitud/{slug}/
                if (preg_match('#^ver-solicitud/([^/]+)/?$#', $request_path)) {
                    $page = get_page_by_path('ver-solicitud');
                    if ($page instanceof WP_Post) {
                        $query->set('page_id', $page->ID);
                        $query->is_page = true;
                        $query->is_singular = true;
                        $query->is_single = false;
                        $query->set('post_type', 'page');
                    }
                }
                
                // Para /cotizar-solicitud/{slug}/
                if (preg_match('#^cotizar-solicitud/([^/]+)/?$#', $request_path)) {
                    $page = get_page_by_path('cotizar-solicitud');
                    if ($page instanceof WP_Post) {
                        $query->set('page_id', $page->ID);
                        $query->is_page = true;
                        $query->is_singular = true;
                        $query->is_single = false;
                        $query->set('post_type', 'page');
                    }
                }
            }
        });
        
        // Forzar render visual de Elementor en /ver-solicitud/{slug}/ y /cotizar-solicitud/{slug}/
        add_filter('elementor/frontend/should_render', function($should_render) {
            global $wp;
            $request_path = isset($wp->request) ? $wp->request : '';

            // Para /ver-solicitud/{slug}/
            if (preg_match('#^ver-solicitud/([^/]+)/?$#', $request_path)) {
                $page = get_page_by_path('ver-solicitud');
                if ($page && isset($GLOBALS['post']) && $GLOBALS['post']->ID === $page->ID) {
                    return true; // Forzar a Elementor a renderizar
                }
            }
            
            // Para /cotizar-solicitud/{slug}/
            if (preg_match('#^cotizar-solicitud/([^/]+)/?$#', $request_path)) {
                $page = get_page_by_path('cotizar-solicitud');
                if ($page && isset($GLOBALS['post']) && $GLOBALS['post']->ID === $page->ID) {
                    return true; // Forzar a Elementor a renderizar
                }
            }

            return $should_render;
        }, 10);

    }
    
    /**
     * Initialize all plugin components
     * 
     * This method initializes all the modular components that make up
     * the RFQ Manager functionality.
     *
     * @since    0.1.0
     * @access   private
     * @return   void
     */
    private static function initializeComponents() {
        // Registrar post types y taxonomías primero, con prioridad 0
        add_action('init', ['GiVendor\GiPlugin\PostType\SolicitudPostType', 'register'], 0);
        add_action('init', ['GiVendor\GiPlugin\PostType\CotizacionPostType', 'register'], 0);
        add_action('init', ['GiVendor\GiPlugin\PostType\CotizacionPostType', 'register_meta_fields'], 0);
        
        // Initialize template manager
        Templates\TemplateManager::init();
        
        // Mover la creación de páginas al hook init con prioridad tardía
        add_action('init', [self::class, 'create_required_pages'], 999);
        
        // Initialize core components
        Order\OrderStatusManager::init();      // Gestiona estados de órdenes WooCommerce
        Order\OrderInterceptor::init();        // Intercepta órdenes de WooCommerce
        Email\EmailManager::init();            // Gestiona emails de WooCommerce
        
        // Crear directorios para templates de emails
        add_action('admin_init', ['GiVendor\GiPlugin\Email\EmailManager', 'create_template_directories']);
        
        // Registrar configuraciones de emails (para uso futuro con Settings API)
        add_action('admin_init', ['GiVendor\GiPlugin\Email\EmailManager', 'register_settings']);
        
        // Initialize admin components
        Admin\AdminInterfaceManager::init();   // Gestiona la interfaz de administración
        Solicitud\View\SolicitudView::init();  // Gestiona la vista de solicitudes
        Cotizacion\View\CotizacionView::init(); // Inicializa la vista de cotizaciones
        
         // Configura Stripe justo antes de inicializar el servicio de pagos
        add_action('init', function(){
            $secret_key = get_option('stripe_secret_key', '');
            if ($secret_key) {
                \Stripe\Stripe::setApiKey($secret_key);
            }
        }, 5);  

        // Initialize services
        Services\PriceManager::init();         // Oculta precios en front-end y API
        Services\PaymentManager::init();       // Gestiona pasarelas de pago
        
        // Initialize WooCommerce overrides
        new WooCommerce\RFQPurchasableOverride(); // Permite productos sin precio en contexto RFQ
        
        
        // Initialize auth and permissions
        Auth\PermissionHandler::init();        // Maneja permisos y redirecciones centralizadamente
        
        // Initialize handlers
        Solicitud\SolicitudStatusHandler::init(); // Gestiona estados de solicitudes
        Solicitud\Scheduler\StatusScheduler::init(); // Gestiona el cambio automático de estado
        Cotizacion\CotizacionHandler::init(); // Inicializa el manejador de cotizaciones
        
        // Initialize AJAX handler
        Ajax\AjaxHandler::init(); // Inicializa el manejador de AJAX
        
        // Initialize shortcodes
        Shortcode\SolicitudShortcodes::init(); // Registra shortcodes de solicitud
        Shortcode\CotizacionShortcodes::init(); // Registra shortcodes de cotización
        WooCommerce\CartShortcode::register(); // Registra shortcode del carrito RFQ
        
        // Forzar flush de reglas de reescritura
        add_action('init', function() {
            if (get_option('rfq_flush_rewrite_rules', false)) {
                flush_rewrite_rules();
                delete_option('rfq_flush_rewrite_rules');
            }
        }, 999);

        new \GiVendor\GiPlugin\Services\Payment\RFQGatewayFilters();

        add_filter('woocommerce_cart_needs_payment', function($needs_payment, $cart) {
        // Si hay productos en el carrito y la RFQ está habilitada, SIEMPRE mostramos la pantalla de métodos de pago, aunque el total sea 0
        if ($cart && count($cart->get_cart()) > 0) {
            foreach ($cart->get_cart() as $cart_item) {
                // Puedes agregar lógica aquí si solo quieres para ciertos productos, pero para RFQ, queremos mostrar siempre
                return true;
            }
        }
        return $needs_payment;
    }, 20, 2);



    }

    /**
     * Crea las páginas necesarias para el plugin
     *
     * @since  0.1.0
     * @return void
     */
    public static function create_required_pages(): void {
        // Verificar si la página 'ver-solicitud' ya existe
        $ver_solicitud = get_page_by_path('ver-solicitud');

        // Crear página de ver solicitud si no existe
        if (!$ver_solicitud) {
            wp_insert_post([
                'post_title'    => __('Ver Solicitud', 'rfq-manager-woocommerce'),
                'post_name'     => 'ver-solicitud',
                'post_status'   => 'publish',
                'post_type'     => 'page',
                'post_content'  => '[rfq_view_solicitud]',
            ]);
        }

        // No crear la página 'cotizar-solicitud' automáticamente para evitar conflicto con el CPT

        // Forzar flush de reglas de reescritura
        flush_rewrite_rules();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     0.1.0
     * @return    string    The name of the plugin.
     */
    public static function getPluginName() {
        return self::$plugin_name;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     0.1.0
     * @return    string    The version number of the plugin.
     */
    public static function getVersion() {
        return self::$version;
    }
}