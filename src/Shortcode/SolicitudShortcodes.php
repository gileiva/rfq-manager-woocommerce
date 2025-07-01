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
        // Registrar el nuevo shortcode de filtros independientes
        add_shortcode('rfq_filters', [\GiVendor\GiPlugin\Shortcode\SolicitudFiltersShortcode::class, 'render_filters']);
        
        // Agregar scripts y estilos
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);

        // Agregar endpoints AJAX
        add_action('wp_ajax_rfq_filter_solicitudes', [self::class, 'ajax_filter_solicitudes']);
        add_action('wp_ajax_nopriv_rfq_filter_solicitudes', [self::class, 'ajax_filter_solicitudes']);
        
        // Endpoint para aceptar cotización
        add_action('wp_ajax_accept_quote', [self::class, 'ajax_accept_quote']);
        add_action('wp_ajax_nopriv_accept_quote', [self::class, 'ajax_accept_quote']);

        // Endpoint para repetir solicitud
        add_action('wp_ajax_repeat_solicitud', [self::class, 'ajax_repeat_solicitud']);

        // Endpoint para cancelar solicitud
        add_action('wp_ajax_cancel_solicitud', [self::class, 'ajax_cancel_solicitud']);
    }

    /**
     * Encola los scripts y estilos necesarios
     *
     * @since  0.1.0
     * @return void
     */
    public static function enqueue_assets(): void {
        // Encolar CSS exclusivo de filtros
        wp_enqueue_style(
            'rfq-filters',
            plugins_url('assets/css/rfq-filters.css', dirname(dirname(__FILE__))),
            [],
            '1.0.0'
        );
        // Verificar si estamos en una página que necesita los scripts
        $is_rfq_page = false;
        $should_enqueue_modals = false;
        
        global $post;
        if ($post) {
            // Verificar si el contenido tiene alguno de nuestros shortcodes
            $has_rfq_list = has_shortcode($post->post_content, 'rfq_list');
            $has_rfq_view = has_shortcode($post->post_content, 'rfq_view_solicitud');
            
            // Solo encolar modals si es rfq_list o rfq_view_solicitud Y el usuario es customer o subscriber
            if (($has_rfq_list || $has_rfq_view) && is_user_logged_in()) {
                $user = wp_get_current_user();
                if (
                    in_array('customer', $user->roles) ||
                    in_array('subscriber', $user->roles) ||
                    in_array('proveedor', $user->roles) ||
                    in_array('administrator', $user->roles)
                ) {
                    $should_enqueue_modals = true;
                    $is_rfq_page = true;
                }
            }

            // Mantener compatibilidad para otros scripts (admin/proveedor)
            if (isset($post) && $post->post_type === 'solicitud') {
                $is_rfq_page = true;
            }
            if (is_singular('solicitud')) {
                $is_rfq_page = true;
            }
        }
        // Si no estamos en una página RFQ, no encolar nada
        if (!$is_rfq_page && !$should_enqueue_modals) {
            return;
        }
        // Encolar estilos
        wp_enqueue_style(
            'rfq-manager-styles',
            plugins_url('assets/css/rfq-manager.css', dirname(dirname(__FILE__))),
            [],
            RFQ_MANAGER_WOO_VERSION
        );
        wp_enqueue_style(
            'rfq-user-solicitud-styles',
            plugins_url('assets/css/user-solicitud.css', dirname(dirname(__FILE__))),
            [],
            '1.0.0'
        );
        wp_enqueue_style(
            'rfq-modals-styles',
            plugins_url('assets/css/rfq-modals.css', dirname(dirname(__FILE__))),
            [],
            RFQ_MANAGER_WOO_VERSION
        );
        // Encolar scripts principales
        wp_enqueue_script(
            'rfq-manager-scripts',
            plugins_url('assets/js/rfq-manager.js', dirname(dirname(__FILE__))),
            ['jquery'],
            RFQ_MANAGER_WOO_VERSION,
            true
        );
        // Encolar script de layout dinámico de productos para cualquier usuario que vea rfq_list o rfq_view_solicitud
        if (($has_rfq_list || $has_rfq_view) && is_user_logged_in()) {
            wp_enqueue_script(
                'rfq-list-layout',
                plugins_url('assets/js/rfq-list-layout.js', dirname(dirname(__FILE__))),
                [],
                '1.0.0',
                true
            );
        }
        // Encolar CSS de layout de lista SIEMPRE que sea una página RFQ relevante (para todos los roles)
        if ($is_rfq_page) {
            wp_enqueue_style(
                'rfq-list-layout',
                plugins_url('assets/css/rfq-list-layout.css', dirname(dirname(__FILE__))),
                [],
                RFQ_MANAGER_WOO_VERSION
            );
        }
        // Encolar rfq-modals.js SOLO si corresponde
        if ($should_enqueue_modals) {
            wp_enqueue_script(
                'rfq-modals-scripts',
                plugins_url('assets/js/rfq-modals.js', dirname(dirname(__FILE__))),
                ['jquery', 'rfq-manager-scripts'],
                RFQ_MANAGER_WOO_VERSION,
                true
            );
            // Obtener el usuario actual
            $user = wp_get_current_user();
            $is_user_logged_in = is_user_logged_in();
            $can_cancel_solicitud = false;
            if ($is_user_logged_in) {
                $can_cancel_solicitud = in_array('administrator', $user->roles) || in_array('customer', $user->roles);
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
                'isUserLoggedIn' => $is_user_logged_in,
                'canCancelSolicitud' => $can_cancel_solicitud,
                'loginRequired' => __('Debes iniciar sesión para realizar esta acción.', 'rfq-manager-woocommerce'),
                'noPermission' => __('No tienes permisos para cancelar esta solicitud.', 'rfq-manager-woocommerce'),
                'acceptQuoteConfirmTitle' => __('Confirmar aceptación', 'rfq-manager-woocommerce'),
                'acceptQuoteConfirmMessage' => __('¿Estás seguro de que deseas aceptar esta cotización? Esta acción no se puede deshacer.', 'rfq-manager-woocommerce'),
                'acceptQuoteYes' => __('Sí, aceptar', 'rfq-manager-woocommerce'),
                'acceptQuoteNo' => __('No, volver', 'rfq-manager-woocommerce'),
                'acceptQuoteSuccess' => __('Cotización aceptada correctamente', 'rfq-manager-woocommerce'),
                'acceptQuoteError' => __('Error al aceptar la cotización.', 'rfq-manager-woocommerce'),
                'quoteAcceptedText' => __('Aceptada', 'rfq-manager-woocommerce'),
                'quotePayText' => __('Pagar', 'rfq-manager-woocommerce'),
                'quoteAcceptingText' => __('Aceptando...', 'rfq-manager-woocommerce'),
                'unexpectedError' => __('Error inesperado', 'rfq-manager-woocommerce'),
                'paymentPageUrl' => home_url('/pagar-cotizacion/'),
                'cartUrl' => wc_get_cart_url(),
                'repeatTitle' => __('Productos no disponibles', 'rfq-manager-woocommerce'),
                'repeatSummary' => __('Los siguientes productos no pudieron ser agregados al carrito:', 'rfq-manager-woocommerce'),
                'close' => __('Cerrar', 'rfq-manager-woocommerce'),
                'continueToCart' => __('Ir al carrito', 'rfq-manager-woocommerce')
            ]);
        }
        // Encolar rfq-list-layout.css y rfq-filters.js si la página tiene el shortcode [rfq_list] y el usuario es customer, proveedor o admin
        if ($post && has_shortcode($post->post_content, 'rfq_list')) {
            $user = wp_get_current_user();
            if (is_user_logged_in() && (in_array('customer', $user->roles) || in_array('subscriber', $user->roles) || in_array('proveedor', $user->roles) || in_array('administrator', $user->roles))) {
                wp_enqueue_style(
                    'rfq-list-layout',
                    plugins_url('assets/css/rfq-list-layout.css', dirname(dirname(__FILE__))),
                    [],
                    '1.0.0'
                );
                wp_enqueue_script(
                    'rfq-filters-scripts',
                    plugins_url('assets/js/rfq-filters.js', dirname(dirname(__FILE__))),
                    ['jquery', 'rfq-manager-scripts'],
                    RFQ_MANAGER_WOO_VERSION,
                    true
                );
            }
        }
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
    public static function render_rfq_list($atts = []): string {
        // Refactor: delega en la nueva clase renderer
        // No modificar lógica ni orden de métodos existentes
        // \use RFQManager\Solicitud\View\SolicitudListRenderer;
        $renderer = new \GiVendor\GiPlugin\Solicitud\View\SolicitudListRenderer();
        return $renderer->render();
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
        // Obtener estados disponibles con conteo SOLO del usuario
        $statuses = \GiVendor\GiPlugin\Shortcode\Components\SolicitudFilters::get_status_counts($user_id);

        // Leer el estado seleccionado (por ahora, desde GET o vaco)
        $selected_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

        // Inicio del contenedor
        $output = '<div class="rfq-list-container">';

        // Tabs de estado + dropdown de orden + título (cabecera visual)
        $selected_order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'desc';
        $output .= \GiVendor\GiPlugin\Shortcode\Components\SolicitudFilters::render_filter_header($statuses, $selected_status, $selected_order);

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

        // Realizar la consulta
        $query = new \WP_Query($query_args);

        if (!$query->have_posts()) {
            $output .= '<p class="rfq-notice">' . __('No tienes solicitudes pendientes.', 'rfq-manager-woocommerce') . '</p>';
        } else {
            $output .= '<table class="rfq-customer-table">';
            $output .= '<thead><tr>';
            $output .= '<th>' . __('Fecha', 'rfq-manager-woocommerce') . '</th>';
            $output .= '<th>' . __('Referencia', 'rfq-manager-woocommerce') . '</th>';
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
                
                // Obtener el UUID y formatear el ID de la solicitud
                $uuid = get_post_meta($solicitud_id, '_solicitud_uuid', true);
                $formatted_id = $uuid ? 'RFQ-' . substr(str_replace('-', '', $uuid), -5) : '';

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
                $output .= '<td>' . esc_html($formatted_id) . '</td>';
                $output .= '<td>' . esc_html(count($items)) . '</td>';
                $output .= '<td class="rfq-status-cell ' . esc_attr(self::get_status_class($estado)) . '">' . esc_html(self::get_status_label($estado)) . '</td>';
                $output .= '<td>' . esc_html(count($cotizaciones)) . '</td>';
                $output .= '<td style="display: flex; gap: 10px;">';
                
                // Botón de ver detalles usando el slug
                $output .= '<a href="' . esc_url(home_url('/ver-solicitud/' . $post->post_name . '/')) . '" class="rfq-view-btn">' . __('Ver Detalles', 'rfq-manager-woocommerce') . '</a>';
                
                // Botón de repetir solicitud - solo para estados históricos o aceptados
                if (in_array($estado, ['rfq-historic', 'rfq-accepted'])) {
                    // Verificar si el usuario actual es el propietario de la solicitud
                    $current_user_id = get_current_user_id();
                    $solicitud_author_id = get_post_field('post_author', $solicitud_id);
                    
                    if ($current_user_id === (int)$solicitud_author_id) {
                        error_log(sprintf('[RFQ] Mostrando botón repetir para solicitud #%d con estado %s - Usuario propietario', $solicitud_id, $estado));
                        $output .= '<button type="button" class="rfq-repeat-btn" data-solicitud="' . esc_attr($solicitud_id) . '" title="Repetir solicitud">' . __('Repetir', 'rfq-manager-woocommerce') . '</button>';
                    } else {
                        error_log(sprintf('[RFQ] Ocultando botón repetir para solicitud #%d con estado %s - Usuario no propietario', $solicitud_id, $estado));
                    }
                } else {
                    error_log(sprintf('[RFQ] Ocultando botón repetir para solicitud #%d con estado %s', $solicitud_id, $estado));
                }
                
                // Botón de cancelar (solo si el handler lo permite)
                if (\GiVendor\GiPlugin\Solicitud\SolicitudCancelationHandler::can_cancel(wp_get_current_user(), $post)) {
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

        $slug = get_query_var('rfq_slug');

        if (empty($slug)) {
            return '<div class="rfq-preview-message" style="padding:1em;background:#f8f9fa;border:1px solid #eee;border-radius:4px;color:#666;">'
                . 'Vista previa: esta página mostrará el detalle de una solicitud cuando se acceda desde un enlace como <code>/ver-solicitud/{slug}</code>.'
                . '</div>';
        }

        $solicitud = get_page_by_path($slug, OBJECT, 'solicitud');
        if (!$solicitud) {
            return '<div class="rfq-error" style="padding:1em;background:#fff3cd;border:1px solid #ffeeba;border-radius:4px;color:#856404;">'
                . '⚠️ No se encontró la solicitud. Verificá el enlace o seleccioná una solicitud desde tu panel.'
                . '</div>';
        }

        if (!is_user_logged_in()) {
            return '<p class="rfq-error">' . esc_html__('Debes iniciar sesión para ver esta solicitud.', 'rfq-manager-woocommerce') . '</p>';
        }

        $solicitud_id = $solicitud->ID;
        $user = wp_get_current_user();
        $is_author = ((int)$solicitud->post_author === (int)$user->ID);
        $is_admin = in_array('administrator', (array)$user->roles, true);
        $is_proveedor = in_array('proveedor', (array)$user->roles, true);

        if (in_array($solicitud->post_status, ['rfq-accepted', 'rfq-closed', 'rfq-historic'])) {
            if (!$is_author && !$is_admin) {
                return '<p class="rfq-error">' . esc_html__('Esta solicitud ya no está disponible.', 'rfq-manager-woocommerce') . '</p>';
            }
        }

        $items = json_decode(get_post_meta($solicitud_id, '_solicitud_items', true), true);
        if (empty($items)) {
            return '<p class="rfq-error">' . esc_html__('No hay productos en esta solicitud.', 'rfq-manager-woocommerce') . '</p>';
        }

        $output = '';
        $output .= '<div class="rfq-cotizar-container">';
        $output .= '<div class="rfq-solicitud-header">';
        $output .= '<h2>' . sprintf(__('Solicitud de %s', 'rfq-manager-woocommerce'), get_the_author_meta('display_name', $solicitud->post_author)) . '</h2>';
        $output .= '<div class="rfq-solicitud-meta">';
        $output .= '<p class="rfq-meta-item">' . sprintf(
            __('Solicitado por %s el %s', 'rfq-manager-woocommerce'),
            get_the_author_meta('display_name', $solicitud->post_author),
            get_the_date('', $solicitud_id)
        ) . '</p>';
        $status_class = 'rfq-status status-' . esc_attr(str_replace('rfq-', '', $solicitud->post_status));
        $output .= '<p class="rfq-meta-item">';
        $output .= '<span class="rfq-status-label">' . __('Estado:', 'rfq-manager-woocommerce') . '</span> ';
        $output .= '<span class="' . $status_class . '">' . esc_html(self::get_status_label($solicitud->post_status)) . '</span>';
        $output .= '</p>';
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
                    $output .= '<button type="button" class="rfq-aceptar-cotizacion-btn button" data-cotizacion-id="' . esc_attr($cotizacion->ID) . '">' . __('Aceptar', 'rfq-manager-woocommerce') . '</button>';
                }
                $output .= '</td>';
                $output .= '</tr>';
            }
            $output .= '</tbody></table>';
            $output .= '</div>';
        }
        if (\GiVendor\GiPlugin\Solicitud\SolicitudCancelationHandler::can_cancel(wp_get_current_user(), $solicitud)) {
            $output .= '<div class="rfq-solicitud-actions">';
            $output .= '<button type="button" class="rfq-cancel-btn" data-solicitud="' . esc_attr($solicitud_id) . '" title="Cancelar solicitud">' . __('Cancelar Solicitud', 'rfq-manager-woocommerce') . '</button>';
            $output .= '</div>';
        }
        if (\GiVendor\GiPlugin\Solicitud\SolicitudRepeatHandler::can_repeat(wp_get_current_user(), $solicitud)) {
            $output .= '<div class="rfq-solicitud-actions">';
            $output .= '<button type="button" class="rfq-repeat-btn" data-solicitud="' . esc_attr($solicitud_id) . '" title="Repetir solicitud">' . __('Repetir Solicitud', 'rfq-manager-woocommerce') . '</button>';
            $output .= '</div>';
        }
        $output .= '</div>';
        $output .= '</div>';
        return $output;
    }

    /**
     * Renderiza la tabla de solicitudes
     *
     * @since  0.1.0
     * @param  array $args Argumentos para la consulta
     * @return string      HTML de la tabla
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
     * AJAX: Aceptar cotización
     *
     * @since  0.1.0
     * @return void
     */
    public static function ajax_accept_quote(): void {
        // Verificar nonce primero
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'rfq_solicitud_status_nonce')) {
            wp_send_json_error(['msg' => __('Error de seguridad. Por favor, recarga la página.', 'rfq-manager-woocommerce')]);
        }
        
        // Verificar que el usuario esté logueado
        if (!is_user_logged_in()) {
            wp_send_json_error(['msg' => __('Debes iniciar sesión para aceptar una cotización.', 'rfq-manager-woocommerce')]);
        }
        
        // Obtener y sanitizar datos
        $user_id = get_current_user_id();
        $cotizacion_id = isset($_POST['cotizacion_id']) ? absint($_POST['cotizacion_id']) : 0;
        
        if (!$cotizacion_id) {
            wp_send_json_error(['msg' => __('ID de cotización inválido.', 'rfq-manager-woocommerce')]);
        }
        
        // Verificar que la cotización existe y es del tipo correcto
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
     * AJAX: Filtrar solicitudes
     *
     * @since  0.1.0
     * @return void
     */
    public static function ajax_filter_solicitudes(): void {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'rfq_solicitud_status_nonce')) {
            wp_send_json_error(['message' => __('Error de seguridad. Por favor, recarga la página.', 'rfq-manager-woocommerce')]);
        }

        // Verificar que el usuario esté logueado
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Debes iniciar sesión para ver las solicitudes.', 'rfq-manager-woocommerce')]);
        }

        // Sanitizar y validar el estado
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $valid_statuses = ['rfq-pending', 'rfq-active', 'rfq-accepted', 'rfq-closed', 'rfq-historic'];
        
        $user = wp_get_current_user();
        $is_provider = in_array('proveedor', $user->roles);
        $is_admin = in_array('administrator', $user->roles);
        // Permitir filtros personalizados para proveedor/admin
        $provider_filters = ['todas', 'cotizadas', 'no-cotizadas', 'historicas', 'aceptadas', ''];
        if (($is_provider || $is_admin) && (empty($status) || in_array($status, $provider_filters, true))) {
            // Permitir filtros personalizados, no bloquear
        } else {
            // Validación clásica para customer/subscriber
            if (!empty($status) && !in_array($status, $valid_statuses, true)) {
                wp_send_json_error(['message' => __('Estado de solicitud inválido.', 'rfq-manager-woocommerce')]);
            }
        }
        
        $order = isset($_POST['order']) ? strtolower(sanitize_text_field($_POST['order'])) : 'desc';
        if (!in_array($order, ['asc', 'desc'], true)) {
            $order = 'desc'; // fallback seguro
        }

        $args = [
            'per_page' => 10,
            'orderby'  => 'date',
            'order'    => $order
        ];

        // Si se seleccionó un estado específico, aplicarlo a la consulta
        if (!empty($status)) {
            $args['post_status'] = $status;
        } else {
            $args['post_status'] = $valid_statuses;
        }

        $user = wp_get_current_user();
        // Si es proveedor o admin, usar el filtro centralizado
        if (in_array('proveedor', $user->roles) || in_array('administrator', $user->roles)) {
            $filtro = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
            $proveedor_id = $user->ID;
            $ids = \GiVendor\GiPlugin\Shortcode\Components\SolicitudFilters::get_provider_filtered_solicitudes($filtro, $proveedor_id);
            // Si hay resultados, filtrar por post__in, si no, devolver mensaje vacío
            if (!empty($ids)) {
                $args['post__in'] = $ids;
            } else {
                // Forzar resultado vacío
                $args['post__in'] = [0];
            }
            // Eliminar post_status para evitar conflicto con post__in
            unset($args['post_status']);
            $renderer = new \GiVendor\GiPlugin\Solicitud\View\SolicitudListRenderer();
            echo $renderer->render_filtered($args);
            wp_die();
        }

        // Corregir visibilidad: solo mostrar solicitudes propias para customer/subscriber
        $args['author'] = get_current_user_id();

        $renderer = new \GiVendor\GiPlugin\Solicitud\View\SolicitudListRenderer();
        echo $renderer->render_filtered($args);
        wp_die();
    }

    /**
     * AJAX: Cancelar solicitud
     *
     * @since  0.1.0
     * @return void
     */
    public static function ajax_cancel_solicitud(): void {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'rfq_solicitud_status_nonce')) {
            wp_send_json_error(['message' => __('Error de seguridad. Por favor, recarga la página.', 'rfq-manager-woocommerce')]);
        }
        // Verificar que el usuario esté logueado
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Debes iniciar sesión para cancelar una solicitud.', 'rfq-manager-woocommerce')]);
        }
        $solicitud_id = isset($_POST['solicitud_id']) ? absint($_POST['solicitud_id']) : 0;
        if (!$solicitud_id) {
            wp_send_json_error(['message' => __('ID de solicitud inválido.', 'rfq-manager-woocommerce')]);
        }
        $result = \GiVendor\GiPlugin\Solicitud\SolicitudCancelationHandler::cancel($solicitud_id, wp_get_current_user());
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        wp_send_json_success();
    }

    /**
     * Obtiene el label del estado de la solicitud
     *
     * @since  0.1.0
     * @param  string $status Estado de la solicitud
     * @return string         Label del estado
     */
    public static function get_status_label(string $status): string {
        $labels = [
            'rfq-pending'  => __('Pendiente', 'rfq-manager-woocommerce'),
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
    public static function get_status_class(string $status): string {
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
     * AJAX: Repetir solicitud
     *
     * @since  0.1.0
     * @return void
     */
    public static function ajax_repeat_solicitud(): void {
        error_log('[RFQ] Iniciando proceso de repetición de solicitud');
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'rfq_solicitud_status_nonce')) {
            error_log('[RFQ] Error: Nonce inválido');
            wp_send_json_error(['message' => __('Error de seguridad. Por favor, recarga la página.', 'rfq-manager-woocommerce')]);
        }
        // Verificar que el usuario esté logueado
        if (!is_user_logged_in()) {
            error_log('[RFQ] Error: Usuario no logueado');
            wp_send_json_error(['message' => __('Debes iniciar sesión para repetir una solicitud.', 'rfq-manager-woocommerce')]);
        }
        // Obtener y sanitizar datos
        $solicitud_id = isset($_POST['solicitud_id']) ? absint($_POST['solicitud_id']) : 0;
        if (!$solicitud_id) {
            error_log('[RFQ] Error: ID de solicitud inválido');
            wp_send_json_error(['message' => __('ID de solicitud inválido.', 'rfq-manager-woocommerce')]);
        }
        error_log(sprintf('[RFQ] Procesando solicitud #%d', $solicitud_id));
        // Usar la clase handler para procesar la solicitud
        $result = \GiVendor\GiPlugin\Solicitud\SolicitudRepeatHandler::repeat($solicitud_id, wp_get_current_user());
        if (is_wp_error($result)) {
            error_log(sprintf('[RFQ] Error al procesar solicitud: %s', $result->get_error_message()));
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        // Preparar respuesta
        $response = [
            'success' => true,
            'added_count' => count($result['added']),
            'failed_count' => count($result['failed']),
            'failed_items' => $result['failed'],
            'cart_url' => wc_get_cart_url()
        ];
        error_log(sprintf('[RFQ] Respuesta preparada: %s', json_encode($response)));
        wp_send_json_success($response);
    }
}
