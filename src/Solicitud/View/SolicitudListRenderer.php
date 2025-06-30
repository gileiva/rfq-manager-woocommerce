<?php
namespace GiVendor\GiPlugin\Solicitud\View;

use WP_Query;
use WP_User;
use GiVendor\GiPlugin\Shortcode\Components\SolicitudFilters;

class SolicitudListRenderer {
    public function render(): string {
        return $this->render_by_role();
    }

    private function render_by_role(): string {
        $atts = shortcode_atts([
            'per_page' => 10,
            'status' => 'active',
            'orderby' => 'date',
            'order' => 'DESC',
        ], [], 'rfq_list');
        if (!is_user_logged_in()) {
            return '<p class="rfq-error">' . __('Debes iniciar sesión para ver las solicitudes.', 'rfq-manager-woocommerce') . '</p>';
        }

        $output = '';

        // Estilos toast siempre incluidos si está logueado
        $output .= '<style>/* (todo tu CSS de .rfq-toast-notification) */</style>';

        $user = wp_get_current_user();

        if (in_array('customer', $user->roles) || in_array('subscriber', $user->roles)) {
            $output .= $this->render_customer_solicitudes($atts, $user->ID);
            return $output;
        }

        if (!in_array('administrator', $user->roles) && !in_array('proveedor', $user->roles)) {
            return '<p class="rfq-error">' . __('No tienes permisos para ver las solicitudes.', 'rfq-manager-woocommerce') . '</p>';
        }

        $selected_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $selected_order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'desc';

        $output .= SolicitudFilters::render_provider_header($selected_status, $selected_order);
        $output .= '<div id="rfq-solicitudes-table-container">';
        $output .= $this->render_solicitudes_table($atts);
        $output .= '</div>';

        return $output;
    }


    private function render_customer_solicitudes($atts, $user_id): string {
        $statuses = SolicitudFilters::get_status_counts($user_id);
        $selected_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $output = '<div class="rfq-list-container">';
        $selected_order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'desc';
        $output .= SolicitudFilters::render_filter_header($statuses, $selected_status, $selected_order);
        $output .= '<div id="rfq-solicitudes-table-container">';
        $query_args = [
            'post_type' => 'solicitud',
            'posts_per_page' => intval($atts['per_page']),
            'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
            'author' => $user_id,
            'post_status' => ['publish', 'rfq-pending', 'rfq-active', 'rfq-accepted', 'rfq-closed', 'rfq-historic'],
            'orderby' => $atts['orderby'],
            'order' => $atts['order'],
        ];
        $query = new WP_Query($query_args);
        if (!$query->have_posts()) {
            $output .= '<p class="rfq-notice">' . __('No tienes solicitudes pendientes.', 'rfq-manager-woocommerce') . '</p>';
        } else {
            while ($query->have_posts()) {
                $query->the_post();
                $solicitud_id = get_the_ID();
                $post = get_post($solicitud_id);
                $items = json_decode(get_post_meta($solicitud_id, '_solicitud_items', true), true);
                $estado = get_post_status();
                $uuid = get_post_meta($solicitud_id, '_solicitud_uuid', true);
                $formatted_id = $uuid ? 'RFQ-' . substr(str_replace('-', '', $uuid), -5) : '';
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
                $nuevas_ofertas = false;
                foreach ($cotizaciones as $cotizacion) {
                    if ($cotizacion->post_status === 'publish') {
                        $nuevas_ofertas = true;
                        break;
                    }
                }
                $output .= $this->render_solicitud_card([
                    'solicitud_id' => $solicitud_id,
                    'post' => $post,
                    'items' => $items,
                    'estado' => $estado,
                    'formatted_id' => $formatted_id,
                    'date' => get_the_date('d M Y'),
                    'ver_detalles_url' => home_url('/ver-solicitud/' . $post->post_name . '/'),
                    'nuevas_ofertas' => $nuevas_ofertas,
                    'is_cliente' => true,
                ]);
            }
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
        $output .= '</div>';
        $output .= '</div>';
        return $output;
    }

    private function render_solicitudes_table($args): string {
        $query_args = [
            'post_type' => 'solicitud',
            'posts_per_page' => intval($args['per_page']),
            'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
            'orderby' => $args['orderby'],
            'order' => $args['order'],
        ];
        if (isset($args['post_status'])) {
            $query_args['post_status'] = $args['post_status'];
        } else {
            $query_args['post_status'] = ['publish', 'rfq-pending', 'rfq-active', 'rfq-closed', 'rfq-historic'];
        }
        if (isset($args['post__in'])) {
            $query_args['post__in'] = $args['post__in'];
        }
        $query = new WP_Query($query_args);
        if (is_wp_error($query)) {
            return '<p class="rfq-error">' . __('Error al cargar las solicitudes.', 'rfq-manager-woocommerce') . '</p>';
        }
        if (!$query->have_posts()) {
            return '<p class="rfq-notice">' . __('No hay solicitudes disponibles.', 'rfq-manager-woocommerce') . '</p>';
        }
        $output = '';
        while ($query->have_posts()) {
            $query->the_post();
            $solicitud_id = get_the_ID();
            $post = get_post($solicitud_id);
            \GiVendor\GiPlugin\Solicitud\SolicitudStatusHandler::check_and_update_status($solicitud_id, $post, true);
            $author_id = get_post_field('post_author', $solicitud_id);
            $items = json_decode(get_post_meta($solicitud_id, '_solicitud_items', true), true);
            $estado = get_post_status();
            $uuid = get_post_meta($solicitud_id, '_solicitud_uuid', true);
            $formatted_id = $uuid ? 'RFQ-' . substr(str_replace('-', '', $uuid), -5) : '';
            $ver_detalles_url = '';
            $user = wp_get_current_user();
            if (in_array($estado, ['rfq-accepted', 'rfq-closed', 'rfq-historic'])) {
                $is_author = ((int)$author_id === (int)$user->ID);
                $is_admin = in_array('administrator', $user->roles);
                if ($is_author || $is_admin) {
                    $ver_detalles_url = home_url('/ver-solicitud/' . $post->post_name . '/');
                }
            } else {
                if (in_array('proveedor', $user->roles)) {
                    $ver_detalles_url = home_url('/cotizar-solicitud/' . $post->post_name . '/');
                } else {
                    $ver_detalles_url = home_url('/ver-solicitud/' . $post->post_name . '/');
                }
            }
            $city = get_post_meta($solicitud_id, '_solicitud_city', true);
            $zipcode = get_post_meta($solicitud_id, '_solicitud_zipcode', true);
            $output .= $this->render_solicitud_card([
                'solicitud_id' => $solicitud_id,
                'post' => $post,
                'items' => $items,
                'estado' => $estado,
                'formatted_id' => $formatted_id,
                'date' => get_the_date('d M Y'),
                'ver_detalles_url' => $ver_detalles_url,
                'is_cliente' => false,
                'city' => $city,
                'zipcode' => $zipcode,
            ]);
        }
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

    private function render_solicitud_card($data): string {
        $solicitud_id = $data['solicitud_id'];
        $post = $data['post'];
        $items = $data['items'];
        $estado = $data['estado'];
        $formatted_id = $data['formatted_id'];
        $date = $data['date'];
        $ver_detalles_url = $data['ver_detalles_url'];
        $is_cliente = isset($data['is_cliente']) ? $data['is_cliente'] : false;
        $nuevas_ofertas = isset($data['nuevas_ofertas']) ? $data['nuevas_ofertas'] : false;
        $author_id = isset($post->post_author) ? $post->post_author : 0;
        // Obtener ciudad y código postal del autor (usuario)
        $city = '';
        $zipcode = '';
        if ($author_id) {
            $city = get_user_meta($author_id, 'gireg_customer_billing_city', true);
            $zipcode = get_user_meta($author_id, 'gireg_customer_billing_postcode', true);
        }
        $city = $city ? $city : '';
        $zipcode = $zipcode ? $zipcode : '';

        $user = wp_get_current_user();
        $is_proveedor = in_array('proveedor', (array)$user->roles, true);
        $status_label = \GiVendor\GiPlugin\Shortcode\SolicitudShortcodes::get_status_label($estado);
        $status_class = \GiVendor\GiPlugin\Shortcode\SolicitudShortcodes::get_status_class($estado);

        $html = '<div class="rfq-solicitud-card" data-solicitud-id="' . esc_attr($solicitud_id) . '">';
        // Header row
        if ($is_cliente) {
            $html .= '<div class="rfq-header-row">';
            $html .= '<div class="rfq-header-left">';
            $html .= $this->render_customer_header($post, $formatted_id, $status_label, $date);
            $html .= '</div>';
            $html .= '<div class="rfq-header-right">';
            if ($nuevas_ofertas) {
                $html .= '<span class="rfq-nueva-oferta">' . __('Nueva oferta', 'rfq-manager-woocommerce') . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        } else {
            // PROVEEDOR: header estructurado en 2 columnas desktop, 1 columna mobile
            $html .= '<div class="rfq-header">';
            // Número de solicitud
            $html .= '<div class="rfq-header-field">';
            $html .= '<div class="rfq-header-label">' . __('Número de solicitud', 'rfq-manager-woocommerce') . '</div>';
            $html .= '<div class="rfq-header-value">' . esc_html($formatted_id) . '</div>';
            $html .= '</div>';
            // Estado
            $html .= '<div class="rfq-header-field">';
            $html .= '<div class="rfq-header-label">' . __('Estado', 'rfq-manager-woocommerce') . '</div>';
            $html .= '<div class="rfq-header-value">' . $this->get_provider_status_label($post, $user) . '</div>';
            $html .= '</div>';
            // Fecha de solicitud
            $html .= '<div class="rfq-header-field">';
            $html .= '<div class="rfq-header-label">' . __('Fecha de solicitud', 'rfq-manager-woocommerce') . '</div>';
            $html .= '<div class="rfq-header-value">' . esc_html(date_i18n('d M Y', strtotime($date))) . '</div>';
            $html .= '</div>';
            // Ciudad
            $html .= '<div class="rfq-header-field">';
            $html .= '<div class="rfq-header-label">' . __('Ciudad', 'rfq-manager-woocommerce') . '</div>';
            $html .= '<div class="rfq-header-value">' . esc_html($city) . '</div>';
            $html .= '</div>';
            // Código Postal
            $html .= '<div class="rfq-header-field">';
            $html .= '<div class="rfq-header-label">' . __('Código Postal', 'rfq-manager-woocommerce') . '</div>';
            $html .= '<div class="rfq-header-value">' . esc_html($zipcode) . '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        // Productos row (galería avanzada para ambos roles)
        $html .= '<div class="rfq-productos-row">';
        $html .= '<ul class="rfq-product-gallery">';
        if (is_array($items)) {
            foreach ($items as $item) {
                $product = wc_get_product($item['product_id']);
                if ($product) {
                    $img_url = $product->get_image_id() ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : wc_placeholder_img_src();
                    $qty = isset($item['qty']) ? (int)$item['qty'] : 1;
                    $unidades_por_paquete = get_post_meta($item['product_id'], '_unidades_por_paquete', true);
                    if ($unidades_por_paquete && is_numeric($unidades_por_paquete)) {
                        $total_unidades = $qty * (int)$unidades_por_paquete;
                    } else {
                        $total_unidades = $qty;
                    }
                    $html .= '<li class="rfq-product-item">';
                    $html .= '<div class="rfq-product-thumb-wrapper">';
                    $html .= '<img src="' . esc_url($img_url) . '" class="rfq-product-thumb" alt="' . esc_attr($product->get_name()) . '">';
                    $html .= '<div class="rfq-product-qty-badge">' . esc_html($total_unidades) . '</div>';
                    $html .= '</div>';
                    $html .= '</li>';
                }
            }
        }
        $html .= '</ul>';
        $html .= '</div>';
        // Acciones row
        $html .= '<div class="rfq-actions-row">';
        if ($ver_detalles_url) {
            $html .= '<a href="' . esc_url($ver_detalles_url) . '" class="rfq-view-btn rfq-btn-detalles">' . __('Ver detalles', 'rfq-manager-woocommerce') . '</a>';
        }
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Renderiza la fila superior de información para el cliente con estructura de bloques verticales.
     */
    private function render_customer_header($post, $formatted_id, $status_label, $date): string {
        // Usar date_i18n para formato localizado
        $date_formatted = date_i18n('d M Y', strtotime($date));
        $html = '';
        // Número de solicitud
        $html .= '<div class="rfq-header-block">';
        $html .= '<div class="rfq-header-title">' . __('Número de solicitud', 'rfq-manager-woocommerce') . '</div>';
        $html .= '<div class="rfq-header-detail">' . esc_html($formatted_id) . '</div>';
        $html .= '</div>';
        // Estado (badge visual)
        $estado = $post->post_status;
        $status_label = \GiVendor\GiPlugin\Shortcode\SolicitudShortcodes::get_status_label($estado);
        // Mapear estado a clase visual
        $map = [
            'rfq-pending'  => 'rfq-status-pendiente',
            'rfq-active'   => 'rfq-status-activa',
            'rfq-accepted' => 'rfq-status-aceptada',
            'rfq-historic' => 'rfq-status-historica',
        ];
        $status_class = isset($map[$estado]) ? $map[$estado] : 'rfq-status-' . esc_attr(str_replace('rfq-', '', $estado));
        $html .= '<div class="rfq-header-block">';
        $html .= '<div class="rfq-header-title">' . __('Estado', 'rfq-manager-woocommerce') . '</div>';
        $html .= '<div class="rfq-header-detail">';
        $html .= '<div class="rfq-status-badge ' . esc_attr($status_class) . '"><span class="rfq-status-dot"></span><span class="rfq-status-text">' . esc_html($status_label) . '</span></div>';
        $html .= '</div>';
        $html .= '</div>';
        // Fecha de solicitud
        $html .= '<div class="rfq-header-block">';
        $html .= '<div class="rfq-header-title">' . __('Fecha de solicitud', 'rfq-manager-woocommerce') . '</div>';
        $html .= '<div class="rfq-header-detail">' . esc_html($date_formatted) . '</div>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Devuelve el HTML del badge de estado para el proveedor según la lógica de negocio.
     * - Para rfq-pending y rfq-active: muestra Cotizada/No cotizada (con dot y clase visual)
     * - Para otros estados: muestra el estado real (con clase visual de estado)
     * @param WP_Post $post
     * @param WP_User $user
     * @return string HTML del badge
     */
    private function get_provider_status_label($post, $user): string {
        $estado = $post->post_status;
        // Solo para proveedor
        if (!in_array('proveedor', (array)$user->roles, true)) {
            return '';
        }
        // Para estados activos: mostrar Cotizada/No cotizada
        if (in_array($estado, ['rfq-pending', 'rfq-active'], true)) {
            // Buscar cotización del proveedor actual
            $cotizacion = null;
            if (function_exists('GiVendor\\GiPlugin\\Shortcode\\CotizacionShortcodes::get_provider_cotizacion')) {
                $cotizacion = \GiVendor\GiPlugin\Shortcode\CotizacionShortcodes::get_provider_cotizacion($post->ID);
            } else if (class_exists('GiVendor\\GiPlugin\\Shortcode\\CotizacionShortcodes')) {
                $cotizacion = \GiVendor\GiPlugin\Shortcode\CotizacionShortcodes::get_provider_cotizacion($post->ID);
            } else {
                $current_user_id = get_current_user_id();
                $cotizaciones = get_posts([
                    'post_type'      => 'cotizacion',
                    'posts_per_page' => 1,
                    'meta_query'     => [
                        [
                            'key'   => '_solicitud_parent',
                            'value' => intval($post->ID),
                            'compare' => '=',
                            'type' => 'NUMERIC',
                        ],
                    ],
                    'author'         => $current_user_id,
                ]);
                $cotizacion = !empty($cotizaciones) ? $cotizaciones[0] : null;
            }
            if ($cotizacion) {
                $label = __('Cotizada', 'rfq-manager-woocommerce');
                $class = 'rfq-status-badge rfq-status-cotizada';
                $dot = '<span class="rfq-status-dot"></span>';
            } else {
                $label = __('No cotizada', 'rfq-manager-woocommerce');
                $class = 'rfq-status-badge rfq-status-no-cotizada';
                $dot = '<span class="rfq-status-dot"></span>';
            }
            return '<div class="' . esc_attr($class) . '">' . $dot . '<span class="rfq-status-text">' . esc_html($label) . '</span></div>';
        }
        // Para otros estados: mostrar el estado real
        $status_label = \GiVendor\GiPlugin\Shortcode\SolicitudShortcodes::get_status_label($estado);
        $status_class = 'rfq-status-badge rfq-status-' . esc_attr(str_replace('rfq-', '', $estado));
        // Dot color para historic/accepted/closed
        $dot = '<span class="rfq-status-dot"></span>';
        return '<div class="' . $status_class . '">' . $dot . '<span class="rfq-status-text">' . esc_html($status_label) . '</span></div>';
    }
}
