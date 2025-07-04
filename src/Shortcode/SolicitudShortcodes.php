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
     * Renderiza el encabezado visual de la solicitud individual con número, estado, fecha, ciudad y código postal.
     *
     * @since 0.1.0
     * @param int $solicitud_id
     * @param \WP_Post $solicitud
     * @return string
     */
    private static function render_solicitud_header(int $solicitud_id, \WP_Post $solicitud): string
    {
        // Número de solicitud (RFQ-xxxxx)
        $uuid = get_post_meta($solicitud_id, '_solicitud_uuid', true);
        $numero = $uuid ? 'RFQ-' . substr(str_replace('-', '', $uuid), -5) : '';

        // Estado
        // Mapear a clases visuales de la lista (rfq-status-badge rfq-status-pendiente, etc.)
        $status_map = [
            'rfq-pending'  => 'rfq-status-badge rfq-status-pendiente',
            'rfq-active'   => 'rfq-status-badge rfq-status-activa',
            'rfq-accepted' => 'rfq-status-badge rfq-status-aceptada',
            'rfq-closed'   => 'rfq-status-badge rfq-status-historica', // No hay closed, usar historica
            'rfq-historic' => 'rfq-status-badge rfq-status-historica',
        ];
        $status_label = self::get_status_label($solicitud->post_status);
        $status_class = isset($status_map[$solicitud->post_status]) ? $status_map[$solicitud->post_status] : 'rfq-status-badge';

        // Fecha
        $fecha = get_the_date('', $solicitud_id);

        // Ciudad y CP
        $ciudad = get_post_meta($solicitud_id, '_solicitud_ciudad', true);
        $cp = get_post_meta($solicitud_id, '_solicitud_cp', true);

        $html = '<div class="rfq-solicitud-header">';
        // Número
        $html .= '<div class="rfq-header-item">'
            . '<span class="rfq-header-label">Número de solicitud</span>'
            . '<span class="rfq-header-value">' . esc_html($numero) . '</span>'
            . '</div>';
        // Estado
        $html .= '<div class="rfq-header-item">'
            . '<span class="rfq-header-label">Status</span>'
            . '<span class="rfq-header-value">'
            . '<div class="' . esc_attr($status_class) . '"><span class="rfq-status-dot"></span><span class="rfq-status-text">' . esc_html($status_label) . '</span></div>'
            . '</span>'
            . '</div>';
        // Fecha
        $html .= '<div class="rfq-header-item">'
            . '<span class="rfq-header-label">Fecha</span>'
            . '<span class="rfq-header-value">' . esc_html($fecha) . '</span>'
            . '</div>';
        // Ciudad
        $html .= '<div class="rfq-header-item">'
            . '<span class="rfq-header-label">Ciudad</span>'
            . '<span class="rfq-header-value">' . esc_html($ciudad) . '</span>'
            . '</div>';
        // Código Postal
        $html .= '<div class="rfq-header-item">'
            . '<span class="rfq-header-label">CP</span>'
            . '<span class="rfq-header-value">' . esc_html($cp) . '</span>'
            . '</div>';
        $html .= '</div>';

        return $html;
    }
    
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
        add_shortcode('rfq_view_ofertas', [self::class, 'render_solicitud_ofertas_shortcode']);
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
                $output .= '<td style="display: flex; gap: 10px; align-items: center;">';

                // Botón de ver detalles usando el slug
                $output .= '<a href="' . esc_url(home_url('/ver-solicitud/' . $post->post_name . '/')) . '" class="rfq-view-btn">' . __('Ver Detalles', 'rfq-manager-woocommerce') . '</a>';

                // Badge Nueva oferta si corresponde
                $hay_nueva_oferta = false;
                foreach ($cotizaciones as $cotizacion) {
                    if (\GiVendor\GiPlugin\Cotizacion\OfertaBadgeHelper::should_show_new_badge($cotizacion, $user_id, $post)) {
                        $hay_nueva_oferta = true;
                        break;
                    }
                }
                if ($hay_nueva_oferta) {
                    $output .= \GiVendor\GiPlugin\Cotizacion\OfertaBadgeHelper::render_new_badge();
                }

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
        $output .= self::render_solicitud_header($solicitud_id, $solicitud);
        $output .= '<div class="rfq-solicitud-items">';
        // Fila de encabezado de la grilla de productos
        $output .= '<div class="rfq-productos-header-row">';
        $output .= '<div class="rfq-productos-header-col rfq-productos-header-producto">' . __('Producto', 'rfq-manager-woocommerce') . '</div>';
        $output .= '<div class="rfq-productos-header-col rfq-productos-header-cantidad">' . __('Cantidad', 'rfq-manager-woocommerce') . '</div>';
        $output .= '</div>';
        // Contenedor con scroll y máximo 6 productos visibles
        $output .= '<div class="rfq-productos-wrapper">';
        foreach ($items as $item) {
            $product = wc_get_product($item['product_id']);
            if (!$product) continue;
            $thumbnail_url = $product->get_image_id() ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : wc_placeholder_img_src();
            $product_name = $product->get_name();
            $qty = $item['qty'];
            $output .= '<div class="rfq-producto-row">';
            $output .= '<div class="rfq-producto-col rfq-producto-col-producto">';
            $output .= '<div class="rfq-producto-thumb"><img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($product_name) . '" width="80" height="80"></div>';
            $output .= '<div class="rfq-producto-nombre">' . esc_html($product_name) . '</div>';
            $output .= '</div>';
            $output .= '<div class="rfq-producto-col rfq-producto-col-cantidad">' . esc_html($qty) . '</div>';
            $output .= '</div>';
        }
        $output .= '</div>';
        $output .= '</div>';
        $output .= self::render_solicitud_actions($solicitud_id, $solicitud);
        $output .= '</div>';
        $output .= '</div>';
        return $output;
    }

    /**
     * Shortcode: [rfq_view_ofertas] - Muestra solo la tabla de cotizaciones recibidas para una solicitud
     *
     * @since  0.1.0
     * @param  array $atts
     * @return string
     */
    public static function render_solicitud_ofertas_shortcode($atts): string
    {
        // 1. Validar login
        if (!is_user_logged_in()) {
            return '<p class="rfq-error">Debes iniciar sesión para ver esta solicitud.</p>';
        }

        // 2. Obtener y validar la solicitud
        $atts = shortcode_atts([
            'solicitud_id' => ''
        ], $atts);

        $solicitud_id = 0;
        if (!empty($atts['solicitud_id'])) {
            $solicitud_id = absint($atts['solicitud_id']);
        } else if (method_exists(__CLASS__, 'get_current_solicitud_id')) {
            $solicitud_id = (int) self::get_current_solicitud_id();
        }

        $solicitud = ($solicitud_id) ? get_post($solicitud_id) : null;
        if (!$solicitud || $solicitud->post_type !== 'solicitud') {
            return '<p class="rfq-error">No se pudo identificar la solicitud.</p>';
        }

        // 3. Validar autoría
        if (get_current_user_id() !== (int)$solicitud->post_author) {
            return '<p class="rfq-error">No tienes permiso para ver esta información.</p>';
        }

        // 4. Renderizar cotizaciones y mostrar mensaje si no hay
        $cotizaciones_html = self::render_solicitud_cotizaciones($solicitud_id, $solicitud);
        if (trim($cotizaciones_html) === '') {
            // Si la solicitud está en estado histórico, mostrar mensaje especial
            if (\GiVendor\GiPlugin\Solicitud\SolicitudStatusHelper::is_historic($solicitud)) {
                return '<p class="rfq-info">No se recibieron ofertas para esta solicitud, puedes enviarla nuevamente haciendo click en el botón Repetir Solicitud.</p>';
            }
            // Si no es histórica, mensaje genérico
            return '<p class="rfq-info">Aún no hay ofertas para esta solicitud.</p>';
        }

        // 5. Mostrar cotizaciones normalmente
        return $cotizaciones_html;
    }

    /**
     * Renderiza el bloque de cotizaciones recibidas para una solicitud.
     *
     * @since  0.1.0
     * @param  int $solicitud_id
     * @param  \WP_Post $solicitud
     * @return string
     */
    /**
     * Renderiza el bloque de cotizaciones recibidas para una solicitud.
     *
     * @since  0.1.0
     * @param  int $solicitud_id
     * @param  \WP_Post $solicitud
     * @return string
     */
    private static function render_solicitud_cotizaciones(int $solicitud_id, \WP_Post $solicitud): string
    {
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
        if (empty($cotizaciones)) {
            return '';
        }
        $cotizacion_aceptada_id = null;
        foreach ($cotizaciones as $cotizacion) {
            if ($cotizacion->post_status === 'rfq-accepted') {
                $cotizacion_aceptada_id = $cotizacion->ID;
                break;
            }
        }
        $output = '';
        $output .= '<div class="rfq-cotizaciones-received">';
        $user_id = get_current_user_id();
        foreach ($cotizaciones as $cotizacion) {
            $proveedor = get_userdata($cotizacion->post_author);
            $total = get_post_meta($cotizacion->ID, '_total', true);
            $proveedor_id = $cotizacion->post_author;
            // Obtener logotipo desde el nuevo campo gi_custom_avatar_id
            $avatar_id = get_user_meta($proveedor_id, 'gireg_provider_gi_custom_avatar_id', true);
            error_log('[RFQ_DEBUG] Avatar ID: ' . print_r($avatar_id, true));
            if ($avatar_id && wp_attachment_is_image($avatar_id)) {
                error_log('[RFQ_DEBUG] Se renderizará la imagen del proveedor');
                $logo_url = wp_get_attachment_image_url($avatar_id, 'thumbnail');
            } else {
                error_log('[RFQ_DEBUG] No se renderiza el logo. ID no válido o no es imagen.');
                $logo_url = false;
            }
            $about = trim(get_user_meta($proveedor_id, 'gireg_provider_about', true));
            $is_accepted = ($cotizacion->post_status === 'rfq-accepted');
            $is_historic = ($cotizacion->post_status === 'rfq-historic');
            $card_class = 'rfq-cotizacion-card';
            if ($is_accepted) {
                $card_class .= ' rfq-cotizacion-aceptada';
            } elseif ($is_historic) {
                $card_class .= ' rfq-cotizacion-historica';
            }
            $output .= '<div class="' . esc_attr($card_class) . '" data-cotizacion-id="' . esc_attr($cotizacion->ID) . '" style="position:relative;">';
            // Badge Nueva oferta (usando helper centralizado)
            if (\GiVendor\GiPlugin\Cotizacion\OfertaBadgeHelper::should_show_new_badge($cotizacion, $user_id, $solicitud)) {
                $output .= '<span class="rfq-nueva-oferta" data-nueva-oferta="1">' . esc_html__('Nueva oferta', 'rfq-manager-woocommerce') . '</span>';
                \GiVendor\GiPlugin\Cotizacion\OfertaBadgeHelper::mark_as_seen($cotizacion->ID, $user_id);
            }
            $output .= '<div class="rfq-cotizacion-header">';
            // Bloque proveedor info: logo y detalles (estructura corregida, sin estilos inline)
            $output .= '<div class="rfq-proveedor-info">';
            if ($logo_url) {
                $output .= '<div class="rfq-proveedor-logo"><img src="' . esc_url($logo_url) . '" alt="' . esc_attr($proveedor->display_name) . '" width="80" height="80"></div>';
            }
            $output .= '<div class="rfq-proveedor-details">';
            $output .= '<h4 class="rfq-proveedor-nombre">' . esc_html($proveedor->display_name) . '</h4>';
            if ($about) {
                $output .= '<p class="rfq-proveedor-about">' . esc_html($about) . '</p>';
            }
            $output .= '</div>';
            $output .= '</div>';
            $output .= '<div class="rfq-proveedor-meta">';
            $output .= '<span class="rfq-proveedor-fecha-label">' . __('Fecha de oferta', 'rfq-manager-woocommerce') . '</span>';
            $output .= '<span class="rfq-proveedor-fecha-value">' . esc_html(get_the_date('d F Y', $cotizacion->ID)) . '</span>';
            $output .= '</div>';
            $output .= '</div>'; // rfq-cotizacion-header

            $output .= '<div class="rfq-cotizacion-actions">';
            $output .= '<h3 class="rfq-cotizacion-total">' . esc_html(number_format((float)$total, 2, ',', '.') . ' €') . '</h3>';
            if ($is_accepted) {
                $output .= '<button type="button" class="button rfq-aceptar-cotizacion-btn" disabled style="background:rgba(239, 239, 239, 1);color:#fff;cursor:default;">' . __('Aceptada', 'rfq-manager-woocommerce') . '</button>';
                $output .= ' <button type="button" class="button rfq-pagar-cotizacion-btn" data-cotizacion-id="' . esc_attr($cotizacion->ID) . '">' . __('Pagar', 'rfq-manager-woocommerce') . '</button>';
            } elseif ($solicitud->post_status !== 'rfq-historic' && $solicitud->post_status !== 'rfq-closed' && $solicitud->post_status !== 'rfq-accepted' && !$is_historic && !$cotizacion_aceptada_id) {
                $output .= '<button type="button" class="rfq-aceptar-cotizacion-btn button" data-cotizacion-id="' . esc_attr($cotizacion->ID) . '">' . __('Aceptar oferta', 'rfq-manager-woocommerce') . '</button>';
            }
            $output .= '</div>'; // rfq-cotizacion-actions

            $output .= '</div>'; // rfq-cotizacion-card
        }
        $output .= '</div>';
        return $output;
    }

    /**
     * Renderiza el badge "Nueva oferta" si corresponde y marca la oferta como vista por el usuario.
     *
     * @param int $cotizacion_id
     * @param int $user_id
     * @return string
     */
    private static function render_nueva_oferta_badge(int $cotizacion_id, int $user_id): string
    {
        $meta_key = '_oferta_vista_' . $user_id;
        $ya_vista = get_post_meta($cotizacion_id, $meta_key, true);
        if (!$ya_vista) {
            // Marcar como vista para este usuario
            update_post_meta($cotizacion_id, $meta_key, 1);
            // Renderizar badge
            return '<span class="rfq-nueva-oferta">' . esc_html__('Nueva oferta', 'rfq-manager-woocommerce') . '</span>';
        }
        return '';
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

    /**
     * Obtiene el ID de la solicitud actual desde la URL amigable.
     *
     * @since  0.1.0
     * @return int|null  ID de la solicitud o null si no se encuentra
     */
    private static function get_current_solicitud_id(): ?int
    {
        $slug = get_query_var('rfq_slug');
        if (empty($slug)) {
            return null;
        }
        $post = get_page_by_path($slug, OBJECT, 'solicitud');
        if (!$post || !isset($post->post_type) || $post->post_type !== 'solicitud') {
            return null;
        }
        return (int) $post->ID;
    }

    /**
     * Renderiza los botones de acción para cancelar o repetir solicitud.
     *
     * @since  0.1.0
     * @param  int      $solicitud_id
     * @param  \WP_Post $solicitud
     * @return string   HTML del bloque de acciones
     */
    private static function render_solicitud_actions(int $solicitud_id, \WP_Post $solicitud): string
    {
        $output = '';

        if (\GiVendor\GiPlugin\Solicitud\SolicitudCancelationHandler::can_cancel(wp_get_current_user(), $solicitud)) {
            $output .= '<div class="rfq-solicitud-actions-wrapper">';
            $output .= '<button type="button" class="rfq-cancel-btn rfq-cancel-btn-link" data-solicitud="' . esc_attr($solicitud_id) . '" title="Cancelar solicitud">' . __('Cancelar Solicitud', 'rfq-manager-woocommerce') . '</button>';
            $output .= '</div>';
        }


        if (\GiVendor\GiPlugin\Solicitud\SolicitudRepeatHandler::can_repeat(wp_get_current_user(), $solicitud)) {
            $output .= '<div class="rfq-solicitud-actions">';
            $output .= '<button type="button" class="rfq-repeat-btn" data-solicitud="' . esc_attr($solicitud_id) . '" title="Repetir solicitud">' . __('Repetir Solicitud', 'rfq-manager-woocommerce') . '</button>';
            $output .= '</div>';
        }

        return $output;
    }
}
