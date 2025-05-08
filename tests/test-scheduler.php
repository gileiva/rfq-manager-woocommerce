<?php
/**
 * Pruebas para el programador de estados de solicitudes
 *
 * @package    GiVendor\GiPlugin\Tests
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Tests;

use GiVendor\GiPlugin\Solicitud\Scheduler\StatusScheduler;

// Verificar que estamos en un entorno de prueba
if (!defined('WP_TESTS_DIR')) {
    die('Este archivo solo puede ser ejecutado en un entorno de pruebas de WordPress');
}

class TestScheduler extends \WP_UnitTestCase {
    
    /**
     * Prueba la inicialización del programador
     */
    public function test_scheduler_initialization() {
        // Inicializar el programador
        StatusScheduler::init();
        
        // Obtener resultados de las pruebas
        $results = StatusScheduler::test_scheduler();
        
        // Verificar que Action Scheduler está disponible
        $this->assertTrue($results['action_scheduler_available'], 'Action Scheduler no está disponible');
        
        // Verificar que los hooks están registrados
        $this->assertNotFalse($results['hooks_registered']['wp_insert_post'], 'Hook wp_insert_post no está registrado');
        $this->assertNotFalse($results['hooks_registered']['status_change'], 'Hook de cambio de estado no está registrado');
        $this->assertNotFalse($results['hooks_registered']['expiry_update'], 'Hook de actualización de vencimiento no está registrado');
        $this->assertNotFalse($results['hooks_registered']['daily_check'], 'Hook de verificación diaria no está registrado');
    }
    
    /**
     * Prueba la programación de cambio de estado
     */
    public function test_schedule_status_change() {
        // Crear una solicitud de prueba
        $post_id = $this->factory->post->create([
            'post_type' => 'solicitud',
            'post_status' => 'rfq-pending'
        ]);
        
        // Establecer fecha de vencimiento
        $expiry_date = date('Y-m-d H:i:s', strtotime('+24 hours'));
        update_post_meta($post_id, '_solicitud_expiry', $expiry_date);
        
        // Programar cambio de estado
        StatusScheduler::schedule_change_to_historic($post_id);
        
        // Verificar que se programó la acción
        $results = StatusScheduler::test_scheduler();
        $this->assertNotEmpty($results['scheduled_actions'], 'No se programó ninguna acción');
        
        // Verificar que la acción está programada para la fecha correcta
        $scheduled_action = $results['scheduled_actions'][0];
        $this->assertEquals($post_id, $scheduled_action['args'][0], 'ID de solicitud incorrecto en la acción programada');
        
        // Limpiar
        wp_delete_post($post_id, true);
    }
    
    /**
     * Prueba el cambio a estado histórico
     */
    public function test_change_to_historic() {
        // Crear una solicitud de prueba
        $post_id = $this->factory->post->create([
            'post_type' => 'solicitud',
            'post_status' => 'rfq-pending'
        ]);
        
        // Cambiar a estado histórico
        StatusScheduler::change_to_historic($post_id);
        
        // Verificar el cambio de estado
        $post = get_post($post_id);
        $this->assertEquals('rfq-historic', $post->post_status, 'El estado no cambió a histórico');
        
        // Verificar que se agregó la nota
        $note = get_post_meta($post_id, '_rfq_internal_note', true);
        $this->assertNotEmpty($note, 'No se agregó la nota de cambio de estado');
        
        // Limpiar
        wp_delete_post($post_id, true);
    }
} 