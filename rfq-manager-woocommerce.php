<?php
/**
 * RFQ Manager for WooCommerce
 *
 * @package           GiVendor\GiPlugin
 * @author            WeLoveWeb
 * @copyright         WeLoveWeb
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       TCD Manager for WooCommerce
 * Plugin URI:        https://weloveweb.eu
 * Description:       Request for Quote management system for WooCommerce
 * Version:           0.4.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            WeLoveWeb
 * Author URI:        https://weloveweb.eu
 * Text Domain:       rfq-manager-woocommerce
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('RFQ_MANAGER_WOO_VERSION', '0.2.0');
define('RFQ_MANAGER_WOO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RFQ_MANAGER_WOO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RFQ_MANAGER_WOO_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Composer autoloader
if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
    require_once dirname(__FILE__) . '/vendor/autoload.php';
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    // Check if WooCommerce is active
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>' . esc_html__('RFQ Manager for WooCommerce requires WooCommerce to be installed and active.', 'rfq-manager-woocommerce') . '</p></div>';
        });
        return;
    }

    // Initialize GiHandler
    if (class_exists('GiVendor\\GiPlugin\\GiHandler')) {
        GiVendor\GiPlugin\GiHandler::run();
    }
});
