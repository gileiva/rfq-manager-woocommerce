<?php
/**
 * Test case for SolicitudPostType
 *
 * @package    GiVendor\GiPlugin\Tests
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Tests;

use PHPUnit\Framework\TestCase;
use GiVendor\GiPlugin\PostType\SolicitudPostType;

/**
 * Test the SolicitudPostType class
 */
class TestSolicitudPostType extends TestCase {

    /**
     * Set up the test by mocking necessary WordPress functions
     */
    protected function setUp(): void {
        parent::setUp();
        
        // Define the global $wp_post_types if it doesn't exist
        if (!isset($GLOBALS['wp_post_types'])) {
            $GLOBALS['wp_post_types'] = [];
        }
        
        // Define the global $wp_post_statuses if it doesn't exist
        if (!isset($GLOBALS['wp_post_statuses'])) {
            $GLOBALS['wp_post_statuses'] = [];
        }
        
        // Mock WordPress functions
        if (!function_exists('register_post_type')) {
            function register_post_type($post_type, $args = []) {
                $GLOBALS['wp_post_types'][$post_type] = (object) $args;
                return true;
            }
        }
        
        if (!function_exists('register_post_status')) {
            function register_post_status($status, $args = []) {
                $GLOBALS['wp_post_statuses'][$status] = (object) $args;
                return true;
            }
        }
        
        if (!function_exists('__')) {
            function __($text, $domain = 'default') {
                return $text;
            }
        }
        
        if (!function_exists('_x')) {
            function _x($text, $context, $domain = 'default') {
                return $text;
            }
        }
        
        if (!function_exists('_n_noop')) {
            function _n_noop($singular, $plural, $domain = null) {
                return [
                    'singular' => $singular,
                    'plural'   => $plural,
                    'domain'   => $domain,
                    'context'  => null
                ];
            }
        }
    }
    
    /**
     * Test that the 'solicitud' post type is registered correctly
     */
    public function testRegisterPostType() {
        // Register the post type
        SolicitudPostType::register();
        
        // Check if the post type was registered
        $this->assertArrayHasKey('solicitud', $GLOBALS['wp_post_types']);
        
        // Check specific settings
        $post_type = $GLOBALS['wp_post_types']['solicitud'];
        $this->assertEquals(false, $post_type->public);
        $this->assertEquals(true, $post_type->show_ui);
        $this->assertEquals(true, $post_type->show_in_rest);
        $this->assertContains('title', $post_type->supports);
        $this->assertContains('editor', $post_type->supports);
        $this->assertContains('custom-fields', $post_type->supports);
    }
    
    /**
     * Test that the custom post statuses are registered correctly
     */
    public function testRegisterStatuses() {
        // Register the post statuses
        SolicitudPostType::registerStatuses();
        
        // Check that the primary status is registered
        $this->assertArrayHasKey('rfq-pending', $GLOBALS['wp_post_statuses']);
        
        // Check that all statuses are registered
        $this->assertArrayHasKey('rfq-accepted', $GLOBALS['wp_post_statuses']);
        $this->assertArrayHasKey('rfq-historic', $GLOBALS['wp_post_statuses']);
        
        // Check specific settings of the pending status
        $status = $GLOBALS['wp_post_statuses']['rfq-pending'];
        $this->assertEquals('Pendiente de cotización', $status->label);
        $this->assertEquals(true, $status->public);
        $this->assertEquals(true, $status->show_in_admin_all_list);
    }
}