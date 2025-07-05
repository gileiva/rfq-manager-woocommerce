<?php
/**
 * Shortcodes relacionados con cotizaciones
 *
 * @package    GiVendor\GiPlugin\Shortcode
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Shortcode;

/**
 * CotizacionShortcodes - Implementa shortcodes para el sistema de cotizaciones
 *
 * Esta clase proporciona shortcodes para mostrar y manejar el formulario de cotización.
 *
 * @package    GiVendor\GiPlugin\Shortcode
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class CotizacionShortcodes {
    
    /**
     * Inicializa los shortcodes
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        add_shortcode('rfq_cotizar', [self::class, 'render_cotizar_form']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_shortcode('rfq_quotes', [self::class, 'render_rfq_quotes']);
    }

    /**
     * Encola los scripts y estilos necesarios
     *
     * @since  0.1.0
     * @return void
     */
    public static function enqueue_assets(): void {
        global $post;
        $should_enqueue = false;
        // Encolar si el shortcode está presente en el contenido
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'rfq_cotizar')) {
            $should_enqueue = true;
        }
        // O si estamos en una vista singular de solicitud y el usuario es proveedor
        if (is_singular('solicitud') && is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('proveedor', (array)$user->roles, true)) {
                $should_enqueue = true;
            }
        }
        if (!$should_enqueue) {
            return;
        }
        // Asegurarnos de que los estilos de WooCommerce estén cargados
        if (function_exists('WC')) {
            wp_enqueue_style('woocommerce-general');
            wp_enqueue_style('woocommerce-layout');
        }
        // wp_enqueue_style(
        //     'rfq-cotizar-styles',
        //     plugins_url('assets/css/rfq-cotizar.css', dirname(dirname(__FILE__))),
        //     ['woocommerce-general', 'woocommerce-layout'],
        //     '1.0.0'
        // );
        wp_enqueue_style(
            'user-cotizar-styles',
            plugins_url('assets/css/user-solicitud.css', dirname(dirname(__FILE__))),
            ['woocommerce-general', 'woocommerce-layout'],
            '1.0.0'
        );
        wp_enqueue_style(
            'RFQ Layout',
            plugins_url('assets/css/rfq-list-layout.css', dirname(dirname(__FILE__))),
            ['woocommerce-general', 'woocommerce-layout'],
            '1.0.0'
        );
        wp_enqueue_script(
            'rfq-cotizar-scripts',
            plugins_url('assets/js/rfq-cotizar.js', dirname(dirname(__FILE__))),
            ['jquery'],
            '1.0.0',
            true
        );
        wp_enqueue_script(
            'rfq-expiry-timer',
            plugins_url('assets/js/rfq-expiry-timer.js', dirname(dirname(__FILE__))),
            [],
            '1.0.0',
            true
        );
        wp_localize_script('rfq-cotizar-scripts', 'rfqCotizarL10n', [
            'completePrices' => __('Por favor, complete todos los precios.', 'rfq-manager-woocommerce'),
            'invalidPrice' => __('El precio debe ser mayor a 0.', 'rfq-manager-woocommerce'),
            'priceTooHigh' => __('Tu precio inicial fue de %s, no puedes cambiarlo a un precio más alto.', 'rfq-manager-woocommerce'),
            'stockConfirmation' => __('Debes confirmar que tienes todos los productos en stock para poder enviar la cotización.', 'rfq-manager-woocommerce'),
        ]);
    }

    /**
     * Renderiza el formulario de cotización
     *
     * @since  0.1.0
     * @param  array  $atts Atributos del shortcode
     * @return string       Output HTML del formulario
     */
    public static function render_cotizar_form($atts = []): string {
        // Verificar permisos
        if (!self::check_permissions()) {
            return '<div class="rfq-error">' . esc_html__('No tienes permisos para ver esta página.', 'rfq-manager-woocommerce') . '</div>';
        }

        $slug = get_query_var('rfq_cotizacion_slug');
        $solicitud = get_page_by_path($slug, OBJECT, 'solicitud');
        if (!$solicitud) {
            return '<div class="rfq-error">' . esc_html__('No se encontró la solicitud para cotizar.', 'rfq-manager-woocommerce') . '</div>';
        }
        $solicitud_id = $solicitud->ID;
        if (!in_array($solicitud->post_status, ['rfq-pending', 'rfq-active'], true)) {
            return '<div class="rfq-error">' . esc_html__('Esta solicitud ya no está disponible para cotizar.', 'rfq-manager-woocommerce') . '</div>';
        }
        $cotizacion = self::get_provider_cotizacion($solicitud_id);
        $items = self::get_solicitud_items($solicitud_id);
        if (empty($items)) {
            return '<div class="rfq-error">' . esc_html__('No hay items en esta solicitud.', 'rfq-manager-woocommerce') . '</div>';
        }

        $output = '';
        $output .= '<div class="rfq-cotizar-container">';
        ob_start();
        echo \GiVendor\GiPlugin\Solicitud\View\SolicitudHeaderRenderer::render($solicitud, 'proveedor', ['show_expiry' => true]);
        $output .= ob_get_clean();

        // --- BODY (FORMULARIO) ---
        $output .= '<form id="rfq-cotizar-form" class="rfq-cotizar-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        $output .= wp_nonce_field('rfq_cotizar_nonce', 'rfq_cotizar_nonce', true, false);
        $output .= '<input type="hidden" name="action" value="submit_cotizacion">';
        $output .= '<input type="hidden" name="solicitud_id" value="' . esc_attr($solicitud_id) . '">';

        $output .= '<div class="rfq-solicitud-items">';
        $output .= '<div class="rfq-productos-header-row">';
        $output .= '<div class="rfq-productos-header-col rfq-productos-header-producto">' . __('Producto', 'rfq-manager-woocommerce') . '</div>';
        $output .= '<div class="rfq-productos-header-col rfq-productos-header-cantidad">' . __('Cantidad', 'rfq-manager-woocommerce') . '</div>';
        $output .= '<div class="rfq-productos-header-col rfq-productos-header-precio">' . __('Precio', 'rfq-manager-woocommerce') . '</div>';
        $output .= '<div class="rfq-productos-header-col rfq-productos-header-iva">' . __('IVA', 'rfq-manager-woocommerce') . '</div>';
        $output .= '<div class="rfq-productos-header-col rfq-productos-header-subtotal">' . __('Subtotal', 'rfq-manager-woocommerce') . '</div>';
        $output .= '</div>';
        $output .= '<div class="rfq-productos-wrapper">';
        $precio_items = $cotizacion ? get_post_meta($cotizacion->ID, '_precio_items', true) : [];
        foreach ($items as $item) {
            $product = wc_get_product($item['product_id']);
            if (!$product) continue;
            $existing_price = isset($precio_items[$item['product_id']]) ? $precio_items[$item['product_id']]['precio'] : '';
            $existing_iva = isset($precio_items[$item['product_id']]) ? $precio_items[$item['product_id']]['iva'] : '21';
            $original_price = is_numeric($existing_price) ? $existing_price : 0;
            $qty = $item['qty'];
            $output .= '<div class="rfq-producto-row">';
            $output .= '<div class="rfq-producto-col rfq-producto-col-producto">' . esc_html($product->get_name()) . '</div>';
            $output .= '<div class="rfq-producto-col rfq-producto-col-cantidad">' . esc_html($qty) . '</div>';
            $output .= '<div class="rfq-producto-col rfq-producto-col-precio">';
            $output .= '<input type="number" name="precios[' . esc_attr($item['product_id']) . ']" class="rfq-precio-input" step="0.01" min="0" required value="' . esc_attr($existing_price) . '" data-original-price="' . esc_attr($original_price) . '">';
            $output .= '</div>';
            $output .= '<div class="rfq-producto-col rfq-producto-col-iva">';
            $output .= '<select name="iva[' . esc_attr($item['product_id']) . ']" class="rfq-iva-select">';
            $output .= '<option value="4"' . selected($existing_iva, '4', false) . '>4%</option>';
            $output .= '<option value="10"' . selected($existing_iva, '10', false) . '>10%</option>';
            $output .= '<option value="21"' . selected($existing_iva, '21', false) . '>21%</option>';
            $output .= '</select>';
            $output .= '</div>';
            $output .= '<div class="rfq-producto-col rfq-producto-col-subtotal">';
            $output .= '<span class="rfq-subtotal-value">' . ($existing_price ? wc_price($existing_price * $qty) : '0.00') . '</span>';
            $output .= '</div>';
            $output .= '</div>';
        }
        $output .= '</div>';
        $output .= '</div>';

        // --- FOOTER ---
        $output .= '<div class="rfq-solicitud-formulario">';
        $output .= '<div class="rfq-stock-confirmation">';
        $output .= '<label class="rfq-stock-checkbox">';
        $output .= '<input type="checkbox" name="stock_confirmation" required>';
        $output .= '<span class="rfq-checkbox-label">' . __('Confirmo que tengo en stock todos los productos solicitados.', 'rfq-manager-woocommerce') . '</span>';
        $output .= '</label>';
        $output .= '</div>';
        $total = $cotizacion ? get_post_meta($cotizacion->ID, '_total', true) : '';
        $output .= '<div class="rfq-total-row">';
        $output .= '<span class="rfq-total-label">' . __('TOTAL', 'rfq-manager-woocommerce') . '</span>';
        $output .= '<span class="rfq-total-amount">' . ($total ? wc_price($total) : '0.00') . '</span>';
        $output .= '</div>';
        $output .= '<div class="rfq-cotizar-submit">';
        $output .= '<button type="submit" class="rfq-submit-btn">' . __('Publicar oferta', 'rfq-manager-woocommerce') . '</button>';
        $output .= '</div>';
        $output .= '</div>';

        $output .= '</form>';
        $output .= '</div>';
        return $output;
    }

    /**
     * Verifica los permisos del usuario
     *
     * @since  0.1.0
     * @return bool
     */
    private static function check_permissions(): bool {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        return in_array('proveedor', (array) $user->roles);
    }

    /**
     * Obtiene el mensaje de error según el tipo de error
     *
     * @since  0.1.0
     * @return string
     */
    private static function get_error_message(): string {
        if (!is_user_logged_in()) {
            return '<p class="rfq-error">' . esc_html__('Debes iniciar sesión para realizar cotizaciones.', 'rfq-manager-woocommerce') . '</p>';
        }

        $user = wp_get_current_user();
        $roles = (array) $user->roles;
        // No mostrar detalles de roles por seguridad
        return '<p class="rfq-error">' . esc_html__('No tienes permisos para realizar cotizaciones. Contacta al administrador si crees que esto es un error.', 'rfq-manager-woocommerce') . '</p>';
    }

    /**
     * Obtiene y valida la solicitud
     *
     * @since  0.1.0
     * @return \WP_Post|\WP_Error
     */
    private static function get_validated_solicitud() {
        $solicitud_id = isset($_GET['solicitud_id']) ? intval($_GET['solicitud_id']) : 0;
        
        if (!$solicitud_id) {
            return new \WP_Error('invalid_id', __('ID de solicitud no válido.', 'rfq-manager-woocommerce'));
        }

        $solicitud = get_post($solicitud_id);
        
        if (!$solicitud || $solicitud->post_type !== 'solicitud') {
            return new \WP_Error('not_found', __('La solicitud no existe.', 'rfq-manager-woocommerce'));
        }

        if (!in_array($solicitud->post_status, ['rfq-pending', 'rfq-active'])) {
            return new \WP_Error('not_available', __('Esta solicitud ya no está disponible para cotizar.', 'rfq-manager-woocommerce'));
        }

        return $solicitud;
    }

    /**
     * Obtiene los items de la solicitud
     *
     * @since  0.1.0
     * @param  int    $solicitud_id ID de la solicitud
     * @return array
     */
    private static function get_solicitud_items(int $solicitud_id): array {
        $items = json_decode(get_post_meta($solicitud_id, '_solicitud_items', true), true);
        return is_array($items) ? $items : [];
    }

    /**
     * Renderiza el formulario de cotización
     *
     * @since  0.1.0
     * @param  \WP_Post $solicitud Objeto de la solicitud
     * @param  array    $items     Items de la solicitud
     * @return string
     */
    private static function render_form(\WP_Post $solicitud, array $items): string {
        $output = '<div class="rfq-cotizar-container">';
        
        // Encabezado
        $output .= self::render_header($solicitud);

        // Formulario
        $output .= '<form id="rfq-cotizar-form" class="rfq-cotizar-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        $output .= wp_nonce_field('rfq_cotizar_nonce', 'rfq_cotizar_nonce', true, false);
        $output .= '<input type="hidden" name="action" value="submit_cotizacion">';
        $output .= '<input type="hidden" name="solicitud_id" value="' . esc_attr($solicitud->ID) . '">';

        // Tabla de productos
        $output .= self::render_items_table($items, $solicitud->ID);

        // Checkbox de confirmación de stock
        $output .= '<div class="rfq-stock-confirmation">';
        $output .= '<label class="rfq-stock-checkbox">';
        $output .= '<input type="checkbox" name="stock_confirmation" id="stock_confirmation" required>';
        $output .= '<span class="rfq-checkbox-label">' . __('Confirmo que tengo en stock todos los productos solicitados.', 'rfq-manager-woocommerce') . '</span>';
        $output .= '</label>';
        $output .= '</div>';

        // Botón de envío
        $output .= self::render_submit_button();

        $output .= '</form>';

        // Sección de cotizaciones recibidas de otros proveedores
        $output .= self::render_other_cotizaciones($solicitud->ID);

        $output .= '</div>';

        return $output;
    }

    /**
     * Renderiza el encabezado del formulario
     *
     * @since  0.1.0
     * @param  \WP_Post $solicitud Objeto de la solicitud
     * @return string
     */
    private static function render_header(\WP_Post $solicitud): string {
        $output = '<div class="rfq-cotizar-header">';
        // Título para proveedor
        $output .= '<h2>' . esc_html__('Cotizar solicitud de ', 'rfq-manager-woocommerce') . esc_html(get_the_author_meta('display_name', $solicitud->post_author)) . '</h2>';
        // Información básica
        $output .= '<div class="rfq-cotizar-meta">';
        // Solicitante y fecha
        $output .= '<p class="rfq-meta-item">' . sprintf(
            __('Solicitado por %s el %s', 'rfq-manager-woocommerce'),
            get_the_author_meta('display_name', $solicitud->post_author),
            get_the_date('', $solicitud->ID)
        ) . '</p>';
        // Estado de la solicitud
        $status_label = self::get_status_label($solicitud->post_status);
        $status_class = 'rfq-status rfq-status-' . esc_attr($solicitud->post_status) . ' status-' . esc_attr(str_replace('rfq-', '', $solicitud->post_status));
        $output .= '<p class="rfq-meta-item ' . $status_class . '">';
        $output .= '<span class="rfq-status-label">' . __('Estado:', 'rfq-manager-woocommerce') . '</span> ';
        $output .= '<span class="rfq-status-value">' . esc_html($status_label) . '</span>';
        $output .= '</p>';
        // Fecha de expiración
        $expiry_date = get_post_meta($solicitud->ID, '_solicitud_expiry', true);
        if ($expiry_date) {
            $expiry_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($expiry_date));
            $output .= '<p class="rfq-meta-item rfq-expiry">';
            $output .= '<span class="rfq-expiry-label">' . __('Vence el:', 'rfq-manager-woocommerce') . '</span> ';
            $output .= '<span class="rfq-expiry-value">' . esc_html($expiry_formatted) . '</span>';
            $output .= '</p>';
        }
        $output .= '</div>'; // .rfq-cotizar-meta
        $output .= '</div>'; // .rfq-cotizar-header
        return $output;
    }

    /**
     * Renderiza la tabla de items
     *
     * @since  0.1.0
     * @param  array $items Items de la solicitud
     * @param  int   $solicitud_id ID de la solicitud
     * @return string
     */
    private static function render_items_table(array $items, int $solicitud_id): string {
        // Obtener cotización existente del proveedor actual
        $existing_cotizacion = self::get_provider_cotizacion($solicitud_id);
        $precio_items = $existing_cotizacion ? get_post_meta($existing_cotizacion->ID, '_precio_items', true) : [];

        $output = '<div class="rfq-cotizar-items">';
        $output .= '<table class="rfq-cotizar-table">';
        $output .= '<thead><tr>';
        $output .= '<th>' . __('Producto', 'rfq-manager-woocommerce') . '</th>';
        $output .= '<th>' . __('Cantidad', 'rfq-manager-woocommerce') . '</th>';
        $output .= '<th>' . __('Precio Unitario', 'rfq-manager-woocommerce') . '</th>';
        $output .= '<th>' . __('IVA', 'rfq-manager-woocommerce') . '</th>';
        $output .= '<th>' . __('Subtotal', 'rfq-manager-woocommerce') . '</th>';
        $output .= '</tr></thead><tbody>';

        foreach ($items as $item) {
            $product = wc_get_product($item['product_id']);
            if (!$product) continue;

            $existing_price = isset($precio_items[$item['product_id']]) ? $precio_items[$item['product_id']]['precio'] : '';
            $existing_iva = isset($precio_items[$item['product_id']]) ? $precio_items[$item['product_id']]['iva'] : '21';
            $original_price = is_numeric($existing_price) ? $existing_price : 0;

            $output .= '<tr>';
            $output .= '<td>' . esc_html($product->get_name()) . '</td>';
            $output .= '<td>' . esc_html($item['qty']) . '</td>';
            $output .= '<td>';
            $output .= '<input type="number" name="precios[' . esc_attr($item['product_id']) . ']" 
                        class="rfq-precio-input" step="0.01" min="0" required
                        value="' . esc_attr($existing_price) . '"
                        data-original-price="' . esc_attr($original_price) . '">';
            $output .= '</td>';
            $output .= '<td>';
            $output .= '<select name="iva[' . esc_attr($item['product_id']) . ']" class="rfq-iva-select">';
            $output .= '<option value="4"' . selected($existing_iva, '4', false) . '>4%</option>';
            $output .= '<option value="10"' . selected($existing_iva, '10', false) . '>10%</option>';
            $output .= '<option value="21"' . selected($existing_iva, '21', false) . '>21%</option>';
            $output .= '</select>';
            $output .= '</td>';
            $output .= '<td class="rfq-subtotal">' . ($existing_price ? wc_price($existing_price * $item['qty']) : '0.00') . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody>';
        $output .= '<tfoot>';
        $output .= '<tr class="rfq-total-row">';
        $output .= '<td colspan="4" class="rfq-total-label">' . __('Total', 'rfq-manager-woocommerce') . '</td>';
        $output .= '<td class="rfq-total-amount">' . ($existing_cotizacion ? wc_price(get_post_meta($existing_cotizacion->ID, '_total', true)) : '0.00') . '</td>';
        $output .= '</tr>';
        $output .= '</tfoot>';
        $output .= '</table>';

        if ($existing_cotizacion) {
            $output .= '<div class="rfq-notice">';
            $output .= '<p>' . __('Puedes editar tu cotización actual. Recuerda que solo puedes bajar los precios, no subirlos.', 'rfq-manager-woocommerce') . '</p>';
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Obtiene la cotización existente del proveedor actual para una solicitud
     *
     * @since  0.1.0
     * @param  int    $solicitud_id ID de la solicitud
     * @return \WP_Post|null
     */
    public static function get_provider_cotizacion(int $solicitud_id): ?\WP_Post {
        $current_user_id = get_current_user_id();
        if (!$current_user_id || !$solicitud_id) {
            return null;
        }
        $cotizaciones = get_posts([
            'post_type'      => 'cotizacion',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_solicitud_parent',
                    'value' => intval($solicitud_id),
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ],
            ],
            'author'         => $current_user_id,
        ]);

        return !empty($cotizaciones) ? $cotizaciones[0] : null;
    }

    /**
     * Renderiza el botón de envío
     *
     * @since  0.1.0
     * @return string
     */
    private static function render_submit_button(): string {
        $output = '<div class="rfq-cotizar-submit">';
        $output .= '<button type="submit" class="rfq-submit-btn">' . __('Enviar Cotización', 'rfq-manager-woocommerce') . '</button>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Renderiza la tabla de cotizaciones recibidas de otros proveedores
     *
     * @since  0.1.0
     * @param  int $solicitud_id
     * @return string
     */
    private static function render_other_cotizaciones(int $solicitud_id): string {
        $current_user_id = get_current_user_id();
        $cotizaciones = get_posts([
            'post_type'      => 'cotizacion',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'   => '_solicitud_parent',
                    'value' => $solicitud_id,
                ],
            ],
            'author__not_in' => [$current_user_id],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        if (empty($cotizaciones)) {
            return '';
        }

        // error_log('RFQ - Renderizando cotizaciones de otros proveedores para solicitud #' . $solicitud_id);
        // error_log('RFQ - Número de cotizaciones encontradas: ' . count($cotizaciones));

        $output = '<div class="rfq-cotizaciones-otros">';
        $output .= '<h3>' . __('Cotizaciones recibidas de otros proveedores', 'rfq-manager-woocommerce') . '</h3>';
        $output .= '<table class="rfq-cotizaciones-otros-table">';
        $output .= '<thead><tr>';
        $output .= '<th>' . __('Proveedor', 'rfq-manager-woocommerce') . '</th>';
        $output .= '<th>' . __('Fecha', 'rfq-manager-woocommerce') . '</th>';
        $output .= '<th>' . __('Total', 'rfq-manager-woocommerce') . '</th>';
        $output .= '</tr></thead><tbody>';

        foreach ($cotizaciones as $cotizacion) {
            $proveedor = get_userdata($cotizacion->post_author);
            $nombre_proveedor = $proveedor ? esc_html($proveedor->display_name) : __('Desconocido', 'rfq-manager-woocommerce');
            $fecha = get_the_date('', $cotizacion->ID);
            $total = get_post_meta($cotizacion->ID, '_total', true);

            // Formatear el precio manualmente
            $total_formatted = number_format((float)$total, 2, ',', '.') . ' €';

            // error_log(sprintf(
            //     'RFQ - Cotización #%d - Proveedor: %s, Total meta: %s, Total formateado: %s',
            //     $cotizacion->ID,
            //     $nombre_proveedor,
            //     $total,
            //     $total_formatted
            // ));

            $output .= '<tr>';
            $output .= '<td>' . $nombre_proveedor . '</td>';
            $output .= '<td>' . esc_html($fecha) . '</td>';
            $output .= '<td class="rfq-total">' . esc_html($total_formatted) . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table>';
        $output .= '</div>';
        return $output;
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

    private static function get_status_class(string $status): string {
        $classes = [
            'rfq-pending'  => 'status-pending',
            'rfq-active'   => 'status-active',
            'rfq-closed'   => 'status-closed',
            'rfq-historic' => 'status-historic',
        ];

        return $classes[$status] ?? '';
    }

    public static function render_rfq_quotes($atts): string {
        // Extraer atributos y aplicar valores por defecto
        $atts = shortcode_atts([
            'per_page' => 10,
            'status' => 'active',
            'orderby' => 'date',
            'order' => 'DESC',
        ], $atts, 'rfq_quotes');

        // Verificar si el usuario está logueado
        if (!is_user_logged_in()) {
            return '<p class="rfq-error">' . __('Debes iniciar sesión para ver las cotizaciones.', 'rfq-manager-woocommerce') . '</p>';
        }

        // Obtener el usuario actual
        $user = wp_get_current_user();
        
        // Verificar si el usuario es administrador o proveedor
        if (!in_array('administrator', $user->roles) && !in_array('proveedor', $user->roles)) {
            return '<p class="rfq-error">' . __('No tienes permisos para ver las cotizaciones.', 'rfq-manager-woocommerce') . '</p>';
        }

        // Preparar argumentos de la consulta
        $query_args = [
            'post_type' => 'solicitud',
            'posts_per_page' => intval($atts['per_page']),
            'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
            'post_status' => ['publish', 'rfq-pending', 'rfq-active', 'rfq-closed', 'rfq-historic'],
            'orderby' => $atts['orderby'],
            'order' => $atts['order'],
        ];

        // Realizar la consulta
        $query = new \WP_Query($query_args);

        // Verificar si hay errores en la consulta
        if (is_wp_error($query)) {
            error_log('RFQ Manager - Error en la consulta: ' . $query->get_error_message());
            return '<p class="rfq-error">' . __('Error al cargar las cotizaciones.', 'rfq-manager-woocommerce') . '</p>';
        }

        // Si no hay posts, mostrar mensaje
        if (!$query->have_posts()) {
            return '<p class="rfq-notice">' . __('No hay cotizaciones disponibles.', 'rfq-manager-woocommerce') . '</p>';
        }

        // Inicio del contenedor
        $output = '<div class="rfq-quotes-container">';
        
        // Tabla de cotizaciones
        $output .= '<table class="rfq-quotes-table">';
        $output .= '<thead>';
        $output .= '<tr>';
        $output .= '<th>' . __('ID', 'rfq-manager-woocommerce') . '</th>';
        $output .= '<th>' . __('Usuario', 'rfq-manager-woocommerce') . '</th>';
        $output .= '<th>' . __('Fecha', 'rfq-manager-woocommerce') . '</th>';
        $output .= '<th>' . __('Estado', 'rfq-manager-woocommerce') . '</th>';
        $output .= '<th>' . __('Total', 'rfq-manager-woocommerce') . '</th>';
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
            $total = get_post_meta($solicitud_id, '_solicitud_total', true);

            $output .= '<tr>';
            $output .= '<td>#' . esc_html($order_id) . '</td>';
            $output .= '<td>' . esc_html(get_the_author_meta('display_name', $author_id)) . '</td>';
            $output .= '<td>' . esc_html(get_the_date()) . '</td>';
            $output .= '<td class="' . esc_attr(self::get_status_class($estado)) . '">' . esc_html(self::get_status_label($estado)) . '</td>';
            $output .= '<td class="rfq-total">' . esc_html(number_format((float)$total, 2, ',', '.') . ' €') . '</td>';
            $output .= '<td>';
            $output .= '<button class="rfq-toggle-details" data-solicitud="' . esc_attr($solicitud_id) . '">' . __('Ver Detalles', 'rfq-manager-woocommerce') . '</button>';
            $output .= '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody>';
        $output .= '</table>';
        $output .= '</div>';

        return $output;
    }
}