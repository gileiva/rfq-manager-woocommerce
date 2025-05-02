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
    private static function loadDependencies() {
        // Core dependencies
        require_once RFQ_MANAGER_WOO_PLUGIN_DIR . 'src/Core/Loader.php';
        
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
        // Hook de depuración
        add_action('init', function() {
            // error_log('RFQ Manager - GiHandler hooks inicializados');
        }, 999);
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
        // Register post types and taxonomies
        add_action('init', ['GiVendor\GiPlugin\PostType\SolicitudPostType', 'register']);
        add_action('init', ['GiVendor\GiPlugin\PostType\SolicitudPostType', 'registerStatuses']);
        
        // Initialize modular components
        Order\OrderStatusManager::init();      // Gestiona estados de órdenes WooCommerce
        Order\OrderInterceptor::init();        // Intercepta órdenes de WooCommerce
        Email\EmailManager::init();            // Gestiona emails de WooCommerce
        Admin\AdminInterfaceManager::init();   // Gestiona la interfaz de administración
        
        // Inicializar servicios de precios y pagos
        Services\PriceManager::init();         // Oculta precios en front-end y API
        Services\PaymentManager::init();       // Gestiona pasarelas de pago
        
        // Inicializar la vista personalizada de solicitudes y scheduler
        Solicitud\Admin\SolicitudView::init(); // Gestiona la vista de solicitudes individuales
        Solicitud\Scheduler\StatusScheduler::init(); // Gestiona el cambio automático de estado
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