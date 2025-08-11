<?php
/**
 * Template Deprecation Manager
 * 
 * Handles deprecation notices for old email templates and provides
 * migration guidance for template customizations.
 *
 * @package    GiVendor\GiPlugin\Utils
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Utils;

/**
 * TemplateDeprecation - Manages template deprecation notices
 *
 * This class provides functionality to mark email templates as deprecated
 * and provide guidance for migration to the new notification system.
 *
 * @package    GiVendor\GiPlugin\Utils
 * @since      0.1.0
 */
class TemplateDeprecation {
    
    /**
     * Deprecated template mapping
     *
     * @since  0.1.0
     * @access private
     * @var    array
     */
    private static $deprecated_templates = [
        'admin-solicitud-created.php' => [
            'version' => '0.1.0',
            'replacement' => 'NotificationManager admin templates',
            'component' => 'AdminNotifications'
        ],
        'admin-cotizacion-submitted.php' => [
            'version' => '0.1.0', 
            'replacement' => 'NotificationManager admin templates',
            'component' => 'AdminNotifications'
        ],
        'admin-cotizacion-accepted.php' => [
            'version' => '0.1.0',
            'replacement' => 'NotificationManager admin templates', 
            'component' => 'AdminNotifications'
        ],
        'user-solicitud-created.php' => [
            'version' => '0.1.0',
            'replacement' => 'NotificationManager user templates',
            'component' => 'UserNotifications'
        ],
        'user-cotizacion-received.php' => [
            'version' => '0.1.0',
            'replacement' => 'NotificationManager user templates',
            'component' => 'UserNotifications'  
        ],
        'user-status-changed.php' => [
            'version' => '0.1.0',
            'replacement' => 'NotificationManager user templates',
            'component' => 'UserNotifications'
        ],
        'supplier-solicitud-created.php' => [
            'version' => '0.1.0',
            'replacement' => 'NotificationManager supplier templates',
            'component' => 'SupplierNotifications'
        ],
        'supplier-cotizacion-accepted.php' => [
            'version' => '0.1.0',
            'replacement' => 'NotificationManager supplier templates',
            'component' => 'SupplierNotifications'
        ]
    ];
    
    /**
     * Mark a template as deprecated
     *
     * @since  0.1.0
     * @param  string $template_name Template filename
     * @param  string $version Version when deprecated
     * @param  string $replacement Replacement recommendation
     * @param  string $component Component using the template
     * @return void
     */
    public static function markDeprecated(
        string $template_name, 
        string $version = '0.1.0',
        string $replacement = '',
        string $component = ''
    ): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $message = "Template '{$template_name}' is deprecated since version {$version}.";
            
            if (!empty($replacement)) {
                $message .= " Use '{$replacement}' instead.";
            }
            
            if (!empty($component)) {
                $message .= " (Component: {$component})";
            }
            
            RfqLogger::warn($message, [
                'deprecated_template' => $template_name,
                'deprecated_since' => $version,
                'replacement' => $replacement,
                'component' => $component,
                'backtrace' => wp_debug_backtrace_summary()
            ]);
            
            // Also trigger WordPress deprecation notice for developers
            if (function_exists('_deprecated_file')) {
                _deprecated_file($template_name, $version, $replacement);
            }
        }
    }
    
    /**
     * Check if a template is deprecated
     *
     * @since  0.1.0
     * @param  string $template_name Template filename
     * @return bool
     */
    public static function isDeprecated(string $template_name): bool {
        return isset(self::$deprecated_templates[$template_name]);
    }
    
    /**
     * Get deprecation info for a template
     *
     * @since  0.1.0
     * @param  string $template_name Template filename
     * @return array|null Deprecation info or null if not deprecated
     */
    public static function getDeprecationInfo(string $template_name): ?array {
        return self::$deprecated_templates[$template_name] ?? null;
    }
    
    /**
     * Add admin notices for deprecated template usage
     *
     * @since  0.1.0
     * @return void
     */
    public static function addAdminNotices(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        add_action('admin_notices', [self::class, 'showDeprecationNotices']);
    }
    
    /**
     * Show deprecation notices in WordPress admin
     *
     * @since  0.1.0  
     * @return void
     */
    public static function showDeprecationNotices(): void {
        // Check if any deprecated templates are being used in theme
        $deprecated_found = [];
        
        foreach (self::$deprecated_templates as $template => $info) {
            $theme_template = locate_template([
                'rfq-manager/emails/' . $template,
                'rfq-manager/' . $template
            ]);
            
            if ($theme_template) {
                $deprecated_found[] = [
                    'template' => $template,
                    'path' => $theme_template,
                    'info' => $info
                ];
            }
        }
        
        if (!empty($deprecated_found)) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>RFQ Manager:</strong> ' . __('Deprecated email templates detected', 'rfq-manager-woocommerce') . '</p>';
            echo '<ul>';
            
            foreach ($deprecated_found as $item) {
                echo '<li>';
                printf(
                    __('Template "%s" is deprecated since version %s. Please migrate to %s.', 'rfq-manager-woocommerce'),
                    esc_html($item['template']),
                    esc_html($item['info']['version']),
                    esc_html($item['info']['replacement'])
                );
                echo '</li>';
            }
            
            echo '</ul>';
            echo '<p><a href="' . admin_url('admin.php?page=rfq-notifications') . '">';
            echo __('Configure new email templates', 'rfq-manager-woocommerce') . '</a></p>';
            echo '</div>';
        }
    }
    
    /**
     * Initialize deprecation system
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        self::addAdminNotices();
        
        // Hook into template loading to check for deprecated usage
        add_action('wp_loaded', [self::class, 'checkTemplateUsage']);
    }
    
    /**
     * Check for deprecated template usage
     *
     * @since  0.1.0
     * @return void
     */
    public static function checkTemplateUsage(): void {
        // This would be called when templates are actually loaded
        // For now, we'll just log that the deprecation system is active
        RfqLogger::debug('Template deprecation system initialized', [
            'deprecated_templates_count' => count(self::$deprecated_templates),
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG
        ]);
    }
}
