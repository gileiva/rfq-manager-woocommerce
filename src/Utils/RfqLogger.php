<?php
/**
 * RFQ System Centralized Logger
 * 
 * Provides centralized logging functionality for the RFQ system with
 * configurable log levels and consistent formatting.
 *
 * @package    GiVendor\GiPlugin\Utils
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Utils;

/**
 * RfqLogger - Centralized logging system for RFQ operations
 *
 * This class provides a unified way to log events across the RFQ system,
 * with structured formatting and configurable log levels.
 *
 * @package    GiVendor\GiPlugin\Utils
 * @since      0.1.0
 */
class RfqLogger {
    
    /**
     * Log levels
     *
     * @since  0.1.0
     * @access public
     * @var    string
     */
    public const LEVEL_ERROR = 'ERROR';
    public const LEVEL_WARN = 'WARN';
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_SUCCESS = 'SUCCESS';
    
    /**
     * Component prefixes for categorization
     *
     * @since  0.1.0
     * @access public
     * @var    string
     */
    public const COMPONENT_FLOW = 'RFQ-FLOW';
    public const COMPONENT_ERROR = 'RFQ-ERROR';
    public const COMPONENT_SUCCESS = 'RFQ-SUCCESS';
    public const COMPONENT_WARN = 'RFQ-WARN';
    public const COMPONENT_NOTIFICATION = 'RFQ-NOTIFICATION';
    public const COMPONENT_SCHEDULER = 'RFQ-SCHEDULER';
    public const COMPONENT_ORDER = 'RFQ-ORDER';
    public const COMPONENT_EMAIL = 'RFQ-EMAIL';
    
    /**
     * Log a message with specified level and context
     *
     * @since  0.1.0
     * @param  string $message The log message
     * @param  string $level Log level (ERROR, WARN, INFO, DEBUG, SUCCESS)
     * @param  string $component Component identifier for categorization
     * @param  array $context Additional context data
     * @return void
     */
    public static function log(string $message, string $level = self::LEVEL_INFO, string $component = self::COMPONENT_FLOW, array $context = []): void {
        $formatted_message = self::format_message($message, $level, $component, $context);
        error_log($formatted_message);
    }
    
    /**
     * Log an error message
     *
     * @since  0.1.0
     * @param  string $message Error message
     * @param  array $context Additional context
     * @return void
     */
    public static function error(string $message, array $context = []): void {
        self::log($message, self::LEVEL_ERROR, self::COMPONENT_ERROR, $context);
    }
    
    /**
     * Log a warning message
     *
     * @since  0.1.0
     * @param  string $message Warning message  
     * @param  array $context Additional context
     * @return void
     */
    public static function warn(string $message, array $context = []): void {
        self::log($message, self::LEVEL_WARN, self::COMPONENT_WARN, $context);
    }
    
    /**
     * Log an info message
     *
     * @since  0.1.0
     * @param  string $message Info message
     * @param  array $context Additional context  
     * @return void
     */
    public static function info(string $message, array $context = []): void {
        self::log($message, self::LEVEL_INFO, self::COMPONENT_FLOW, $context);
    }
    
    /**
     * Log a success message
     *
     * @since  0.1.0
     * @param  string $message Success message
     * @param  array $context Additional context
     * @return void
     */
    public static function success(string $message, array $context = []): void {
        self::log($message, self::LEVEL_SUCCESS, self::COMPONENT_SUCCESS, $context);
    }
    
    /**
     * Log a debug message
     *
     * @since  0.1.0
     * @param  string $message Debug message
     * @param  array $context Additional context
     * @return void
     */
    public static function debug(string $message, array $context = []): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::log($message, self::LEVEL_DEBUG, self::COMPONENT_FLOW, $context);
        }
    }
    
    /**
     * Log notification-specific events
     *
     * @since  0.1.0
     * @param  string $message Notification message
     * @param  string $level Log level
     * @param  array $context Additional context including recipient info
     * @return void
     */
    public static function notification(string $message, string $level = self::LEVEL_INFO, array $context = []): void {
        self::log($message, $level, self::COMPONENT_NOTIFICATION, $context);
    }
    
    /**
     * Log scheduler-specific events
     *
     * @since  0.1.0
     * @param  string $message Scheduler message
     * @param  string $level Log level
     * @param  array $context Additional context
     * @return void  
     */
    public static function scheduler(string $message, string $level = self::LEVEL_INFO, array $context = []): void {
        self::log($message, $level, self::COMPONENT_SCHEDULER, $context);
    }
    
    /**
     * Log order-specific events
     *
     * @since  0.1.0
     * @param  string $message Order message
     * @param  string $level Log level
     * @param  array $context Additional context
     * @return void
     */
    public static function order(string $message, string $level = self::LEVEL_INFO, array $context = []): void {
        self::log($message, $level, self::COMPONENT_ORDER, $context);
    }
    
    /**
     * Log email-specific events
     *
     * @since  0.1.0
     * @param  string $message Email message
     * @param  string $level Log level
     * @param  array $context Additional context including recipients
     * @return void
     */
    public static function email(string $message, string $level = self::LEVEL_INFO, array $context = []): void {
        self::log($message, $level, self::COMPONENT_EMAIL, $context);
    }
    
    /**
     * Format log message with consistent structure
     *
     * @since  0.1.0
     * @param  string $message The base message
     * @param  string $level Log level
     * @param  string $component Component identifier
     * @param  array $context Additional context data
     * @return string Formatted log message
     */
    protected static function format_message(string $message, string $level, string $component, array $context): string {
        $timestamp = current_time('Y-m-d H:i:s');
        $formatted = "[{$timestamp}] [{$component}] [{$level}] {$message}";
        
        if (!empty($context)) {
            $context_str = self::format_context($context);
            $formatted .= " | Context: {$context_str}";
        }
        
        return $formatted;
    }
    
    /**
     * Format context array into readable string
     *
     * @since  0.1.0
     * @param  array $context Context data
     * @return string Formatted context string
     */
    protected static function format_context(array $context): string {
        $parts = [];
        
        foreach ($context as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = wp_json_encode($value);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $value = 'null';
            } else {
                $value = (string) $value;
            }
            
            $parts[] = "{$key}={$value}";
        }
        
        return implode(', ', $parts);
    }
    
    /**
     * Log cotización acceptance flow with structured data
     *
     * @since  0.1.0
     * @param  string $stage Current stage of acceptance process
     * @param  int $cotizacion_id Cotización ID
     * @param  int $solicitud_id Solicitud ID
     * @param  int $user_id User ID
     * @param  int|null $order_id Order ID (if available)
     * @return void
     */
    public static function logCotizacionAcceptance(
        string $stage, 
        int $cotizacion_id, 
        int $solicitud_id, 
        int $user_id, 
        ?int $order_id = null
    ): void {
        $context = [
            'stage' => $stage,
            'cotizacion_id' => $cotizacion_id,
            'solicitud_id' => $solicitud_id,
            'user_id' => $user_id
        ];
        
        if ($order_id !== null) {
            $context['order_id'] = $order_id;
        }
        
        self::info("Cotización acceptance flow: {$stage}", $context);
    }
    
    /**
     * Log hook execution
     *
     * @since  0.1.0
     * @param  string $hook_name Hook name
     * @param  array $args Hook arguments
     * @param  string $action Action taken (firing, listening, etc.)
     * @return void
     */
    public static function logHook(string $hook_name, array $args = [], string $action = 'fired'): void {
        $context = [
            'hook' => $hook_name,
            'action' => $action,
            'args_count' => count($args)
        ];
        
        if (!empty($args)) {
            $context['args'] = array_slice($args, 0, 5); // Limit to first 5 args to prevent log bloat
        }
        
        self::info("Hook {$action}: {$hook_name}", $context);
    }
}
