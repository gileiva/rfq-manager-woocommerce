<?php
/**
 * Administración de vista individual de solicitud
 *
 * @package    GiVendor\GiPlugin\Solicitud\Admin
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Solicitud\Admin;

/**
 * SolicitudView - Gestiona la vista administrativa de una solicitud individual
 *
 * Esta clase es responsable de crear y gestionar la interfaz de administración
 * para visualizar y administrar solicitudes individuales.
 *
 * @package    GiVendor\GiPlugin\Solicitud\Admin
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class SolicitudView {
    
    /**
     * Inicializa los hooks relacionados con la vista de solicitud
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        // Reemplazar la pantalla de edición estándar con nuestra vista personalizada
        add_action('load-post.php', [__CLASS__, 'setup_solicitud_view']);
        
        // Registrar los metaboxes personalizados para la pantalla de solicitud
        add_action('add_meta_boxes_solicitud', [__CLASS__, 'register_meta_boxes'], 10, 1);
        
        // Manejar el guardado de los datos de metabox
        add_action('save_post_solicitud', [__CLASS__, 'save_metabox_data'], 10, 3);
        
        // Agregar estilos y scripts específicos para esta vista
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
    }
    
    /**
     * Configura la vista personalizada para el tipo de post 'solicitud'
     *
     * @since  0.1.0
     * @return void
     */
    public static function setup_solicitud_view(): void {
        global $post, $post_type;
        
        if (isset($post_type) && $post_type === 'solicitud') {
            // Remover el editor predeterminado de WordPress
            remove_post_type_support('solicitud', 'editor');
            
            // Activar nuestros metaboxes personalizados
            add_screen_option('layout_columns', ['max' => 2, 'default' => 2]);
        }
    }
    
    /**
     * Registra los metaboxes personalizados para la pantalla de solicitud
     *
     * @since  0.1.0
     * @param  \WP_Post $post Objeto post actual
     * @return void
     */
    public static function register_meta_boxes(\WP_Post $post): void {
        // Metabox principal para la grilla de productos
        add_meta_box(
            'rfq_productos_solicitados',
            __('Productos Solicitados', 'rfq-manager-woocommerce'),
            [__CLASS__, 'render_productos_metabox'],
            'solicitud',
            'normal',
            'high'
        );
        
        // Metabox para datos del cliente
        add_meta_box(
            'rfq_datos_cliente',
            __('Datos del Cliente', 'rfq-manager-woocommerce'),
            [__CLASS__, 'render_cliente_metabox'],
            'solicitud',
            'side',
            'high'
        );
        
        // Metabox para estado de la solicitud
        add_meta_box(
            'rfq_estado_solicitud',
            __('Estado de la Solicitud', 'rfq-manager-woocommerce'),
            [__CLASS__, 'render_estado_metabox'],
            'solicitud',
            'side',
            'high'
        );
        
        // Metabox para ofertas recibidas (preparado para futuro)
        add_meta_box(
            'rfq_ofertas_recibidas',
            __('Ofertas Recibidas', 'rfq-manager-woocommerce'),
            [__CLASS__, 'render_ofertas_metabox'],
            'solicitud',
            'normal',
            'default'
        );
    }
    
    /**
     * Renderiza el metabox de productos solicitados
     *
     * @since  0.1.0
     * @param  \WP_Post $post Objeto post actual
     * @return void
     */
    public static function render_productos_metabox(\WP_Post $post): void {
        // Recuperar datos guardados de productos
        $items = json_decode(get_post_meta($post->ID, '_solicitud_items', true), true);
        
        if (empty($items)) {
            echo '<p>' . __('No hay productos en esta solicitud.', 'rfq-manager-woocommerce') . '</p>';
            return;
        }
        
        // Añadir nonce para seguridad
        wp_nonce_field('rfq_save_solicitud_data', 'rfq_solicitud_nonce');
        
        echo '<div class="rfq-productos-table-container">';
        echo '<table class="widefat rfq-productos-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('SKU', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('Producto', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('Cantidad', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('Precio Tienda', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('Total', 'rfq-manager-woocommerce') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        $gran_total = 0;
        $total_items = 0;

        foreach ($items as $item) {
            $product = wc_get_product($item['product_id']);
            $sku = $product ? $product->get_sku() : __('N/A', 'rfq-manager-woocommerce');
            $precio_unitario = floatval($item['subtotal']) / intval($item['qty']);
            $total = floatval($item['subtotal']);
            $gran_total += $total;
            $total_items += intval($item['qty']);
            
            echo '<tr>';
            echo '<td>' . esc_html($sku) . '</td>';
            echo '<td>' . esc_html($item['name']) . '</td>';
            echo '<td>' . absint($item['qty']) . '</td>';
            echo '<td>' . wc_price($precio_unitario) . '</td>';
            echo '<td>' . wc_price($total) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '<tfoot>';
        echo '<tr class="rfq-totals-row">';
        echo '<th colspan="2">' . __('Totales', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . $total_items . '</th>';
        echo '<th>' . __('Gran Total:', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . wc_price($gran_total) . '</th>';
        echo '</tr>';
        echo '</tfoot>';
        echo '</table>';
        echo '</div>';
    }
    
    /**
     * Renderiza el metabox de datos del cliente
     *
     * @since  0.1.0
     * @param  \WP_Post $post Objeto post actual
     * @return void
     */
    public static function render_cliente_metabox(\WP_Post $post): void {
        // Recuperar datos guardados del cliente
        $customer_data = json_decode(get_post_meta($post->ID, '_solicitud_customer', true), true);
        $shipping_data = json_decode(get_post_meta($post->ID, '_solicitud_shipping', true), true);
        $order_id = get_post_meta($post->ID, '_solicitud_order_id', true);
        
        if (empty($customer_data)) {
            echo '<p>' . __('No hay datos de cliente disponibles.', 'rfq-manager-woocommerce') . '</p>';
            return;
        }
        
        // Obtener información del usuario si existe
        $user_id = 0;
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $user_id = $order->get_customer_id();
            }
        }
        
        echo '<div class="rfq-customer-info">';
        echo '<p><strong>' . __('Nombre:', 'rfq-manager-woocommerce') . '</strong> ' . 
              esc_html($customer_data['first_name'] . ' ' . $customer_data['last_name']) . '</p>';
        
        echo '<p><strong>' . __('Email:', 'rfq-manager-woocommerce') . '</strong> ' . 
              esc_html($customer_data['email']) . '</p>';
        
        echo '<p><strong>' . __('Teléfono:', 'rfq-manager-woocommerce') . '</strong> ' . 
              esc_html($customer_data['phone']) . '</p>';
        
        // Añadir enlace al perfil del usuario si está registrado
        if ($user_id > 0) {
            echo '<p><a href="' . esc_url(admin_url('user-edit.php?user_id=' . $user_id)) . '" class="button button-small">';
            echo '<span class="dashicons dashicons-admin-users" style="margin-top: 3px;"></span> ';
            echo __('Ver perfil de usuario', 'rfq-manager-woocommerce');
            echo '</a></p>';
        }
        
        echo '<hr>';
        
        if (!empty($shipping_data)) {
            echo '<h4>' . __('Dirección de Envío:', 'rfq-manager-woocommerce') . '</h4>';
            echo '<p>' . esc_html($shipping_data['address_1']) . '</p>';
            
            if (!empty($shipping_data['address_2'])) {
                echo '<p>' . esc_html($shipping_data['address_2']) . '</p>';
            }
            
            echo '<p>' . esc_html($shipping_data['city'] . ', ' . 
                  $shipping_data['state'] . ' ' . $shipping_data['postcode']) . '</p>';
            
            echo '<p>' . esc_html($shipping_data['country']) . '</p>';
        }
        echo '</div>';
    }
    
    /**
     * Renderiza el metabox de estado de la solicitud
     *
     * @since  0.1.0
     * @param  \WP_Post $post Objeto post actual
     * @return void
     */
    public static function render_estado_metabox(\WP_Post $post): void {
        // Obtener el estado actual
        $current_status = $post->post_status;
        
        // Obtener las cotizaciones para esta solicitud
        $cotizaciones = get_posts([
            'post_type'      => 'cotizacion',
            'posts_per_page' => -1,
            'meta_key'       => '_solicitud_parent',
            'meta_value'     => $post->ID,
            'post_status'    => 'publish',
        ]);
        
        // Contar cotizaciones únicas
        $cotizaciones_unicas = [];
        foreach ($cotizaciones as $cotizacion) {
            $total = get_post_meta($cotizacion->ID, '_total', true);
            if ($total > 0) {
                $cotizaciones_unicas[$cotizacion->ID] = $total;
            }
        }
        
        // Mostrar el estado actual
        echo '<p><strong>' . __('Estado actual:', 'rfq-manager-woocommerce') . '</strong> ';
        echo esc_html(self::get_status_label($current_status));
        echo '</p>';
        
        // Campo para ajustar la fecha de vencimiento
        $expiry_date = get_post_meta($post->ID, '_solicitud_expiry', true);
        $expiry_date_formatted = $expiry_date ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($expiry_date)) : '';
        
        echo '<div class="rfq-expiry-date">';
        echo '<p><strong>' . __('Fecha de vencimiento:', 'rfq-manager-woocommerce') . '</strong></p>';
        echo '<input type="text" name="rfq_expiry_date" id="rfq-expiry-date" value="' . esc_attr($expiry_date) . '" class="widefat">';
        echo '<p class="description">' . __('Formato: YYYY-MM-DD HH:MM:SS', 'rfq-manager-woocommerce') . '</p>';
        if ($expiry_date) {
            echo '<p>' . __('Vence el:', 'rfq-manager-woocommerce') . ' <strong>' . esc_html($expiry_date_formatted) . '</strong></p>';
        }
        echo '</div>';
        
        // Mostrar número de cotizaciones
        echo '<p><strong>' . __('Ofertas recibidas:', 'rfq-manager-woocommerce') . '</strong> ';
        echo count($cotizaciones_unicas);
        echo '</p>';
        
        // Mostrar fecha de vencimiento si existe
        $expiry_date = get_post_meta($post->ID, '_solicitud_expiry', true);
        if ($expiry_date) {
            echo '<p><strong>' . __('Vence el:', 'rfq-manager-woocommerce') . '</strong> ';
            echo esc_html(date_i18n(get_option('date_format'), strtotime($expiry_date)));
            echo '</p>';
        }
        
        // Mostrar lista de cotizaciones
        if (!empty($cotizaciones_unicas)) {
            echo '<div class="rfq-cotizaciones-list">';
            echo '<h4>' . __('Cotizaciones recibidas:', 'rfq-manager-woocommerce') . '</h4>';
            echo '<ul>';
            foreach ($cotizaciones_unicas as $cotizacion_id => $total) {
                $cotizacion = get_post($cotizacion_id);
                if ($cotizacion) {
                    echo '<li>';
                    echo '<a href="' . esc_url(get_edit_post_link($cotizacion_id)) . '">';
                    echo esc_html($cotizacion->post_title);
                    echo '</a> - ';
                    echo wc_price($total);
                    echo '</li>';
                }
            }
            echo '</ul>';
            echo '</div>';
        }
    }
    
    /**
     * Renderiza el metabox de ofertas recibidas
     *
     * @since  0.1.0
     * @param  \WP_Post $post Objeto post actual
     * @return void
     */
    public static function render_ofertas_metabox(\WP_Post $post): void {
        // Obtener todas las cotizaciones relacionadas con esta solicitud
        $cotizaciones = get_posts([
            'post_type' => 'cotizacion',
            'meta_key' => '_solicitud_parent',
            'meta_value' => $post->ID,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        if (empty($cotizaciones)) {
            echo '<p>' . __('No hay cotizaciones recibidas para esta solicitud.', 'rfq-manager-woocommerce') . '</p>';
            return;
        }

        echo '<div class="rfq-cotizaciones-container">';
        echo '<table class="widefat rfq-cotizaciones-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Fecha', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('Proveedor', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('Total', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('Acciones', 'rfq-manager-woocommerce') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($cotizaciones as $cotizacion) {
            $proveedor = get_user_by('id', $cotizacion->post_author);
            $total = get_post_meta($cotizacion->ID, '_total', true);

            echo '<tr>';
            echo '<td>' . get_the_date('d/m/Y H:i', $cotizacion->ID) . '</td>';
            echo '<td>' . esc_html($proveedor ? $proveedor->display_name : __('N/A', 'rfq-manager-woocommerce')) . '</td>';
            echo '<td>' . wc_price($total) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url(get_edit_post_link($cotizacion->ID)) . '" class="button button-small">';
            echo '<span class="dashicons dashicons-visibility"></span> ' . __('Ver Detalles', 'rfq-manager-woocommerce');
            echo '</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
    
    /**
     * Guarda los datos de los metaboxes
     *
     * @since  0.1.0
     * @param  int      $post_id ID del post
     * @param  \WP_Post $post    Objeto post
     * @param  bool     $update  Si es una actualización o no
     * @return void
     */
    public static function save_metabox_data($post_id, $post, $update): void {
        // Verificar el nonce
        if (!isset($_POST['rfq_solicitud_nonce']) || !wp_verify_nonce($_POST['rfq_solicitud_nonce'], 'rfq_save_solicitud_data')) {
            return;
        }
        
        // Verificar si es autoguardado
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Guardar fecha de vencimiento si está presente
        if (isset($_POST['rfq_expiry_date'])) {
            $expiry_date = sanitize_text_field($_POST['rfq_expiry_date']);
            
            // Validar formato de fecha
            if (strtotime($expiry_date)) {
                update_post_meta($post_id, '_solicitud_expiry', $expiry_date);
            }
        }
    }
    
    /**
     * Carga scripts y estilos específicos para la administración de solicitudes
     *
     * @since  0.1.0
     * @param  string $hook Hook actual
     * @return void
     */
    public static function enqueue_admin_scripts($hook): void {
        global $post_type, $post;

        // Sólo cargar en la pantalla de edición de solicitudes
        if (!($hook == 'post.php' && isset($post_type) && $post_type === 'solicitud')) {
            return;
        }
        
        // Registrar y encolar estilos
        wp_register_style(
            'rfq-admin-styles',
            plugins_url('assets/css/admin-solicitud.css', dirname(dirname(dirname(__FILE__)))),
            [],
            '0.1.0'
        );
        
        wp_enqueue_style('rfq-admin-styles');
        
        // Añadir soporte para datepicker de jQuery UI para selector de fecha
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        
        // Script para inicializar componentes interactivos
        wp_enqueue_script(
            'rfq-admin-script',
            plugins_url('assets/js/admin-solicitud.js', dirname(dirname(dirname(__FILE__)))),
            ['jquery', 'jquery-ui-datepicker'],
            '0.1.0',
            true
        );
    }

    /**
     * Obtiene la etiqueta legible para un estado
     *
     * @since  0.1.0
     * @param  string $status Estado a traducir
     * @return string        Etiqueta traducida
     */
    private static function get_status_label(string $status): string {
        switch ($status) {
            case 'rfq-pending':
                return __('Pendiente de cotización', 'rfq-manager-woocommerce');
            case 'rfq-accepted':
                return __('Propuesta aceptada', 'rfq-manager-woocommerce');
            case 'rfq-historic':
                return __('Histórico', 'rfq-manager-woocommerce');
            default:
                return $status;
        }
    }
}