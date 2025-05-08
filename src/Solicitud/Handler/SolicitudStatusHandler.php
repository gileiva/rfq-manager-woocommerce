<?php
/**
 * Manejador de estados de solicitud
 *
 * @package    GiVendor\GiPlugin\Solicitud\Handler
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Solicitud\Handler;

/**
 * SolicitudStatusHandler - Maneja los estados de las solicitudes
 *
 * Esta clase es responsable de registrar y gestionar los estados de las solicitudes.
 *
 * @package    GiVendor\GiPlugin\Solicitud\Handler
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
        add_action('init', [self::class, 'register_statuses']);
        add_action('admin_init', [self::class, 'handle_status_transitions']);
        add_filter('display_post_states', [self::class, 'modify_post_states'], 10, 2);
        add_action('save_post_solicitud', [self::class, 'check_expiry_date'], 20);
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
     * Maneja las transiciones de estado
     *
     * @since  0.1.0
     * @return void
     */
    public static function handle_status_transitions(): void {
        add_action('transition_post_status', [self::class, 'on_status_transition'], 10, 3);
    }

    /**
     * Maneja la transición de estado de una solicitud
     *
     * @since  0.1.0
     * @param  string  $new_status Nuevo estado
     * @param  string  $old_status Estado anterior
     * @param  \WP_Post $post     Objeto post
     * @return void
     */
    public static function on_status_transition(string $new_status, string $old_status, \WP_Post $post): void {
        if ($post->post_type !== 'solicitud') {
            return;
        }

        // Si se está actualizando la fecha de expiración, mantener el estado actual
        if (isset($_POST['_solicitud_expiry'])) {
            return;
        }

        // Lógica de transición de estados
        switch ($new_status) {
            case 'publish':
                // Verificar si hay cotizaciones
                $cotizaciones = get_posts([
                    'post_type' => 'cotizacion',
                    'meta_key' => '_solicitud_parent',
                    'meta_value' => $post->ID,
                    'posts_per_page' => 1
                ]);
                
                if (!empty($cotizaciones)) {
                    $post->post_status = 'rfq-active';
                } else {
                    $post->post_status = 'rfq-pending';
                }
                break;

            case 'rfq-closed':
                // Verificar si hay una cotización aceptada
                $cotizacion_aceptada = get_posts([
                    'post_type' => 'cotizacion',
                    'meta_key' => '_solicitud_parent',
                    'meta_value' => $post->ID,
                    'meta_query' => [
                        [
                            'key' => '_aceptada',
                            'value' => '1'
                        ]
                    ],
                    'posts_per_page' => 1
                ]);

                if (!empty($cotizacion_aceptada)) {
                    $post->post_status = 'rfq-closed';
                }
                break;
        }

        wp_update_post($post);
    }

    /**
     * Verifica la fecha de expiración y actualiza el estado si es necesario
     *
     * @since  0.1.0
     * @param  int $post_id ID del post
     * @return void
     */
    public static function check_expiry_date(int $post_id): void {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'solicitud') {
            return;
        }

        $expiry_date = get_post_meta($post_id, '_solicitud_expiry', true);
        if (!$expiry_date) {
            return;
        }

        // Si la fecha de expiración ha pasado y no hay cotización aceptada
        if (strtotime($expiry_date) < time()) {
            $cotizacion_aceptada = get_posts([
                'post_type' => 'cotizacion',
                'meta_key' => '_solicitud_parent',
                'meta_value' => $post_id,
                'meta_query' => [
                    [
                        'key' => '_aceptada',
                        'value' => '1'
                    ]
                ],
                'posts_per_page' => 1
            ]);

            if (empty($cotizacion_aceptada)) {
                $post->post_status = 'rfq-historic';
                wp_update_post($post);
            }
        }
    }

    /**
     * Modifica los estados mostrados en el admin
     *
     * @since  0.1.0
     * @param  array    $post_states Estados actuales
     * @param  \WP_Post $post       Objeto post
     * @return array               Estados modificados
     */
    public static function modify_post_states(array $post_states, \WP_Post $post): array {
        if ($post->post_type === 'solicitud') {
            $status = get_post_status($post->ID);
            if ($status === 'publish') {
                // Mantener el estado actual basado en las condiciones
                $cotizaciones = get_posts([
                    'post_type' => 'cotizacion',
                    'meta_key' => '_solicitud_parent',
                    'meta_value' => $post->ID,
                    'posts_per_page' => 1
                ]);

                if (!empty($cotizaciones)) {
                    $post_states['publish'] = __('Activa', 'rfq-manager-woocommerce');
                } else {
                    $post_states['publish'] = __('Pendiente de cotización', 'rfq-manager-woocommerce');
                }
            }
        }
        return $post_states;
    }
} 