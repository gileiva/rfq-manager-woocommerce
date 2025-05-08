<?php
/**
 * Shortcodes relacionados con solicitudes
 *
 * @package    GiVendor\GiPlugin\Shortcode
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Shortcode;

/**
 * SolicitudShortcodes - Implementa shortcodes para el sistema de solicitudes
 *
 * Esta clase proporciona shortcodes para mostrar listas de solicitudes
 * y manejar su visualización.
 *
 * @package    GiVendor\GiPlugin\Shortcode
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class SolicitudShortcodes {
    
    /**
     * Inicializa los shortcodes
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        // Registrar shortcodes
        add_shortcode('rfq_list', [self::class, 'render_rfq_list']);
        add_shortcode('rfq_view_solicitud', [self::class, 'render_solicitud_view']);
        
        // Agregar scripts y estilos
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);

        // Agregar endpoints AJAX
        add_action('wp_ajax_rfq_filter_solicitudes', [self::class, 'ajax_filter_solicitudes']);
        add_action('wp_ajax_nopriv_rfq_filter_solicitudes', [self::class, 'ajax_filter_solicitudes']);
        add_action('wp_ajax_rfq_cancel_solicitud', [self::class, 'ajax_cancel_solicitud']);
    }

    /**
     * Encola los scripts y estilos necesarios
     *
     * @since  0.1.0
     * @return void
     */
    public static function enqueue_assets(): void {
        // Encolar estilos
        wp_enqueue_style(
            'rfq-manager-styles',
            plugins_url('assets/css/rfq-manager.css', dirname(dirname(__FILE__))),
            [],
            '1.0.0'
        );

        // Encolar scripts
        wp_enqueue_script(
            'rfq-manager-scripts',
            plugins_url('assets/js/rfq-manager.js', dirname(dirname(__FILE__))),
            ['jquery'],
            '1.0.0',
            true
        );

        // Agregar traducciones y datos para JavaScript
        wp_localize_script('rfq-manager-scripts', 'rfqManagerL10n', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rfq_filter_nonce'),
            'loading' => __('Cargando...', 'rfq-manager-woocommerce'),
            'error' => __('Error al cargar las solicitudes', 'rfq-manager-woocommerce'),
            'cancelConfirm' => __('¿Estás seguro de que deseas cancelar esta solicitud?', 'rfq-manager-woocommerce'),
            'cancelSuccess' => __('Solicitud cancelada correctamente', 'rfq-manager-woocommerce'),
            'cancelError' => __('Error al cancelar la solicitud', 'rfq-manager-woocommerce'),
        ]);
    }

    /**
     * Obtiene las solicitudes según los parámetros
     *
     * @since  0.1.0
     * @param  array  $args Argumentos para la consulta
     * @return \WP_Query Objeto WP_Query con los resultados
     */
    private static function get_solicitudes($args): \WP_Query {
        $defaults = [
            'post_type' => 'solicitud',
            'posts_per_page' => 10,
            'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
            'post_status' => ['publish', 'rfq-pending', 'rfq-active'],
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $query_args = wp_parse_args($args, $defaults);
        
        
        $query = new \WP_Query($query_args);
        
                
        return $query;
    }

    /**
     * Renderiza la lista de solicitudes
     *
     * @since  0.1.0
     * @param  array  $atts Atributos del shortcode
     * @return string       Output HTML del shortcode
     */
    public static function render_rfq_list($atts): string {
        // Extraer atributos y aplicar valores por defecto
        $atts = shortcode_atts([
            'per_page' => 10,
            'status' => 'active',
            'orderby' => 'date',
            'order' => 'DESC',
        ], $atts, 'rfq_list');

        // Verificar si el usuario está logueado
        if (!is_user_logged_in()) {
            return '<p class="rfq-error">' . __('Debes iniciar sesión para ver las solicitudes.', 'rfq-manager-woocommerce') . '</p>';
        }

        // Agregar el modal de confirmación
        $output = '<div id="rfq-confirm-modal" class="rfq-modal" style="display: none;">
            <div class="rfq-modal-content">
                <h3>' . __('Confirmar Cancelación', 'rfq-manager-woocommerce') . '</h3>
                <p>' . __('¿Estás seguro de que deseas cancelar esta solicitud?', 'rfq-manager-woocommerce') . '</p>
                <div class="rfq-modal-buttons">
                    <button class="rfq-modal-cancel">' . __('No, volver', 'rfq-manager-woocommerce') . '</button>
                    <button class="rfq-modal-confirm">' . __('Sí, cancelar', 'rfq-manager-woocommerce') . '</button>
                </div>
            </div>
        </div>';

        // Obtener el usuario actual
        $user = wp_get_current_user();
        
        // Si es customer o subscriber, mostrar sus solicitudes
        if (in_array('customer', $user->roles) || in_array('subscriber', $user->roles)) {
            return self::render_customer_solicitudes($atts, $user->ID);
        }
        
        // Verificar si el usuario es administrador o proveedor
        if (!in_array('administrator', $user->roles) && !in_array('proveedor', $user->roles)) {
            return '<p class="rfq-error">' . __('No tienes permisos para ver las solicitudes.', 'rfq-manager-woocommerce') . '</p>';
        }

        // Obtener estados disponibles con conteo
        $statuses = self::get_available_statuses();

        // Inicio del contenedor
        $output .= '<div class="rfq-list-container">';
        
        // Filtro de estados
        $output .= '<div class="rfq-status-filter">';
        $output .= '<select id="rfq-status-filter" class="rfq-status-select">';
        $output .= '<option value="">' . __('Todos los estados', 'rfq-manager-woocommerce') . '</option>';
        
        foreach ($statuses as $status => $count) {
            if ($count > 0) {
                $output .= sprintf(
                    '<option value="%s">%s (%d)</option>',
                    esc_attr($status),
                    esc_html(self::get_status_label($status)),
                    $count
                );
            }
        }
        
        $output .= '</select>';
        $output .= '</div>';

        // Contenedor para la tabla de solicitudes
        $output .= '<div id="rfq-solicitudes-table-container">';
        $output .= self::render_solicitudes_table($atts);
        $output .= '</div>';

        $output .= '</div>';

        return $output;
    }

    /**
     * Renderiza la lista de solicitudes para customers
     *
     * @since  0.1.0
     * @param  array $atts Atributos del shortcode
     * @param  int   $user_id ID del usuario
     * @return string
     */
    private static function render_customer_solicitudes($atts, $user_id): string {
        // Preparar argumentos de la consulta
        $query_args = [
            'post_type' => 'solicitud',
            'posts_per_page' => intval($atts['per_page']),
            'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
            'author' => $user_id,
            'post_status' => ['publish', 'rfq-pending', 'rfq-active', 'rfq-closed', 'rfq-historic'],
            'orderby' => $atts['orderby'],
            'order' => $atts['order'],
        ];

        // Realizar la consulta
        $query = new \WP_Query($query_args);

        if (!$query->have_posts()) {
            return '<p class="rfq-notice">' . __('No tienes solicitudes pendientes.', 'rfq-manager-woocommerce') . '</p>';
        }

        $output = '<div class="rfq-customer-solicitudes">';
        $output .= '<h2>' . __('Mis Solicitudes', 'rfq-manager-woocommerce') . '</h2>';
        
        $output .= '<table class="rfq-customer-table">';
        $output .= '<thead><tr>';
        $output .= '<th>' . __('Fecha', 'rfq-manager-woocommerce') . '</th>';
        $output .= '<th>' . __('Nro. Solicitud', 'rfq-manager-woocommerce') . '</th>';
        $output .= '<th>' . __('Productos', 'rfq-manager-woocommerce') . '</th>';
        $output .= '<th>' . __('Estado', 'rfq-manager-woocommerce') . '</th>';
        $output .= '<th>' . __('Cotizaciones', 'rfq-manager-woocommerce') . '</th>';
        $output .= '<th>' . __('Acciones', 'rfq-manager-woocommerce') . '</th>';
        $output .= '</tr></thead><tbody>';

        while ($query->have_posts()) {
            $query->the_post();
            $solicitud_id = get_the_ID();
            $items = json_decode(get_post_meta($solicitud_id, '_solicitud_items', true), true);
            $estado = get_post_status();
            $order_id = get_post_meta($solicitud_id, '_solicitud_order_id', true);

            // Obtener número de cotizaciones
            $cotizaciones = get_posts([
                'post_type' => 'cotizacion',
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => '_solicitud_parent',
                        'value' => $solicitud_id,
                    ],
                ],
            ]);

            $output .= '<tr>';
            $output .= '<td>' . esc_html(get_the_date()) . '</td>';
            $output .= '<td>#' . esc_html($order_id) . '</td>';
            $output .= '<td>' . esc_html(count($items)) . '</td>';
            $output .= '<td class="' . esc_attr(self::get_status_class($estado)) . '">' . esc_html(self::get_status_label($estado)) . '</td>';
            $output .= '<td>' . esc_html(count($cotizaciones)) . '</td>';
            $output .= '<td style="display: flex; gap: 10px;">';
            
            // Botón de ver detalles
            $output .= '<a href="' . esc_url(add_query_arg(['solicitud_id' => $solicitud_id], home_url('/ver-solicitud/'))) . '" class="rfq-view-btn">' . __('Ver Detalles', 'rfq-manager-woocommerce') . '</a>';
            
            // Botón de cancelar (solo si está pendiente o activa)
            if (in_array($estado, ['rfq-pending', 'rfq-active'])) {
                $output .= '<a href="#" class="rfq-view-btn rfq-cancel-btn" style="background-color: #ffd700; opacity: 0.8;" data-solicitud="' . esc_attr($solicitud_id) . '">' . __('Cancelar solicitud', 'rfq-manager-woocommerce') . '</a>';
            }
            
            $output .= '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table>';

        // Paginación
        if ($query->max_num_pages > 1) {
            $output .= '<div class="rfq-pagination">';
            $output .= paginate_links([
                'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                'format' => '?paged=%#%',
                'current' => max(1, get_query_var('paged')),
                'total' => $query->max_num_pages,
                'prev_text' => __('&laquo; Anterior', 'rfq-manager-woocommerce'),
                'next_text' => __('Siguiente &raquo;', 'rfq-manager-woocommerce'),
            ]);
            $output .= '</div>';
        }

        wp_reset_postdata();
        $output .= '</div>';

        return $output;
    }

    /**
     * Renderiza la vista individual de una solicitud
     *
     * @since  0.1.0
     * @param  array  $atts Atributos del shortcode
     * @return string
     */
    public static function render_solicitud_view($atts): string {
        // Verificar si el usuario está logueado
        if (!is_user_logged_in()) {
            return '<p class="rfq-error">' . __('Debes iniciar sesión para ver esta solicitud.', 'rfq-manager-woocommerce') . '</p>';
        }

        // Obtener el ID de la solicitud
        $solicitud_id = isset($_GET['solicitud_id']) ? intval($_GET['solicitud_id']) : 0;
        if (!$solicitud_id) {
            return '<p class="rfq-error">' . __('ID de solicitud no válido.', 'rfq-manager-woocommerce') . '</p>';
        }

        // Verificar que la solicitud existe
        $solicitud = get_post($solicitud_id);
        if (!$solicitud || $solicitud->post_type !== 'solicitud') {
            return '<p class="rfq-error">' . __('La solicitud no existe.', 'rfq-manager-woocommerce') . '</p>';
        }

        // Verificar que el usuario es el autor de la solicitud
        $user = wp_get_current_user();
        if ($solicitud->post_author != $user->ID && !in_array('administrator', $user->roles)) {
            return '<p class="rfq-error">' . __('No tienes permisos para ver esta solicitud.', 'rfq-manager-woocommerce') . '</p>';
        }

        // Obtener los items de la solicitud
        $items = json_decode(get_post_meta($solicitud_id, '_solicitud_items', true), true);
        if (empty($items)) {
            return '<p class="rfq-error">' . __('No hay productos en esta solicitud.', 'rfq-manager-woocommerce') . '</p>';
        }

        $output = '<div class="rfq-solicitud-view">';
        
        // Encabezado
        $output .= '<div class="rfq-solicitud-header">';
        $output .= '<h2>' . sprintf(__('Solicitud #%s', 'rfq-manager-woocommerce'), get_post_meta($solicitud_id, '_solicitud_order_id', true)) . '</h2>';
        $output .= '<div class="rfq-solicitud-meta">';
        $output .= '<p class="rfq-meta-item">' . sprintf(
            __('Fecha: %s', 'rfq-manager-woocommerce'),
            get_the_date('', $solicitud_id)
        ) . '</p>';
        $output .= '<p class="rfq-meta-item rfq-status ' . esc_attr(self::get_status_class($solicitud->post_status)) . '">';
        $output .= '<span class="rfq-status-label">' . __('Estado:', 'rfq-manager-woocommerce') . '</span> ';
        $output .= '<span class="rfq-status-value">' . esc_html(self::get_status_label($solicitud->post_status)) . '</span>';
        $output .= '</p>';
        $output .= '</div>';
        $output .= '</div>';

        // Tabla de productos
        $output .= '<div class="rfq-solicitud-items">';
        $output .= '<h3>' . __('Productos Solicitados', 'rfq-manager-woocommerce') . '</h3>';
        $output .= '<table class="rfq-solicitud-table">';
        $output .= '<thead><tr>';
        $output .= '<th>' . __('Producto', 'rfq-manager-woocommerce') . '</th>';
        $output .= '<th>' . __('Cantidad', 'rfq-manager-woocommerce') . '</th>';
        $output .= '</tr></thead><tbody>';

        foreach ($items as $item) {
            $product = wc_get_product($item['product_id']);
            if (!$product) continue;

            $output .= '<tr>';
            $output .= '<td>' . esc_html($product->get_name()) . '</td>';
            $output .= '<td>' . esc_html($item['qty']) . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table>';
        $output .= '</div>';

        // Tabla de cotizaciones
        $cotizaciones = get_posts([
            'post_type' => 'cotizacion',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_solicitud_parent',
                    'value' => $solicitud_id,
                ],
            ],
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        if (!empty($cotizaciones)) {
            $output .= '<div class="rfq-cotizaciones-received">';
            $output .= '<h3>' . __('Cotizaciones Recibidas', 'rfq-manager-woocommerce') . '</h3>';
            $output .= '<table class="rfq-cotizaciones-table">';
            $output .= '<thead><tr>';
            $output .= '<th>' . __('Fecha', 'rfq-manager-woocommerce') . '</th>';
            $output .= '<th>' . __('Proveedor', 'rfq-manager-woocommerce') . '</th>';
            $output .= '<th>' . __('Total', 'rfq-manager-woocommerce') . '</th>';
            $output .= '</tr></thead><tbody>';

            foreach ($cotizaciones as $cotizacion) {
                $proveedor = get_userdata($cotizacion->post_author);
                $total = get_post_meta($cotizacion->ID, '_total', true);
                
                // Obtener logo del proveedor
                $logo_url = get_user_meta($cotizacion->post_author, 'proveedor_logo', true);
                $logo_html = $logo_url ? '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($proveedor->display_name) . '" class="rfq-proveedor-logo">' : '';

                $output .= '<tr>';
                $output .= '<td>' . esc_html(get_the_date('', $cotizacion->ID)) . '</td>';
                $output .= '<td class="rfq-proveedor-info">';
                $output .= $logo_html;
                $output .= '<span class="rfq-proveedor-nombre">' . esc_html($proveedor->display_name) . '</span>';
                $output .= '</td>';
                $output .= '<td class="rfq-total">' . esc_html(number_format((float)$total, 2, ',', '.') . ' €') . '</td>';
                $output .= '</tr>';
            }

            $output .= '</tbody></table>';
            $output .= '</div>';
        }

        // Botón de cancelar si está pendiente o activa
        if (in_array($solicitud->post_status, ['rfq-pending', 'rfq-active'])) {
            $output .= '<div class="rfq-solicitud-actions">';
            $output .= '<a href="#" class="rfq-view-btn rfq-cancel-btn" data-solicitud="' . esc_attr($solicitud_id) . '">' . __('Cancelar solicitud', 'rfq-manager-woocommerce') . '</a>';
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Renderiza la tabla de solicitudes
     *
     * @since  0.1.0
     * @param  array $args Argumentos para la consulta
     * @return string      HTML de la tabla
     */
    private static function render_solicitudes_table($args): string {
        // Preparar argumentos de la consulta
        $query_args = [
            'post_type' => 'solicitud',
            'posts_per_page' => intval($args['per_page']),
            'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
            'orderby' => $args['orderby'],
            'order' => $args['order'],
        ];

        // Aplicar el estado si está especificado
        if (isset($args['post_status'])) {
            $query_args['post_status'] = $args['post_status'];
        } else {
            $query_args['post_status'] = ['publish', 'rfq-pending', 'rfq-active', 'rfq-closed', 'rfq-historic'];
        }

        // Realizar la consulta
        $query = new \WP_Query($query_args);

        // Verificar si hay errores en la consulta
        if (is_wp_error($query)) {
            return '<p class="rfq-error">' . __('Error al cargar las solicitudes.', 'rfq-manager-woocommerce') . '</p>';
        }

        // Si no hay posts, mostrar mensaje
        if (!$query->have_posts()) {
            return '<p class="rfq-notice">' . __('No hay solicitudes disponibles.', 'rfq-manager-woocommerce') . '</p>';
        }

        $output = '<table class="rfq-list-table">';
        $output .= '<thead>';
        $output .= '<tr>';
        $output .= '<th>' . __('ID', 'rfq-manager-woocommerce') . '</th>';
        $output .= '<th>' . __('Usuario', 'rfq-manager-woocommerce') . '</th>';
        $output .= '<th>' . __('Fecha', 'rfq-manager-woocommerce') . '</th>';
        $output .= '<th>' . __('Estado', 'rfq-manager-woocommerce') . '</th>';
        $output .= '<th>' . __('Productos', 'rfq-manager-woocommerce') . '</th>';
        $output .= '<th>' . __('Acciones', 'rfq-manager-woocommerce') . '</th>';
        $output .= '</tr>';
        $output .= '</thead>';
        $output .= '<tbody>';

        while ($query->have_posts()) {
            $query->the_post();
            $solicitud_id = get_the_ID();
            $post = get_post($solicitud_id);

            // Verificar y actualizar el estado antes de mostrarlo
            \GiVendor\GiPlugin\Solicitud\SolicitudStatusHandler::check_and_update_status($solicitud_id, $post, true);

            $author_id = get_post_field('post_author', $solicitud_id);
            $items = json_decode(get_post_meta($solicitud_id, '_solicitud_items', true), true);
            $estado = get_post_status();
            $order_id = get_post_meta($solicitud_id, '_solicitud_order_id', true);

            $output .= '<tr>';
            $output .= '<td>#' . esc_html($order_id) . '</td>';
            $output .= '<td>' . esc_html(get_the_author_meta('display_name', $author_id)) . '</td>';
            $output .= '<td>' . esc_html(get_the_date()) . '</td>';
            $output .= '<td class="' . esc_attr(self::get_status_class($estado)) . '">' . esc_html(self::get_status_label($estado)) . '</td>';
            $output .= '<td>' . esc_html(count($items)) . '</td>';
            $output .= '<td style="display: flex; gap: 10px;">';
            
            // Botón de ver detalles - Redirigir según el rol del usuario
            $user = wp_get_current_user();
            $view_url = in_array('proveedor', $user->roles) 
                ? add_query_arg(['solicitud_id' => $solicitud_id], home_url('/cotizar-solicitud/'))
                : add_query_arg(['solicitud_id' => $solicitud_id], home_url('/ver-solicitud/'));
            
            $output .= '<a href="' . esc_url($view_url) . '" class="rfq-view-btn">' . __('Ver Detalles', 'rfq-manager-woocommerce') . '</a>';
            
            $output .= '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody>';
        $output .= '</table>';

        // Paginación
        if ($query->max_num_pages > 1) {
            $output .= '<div class="rfq-pagination">';
            $output .= paginate_links([
                'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                'format' => '?paged=%#%',
                'current' => max(1, get_query_var('paged')),
                'total' => $query->max_num_pages,
                'prev_text' => __('&laquo; Anterior', 'rfq-manager-woocommerce'),
                'next_text' => __('Siguiente &raquo;', 'rfq-manager-woocommerce'),
            ]);
            $output .= '</div>';
        }

        wp_reset_postdata();

        return $output;
    }

    /**
     * Obtiene los estados disponibles con su conteo
     *
     * @since  0.1.0
     * @return array Array con estados y su conteo
     */
    private static function get_available_statuses(): array {
        $statuses = [
            'rfq-pending' => 0,
            'rfq-active' => 0,
            'rfq-closed' => 0,
            'rfq-historic' => 0
        ];

        $query = new \WP_Query([
            'post_type' => 'solicitud',
            'posts_per_page' => -1,
            'post_status' => array_keys($statuses),
            'fields' => 'ids'
        ]);

        foreach ($query->posts as $post_id) {
            $status = get_post_status($post_id);
            if (isset($statuses[$status])) {
                $statuses[$status]++;
            }
        }

        return $statuses;
    }

    /**
     * Maneja la petición AJAX para filtrar solicitudes
     *
     * @since  0.1.0
     * @return void
     */
    public static function ajax_filter_solicitudes(): void {
        check_ajax_referer('rfq_filter_nonce', 'nonce');

        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        
        $args = [
            'per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        // Si se seleccionó un estado específico, aplicarlo a la consulta
        if (!empty($status)) {
            $args['post_status'] = $status;
        } else {
            $args['post_status'] = ['publish', 'rfq-pending', 'rfq-active', 'rfq-closed', 'rfq-historic'];
        }

        echo self::render_solicitudes_table($args);
        wp_die();
    }

    /**
     * Maneja la cancelación de una solicitud vía AJAX
     *
     * @since  0.1.0
     * @return void
     */
    public static function ajax_cancel_solicitud(): void {
        check_ajax_referer('rfq_filter_nonce', 'nonce');

        $solicitud_id = isset($_POST['solicitud_id']) ? intval($_POST['solicitud_id']) : 0;
        if (!$solicitud_id) {
            wp_send_json_error(__('ID de solicitud no válido.', 'rfq-manager-woocommerce'));
        }

        // Verificar que la solicitud existe
        $solicitud = get_post($solicitud_id);
        if (!$solicitud || $solicitud->post_type !== 'solicitud') {
            wp_send_json_error(__('La solicitud no existe.', 'rfq-manager-woocommerce'));
        }

        // Verificar que el usuario es el autor de la solicitud
        $user = wp_get_current_user();
        if ($solicitud->post_author != $user->ID && !in_array('administrator', $user->roles)) {
            wp_send_json_error(__('No tienes permisos para cancelar esta solicitud.', 'rfq-manager-woocommerce'));
        }

        // Verificar que la solicitud está en un estado que permite cancelación
        if (!in_array($solicitud->post_status, ['rfq-pending', 'rfq-active'])) {
            wp_send_json_error(__('Esta solicitud no puede ser cancelada.', 'rfq-manager-woocommerce'));
        }

        // Actualizar el estado a histórico
        $updated = wp_update_post([
            'ID' => $solicitud_id,
            'post_status' => 'rfq-historic'
        ]);

        if (is_wp_error($updated)) {
            wp_send_json_error(__('Error al cancelar la solicitud.', 'rfq-manager-woocommerce'));
        }

        wp_send_json_success(__('Solicitud cancelada correctamente.', 'rfq-manager-woocommerce'));
    }

    /**
     * Obtiene el label del estado de la solicitud
     *
     * @since  0.1.0
     * @param  string $status Estado de la solicitud
     * @return string         Label del estado
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

    private static function get_status_class(string $status): string {
        $classes = [
            'rfq-pending'  => 'status-pending',
            'rfq-active'   => 'status-active',
            'rfq-closed'   => 'status-closed',
            'rfq-historic' => 'status-historic',
        ];

        return $classes[$status] ?? '';
    }
} 