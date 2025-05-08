<?php
/**
 * Programador de cambios de estado para solicitudes
 *
 * @package    GiVendor\GiPlugin\Solicitud\Scheduler
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Solicitud\Scheduler;

/**
 * StatusScheduler - Gestiona el cambio automático de estado de solicitudes
 *
 * Esta clase utiliza Action Scheduler para cambiar automáticamente 
 * el estado de las solicitudes después de un período determinado.
 *
 * @package    GiVendor\GiPlugin\Solicitud\Scheduler
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class StatusScheduler {
    
    /**
     * Acción para cambiar el estado a histórico
     */
    const ACTION_TO_HISTORIC = 'rfq_solicitud_to_historic';
    
    /**
     * Grupo para acciones relacionadas con solicitudes
     */
    const ACTION_GROUP = 'rfq_scheduler';
    
    /**
     * Inicializa los hooks relacionados con la programación de cambios de estado
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        // Verificar que Action Scheduler esté disponible (viene con WooCommerce)
        if (function_exists('as_schedule_single_action')) {
            // Hook para programar las acciones al crear una nueva solicitud
            add_action('wp_insert_post', [__CLASS__, 'schedule_status_change'], 10, 3);
            
            // Hook para manejar el cambio de estado a histórico
            add_action(self::ACTION_TO_HISTORIC, [__CLASS__, 'change_to_historic'], 10, 1);
            
            // Hook para reprogramar cuando se actualiza la fecha de vencimiento
            add_action('updated_post_meta', [__CLASS__, 'handle_expiry_update'], 10, 4);
            add_action('added_post_meta', [__CLASS__, 'handle_expiry_update'], 10, 4);
            
            // Verificar solicitudes vencidas diariamente para garantizar que no se queden solicitudes sin procesar
            if (!wp_next_scheduled('rfq_daily_check_expired')) {
                wp_schedule_event(time(), 'daily', 'rfq_daily_check_expired');
            }
            add_action('rfq_daily_check_expired', [__CLASS__, 'check_expired_solicitudes']);
        } else {
            // Registrar un error si Action Scheduler no está disponible
            error_log('RFQ Manager - Action Scheduler no está disponible. Algunas funcionalidades pueden no trabajar correctamente.');
        }
    }
    
    /**
     * Programa el cambio de estado para una nueva solicitud
     *
     * @since  0.1.0
     * @param  int      $post_id ID del post
     * @param  \WP_Post $post    Objeto post
     * @param  bool     $update  Si es una actualización o no
     * @return void
     */
    public static function schedule_status_change($post_id, $post, $update): void {
        // Solo procesar nuevas solicitudes (no actualizaciones)
        if ($update || $post->post_type !== 'solicitud') {
            return;
        }
        
        // Programar el cambio a estado histórico después de 24 horas
        self::schedule_change_to_historic($post_id);
    }
    
    /**
     * Programa el cambio a estado histórico
     *
     * @since  0.1.0
     * @param  int $post_id ID de la solicitud
     * @return void
     */
    public static function schedule_change_to_historic(int $post_id): void {
        // Verificar que el post exista y sea una solicitud
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'solicitud' || $post->post_status === 'rfq-historic') {
            return;
        }
        
        // Obtener la fecha de vencimiento o establecer una por defecto (24 horas)
        $expiry_date = get_post_meta($post_id, '_solicitud_expiry', true);
        if (empty($expiry_date)) {
            $expiry_date = date('Y-m-d H:i:s', strtotime('+24 hours'));
            update_post_meta($post_id, '_solicitud_expiry', $expiry_date);
        }
        
        // Cancelar cualquier acción programada previamente
        self::unschedule_action($post_id);
        
        // Programar la nueva acción
        $timestamp = strtotime($expiry_date);
        as_schedule_single_action(
            $timestamp,
            self::ACTION_TO_HISTORIC,
            [$post_id],
            self::ACTION_GROUP
        );
        
        error_log(sprintf(
            'RFQ Manager - Programado cambio a estado histórico para la solicitud #%d en: %s',
            $post_id,
            date('Y-m-d H:i:s', $timestamp)
        ));
    }
    
    /**
     * Manejador para el cambio a estado histórico
     *
     * @since  0.1.0
     * @param  int $post_id ID de la solicitud
     * @return void
     */
    public static function change_to_historic(int $post_id): void {
        // Verificar que el post exista y sea una solicitud
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'solicitud') {
            return;
        }
        
        // Evitar cambios si ya está en algún estado final
        if ($post->post_status === 'rfq-historic' || $post->post_status === 'rfq-accepted') {
            return;
        }
        
        // Cambiar a estado histórico
        wp_update_post([
            'ID' => $post_id,
            'post_status' => 'rfq-historic',
        ]);
        
        // Registrar una nota interna
        $note = __('Solicitud pasada a estado Histórico automáticamente por vencimiento.', 'rfq-manager-woocommerce');
        add_post_meta($post_id, '_rfq_internal_note', $note);
        
        error_log(sprintf('RFQ Manager - Solicitud #%d cambiada a estado histórico por vencimiento', $post_id));
    }
    
    /**
     * Maneja la actualización de la fecha de vencimiento para reprogramar el cambio de estado
     *
     * @since  0.1.0
     * @param  int    $meta_id    ID de la metadata
     * @param  int    $post_id    ID del post
     * @param  string $meta_key   Clave de metadata
     * @param  mixed  $meta_value Valor de metadata
     * @return void
     */
    public static function handle_expiry_update($meta_id, $post_id, $meta_key, $meta_value): void {
        if ($meta_key !== '_solicitud_expiry') {
            return;
        }
        
        // Verificar que sea una solicitud
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'solicitud') {
            return;
        }
        
        // Reprogramar la acción con la nueva fecha
        self::schedule_change_to_historic($post_id);
    }
    
    /**
     * Verifica y procesa solicitudes que deberían haber vencido pero no fueron procesadas
     * 
     * Este método es importante como respaldo en caso de que Action Scheduler no se ejecute correctamente.
     *
     * @since  0.1.0
     * @return void
     */
    public static function check_expired_solicitudes(): void {
        $current_time = current_time('mysql');
        
        // Buscar solicitudes pendientes con fecha de vencimiento pasada
        $args = [
            'post_type'      => 'solicitud',
            'post_status'    => 'rfq-pending',
            'posts_per_page' => 50, // Procesar en lotes para evitar sobrecarga
            'meta_query'     => [
                [
                    'key'     => '_solicitud_expiry',
                    'value'   => $current_time,
                    'compare' => '<',
                    'type'    => 'DATETIME',
                ],
            ],
        ];
        
        $query = new \WP_Query($args);
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                // Cambiar a estado histórico
                self::change_to_historic($post_id);
            }
            
            error_log('RFQ Manager - Procesadas ' . $query->post_count . ' solicitudes vencidas durante la verificación diaria');
        }
        
        wp_reset_postdata();
    }
    
    /**
     * Cancela cualquier acción programada para una solicitud específica
     *
     * @since  0.1.0
     * @param  int $post_id ID de la solicitud
     * @return void
     */
    private static function unschedule_action(int $post_id): void {
        as_unschedule_all_actions(self::ACTION_TO_HISTORIC, [$post_id], self::ACTION_GROUP);
    }

    /**
     * Método de prueba para verificar el funcionamiento del programador
     * 
     * @since  0.1.0
     * @return array Resultados de las pruebas
     */
    public static function test_scheduler(): array {
        $results = [
            'action_scheduler_available' => function_exists('as_schedule_single_action'),
            'hooks_registered' => [
                'wp_insert_post' => has_action('wp_insert_post', [__CLASS__, 'schedule_status_change']),
                'status_change' => has_action(self::ACTION_TO_HISTORIC, [__CLASS__, 'change_to_historic']),
                'expiry_update' => has_action('updated_post_meta', [__CLASS__, 'handle_expiry_update']),
                'daily_check' => has_action('rfq_daily_check_expired', [__CLASS__, 'check_expired_solicitudes'])
            ],
            'scheduled_actions' => []
        ];

        // Verificar acciones programadas
        if (function_exists('as_get_scheduled_actions')) {
            $scheduled = as_get_scheduled_actions([
                'hook' => self::ACTION_TO_HISTORIC,
                'group' => self::ACTION_GROUP
            ]);
            
            foreach ($scheduled as $action) {
                $results['scheduled_actions'][] = [
                    'id' => $action->get_id(),
                    'args' => $action->get_args(),
                    'scheduled_date' => $action->get_schedule()->get_date()->format('Y-m-d H:i:s')
                ];
            }
        }

        return $results;
    }
}