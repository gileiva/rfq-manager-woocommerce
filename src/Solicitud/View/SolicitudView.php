<?php
/**
 * SolicitudView - Vista para el post type 'solicitud'
 *
 * @package    GiVendor\GiPlugin\Solicitud\View
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Solicitud\View;

/**
 * SolicitudView - Clase que maneja la vista y los metaboxes de las solicitudes
 *
 * @package    GiVendor\GiPlugin\Solicitud\View
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class SolicitudView {
    
    /**
     * Inicializa la vista
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        add_action('add_meta_boxes', [self::class, 'add_meta_boxes']);
        add_action('save_post_solicitud', [self::class, 'save_meta_boxes']);
    }

    /**
     * Agrega los metaboxes a la página de edición
     *
     * @since  0.1.0
     * @return void
     */
    public static function add_meta_boxes(): void {
        add_meta_box(
            'solicitud_details',
            __('Detalles de la Solicitud', 'rfq-manager-woocommerce'),
            [self::class, 'render_detalles_metabox'],
            'solicitud',
            'normal',
            'high'
        );

        add_meta_box(
            'solicitud_status',
            __('Estado de la Solicitud', 'rfq-manager-woocommerce'),
            [self::class, 'render_status_metabox'],
            'solicitud',
            'side',
            'high'
        );
    }

    /**
     * Renderiza el metabox de detalles
     *
     * @since  0.1.0
     * @param  \WP_Post $post Objeto post
     * @return void
     */
    public static function render_detalles_metabox(\WP_Post $post): void {
        wp_nonce_field('solicitud_details_nonce', 'solicitud_details_nonce');

        $items = get_post_meta($post->ID, '_solicitud_items', true);
        $items = is_array($items) ? $items : [];

        echo '<div class="solicitud-items">';
        echo '<table class="widefat">';
        echo '<thead><tr>';
        echo '<th>' . __('Producto', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('Cantidad', 'rfq-manager-woocommerce') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($items as $item) {
            $product = wc_get_product($item['product_id']);
            if (!$product) continue;

            echo '<tr>';
            echo '<td>' . esc_html($product->get_name()) . '</td>';
            echo '<td>' . esc_html($item['qty']) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    /**
     * Renderiza el metabox de estado
     *
     * @since  0.1.0
     * @param  \WP_Post $post Objeto post
     * @return void
     */
    public static function render_status_metabox(\WP_Post $post): void {
        wp_nonce_field('solicitud_status_nonce', 'solicitud_status_nonce');

        // Verificar y actualizar el estado antes de mostrarlo
        \GiVendor\GiPlugin\Solicitud\SolicitudStatusHandler::check_and_update_status($post->ID, $post, true);

        $expiry_date = get_post_meta($post->ID, '_solicitud_expiry', true);
        $expiry_date = $expiry_date ? date('Y-m-d\TH:i', strtotime($expiry_date)) : '';

        echo '<p>';
        echo '<label for="solicitud_expiry">' . __('Fecha de expiración:', 'rfq-manager-woocommerce') . '</label><br>';
        echo '<input type="datetime-local" id="solicitud_expiry" name="_solicitud_expiry" value="' . esc_attr($expiry_date) . '" class="widefat">';
        echo '</p>';

        // Mostrar estado actual
        $status = get_post_status($post->ID);
        $status_label = self::get_status_label($status);
        $status_class = 'solicitud-status-' . sanitize_html_class($status);
        
        echo '<p class="solicitud-status-wrapper">';
        echo '<strong>' . __('Estado actual:', 'rfq-manager-woocommerce') . '</strong><br>';
        echo '<span class="solicitud-status ' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span>';
        echo '</p>';

        // Mostrar información adicional
        $cotizaciones = get_posts([
            'post_type'      => 'cotizacion',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'   => '_solicitud_parent',
                    'value' => $post->ID,
                ],
            ],
        ]);

        echo '<p>';
        echo '<strong>' . __('Cotizaciones recibidas:', 'rfq-manager-woocommerce') . '</strong><br>';
        echo count($cotizaciones) . ' ' . __('cotizaciones', 'rfq-manager-woocommerce');
        echo '</p>';
    }

    /**
     * Guarda los datos de los metaboxes
     *
     * @since  0.1.0
     * @param  int $post_id ID del post
     * @return void
     */
    public static function save_meta_boxes(int $post_id): void {
        // Verificar nonce de detalles
        if (!isset($_POST['solicitud_details_nonce']) || !wp_verify_nonce($_POST['solicitud_details_nonce'], 'solicitud_details_nonce')) {
            return;
        }

        // Verificar nonce de estado
        if (!isset($_POST['solicitud_status_nonce']) || !wp_verify_nonce($_POST['solicitud_status_nonce'], 'solicitud_status_nonce')) {
            return;
        }

        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Guardar fecha de expiración
        if (isset($_POST['_solicitud_expiry'])) {
            $expiry_date = sanitize_text_field($_POST['_solicitud_expiry']);
            update_post_meta($post_id, '_solicitud_expiry', $expiry_date);
        }
    }

    /**
     * Obtiene la etiqueta legible para un estado
     *
     * @since  0.1.0
     * @param  string $status Estado a traducir
     * @return string        Etiqueta traducida
     */
    private static function get_status_label(string $status): string {
        $labels = [
            'rfq-pending'  => __('Pendiente de cotización', 'rfq-manager-woocommerce'),
            'rfq-active'   => __('Activa', 'rfq-manager-woocommerce'),
            'rfq-closed'   => __('Cerrada', 'rfq-manager-woocommerce'),
            'rfq-historic' => __('Histórica', 'rfq-manager-woocommerce'),
        ];

        return $labels[$status] ?? $status;
    }
} 