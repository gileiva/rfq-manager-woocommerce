<?php
/**
 * Programador de cambios de estado para solicitudes
 *
 * @package    GiVendor\GiPlugin\Solicitud\Scheduler
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Solicitud\Scheduler;

use GiVendor\GiPlugin\Solicitud\SolicitudStatusHandler;

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
     * Nombre de la opción para el tiempo de vencimiento por defecto
     */
    const OPTION_DEFAULT_EXPIRY = 'rfq_default_expiry_hours';
    
    /**
     * Tiempo de vencimiento por defecto (24 horas)
     */
    const DEFAULT_EXPIRY_HOURS = 24;
    
    /**
     * Inicializa los hooks relacionados con la programación de cambios de estado
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        // Verificar que Action Scheduler esté disponible
        if (!function_exists('as_schedule_single_action')) {
            error_log('[RFQ] Action Scheduler no está disponible. Algunas funcionalidades pueden no trabajar correctamente.');
            return;
        }

        // Registrar hooks
            add_action('wp_insert_post', [__CLASS__, 'schedule_status_change'], 10, 3);
            add_action(self::ACTION_TO_HISTORIC, [__CLASS__, 'change_to_historic'], 10, 1);
            add_action('updated_post_meta', [__CLASS__, 'handle_expiry_update'], 10, 4);
            add_action('added_post_meta', [__CLASS__, 'handle_expiry_update'], 10, 4);
            
        // Programar verificación diaria
            if (!wp_next_scheduled('rfq_daily_check_expired')) {
                wp_schedule_event(time(), 'daily', 'rfq_daily_check_expired');
            }
            add_action('rfq_daily_check_expired', [__CLASS__, 'check_expired_solicitudes']);

        // Registrar opción de configuración
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    /**
     * Registra las configuraciones del plugin
     *
     * @since  0.1.0
     * @return void
     */
    public static function register_settings(): void {
        register_setting('rfq_manager_settings', self::OPTION_DEFAULT_EXPIRY, [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => self::DEFAULT_EXPIRY_HOURS,
        ]);
    }

    /**
     * Obtiene el tiempo de vencimiento por defecto
     *
     * @since  0.1.0
     * @return int Horas hasta el vencimiento
     */
    public static function get_default_expiry_hours(): int {
        return absint(get_option(self::OPTION_DEFAULT_EXPIRY, self::DEFAULT_EXPIRY_HOURS));
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
        if ($update || $post->post_type !== 'solicitud') {
            return;
        }
        
        error_log(sprintf('[RFQ] Programando cambio de estado para solicitud #%d', $post_id));
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
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'solicitud' || $post->post_status === 'rfq-historic') {
            return;
        }
        
        // Obtener la fecha de vencimiento
        $expiry_date = get_post_meta($post_id, '_solicitud_expiry', true);
        if (empty($expiry_date)) {
            $expiry_date = date('Y-m-d H:i:s', strtotime('+' . self::get_default_expiry_hours() . ' hours'));
            update_post_meta($post_id, '_solicitud_expiry', $expiry_date);
        }
        
        // Cancelar acciones previas
        self::unschedule_action($post_id);
        
        // Programar nueva acción
        $timestamp = strtotime($expiry_date);
        if ($timestamp > time()) {
        as_schedule_single_action(
            $timestamp,
            self::ACTION_TO_HISTORIC,
            [$post_id],
            self::ACTION_GROUP
        );
        
        error_log(sprintf(
                '[RFQ] Programado cambio a histórico para solicitud #%d en: %s',
            $post_id,
            date('Y-m-d H:i:s', $timestamp)
        ));
        }
    }
    
    /**
     * Manejador para el cambio a estado histórico
     *
     * @since  0.1.0
     * @param  int $post_id ID de la solicitud
     * @return void
     */
    public static function change_to_historic(int $post_id): void {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'solicitud') {
            return;
        }
        
        // Solo cambiar si está en estado pendiente o activa
        if (!in_array($post->post_status, ['rfq-pending', 'rfq-active'])) {
            return;
        }
        
        // Cambiar a estado histórico
        SolicitudStatusHandler::update_status($post_id, 'rfq-historic');
        
        // Registrar nota
        $note = sprintf(
            __('Solicitud pasada a estado Histórico automáticamente por vencimiento (%s).', 'rfq-manager-woocommerce'),
            date_i18n(get_option('date_format') . ' ' . get_option('time_format'))
        );
        add_post_meta($post_id, '_rfq_internal_note', $note);
        
        error_log(sprintf('[RFQ] Solicitud #%d cambiada a histórico por vencimiento', $post_id));
    }
    
    /**
     * Maneja la actualización de la fecha de vencimiento
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
        
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'solicitud') {
            return;
        }
        
        // Validar formato de fecha
        if (!strtotime($meta_value)) {
            error_log(sprintf('[RFQ] Fecha de vencimiento inválida para solicitud #%d: %s', $post_id, $meta_value));
            return;
        }
        
        error_log(sprintf('[RFQ] Programando cambio de estado para solicitud #%d', $post_id));
        self::schedule_change_to_historic($post_id);
    }
    
    /**
     * Verifica y procesa solicitudes vencidas
     *
     * @since  0.1.0
     * @return void
     */
    public static function check_expired_solicitudes(): void {
        $current_time = current_time('mysql');
        
        $args = [
            'post_type'      => 'solicitud',
            'post_status'    => ['rfq-pending', 'rfq-active'],
            'posts_per_page' => 50,
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
                self::change_to_historic(get_the_ID());
            }
            
            error_log(sprintf('[RFQ] Procesadas %d solicitudes vencidas durante la verificación diaria', $query->post_count));
        }
        
        wp_reset_postdata();
    }
    
    /**
     * Cancela acciones programadas para una solicitud
     *
     * @since  0.1.0
     * @param  int $post_id ID de la solicitud
     * @return void
     */
    public static function unschedule_action(int $post_id): void {
        as_unschedule_all_actions(self::ACTION_TO_HISTORIC, [$post_id], self::ACTION_GROUP);
    }

    /**
     * Verifica el funcionamiento del programador
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
            'scheduled_actions' => [],
            'default_expiry' => self::get_default_expiry_hours()
        ];

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