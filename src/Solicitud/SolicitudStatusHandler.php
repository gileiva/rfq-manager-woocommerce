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
        add_action('save_post_solicitud', [__CLASS__, 'check_and_update_status'], 10, 3);
        add_action('save_post_cotizacion', [__CLASS__, 'update_solicitud_status'], 10, 3);
        add_action('rfq_cotizacion_accepted', [__CLASS__, 'handle_cotizacion_accepted'], 10, 1);
        
        // Agregar acción para limpiar la caché cuando se actualiza el estado
        add_action('post_updated', [__CLASS__, 'clear_status_cache'], 10, 3);
    }

    /**
     * Registra los estados personalizados para las solicitudes
     *
     * @since  0.1.0
     * @return void
     */
    public static function register_statuses(): void {
        register_post_status('rfq-pending', [
            'label'                     => __('Pendiente de cotización', 'rfq-manager-woocommerce'),
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

        register_post_status('rfq-closed', [
            'label'                     => __('Cerrada', 'rfq-manager-woocommerce'),
            'public'                    => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Cerrada (%s)', 'Cerradas (%s)', 'rfq-manager-woocommerce'),
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
     * Verifica y actualiza el estado de una solicitud
     *
     * @since  0.1.0
     * @param  int     $post_id ID del post
     * @param  \WP_Post $post   Objeto post
     * @param  bool    $update  Si es una actualización
     * @return void
     */
    public static function check_and_update_status(int $post_id, \WP_Post $post, bool $update): void {
        // No actualizar en autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // No actualizar en revisiones
        if ($post->post_type !== 'solicitud' || wp_is_post_revision($post_id)) {
            return;
        }

        // Verificar si la solicitud ha expirado
        $expiry_date = get_post_meta($post_id, '_solicitud_expiry', true);
        if ($expiry_date && strtotime($expiry_date) < time()) {
            self::update_status($post_id, 'rfq-historic');
            return;
        }

        // Verificar si hay cotizaciones
        $cotizaciones = get_posts([
            'post_type'      => 'cotizacion',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_solicitud_parent',
                    'value' => $post_id,
                ],
            ],
        ]);

        if (empty($cotizaciones)) {
            self::update_status($post_id, 'rfq-pending');
        } else {
            self::update_status($post_id, 'rfq-active');
        }
    }

    /**
     * Actualiza el estado de una solicitud cuando se guarda una cotización
     *
     * @since  0.1.0
     * @param  int     $post_id ID del post
     * @param  \WP_Post $post   Objeto post
     * @param  bool    $update  Si es una actualización
     * @return void
     */
    public static function update_solicitud_status(int $post_id, \WP_Post $post, bool $update): void {
        if ($post->post_type !== 'cotizacion') {
            return;
        }

        $solicitud_id = get_post_meta($post_id, '_solicitud_parent', true);
        if (!$solicitud_id) {
            return;
        }

        self::check_and_update_status($solicitud_id, get_post($solicitud_id), true);
    }

    /**
     * Maneja el evento de aceptación de una cotización
     *
     * @since  0.1.0
     * @param  int $cotizacion_id ID de la cotización aceptada
     * @return void
     */
    public static function handle_cotizacion_accepted(int $cotizacion_id): void {
        $solicitud_id = get_post_meta($cotizacion_id, '_solicitud_parent', true);
        if ($solicitud_id) {
            self::update_status($solicitud_id, 'rfq-closed');
        }
    }

    /**
     * Actualiza el estado de una solicitud
     *
     * @since  0.1.0
     * @param  int    $post_id ID del post
     * @param  string $status  Nuevo estado
     * @return void
     */
    private static function update_status(int $post_id, string $status): void {
        $current_status = get_post_status($post_id);
        if ($current_status !== $status) {
            // Actualizar el estado
            wp_update_post([
                'ID'          => $post_id,
                'post_status' => $status,
            ]);

            // Limpiar caché
            clean_post_cache($post_id);
            delete_transient('rfq_solicitud_status_' . $post_id);
            wp_cache_delete($post_id, 'post_meta');

            // Registrar el cambio en el log
            error_log(sprintf(
                'RFQ - Estado de solicitud #%d actualizado de %s a %s',
                $post_id,
                $current_status,
                $status
            ));

            // Disparar acción para notificar el cambio de estado
            do_action('rfq_solicitud_status_changed', $post_id, $status, $current_status);
        }
    }
} 