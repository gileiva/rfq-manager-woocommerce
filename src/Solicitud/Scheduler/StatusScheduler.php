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
     * Este método programa una acción automática para cambiar el estado de una solicitud
     * a 'rfq-historic' cuando llegue su fecha de vencimiento. Incluye verificación del
     * resultado de Action Scheduler y logging detallado para diagnóstico.
     *
     * @since  0.1.0
     * @param  int $post_id ID de la solicitud
     * @return void
     */
    public static function schedule_change_to_historic(int $post_id): void {
        error_log(sprintf('[RFQ-DEBUG] *** ENTRANDO A schedule_change_to_historic() para solicitud #%d ***', $post_id));
        
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'solicitud' || $post->post_status === 'rfq-historic') {
            error_log(sprintf('[RFQ-DEBUG] Saliendo temprano - Post: %s | Tipo: %s | Estado: %s',
                $post ? 'EXISTE' : 'NO EXISTE',
                $post ? $post->post_type : 'N/A',
                $post ? $post->post_status : 'N/A'
            ));
            return;
        }
        
        error_log(sprintf('[RFQ-DEBUG] Post válido - Tipo: %s | Estado: %s', $post->post_type, $post->post_status));
        
        // Obtener la fecha de vencimiento
        $expiry_date = get_post_meta($post_id, '_solicitud_expiry', true);
        if (empty($expiry_date)) {
            $expiry_timestamp = current_time('timestamp') + (self::get_default_expiry_hours() * HOUR_IN_SECONDS);
            $expiry_date = date('Y-m-d H:i:s', $expiry_timestamp);
            update_post_meta($post_id, '_solicitud_expiry', $expiry_date);
        }
        
        // Cancelar acciones previas
        self::unschedule_action($post_id);
        
        // Programar nueva acción
        $expiry_timestamp = strtotime($expiry_date);
        $current_timestamp = current_time('timestamp');
        
        if ($expiry_timestamp > $current_timestamp) {
            // Intentar programar la acción y verificar el resultado
            $action_id = as_schedule_single_action(
                $expiry_timestamp,
                self::ACTION_TO_HISTORIC,
                [$post_id],
                self::ACTION_GROUP
            );
            
            // Verificar si la programación fue exitosa
            if ($action_id === 0 || $action_id === false) {
                error_log(sprintf(
                    '[RFQ-ERROR] Falló la programación de Action Scheduler para solicitud #%d. Timestamp: %s, Action ID retornado: %s',
                    $post_id,
                    date('Y-m-d H:i:s', $expiry_timestamp),
                    var_export($action_id, true)
                ));
                
                // Log adicional para diagnóstico
                error_log(sprintf(
                    '[RFQ-ERROR] Detalles del fallo - Post ID: %d, Timestamp actual: %s, Timestamp programado: %s, Diferencia: %d segundos',
                    $post_id,
                    date('Y-m-d H:i:s', $current_timestamp),
                    date('Y-m-d H:i:s', $expiry_timestamp),
                    ($expiry_timestamp - $current_timestamp)
                ));
                
                // Verificar si Action Scheduler está disponible
                if (!function_exists('as_schedule_single_action')) {
                    error_log('[RFQ-ERROR] Action Scheduler no está disponible');
                } else {
                    error_log('[RFQ-ERROR] Action Scheduler está disponible pero falló al programar la acción');
                }
                
                return; // Salir temprano para evitar log de éxito
            }
            
            error_log(sprintf(
                '[RFQ] Programado cambio a histórico para solicitud #%d en: %s (Action ID: %s)',
                $post_id,
                date('Y-m-d H:i:s', $expiry_timestamp),
                $action_id
            ));
        } else {
            error_log(sprintf(
                '[RFQ-WARNING] No se programó cambio a histórico para solicitud #%d - Timestamp en el pasado. Timestamp: %s, Tiempo actual: %s',
                $post_id,
                date('Y-m-d H:i:s', $expiry_timestamp),
                date('Y-m-d H:i:s', $current_timestamp)
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
            error_log(sprintf('[RFQ-ERROR] Fecha de vencimiento inválida para solicitud #%d: %s', $post_id, $meta_value));
            return;
        }
        
        error_log(sprintf('[RFQ] Handle expiry update: Solicitud #%d, Nueva fecha: %s, Timestamp: %d', $post_id, $meta_value, strtotime($meta_value)));
        
        // NUEVO: Verificar si hay transient de protección activo para evitar doble procesamiento
        if (get_transient('rfq_ajax_status_update_' . $post_id)) {
            error_log(sprintf('[RFQ] Actualización AJAX en progreso para solicitud #%d, omitiendo handle_expiry_update para evitar conflicto', $post_id));
            return;
        }
        
        error_log(sprintf('[RFQ] Procediendo a programar cambio de estado para solicitud #%d', $post_id));
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