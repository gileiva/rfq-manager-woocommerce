<?php
/**
 * Phase 0 Hook Listeners for Testing
 * 
 * This file contains hook listeners to test the new functionality
 * added in Phase 0 of the notification system improvements.
 *
 * @package    GiVendor\GiPlugin\Hooks
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Hooks;

use GiVendor\GiPlugin\Utils\RfqLogger;

/**
 * Phase0Listeners - Hook listeners for testing Phase 0 improvements
 *
 * @package    GiVendor\GiPlugin\Hooks  
 * @since      0.1.0
 */
class Phase0Listeners {
    
    /**
     * Initialize hook listeners
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        // Only load listeners in debug mode
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        // Listen to the new hook for cotización acceptance by user
        add_action('rfq_cotizacion_accepted_by_user', [self::class, 'onCotizacionAcceptedByUser'], 10, 4);
        
        // Test logging integration
        add_action('rfq_cotizacion_accepted', [self::class, 'onCotizacionAccepted'], 10, 4);
    }
    
    /**
     * Handle cotización accepted by user event
     *
     * @since  0.1.0
     * @param  int $cotizacion_id Cotización ID
     * @param  int $solicitud_id Solicitud ID
     * @param  int $order_id Order ID
     * @param  int $user_id User ID
     * @return void
     */
    public static function onCotizacionAcceptedByUser(int $cotizacion_id, int $solicitud_id, int $order_id, int $user_id): void {
        RfqLogger::logCotizacionAcceptance('user_accepted_hook_fired', $cotizacion_id, $solicitud_id, $user_id, $order_id);
        
        // Additional specific logging for the new hook
        RfqLogger::logHook('rfq_cotizacion_accepted_by_user', [
            $cotizacion_id, $solicitud_id, $order_id, $user_id
        ], 'fired');
        
        // Log that this is the new Phase 0 hook
        RfqLogger::info("Phase 0 hook rfq_cotizacion_accepted_by_user executed successfully", [
            'hook_name' => 'rfq_cotizacion_accepted_by_user',
            'phase' => 'Phase 0',
            'cotizacion_id' => $cotizacion_id,
            'solicitud_id' => $solicitud_id,
            'order_id' => $order_id,
            'user_id' => $user_id
        ]);
    }
    
    /**
     * Handle original cotización accepted event for comparison
     *
     * @since  0.1.0
     * @param  int $cotizacion_id Cotización ID
     * @param  int $solicitud_id Solicitud ID
     * @param  int $order_id Order ID
     * @param  int $user_id User ID
     * @return void
     */
    public static function onCotizacionAccepted(int $cotizacion_id, int $solicitud_id, int $order_id, int $user_id): void {
        RfqLogger::logHook('rfq_cotizacion_accepted', [
            $cotizacion_id, $solicitud_id, $order_id, $user_id
        ], 'fired');
        
        RfqLogger::info("Original hook rfq_cotizacion_accepted executed", [
            'hook_name' => 'rfq_cotizacion_accepted',
            'cotizacion_id' => $cotizacion_id,
            'solicitud_id' => $solicitud_id,
            'order_id' => $order_id,
            'user_id' => $user_id
        ]);
    }
}
