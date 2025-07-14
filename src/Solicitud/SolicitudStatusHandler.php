<?php
/**
 * Manejador de estados de solicitud
 *
 * @package    GiVendor\GiPlugin\Solicitud
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Solicitud;

/**
 * SolicitudStatusHandler - Maneja los estados de las solicitudes
 *
 * Esta clase es responsable de registrar y gestionar los estados de las solicitudes,
 * así como de actualizar automáticamente los estados según las condiciones.
 *
 * @package    GiVendor\GiPlugin\Solicitud
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class SolicitudStatusHandler {
    
    /**
     * Inicializa el manejador de estados
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        add_action('init', [__CLASS__, 'register_statuses']);
        
        // Cambio de prioridad para ejecutar después del metabox y con verificación
        add_action('save_post_solicitud', [__CLASS__, 'check_and_update_status'], 20, 3);
        add_action('save_post_cotizacion', [__CLASS__, 'update_solicitud_status'], 10, 3);
        add_action('rfq_cotizacion_accepted', [__CLASS__, 'handle_cotizacion_accepted'], 10, 2);
        
        // Agregar acción para limpiar la caché cuando se actualiza el estado
        add_action('post_updated', [__CLASS__, 'clear_status_cache'], 10, 3);
    }

    /**
     * Actualiza el estado de una solicitud en WordPress
     *
     * @since  0.1.0
     * @param  int    $solicitud_id ID de la solicitud
     * @param  string $new_status   Nuevo estado a establecer
     * @return bool
     */
    public static function update_status(int $solicitud_id, string $new_status): bool {
        error_log("[RFQ-FLOW] Iniciando update_status - Solicitud: {$solicitud_id}, Nuevo estado: {$new_status}");
        
        // Obtener el estado actual
        $post = get_post($solicitud_id);
        if (!$post || $post->post_type !== 'solicitud') {
            error_log("[RFQ-ERROR] No se pudo encontrar la solicitud {$solicitud_id} o no es del tipo correcto");
            return false;
        }
        
        $old_status = $post->post_status;
        if ($old_status === $new_status) {
            error_log("[RFQ-FLOW] La solicitud {$solicitud_id} ya tiene el post_status '{$new_status}', no se realizará cambio");
            return false;
        }
        
        error_log("[RFQ-FLOW] Actualizando post_status de solicitud {$solicitud_id} de '{$old_status}' a '{$new_status}'");
        
        // Actualizar el post_status
        $update_result = wp_update_post([
            'ID' => $solicitud_id,
            'post_status' => $new_status
        ]);
        
        if (is_wp_error($update_result)) {
            error_log("[RFQ-ERROR] Error al actualizar post_status: " . $update_result->get_error_message());
            return false;
        }
        
        error_log("[RFQ-FLOW] Post status actualizado correctamente a '{$new_status}'");
        
        // Actualizar el meta de estado
        $meta_status = '';
        switch ($new_status) {
            case 'rfq-pending':
                $meta_status = 'pendiente';
                break;
            case 'rfq-active':
                $meta_status = 'activa';
                break;
            case 'rfq-accepted':
                $meta_status = 'aceptada';
                break;
            case 'rfq-historic':
                $meta_status = 'historica';
                break;
            default:
                $meta_status = $new_status;
        }
        
        error_log("[RFQ-FLOW] Actualizando meta '_rfq_status' a '{$meta_status}'");
        update_post_meta($solicitud_id, '_rfq_status', $meta_status);
        
        // Obtener el meta status actual para pasar a la acción
        $old_meta_status = '';
        switch ($old_status) {
            case 'rfq-pending':
                $old_meta_status = 'pendiente';
                break;
            case 'rfq-active':
                $old_meta_status = 'activa';
                break;
            case 'rfq-accepted':
                $old_meta_status = 'aceptada';
                break;
            case 'rfq-historic':
                $old_meta_status = 'historica';
                break;
            default:
                $old_meta_status = $old_status;
        }
        
        // Disparar acción para notificaciones
        error_log("[RFQ-FLOW] Disparando acción 'rfq_solicitud_status_changed' para solicitud ID: {$solicitud_id}, estado: {$meta_status}, anterior: {$old_meta_status}");
        do_action('rfq_solicitud_status_changed', $solicitud_id, $meta_status, $old_meta_status);
        
        return true;
    }

    /**
     * Verifica y actualiza el estado de una solicitud basado en las cotizaciones
     *
     * @since  0.1.0
     * @param  int     $post_id     ID de la solicitud
     * @param  \WP_Post $post        Objeto solicitud
     * @param  bool    $force_check Forzar verificación
     * @return void
     */
    public static function check_and_update_status(int $post_id, \WP_Post $post, bool $force_check = false): void {
        error_log("[RFQ-FLOW] Iniciando check_and_update_status - Solicitud: {$post_id}, Forzar verificación: " . ($force_check ? "Sí" : "No"));
        
        // Verificar que es una solicitud
        if ($post->post_type !== 'solicitud') {
            error_log("[RFQ-FLOW] No es una solicitud, omitiendo proceso");
            return;
        }

        // Verificar si es un cambio automático por nuestro código
        if (!$force_check && get_transient('rfq_manual_status_change_' . $post_id)) {
            error_log("[RFQ-FLOW] Es un cambio manual, omitiendo verificación automática");
            delete_transient('rfq_manual_status_change_' . $post_id);
            return;
        }

        // NUEVA VERIFICACIÓN: Evitar condición de carrera con AJAX de estado
        if (!$force_check && wp_doing_ajax() && isset($_POST['action']) && $_POST['action'] === 'update_solicitud_status') {
            error_log("[RFQ-FLOW] AJAX de actualización de estado en progreso, omitiendo verificación automática para evitar condición de carrera");
            return;
        }

        // NUEVA VERIFICACIÓN: Verificar si hay un transient que indique que se está procesando una actualización de estado
        if (!$force_check && get_transient('rfq_ajax_status_update_' . $post_id)) {
            error_log("[RFQ-FLOW] Actualización de estado AJAX en progreso (transient activo), omitiendo verificación automática");
            return;
        }

        error_log("[RFQ-FLOW] Verificando si la solicitud tiene cotizaciones");
        
        // Si la solicitud está en estado pendiente y tiene cotizaciones, cambiar a activa
        if ($post->post_status === 'rfq-pending' && self::has_cotizaciones($post_id)) {
            error_log("[RFQ-FLOW] Solicitud en estado pendiente tiene cotizaciones, cambiando a estado activo");
            self::update_status($post_id, 'rfq-active');
        }
    }

    /**
     * Verifica si una solicitud tiene cotizaciones
     *
     * @since  0.1.0
     * @param  int  $solicitud_id ID de la solicitud
     * @return bool True si tiene cotizaciones
     */
    private static function has_cotizaciones(int $solicitud_id): bool {
        error_log("[RFQ-FLOW] Verificando si la solicitud {$solicitud_id} tiene cotizaciones");
        
        $query = new \WP_Query([
            'post_type' => 'cotizacion',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_solicitud_parent',
                    'value' => $solicitud_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1
        ]);

        $has_cotizaciones = $query->have_posts();
        
        if ($has_cotizaciones) {
            error_log("[RFQ-FLOW] La solicitud {$solicitud_id} tiene cotizaciones");
        } else {
            error_log("[RFQ-FLOW] La solicitud {$solicitud_id} no tiene cotizaciones");
        }
        
        return $has_cotizaciones;
    }

    /**
     * Registra los estados personalizados para las solicitudes
     *
     * @since  0.1.0
     * @return void
     */
    public static function register_statuses(): void {
        register_post_status('rfq-pending', [
            'label'                     => __('Pendiente', 'rfq-manager-woocommerce'),
            'public'                    => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Pendiente (%s)', 'Pendientes (%s)', 'rfq-manager-woocommerce'),
        ]);

        register_post_status('rfq-active', [
            'label'                     => __('Activa', 'rfq-manager-woocommerce'),
            'public'                    => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Activa (%s)', 'Activas (%s)', 'rfq-manager-woocommerce'),
        ]);

        register_post_status('rfq-accepted', [
            'label'                     => __('Aceptada', 'rfq-manager-woocommerce'),
            'public'                    => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Aceptada (%s)', 'Aceptadas (%s)', 'rfq-manager-woocommerce'),
        ]);

        register_post_status('rfq-historic', [
            'label'                     => __('Histórica', 'rfq-manager-woocommerce'),
            'public'                    => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Histórica (%s)', 'Históricas (%s)', 'rfq-manager-woocommerce'),
        ]);
    }

    /**
     * Limpia la caché cuando se actualiza el estado
     *
     * @since  0.1.0
     * @param  int     $post_id     ID del post
     * @param  \WP_Post $post_after  Post después de la actualización
     * @param  \WP_Post $post_before Post antes de la actualización
     * @return void
     */
    public static function clear_status_cache(int $post_id, \WP_Post $post_after, \WP_Post $post_before): void {
        if ($post_after->post_type === 'solicitud' && $post_after->post_status !== $post_before->post_status) {
            // Limpiar caché de transients
            delete_transient('rfq_solicitud_status_' . $post_id);
            
            // Limpiar caché de meta
            wp_cache_delete($post_id, 'post_meta');
            
            // Forzar actualización de la caché de WordPress
            clean_post_cache($post_id);
        }
    }

    /**
     * Actualiza el estado de una solicitud cuando se guarda una cotización
     *
     * @since  0.1.0
     * @param  int     $cotizacion_id ID de la cotización
     * @param  \WP_Post $cotizacion   Objeto cotización
     * @param  bool    $update  Si es una actualización
     * @return void
     */
    public static function update_solicitud_status(int $cotizacion_id, \WP_Post $cotizacion, bool $update): void {
        error_log("[RFQ-FLOW] Iniciando update_solicitud_status por save_post_cotizacion - Cotización: {$cotizacion_id}, Es actualización: " . ($update ? "Sí" : "No"));
        
        if (!$update || $cotizacion->post_type !== 'cotizacion') {
            if (!$update) {
                error_log("[RFQ-FLOW] No es una actualización, omitiendo proceso");
            } else {
                error_log("[RFQ-FLOW] No es una cotización, omitiendo proceso");
            }
            return;
        }

        $solicitud_id = get_post_meta($cotizacion_id, '_solicitud_parent', true);
        if (!$solicitud_id) {
            error_log("[RFQ-ERROR] No se encontró la solicitud padre para la cotización {$cotizacion_id}");
            return;
        }

        error_log("[RFQ-FLOW] Solicitud padre encontrada. ID: {$solicitud_id}");
        
        // Obtener la solicitud
        $solicitud = get_post($solicitud_id);
        if (!$solicitud) {
            error_log("[RFQ-ERROR] No se pudo encontrar la solicitud {$solicitud_id}");
            return;
        }

        error_log("[RFQ-FLOW] Verificando si es necesario actualizar el estado de la solicitud");
        
        // Actualizar el estado de la solicitud
        self::check_and_update_status($solicitud_id, $solicitud, true);
    }

    /**
     * Maneja el evento de aceptación de una cotización
     *
     * @since  0.1.0
     * @param  int $cotizacion_id ID de la cotización aceptada
     * @param  int $solicitud_id ID de la solicitud relacionada
     * @return void
     */
    public static function handle_cotizacion_accepted(int $cotizacion_id, int $solicitud_id): void {
        error_log("[RFQ-FLOW] Iniciando handle_cotizacion_accepted - Cotización: {$cotizacion_id}, Solicitud: {$solicitud_id}");
        
        // Verificar que la solicitud existe y está en un estado válido
        $solicitud = get_post($solicitud_id);
        if (!$solicitud || $solicitud->post_type !== 'solicitud') {
            error_log("[RFQ-ERROR] No se pudo encontrar la solicitud {$solicitud_id} o no es del tipo correcto");
            return;
        }
        
        error_log("[RFQ-FLOW] Solicitud encontrada. Estado actual: {$solicitud->post_status}");
        
        // Solo permitir la transición a aceptada si está en estado activo
        if ($solicitud->post_status === 'rfq-active') {
            error_log("[RFQ-FLOW] Solicitud está en estado activo, procediendo a actualizar a estado aceptado");
            self::update_status($solicitud_id, 'rfq-accepted');
        } else {
            error_log("[RFQ-ERROR] No se puede aceptar una cotización para una solicitud en estado {$solicitud->post_status}");
        }
    }
} 