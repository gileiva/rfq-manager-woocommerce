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
        
        // Endpoint para aceptar cotización
        add_action('wp_ajax_accept_quote', [self::class, 'ajax_accept_quote']);
        add_action('wp_ajax_nopriv_accept_quote', [self::class, 'ajax_accept_quote']);
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
            RFQ_MANAGER_WOO_VERSION
        );

        // Encolar estilos específicos para solicitudes de usuario
        wp_enqueue_style(
            'rfq-user-solicitud-styles',
            plugins_url('assets/css/user-solicitud.css', dirname(dirname(__FILE__))),
            [],
            '1.0.0'
        );

        // Encolar estilos de modales
        wp_enqueue_style(
            'rfq-modals-styles',
            plugins_url('assets/css/rfq-modals.css', dirname(dirname(__FILE__))),
            [],
            RFQ_MANAGER_WOO_VERSION
        );

        // Encolar scripts
        wp_enqueue_script(
            'rfq-manager-scripts',
            plugins_url('assets/js/rfq-manager.js', dirname(dirname(__FILE__))),
            ['jquery'],
            RFQ_MANAGER_WOO_VERSION,
            true
        );

        // Encolar scripts de modales
        // Solo encolar el script si hay botones de cancelar en el DOM (o si estamos en la página con solicitudes)
        $should_enqueue_modals = false;

        if ($post && is_a($post, 'WP_Post')) {
            if (has_shortcode($post->post_content, 'rfq_list') || has_shortcode($post->post_content, 'rfq_view_solicitud')) {
                $should_enqueue_modals = true;
            }

            if ($post->post_type === 'solicitud' || is_singular('solicitud')) {
                $should_enqueue_modals = true;
            }
        }

        if ($should_enqueue_modals) {
            wp_enqueue_script(
                'rfq-modals-scripts',
                plugins_url('assets/js/rfq-modals.js', dirname(dirname(__FILE__))),
                ['jquery', 'rfq-manager-scripts'],
                RFQ_MANAGER_WOO_VERSION,
                true
            );
        }

        // Encolar script de filtros dinámicos solo en páginas de solicitudes de usuario
        if (
            $post && is_a($post, 'WP_Post') &&
            (has_shortcode($post->post_content, 'rfq_list') || has_shortcode($post->post_content, 'rfq_view_solicitud'))
        ) {
            wp_enqueue_script(
                'rfq-filters-scripts',
                plugins_url('assets/js/rfq-filters.js', dirname(dirname(__FILE__))),
                ['jquery', 'rfq-manager-scripts'],
                RFQ_MANAGER_WOO_VERSION,
                true
            );
        }

        // Agregar traducciones y datos para JavaScript
        wp_localize_script('rfq-modals-scripts', 'rfqManagerL10n', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rfq_solicitud_status_nonce'),
            'loading' => __('Cargando...', 'rfq-manager-woocommerce'),
            'error' => __('Error al cargar las solicitudes', 'rfq-manager-woocommerce'),
            'cancelConfirmTitle' => __('Confirmar Cancelación', 'rfq-manager-woocommerce'),
            'cancelConfirm' => __('¿Estás seguro de que deseas cancelar esta solicitud?', 'rfq-manager-woocommerce'),
            'cancelNo' => __('No, volver', 'rfq-manager-woocommerce'),
            'cancelYes' => __('Sí, cancelar', 'rfq-manager-woocommerce'),
            'cancelSuccess' => __('Solicitud cancelada correctamente', 'rfq-manager-woocommerce'),
            'cancelError' => __('Error al cancelar la solicitud', 'rfq-manager-woocommerce'),
            'showDetails' => __('Ver Detalles', 'rfq-manager-woocommerce'),
            'hideDetails' => __('Ocultar Detalles', 'rfq-manager-woocommerce'),
            'completePrices' => __('Por favor, completa todos los precios antes de enviar.', 'rfq-manager-woocommerce'),
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

        // Estilos para notificaciones tipo toast
        $output = '<style>
        .rfq-toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4caf50;
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            z-index: 9999;
            min-width: 300px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            font-weight: 500;
            animation: rfqSlideIn 0.3s ease-out;
        }
        .rfq-toast-notification.error {
            background: #f44336;
        }
        @keyframes rfqSlideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        </style>';

        // Obtener el usuario actual
        $user = wp_get_current_user();
        
        // Si es customer o subscriber, mostrar sus solicitudes
        if (in_array('customer', $user->roles) || in_array('subscriber', $user->roles)) {
            return $output . self::render_customer_solicitudes($atts, $user->ID);
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
    private static function render_customer_solicitudes($atts, $user_id, $selected_status = ''): string {
        // Obtener estados disponibles con conteo SOLO del usuario
        $statuses = self::get_status_counts($user_id);

        // Usar el estado seleccionado recibido (AJAX) o el de $_GET
        if (empty($selected_status)) {
            $selected_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        }

        // Inicio del contenedor
        $output = '<div class="rfq-list-container">';

        // Tabs de estado
        $output .= self::render_status_tabs($statuses, $selected_status);

        // Contenedor para la tabla de solicitudes
        $output .= '<div id="rfq-solicitudes-table-container">';
        // Preparar argumentos de la consulta
        $query_args = [
            'post_type' => 'solicitud',
            'posts_per_page' => intval($atts['per_page']),
            'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
            'author' => $user_id,
            'post_status' => ['publish', 'rfq-pending', 'rfq-active', 'rfq-accepted', 'rfq-closed', 'rfq-historic'],
            'orderby' => $atts['orderby'],
            'order' => $atts['order'],
        ];
        if (!empty($selected_status)) {
            $query_args['post_status'] = $selected_status;
        }

        // Realizar la consulta
        $query = new \WP_Query($query_args);

        if (!$query->have_posts()) {
            $output .= '<p class="rfq-notice">' . __('No tienes solicitudes pendientes.', 'rfq-manager-woocommerce') . '</p>';
        } else {
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
                $post = get_post($solicitud_id);
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

                $output .= '<tr data-solicitud-id="' . esc_attr($solicitud_id) . '">';
                $output .= '<td>' . esc_html(get_the_date()) . '</td>';
                $output .= '<td>#' . esc_html($order_id) . '</td>';
                $output .= '<td>' . esc_html(count($items)) . '</td>';
                $output .= '<td class="rfq-status-cell ' . esc_attr(self::get_status_class($estado)) . '">' . esc_html(self::get_status_label($estado)) . '</td>';
                $output .= '<td>' . esc_html(count($cotizaciones)) . '</td>';
                $output .= '<td style="display: flex; gap: 10px;">';
                // Botón de ver detalles usando el slug
                $output .= '<a href="' . esc_url(home_url('/ver-solicitud/' . $post->post_name . '/')) . '" class="rfq-view-btn">' . __('Ver Detalles', 'rfq-manager-woocommerce') . '</a>';
                // Botón de cancelar (solo si está pendiente o activa)
                if (in_array($estado, ['rfq-pending', 'rfq-active'])) {
                    $output .= '<button type="button" class="rfq-cancel-btn rfq-cancel-icon" id="rfq-cancel-btn-' . esc_attr($solicitud_id) . '" data-solicitud="' . esc_attr($solicitud_id) . '" title="Cancelar solicitud" style="background: none; border: none; padding: 0; cursor: pointer;">'
                        . '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10" cy="10" r="10" fill="#fff"/><path d="M6 6L14 14M14 6L6 14" stroke="#dc3545" stroke-width="3" stroke-linecap="round"/></svg>'
                        . '</button>';
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
        }

        wp_reset_postdata();
        $output .= '</div>'; // Cerrar contenedor de tabla
        $output .= '</div>'; // Cerrar contenedor principal

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
            return '<p class="rfq-error">' . esc_html__('Debes iniciar sesión para ver esta solicitud.', 'rfq-manager-woocommerce') . '</p>';
        }

        $output = '';

        // Obtener el ID de la solicitud desde la URL
        global $wp;
        $current_url = home_url($wp->request);
        $solicitud_slug = sanitize_title(basename(parse_url($current_url, PHP_URL_PATH)));

        // Buscar la solicitud por slug
        $solicitud = get_page_by_path($solicitud_slug, OBJECT, 'solicitud');

        if (!$solicitud) {
            return '<p class="rfq-error">' . esc_html__('La solicitud no existe.', 'rfq-manager-woocommerce') . '</p>';
        }

        $solicitud_id = $solicitud->ID;

        // Verificar que el usuario es el autor de la solicitud o administrador
        $user = wp_get_current_user();
        $is_author = ((int)$solicitud->post_author === (int)$user->ID);
        $is_admin = in_array('administrator', (array)$user->roles, true);
        $is_proveedor = in_array('proveedor', (array)$user->roles, true);
        
        // Si la solicitud está cerrada, aceptada o histórica, solo el autor o admin pueden verla
        if (in_array($solicitud->post_status, ['rfq-accepted', 'rfq-closed', 'rfq-historic'])) {
            if (!$is_author && !$is_admin) {
                return '<p class="rfq-error">' . esc_html__('Esta solicitud ya no está disponible.', 'rfq-manager-woocommerce') . '</p>';
            }
        }

        // Obtener los items de la solicitud
        $items = json_decode(get_post_meta($solicitud_id, '_solicitud_items', true), true);
        if (empty($items)) {
            return '<p class="rfq-error">' . esc_html__('No hay productos en esta solicitud.', 'rfq-manager-woocommerce') . '</p>';
        }

        $output .= '<div class="rfq-cotizar-container">';
        
        // Cabecera unificada
        $output .= '<div class="rfq-solicitud-header">';
        $output .= '<h2>' . sprintf(__('Solicitud de %s', 'rfq-manager-woocommerce'), get_the_author_meta('display_name', $solicitud->post_author)) . '</h2>';
        $output .= '<div class="rfq-solicitud-meta">';
        
        // Solicitante y fecha
        $output .= '<p class="rfq-meta-item">' . sprintf(
            __('Solicitado por %s el %s', 'rfq-manager-woocommerce'),
            get_the_author_meta('display_name', $solicitud->post_author),
            get_the_date('', $solicitud_id)
        ) . '</p>';
        
        // Estado - CORREGIDO: usar las clases adecuadas
        $status_class = 'rfq-status status-' . esc_attr(str_replace('rfq-', '', $solicitud->post_status));
        $output .= '<p class="rfq-meta-item">';
        $output .= '<span class="rfq-status-label">' . __('Estado:', 'rfq-manager-woocommerce') . '</span> ';
        $output .= '<span class="' . $status_class . '">' . esc_html(self::get_status_label($solicitud->post_status)) . '</span>';
        $output .= '</p>';
        
        // Fecha de expiración
        $expiry_date = get_post_meta($solicitud_id, '_solicitud_expiry', true);
        if ($expiry_date) {
            $expiry_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($expiry_date));
            $output .= '<p class="rfq-meta-item rfq-expiry">';
            $output .= '<span class="rfq-expiry-label">' . __('Vence el:', 'rfq-manager-woocommerce') . '</span> ';
            $output .= '<span class="rfq-expiry-value">' . esc_html($expiry_formatted) . '</span>';
            $output .= '</p>';
        }
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
            'post_status' => ['publish', 'rfq-accepted', 'rfq-historic'],
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        if (!empty($cotizaciones)) {
            // Buscar si hay una cotización aceptada
            $cotizacion_aceptada_id = null;
            foreach ($cotizaciones as $cotizacion) {
                if ($cotizacion->post_status === 'rfq-accepted') {
                    $cotizacion_aceptada_id = $cotizacion->ID;
                    break;
                }
            }
            
            $output .= '<div class="rfq-cotizaciones-received">';
            $output .= '<h3>' . __('Cotizaciones Recibidas', 'rfq-manager-woocommerce') . '</h3>';
            $output .= '<table class="rfq-cotizaciones-table">';
            $output .= '<thead><tr>';
            $output .= '<th>' . __('Fecha', 'rfq-manager-woocommerce') . '</th>';
            $output .= '<th>' . __('Proveedor', 'rfq-manager-woocommerce') . '</th>';
            $output .= '<th>' . __('Total', 'rfq-manager-woocommerce') . '</th>';
            $output .= '<th>' . __('Estado', 'rfq-manager-woocommerce') . '</th>';
            $output .= '<th>' . __('Acciones', 'rfq-manager-woocommerce') . '</th>';
            $output .= '</tr></thead><tbody>';

            foreach ($cotizaciones as $cotizacion) {
                $proveedor = get_userdata($cotizacion->post_author);
                $total = get_post_meta($cotizacion->ID, '_total', true);
                $logo_url = get_user_meta($cotizacion->post_author, 'proveedor_logo', true);
                $logo_html = $logo_url ? '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($proveedor->display_name) . '" class="rfq-proveedor-logo">' : '';
                $is_accepted = ($cotizacion->post_status === 'rfq-accepted');
                $is_historic = ($cotizacion->post_status === 'rfq-historic');
                $row_class = $is_accepted ? 'rfq-cotizacion-aceptada' : ($is_historic ? 'rfq-cotizacion-historica' : '');
                
                $output .= '<tr class="' . esc_attr($row_class) . '" data-cotizacion-id="' . esc_attr($cotizacion->ID) . '">';
                $output .= '<td>' . esc_html(get_the_date('', $cotizacion->ID)) . '</td>';
                $output .= '<td class="rfq-proveedor-info">' . $logo_html . '<span class="rfq-proveedor-nombre">' . esc_html($proveedor->display_name) . '</span></td>';
                $output .= '<td class="rfq-total">' . esc_html(number_format((float)$total, 2, ',', '.') . ' €') . '</td>';
                $output .= '<td>';
                if ($is_accepted) {
                    $output .= '<span class="rfq-estado rfq-estado-aceptada">' . __('Aceptada', 'rfq-manager-woocommerce') . '</span>';
                } elseif ($is_historic) {
                    $output .= '<span class="rfq-estado rfq-estado-historica">' . __('Histórica', 'rfq-manager-woocommerce') . '</span>';
                } else {
                    $output .= '<span class="rfq-estado rfq-estado-pendiente">' . __('Pendiente', 'rfq-manager-woocommerce') . '</span>';
                }
                $output .= '</td>';
                $output .= '<td>';
                if ($is_accepted) {
                    $output .= '<button type="button" class="button rfq-aceptar-cotizacion-btn" disabled style="background:#4caf50;color:#fff;cursor:default;">' . __('Aceptada', 'rfq-manager-woocommerce') . '</button>';
                    $output .= ' <button type="button" class="button rfq-pagar-cotizacion-btn" data-cotizacion-id="' . esc_attr($cotizacion->ID) . '">' . __('Pagar', 'rfq-manager-woocommerce') . '</button>';
                } elseif ($solicitud->post_status !== 'rfq-historic' && $solicitud->post_status !== 'rfq-closed' && $solicitud->post_status !== 'rfq-accepted' && !$is_historic && !$cotizacion_aceptada_id) {
                    // Solo mostrar botón aceptar si la solicitud no está cerrada/histórica/aceptada y no hay otra cotización aceptada
                    $output .= '<button type="button" class="rfq-aceptar-cotizacion-btn button" data-cotizacion-id="' . esc_attr($cotizacion->ID) . '">' . __('Aceptar', 'rfq-manager-woocommerce') . '</button>';
                }
                $output .= '</td>';
                $output .= '</tr>';
            }

            $output .= '</tbody></table>';
            $output .= '</div>';
        }

        // Botón de cancelar si está pendiente o activa
        if (in_array($solicitud->post_status, ['rfq-pending', 'rfq-active'])) {
            $output .= '<div class="rfq-solicitud-actions">';
            $output .= '<button type="button" class="rfq-cancel-btn" data-solicitud="' . esc_attr($solicitud_id) . '" title="Cancelar solicitud">' . __('Cancelar Solicitud', 'rfq-manager-woocommerce') . '</button>';
            $output .= '</div>';
        }

        $output .= '</div>';
        $output .= '</div>';

        // Modal de confirmación para aceptar cotización
        $output .= '<div id="rfq-aceptar-modal" class="rfq-modal" style="display:none;">'
            . '<div class="rfq-modal-content">'
            . '<h3>' . __('Confirmar aceptación', 'rfq-manager-woocommerce') . '</h3>'
            . '<p>' . __('¿Estás seguro de que deseas aceptar esta cotización? Esta acción no se puede deshacer.', 'rfq-manager-woocommerce') . '</p>'
            . '<div class="rfq-modal-buttons">'
            . '<button class="rfq-modal-cancel">' . __('No, volver', 'rfq-manager-woocommerce') . '</button>'
            . '<button class="rfq-modal-confirm-aceptar">' . __('Sí, aceptar', 'rfq-manager-woocommerce') . '</button>'
            . '</div>'
            . '</div>'
            . '</div>';

        // Script para manejar los modales y acciones AJAX (código existente sin cambios...)
        $output .= '<script>
        jQuery(function($){
            // Función para mostrar notificación toast
            function showToast(message, isError = false) {
                const toast = $("<div class=\"rfq-toast-notification" + (isError ? " error" : "") + "\">" + message + "</div>");
                $("body").append(toast);
                
                setTimeout(function() {
                    toast.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 4000);
            }
            
            // Manejar aceptación de cotización
            var cotizacionId = null;
            $(".rfq-aceptar-cotizacion-btn").on("click", function(){
                cotizacionId = $(this).data("cotizacion-id");
                $("#rfq-aceptar-modal").fadeIn();
            });
            
            $("#rfq-aceptar-modal .rfq-modal-cancel").on("click", function(){
                cotizacionId = null;
                $("#rfq-aceptar-modal").fadeOut();
            });
            
            $("#rfq-aceptar-modal .rfq-modal-confirm-aceptar").on("click", function(){
                if (!cotizacionId) return;
                var $confirmBtn = $(this);
                $confirmBtn.prop("disabled", true);
                
                $.post(rfqManagerL10n.ajaxurl, {
                    action: "accept_quote",
                    cotizacion_id: cotizacionId,
                    nonce: rfqManagerL10n.nonce
                }, function(resp){
                    if (resp.success) {
                        // Cerrar modal primero
                        $("#rfq-aceptar-modal").fadeOut();
                        
                        // Actualizar la UI en lugar de recargar
                        var $row = $("tr[data-cotizacion-id=\"" + cotizacionId + "\"]");
                        var $button = $row.find(".rfq-aceptar-cotizacion-btn");
                        
                        // Cambiar el botón "Aceptar" por "Aceptada" (deshabilitado)
                        $button.replaceWith("<button type=\"button\" class=\"button rfq-aceptar-cotizacion-btn\" disabled style=\"background:#4caf50;color:#fff;cursor:default;\">' . esc_js(__('Aceptada', 'rfq-manager-woocommerce')) . '</button>");
                        
                        // Agregar botón "Pagar" al lado
                        $row.find("td:last-child").append(" <button type=\"button\" class=\"button button-primary rfq-pagar-cotizacion-btn\" data-cotizacion-id=\"" + cotizacionId + "\">' . esc_js(__('Pagar', 'rfq-manager-woocommerce')) . '</button>");
                        
                        // Inhabilitar todos los otros botones "Aceptar"
                        $(".rfq-aceptar-cotizacion-btn").not(":disabled").each(function() {
                            $(this).prop("disabled", true).addClass("rfq-disabled");
                            $(this).closest("tr").addClass("rfq-cotizacion-no-aceptada");
                        });
                        
                        // Resaltar la fila aceptada
                        $row.addClass("rfq-cotizacion-aceptada").removeClass("rfq-cotizacion-no-aceptada");
                        $row.find("td:nth-child(4)").html("<span class=\"rfq-estado rfq-estado-aceptada\">' . esc_js(__('Aceptada', 'rfq-manager-woocommerce')) . '</span>");
                        
                        // Agregar handler para el botón pagar
                        $row.find(".rfq-pagar-cotizacion-btn").on("click", function() {
                            var cotizacionIdPagar = $(this).data("cotizacion-id");
                            // Crear la URL para la página de pago
                            var paymentUrl = "' . esc_js(home_url('/pagar-cotizacion/')) . '" + cotizacionIdPagar + "/";
                            window.location.href = paymentUrl;
                        });
                        
                                                
                        showToast("' . esc_js(__('Cotización aceptada correctamente', 'rfq-manager-woocommerce')) . '");
                        
                        // Redireccionar después de 2 segundos para que vea el cambio
                        setTimeout(function() {
                            var paymentUrl = "' . esc_js(home_url('/pagar-cotizacion/')) . '" + cotizacionId + "/";
                            window.location.href = paymentUrl;
                        }, 2000);
                    } else {
                        showToast(resp.data && resp.data.msg ? resp.data.msg : "Error inesperado", true);
                        $("#rfq-aceptar-modal").fadeOut();
                    }
                    $confirmBtn.prop("disabled", false);
                });
            });
            
            // Handler para botones de pago ya existentes (cotizaciones ya aceptadas)
            $(".rfq-pagar-cotizacion-btn").on("click", function() {
                var cotizacionIdPagar = $(this).data("cotizacion-id");
                var paymentUrl = "' . esc_js(home_url('/pagar-cotizacion/')) . '" + cotizacionIdPagar + "/";
                window.location.href = paymentUrl;
            });
            
            // Manejar cancelación de solicitud (código existente...)
            $(".rfq-cancel-btn").on("click", function(e) {
                e.preventDefault();
                var solicitudId = $(this).data("solicitud");
                
                $("#rfq-confirm-modal").fadeIn();
                
                $("#rfq-confirm-modal .rfq-modal-confirm").off("click.cancel").on("click.cancel", function() {
                    var $button = $(this);
                    $button.prop("disabled", true);
                    
                    $.ajax({
                        url: rfqManagerL10n.ajaxurl,
                        type: "POST",
                        data: {
                            action: "update_solicitud_status",
                            rfq_nonce: rfqManagerL10n.nonce,
                            solicitud_id: solicitudId,
                            solicitud_status: "rfq-historic"
                        },
                        success: function(response) {
                            if (response.success) {
                                showToast(rfqManagerL10n.cancelSuccess);
                                setTimeout(function() {
                                    window.location.reload();
                                }, 1500);
                            } else {
                                showToast(response.data ? response.data.message : rfqManagerL10n.cancelError, true);
                            }
                        },
                        error: function() {
                            showToast(rfqManagerL10n.cancelError, true);
                        },
                        complete: function() {
                            $("#rfq-confirm-modal").fadeOut();
                            $button.prop("disabled", false);
                        }
                    });
                });
            });
        });
        </script>';

        // Actualizar estilos para resaltar y opacar filas
        $output .= '<style>
        .rfq-cotizacion-aceptada { background: #e8f5e9 !important; }
        .rfq-cotizacion-aceptada td { color: #256029; font-weight: bold; }
        .rfq-cotizacion-no-aceptada { opacity: 0.6; }
        .rfq-cotizacion-no-aceptada .rfq-aceptar-cotizacion-btn { display: none !important; }
        .rfq-aceptar-cotizacion-btn.rfq-disabled { 
            background: #ccc !important; 
            color: #666 !important; 
            cursor: not-allowed !important; 
        }
        .rfq-pagar-cotizacion-btn {
            background: #007cba;
            border-color: #005a87;
            color: #fff;
        }
        .rfq-pagar-cotizacion-btn:hover {
            background: #005a87;
            border-color: #003f5c;
        }
        </style>';

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
            
            // Botón de ver detalles - Redirigir según el rol del usuario y estado de la solicitud
            $user = wp_get_current_user();
            $view_url = '';
            
            // Si la solicitud está cerrada, aceptada o histórica, solo el autor o admin pueden verla
            if (in_array($estado, ['rfq-accepted', 'rfq-closed', 'rfq-historic'])) {
                $is_author = ((int)$author_id === (int)$user->ID);
                $is_admin = in_array('administrator', $user->roles);
                
                if ($is_author || $is_admin) {
                    $view_url = home_url('/ver-solicitud/' . $post->post_name . '/');
                }
            } else {
                // Para solicitudes activas/pendientes, aplicar lógica normal
                if (in_array('proveedor', $user->roles)) {
                    // Si es proveedor, va al formulario de cotización
                    $view_url = home_url('/cotizar-solicitud/' . $post->post_name . '/');
                } else {
                    // Si es usuario o customer, va a la vista de detalles
                    $view_url = home_url('/ver-solicitud/' . $post->post_name . '/');
                }
            }
            
            if ($view_url) {
                $output .= '<a href="' . esc_url($view_url) . '" class="rfq-view-btn">' . __('Ver Detalles', 'rfq-manager-woocommerce') . '</a>';
            } else {
                $output .= '<span class="rfq-no-access">' . __('No disponible', 'rfq-manager-woocommerce') . '</span>';
            }
            
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
            'rfq-accepted' => 0,
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
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'rfq_solicitud_status_nonce')) {
            wp_send_json_error(['message' => __('Error de seguridad. Por favor, recarga la página.', 'rfq-manager-woocommerce')]);
        }

        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $args = [
            'per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        // Si el usuario es customer o subscriber, devolver su tabla personalizada
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('customer', $user->roles) || in_array('subscriber', $user->roles)) {
                // Pasar el estado seleccionado al método personalizado
                echo self::render_customer_solicitudes($args, $user->ID, $status);
                wp_die();
            }
        }

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
     * AJAX: Aceptar cotización
     *
     * @since  0.1.0
     * @return void
     */
    public static function ajax_accept_quote(): void {
        if (!is_user_logged_in()) {
            wp_send_json_error(['msg' => __('Debes iniciar sesión para aceptar una cotización.', 'rfq-manager-woocommerce')]);
        }
        
        $user_id = get_current_user_id();
        $cotizacion_id = absint($_POST['cotizacion_id'] ?? 0);
        $nonce = $_POST['nonce'] ?? '';
        
        if (!$cotizacion_id || !wp_verify_nonce($nonce, 'rfq_solicitud_status_nonce')) {
            wp_send_json_error(['msg' => __('Solicitud inválida.', 'rfq-manager-woocommerce')]);
        }
        
        $cotizacion = get_post($cotizacion_id);
        if (!$cotizacion || $cotizacion->post_type !== 'cotizacion') {
            wp_send_json_error(['msg' => __('Cotización no encontrada.', 'rfq-manager-woocommerce')]);
        }
        
        // Verificar que el usuario sea el autor de la solicitud asociada
        $solicitud_id = get_post_meta($cotizacion_id, '_solicitud_parent', true);
        $solicitud = get_post($solicitud_id);
        if (!$solicitud || (int)$solicitud->post_author !== (int)$user_id) {
            wp_send_json_error(['msg' => __('No tienes permisos para aceptar esta cotización.', 'rfq-manager-woocommerce')]);
        }
        
        // Verificar que la solicitud aún permite aceptar cotizaciones
        if (in_array($solicitud->post_status, ['rfq-accepted', 'rfq-closed', 'rfq-historic'])) {
            wp_send_json_error(['msg' => __('Esta solicitud ya no acepta nuevas cotizaciones.', 'rfq-manager-woocommerce')]);
        }
        
        // Cambiar el estado de la cotización a "aceptada"
        $result = wp_update_post([
            'ID' => $cotizacion_id,
            'post_status' => 'rfq-accepted',
        ], true);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['msg' => __('No se pudo aceptar la cotización.', 'rfq-manager-woocommerce')]);
        }
        
        // Cambiar el estado de la solicitud a "Aceptada"
        $solicitud_update = \GiVendor\GiPlugin\Solicitud\SolicitudStatusHandler::update_status($solicitud_id, 'rfq-accepted');
        
        if (!$solicitud_update) {
            // Si falla el cambio de estado de la solicitud, revertir la cotización
            wp_update_post([
                'ID' => $cotizacion_id,
                'post_status' => 'publish',
            ]);
            wp_send_json_error(['msg' => __('Error al actualizar el estado de la solicitud.', 'rfq-manager-woocommerce')]);
        }
        
        // Marcar todas las demás cotizaciones como históricas
        $other_cotizaciones = get_posts([
            'post_type' => 'cotizacion',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_solicitud_parent',
                    'value' => $solicitud_id,
                ],
            ],
            'post__not_in' => [$cotizacion_id],
            'post_status' => 'publish',
        ]);
        
        foreach ($other_cotizaciones as $other_cotizacion) {
            wp_update_post([
                'ID' => $other_cotizacion->ID,
                'post_status' => 'rfq-historic',
            ]);
        }

        // Disparar el hook de cotización aceptada
        do_action('rfq_cotizacion_accepted', $cotizacion_id, $solicitud_id);
        
        wp_send_json_success(['msg' => __('Cotización aceptada correctamente.', 'rfq-manager-woocommerce')]);
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
            'rfq-accepted' => __('Aceptada', 'rfq-manager-woocommerce'),
            'rfq-closed'   => __('Cerrada', 'rfq-manager-woocommerce'),
            'rfq-historic' => __('Histórica', 'rfq-manager-woocommerce'),
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * Obtiene la clase CSS para el estado de la solicitud
     *
     * @since  0.1.0
     * @param  string $status Estado de la solicitud
     * @return string         Clase CSS
     */
    private static function get_status_class(string $status): string {
        $classes = [
            'rfq-pending'  => 'status-pending',
            'rfq-active'   => 'status-active',
            'rfq-accepted' => 'status-accepted',
            'rfq-closed'   => 'status-closed',
            'rfq-historic' => 'status-historic',
        ];

        return $classes[$status] ?? '';
    }

    /**
     * Obtiene el conteo de solicitudes por estado
     *
     * @since  0.1.0
     * @param  int|null $user_id ID del usuario (opcional)
     * @return array              Array con el conteo de solicitudes por estado
     */
    private static function get_status_counts($user_id = null): array {
        $statuses = [
            'rfq-pending' => 0,
            'rfq-active' => 0,
            'rfq-accepted' => 0,
            'rfq-closed' => 0,
            'rfq-historic' => 0
        ];

        $query_args = [
            'post_type' => 'solicitud',
            'posts_per_page' => -1,
            'post_status' => array_keys($statuses),
            'fields' => 'ids'
        ];

        if ($user_id) {
            $query_args['author'] = $user_id;
        }

        $query = new \WP_Query($query_args);

        foreach ($query->posts as $post_id) {
            $status = get_post_status($post_id);
            if (isset($statuses[$status])) {
                $statuses[$status]++;
            }
        }

        return $statuses;
    }

    /**
     * Renderiza las pestañas de estado
     *
     * @since  0.1.0
     * @param  array  $statuses Estados disponibles
     * @param  string $selected Estado seleccionado
     * @return string          HTML de las pestañas de estado
     */
    private static function render_status_tabs($statuses, $selected = ''): string {
        $output = '<div class="rfq-status-tabs">';
        $output .= sprintf(
            '<button class="rfq-status-tab%s" data-status="">%s</button>',
            ($selected === '' ? ' active' : ''),
            esc_html__('Todos', 'rfq-manager-woocommerce')
        );
        foreach ($statuses as $status => $count) {
            if ($count > 0) {
                $active = ($status === $selected) ? ' active' : '';
                $output .= sprintf(
                    '<button class="rfq-status-tab%s" data-status="%s">%s (%d)</button>',
                    $active,
                    esc_attr($status),
                    esc_html(self::get_status_label($status)),
                    $count
                );
            }
        }
        $output .= '</div>';
        return $output;
    }
}
