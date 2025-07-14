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
        add_action('save_post_solicitud', [self::class, 'save_meta_boxes'], 10, 3);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_scripts']);
        
        // Remover el metabox nativo de publicar para solicitudes - Con prioridad alta para ejecutar después
        add_action('add_meta_boxes_solicitud', [self::class, 'remove_publish_meta_box'], 99);
        
        // Handlers AJAX para actualizar estado y fecha
        add_action('wp_ajax_update_solicitud_status', [self::class, 'ajax_update_status']);
        add_action('wp_ajax_update_solicitud_expiry', [self::class, 'ajax_update_expiry']);
        
        // Agregar filtros para mantener el estado personalizado
        add_filter('wp_insert_post_data', [self::class, 'maintain_custom_status'], 10, 2);
        add_filter('post_status_transitions', [self::class, 'handle_status_transition'], 10, 3);
        
        // NUEVO: Hook para mostrar estados correctamente en el backend
        add_filter('display_post_states', [self::class, 'display_post_states'], 10, 2);
    }

    /**
     * Encola scripts y estilos para la administración
     *
     * @since  0.1.0
     * @param  string $hook Hook actual
     * @return void
     */
    public static function enqueue_admin_scripts(string $hook): void {
        global $post_type;

        // Solo cargar en la pantalla de edición de solicitudes
        if (!in_array($hook, ['post.php', 'post-new.php']) || $post_type !== 'solicitud') {
            return;
        }

        // Flatpickr para el datetime picker
        wp_enqueue_script(
            'flatpickr',
            'https://cdn.jsdelivr.net/npm/flatpickr',
            [],
            '4.6.13',
            true
        );

        wp_enqueue_style(
            'flatpickr',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
            [],
            '4.6.13'
        );

        // Script en español para flatpickr
        wp_enqueue_script(
            'flatpickr-es',
            'https://npmcdn.com/flatpickr/dist/l10n/es.js',
            ['flatpickr'],
            '4.6.13',
            true
        );

        // Estilos para la administración de solicitudes
        wp_enqueue_style(
            'rfq-admin-solicitud',
            plugins_url('assets/css/admin-solicitud.css', dirname(dirname(dirname(__FILE__)))),
            [],
            '0.1.0'
        );
    }

    /**
     * Agrega los metaboxes a la página de edición
     *
     * @since  0.1.0
     * @return void
     */
    public static function add_meta_boxes(): void {
        // Metabox principal para la grilla de productos
        add_meta_box(
            'rfq_productos_solicitados',
            __('Productos Solicitados', 'rfq-manager-woocommerce'),
            [self::class, 'render_productos_metabox'],
            'solicitud',
            'normal',
            'high'
        );
        
        // Metabox para datos del cliente
        add_meta_box(
            'rfq_datos_cliente',
            __('Datos del Cliente', 'rfq-manager-woocommerce'),
            [self::class, 'render_cliente_metabox'],
            'solicitud',
            'side',
            'high'
        );
        
        // Metabox para datos de la solicitud
        add_meta_box(
            'rfq_datos_solicitud',
            __('Datos de la Solicitud', 'rfq-manager-woocommerce'),
            [self::class, 'render_datos_solicitud_metabox'],
            'solicitud',
            'side',
            'high'
        );

        // Metabox para estado de la solicitud
        add_meta_box(
            'solicitud_status',
            __('Estado de la Solicitud', 'rfq-manager-woocommerce'),
            [self::class, 'render_estado_metabox'],
            'solicitud',
            'side',
            'high'
        );
        
        // Metabox para ofertas recibidas
        add_meta_box(
            'rfq_ofertas_recibidas',
            __('Ofertas Recibidas', 'rfq-manager-woocommerce'),
            [self::class, 'render_ofertas_metabox'],
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
        // Verificar permisos
        if (!current_user_can('read_post', $post->ID)) {
            echo '<p>' . esc_html__('No tienes permisos para ver esta información.', 'rfq-manager-woocommerce') . '</p>';
            return;
        }

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
        // Verificar permisos
        if (!current_user_can('read_post', $post->ID)) {
            echo '<p>' . esc_html__('No tienes permisos para ver esta información.', 'rfq-manager-woocommerce') . '</p>';
            return;
        }

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
     * Renderiza el metabox de datos de la solicitud
     *
     * @since  0.1.0
     * @param  \WP_Post $post Objeto post actual
     * @return void
     */
    public static function render_datos_solicitud_metabox(\WP_Post $post): void {
        // Verificar permisos
        if (!current_user_can('read_post', $post->ID)) {
            echo '<p>' . esc_html__('No tienes permisos para ver esta información.', 'rfq-manager-woocommerce') . '</p>';
            return;
        }

        // Obtener el ID de la orden asociada
        $order_id = get_post_meta($post->ID, '_solicitud_order_id', true);
        
        // Obtener la fecha de creación
        $creation_date = get_post_meta($post->ID, '_solicitud_date', true);
        
        // Obtener todas las cotizaciones relacionadas con esta solicitud
        $cotizaciones = get_posts([
            'post_type' => 'cotizacion',
            'meta_key' => '_solicitud_parent',
            'meta_value' => $post->ID,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => ['publish', 'rfq-accepted', 'rfq-historic', 'rfq-closed'] // Incluir todos los estados posibles
        ]);
        
        // Contar cotizaciones únicas
        $cotizaciones_unicas = [];
        foreach ($cotizaciones as $cotizacion) {
            $total = get_post_meta($cotizacion->ID, '_total', true);
            if ($total > 0) {
                $cotizaciones_unicas[$cotizacion->ID] = $total;
            }
        }
        
        // Obtener fecha de vencimiento
        $expiry_date = get_post_meta($post->ID, '_solicitud_expiry', true);
        
        echo '<div class="rfq-datos-solicitud">';
        
        // Número de orden - CORREGIDO
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                echo '<p><strong>' . __('Orden WooCommerce:', 'rfq-manager-woocommerce') . '</strong> ';
                echo '<a href="' . esc_url(get_edit_post_link($order_id)) . '">#' . esc_html($order->get_order_number()) . '</a>';
                echo '</p>';
            }
        }
        
        // Fecha de creación
        if ($creation_date) {
            echo '<p><strong>' . __('Fecha de creación:', 'rfq-manager-woocommerce') . '</strong> ';
            echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($creation_date)));
            echo '</p>';
        }
        
        // Ofertas recibidas
        echo '<p><strong>' . __('Ofertas recibidas:', 'rfq-manager-woocommerce') . '</strong> ';
        echo count($cotizaciones_unicas);
        echo '</p>';
        
        // Fecha de vencimiento
        if ($expiry_date) {
            echo '<p><strong>' . __('Vence el:', 'rfq-manager-woocommerce') . '</strong> ';
            echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($expiry_date)));
            echo '</p>';
        }
        
        // Lista de cotizaciones
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
        
        echo '</div>';
    }

    /**
     * Renderiza el metabox de ofertas recibidas
     *
     * @since  0.1.0
     * @param  \WP_Post $post Objeto post actual
     * @return void
     */
    public static function render_ofertas_metabox(\WP_Post $post): void {
        // Verificar permisos
        if (!current_user_can('read_post', $post->ID)) {
            echo '<p>' . esc_html__('No tienes permisos para ver esta información.', 'rfq-manager-woocommerce') . '</p>';
            return;
        }

        // Obtener todas las cotizaciones relacionadas con esta solicitud
        $cotizaciones = get_posts([
            'post_type' => 'cotizacion',
            'meta_key' => '_solicitud_parent',
            'meta_value' => $post->ID,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => ['publish', 'rfq-accepted', 'rfq-historic', 'rfq-closed'] // Incluir todos los estados posibles
        ]);

        echo '<div class="rfq-cotizaciones-container">';
        
        if (empty($cotizaciones)) {
            echo '<div class="rfq-no-quotes-message">';
            echo '<p>' . __('No hay cotizaciones recibidas para esta solicitud.', 'rfq-manager-woocommerce') . '</p>';
            echo '</div>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat rfq-cotizaciones-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Fecha', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('Proveedor', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('Total', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('Estado', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('%OFF', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('Acciones', 'rfq-manager-woocommerce') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($cotizaciones as $cotizacion) {
            $proveedor = get_user_by('id', $cotizacion->post_author);
            $total = get_post_meta($cotizacion->ID, '_total', true);
            $estado = $cotizacion->post_status;

            // Calcular el porcentaje de descuento
            $items = json_decode(get_post_meta($post->ID, '_solicitud_items', true), true);
            $gran_total = 0;
            foreach ($items as $item) {
                $gran_total += floatval($item['subtotal']);
            }
            
            $porcentaje = 0;
            if ($gran_total > 0) {
                // Si el total de la cotización es mayor que el gran total, el porcentaje será negativo
                // Si el total de la cotización es menor que el gran total, el porcentaje será positivo
                $porcentaje = (($gran_total - $total) / $gran_total) * 100;
                
                // Formatear el porcentaje para mostrar solo 2 decimales
                $porcentaje = round($porcentaje, 2);
            }

            echo '<tr>';
            echo '<td>' . get_the_date('d/m/Y H:i', $cotizacion->ID) . '</td>';
            echo '<td>' . esc_html($proveedor ? $proveedor->display_name : __('N/A', 'rfq-manager-woocommerce')) . '</td>';
            echo '<td>' . wc_price($total) . '</td>';
            echo '<td class="rfq-estado-cotizacion ' . esc_attr($estado) . '">' . esc_html(self::get_cotizacion_status_label($estado)) . '</td>';
            echo '<td class="rfq-porcentaje-descuento ' . ($porcentaje > 0 ? 'descuento-positivo' : 'descuento-negativo') . '">' . 
                 number_format($porcentaje, 2) . '%' . '</td>';
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
     * Renderiza el metabox de estado
     *
     * @since  0.1.0
     * @param  \WP_Post $post Objeto post
     * @return void
     */
    public static function render_estado_metabox(\WP_Post $post): void {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            echo '<p>' . esc_html__('No tienes permisos para modificar el estado de esta solicitud.', 'rfq-manager-woocommerce') . '</p>';
            return;
        }

        // Obtener estados disponibles desde SolicitudStatusHandler
        $statuses = [
            'rfq-pending' => __('Pendiente', 'rfq-manager-woocommerce'),
            'rfq-active' => __('Activa', 'rfq-manager-woocommerce'),
            'rfq-accepted' => __('Aceptada', 'rfq-manager-woocommerce'),
            'rfq-closed' => __('Cerrada', 'rfq-manager-woocommerce'),
            'rfq-historic' => __('Histórica', 'rfq-manager-woocommerce')
        ];

        // Obtener estado actual
        $current_status = $post->post_status;
        if (!in_array($current_status, array_keys($statuses), true)) {
            $current_status = 'rfq-pending';
        }

        // Obtener fecha de expiración
        $expiry_date = get_post_meta($post->ID, '_solicitud_expiry', true);
        if (!$expiry_date) {
            $expiry_timestamp = current_time('timestamp') + (24 * HOUR_IN_SECONDS);
            $expiry_date = date('Y-m-d H:i:s', $expiry_timestamp);
            update_post_meta($post->ID, '_solicitud_expiry', $expiry_date);
        }

        // Convertir a formato de visualización para datetime-local input
        $expiry_timestamp = strtotime($expiry_date);
        $expiry_display = date('Y-m-d\TH:i', $expiry_timestamp);

        // Nonce unificado para seguridad
        wp_nonce_field('rfq_solicitud_status_nonce', 'rfq_nonce');
        ?>
        
        <div class="rfq-estado-metabox">
            <div class="rfq-current-status">
                <strong><?php esc_html_e('Estado actual:', 'rfq-manager-woocommerce'); ?></strong>
                <span class="status-label status-<?php echo esc_attr($current_status); ?>">
                    <?php echo esc_html($statuses[$current_status] ?? $current_status); ?>
                </span>
            </div>

            <p>
                <label for="solicitud_status"><?php esc_html_e('Cambiar estado:', 'rfq-manager-woocommerce'); ?></label>
                <select name="solicitud_status" id="solicitud_status" class="widefat" data-current-status="<?php echo esc_attr($current_status); ?>">
                    <?php foreach ($statuses as $status => $label) : ?>
                        <option value="<?php echo esc_attr($status); ?>"<?php echo ($current_status === $status) ? ' selected="selected"' : ''; ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label for="_solicitud_expiry"><?php esc_html_e('Fecha de expiración:', 'rfq-manager-woocommerce'); ?></label>
                <input type="datetime-local" 
                       name="_solicitud_expiry"
                       id="_solicitud_expiry"
                       value="<?php echo esc_attr($expiry_display); ?>" 
                       class="widefat rfq-datetime-picker">
                <small style="color: #666;"><?php esc_html_e('Solo aplica para estados Pendiente y Activa', 'rfq-manager-woocommerce'); ?></small>
            </p>

            <div class="rfq-form-actions">
                <button type="button" id="rfq-save-status" class="button button-primary"><?php esc_html_e('Guardar cambios', 'rfq-manager-woocommerce'); ?></button>
                <span id="rfq-save-status-spinner" class="spinner" style="float: none; margin-left: 10px;"></span>
            </div>

            <div class="rfq-status-message" style="display: none;"></div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var originalStatus = '<?php echo esc_js($current_status); ?>';
            var originalExpiry = '<?php echo esc_js($expiry_display); ?>';
            
            // Guardar referencia al select
            var $select = $('#solicitud_status');
            var currentSelectedStatus = originalStatus;
            
            // Función para obtener el valor actual del select de forma más robusta
            function getCurrentStatus() {
                // PRIORIDAD 1: SIEMPRE usar el valor directo del DOM del select
                var selectValue = $select.val();
                if (selectValue && selectValue !== '' && selectValue !== null) {
                    // Verificar que sea un estado válido (no una fecha)
                    var validStates = ['rfq-pending', 'rfq-active', 'rfq-accepted', 'rfq-closed', 'rfq-historic'];
                    if (validStates.includes(selectValue)) {
                        // Sincronizar memoria solo si es válido
                        currentSelectedStatus = selectValue;
                        return selectValue;
                    } else {
                        console.warn('[RFQ] Estado inválido detectado en select:', selectValue);
                    }
                }
                
                // PRIORIDAD 2: Obtener de la opción seleccionada específicamente
                var selectedOption = $select.find('option:selected').val();
                if (selectedOption && selectedOption !== '') {
                    var validStates = ['rfq-pending', 'rfq-active', 'rfq-accepted', 'rfq-closed', 'rfq-historic'];
                    if (validStates.includes(selectedOption)) {
                        currentSelectedStatus = selectedOption; // Sincronizar memoria
                        return selectedOption;
                    }
                }
                
                // PRIORIDAD 3: Usar el estado original como fallback SEGURO
                if (originalStatus && originalStatus !== '') {
                    currentSelectedStatus = originalStatus; // Restaurar memoria
                    return originalStatus;
                }
                
                // Último recurso: buscar la primera opción válida
                var firstValidOption = $select.find('option[value!=""]').first().val();
                if (firstValidOption) {
                    currentSelectedStatus = firstValidOption;
                    return firstValidOption;
                }
                
                // Error crítico: no se pudo obtener ningún valor
                console.error('[RFQ] ERROR CRÍTICO: No se pudo obtener ningún estado válido');
                return null;
            }
            
            // Función para establecer el valor del select de forma robusta
            function setCurrentStatus(newStatus) {
                if (!newStatus || newStatus === '' || newStatus === null) {
                    console.warn('[RFQ] Intento de establecer estado inválido:', newStatus);
                    return false;
                }
                
                // Verificar que la opción existe en el select
                var optionExists = $select.find('option[value="' + newStatus + '"]').length > 0;
                if (!optionExists) {
                    console.error('[RFQ] Estado no existe en las opciones del select:', newStatus);
                    return false;
                }
                
                // Actualizar la variable de estado en memoria
                currentSelectedStatus = newStatus;
                
                // Actualizar el select
                $select.val(newStatus);
                $select.find('option').prop('selected', false);
                $select.find('option[value="' + newStatus + '"]').prop('selected', true);
                
                console.log('[RFQ] Estado establecido correctamente:', newStatus);
                return true;
            }
            
            // Forzar el valor correcto al inicio
            setCurrentStatus(originalStatus);
            
            // Verificación adicional para asegurar la consistencia
            setTimeout(function() {
                var verificacionEstado = getCurrentStatus();
                if (!verificacionEstado || verificacionEstado === '' || verificacionEstado === null) {
                    console.warn('[RFQ] Estado inconsistente detectado al inicializar, forzando estado original');
                    setCurrentStatus(originalStatus);
                }
            }, 100);
            
            // Inicializar el datetime picker
            $('.rfq-datetime-picker').flatpickr({
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                locale: "es",
                time_24hr: true
            });

            // Función para ejecutar el cambio de estado y fecha
            function executeStatusAndExpiryChange() {
                var $button = $('#rfq-save-status');
                var $message = $('.rfq-status-message');
                var $spinner = $('#rfq-save-status-spinner');
                var solicitudId = <?php echo esc_js($post->ID); ?>;
                var newStatus = getCurrentStatus();
                var expiryDate = $('#_solicitud_expiry').val();
                
                // Validar que tenemos un estado válido
                if (!newStatus || newStatus === '' || newStatus === null) {
                    console.error('[RFQ] ERROR CRÍTICO: Estado no válido obtenido:', newStatus);
                    
                    $message
                        .addClass('notice-error')
                        .html('<span class="dashicons dashicons-warning"></span> Error: No se pudo obtener el estado seleccionado.')
                        .show();
                    return;
                }
                
                // Preparar datos
                var formData = {
                    action: 'update_solicitud_status',
                    rfq_nonce: $('#rfq_nonce').val(),
                    solicitud_id: solicitudId,
                    solicitud_status: newStatus,
                    _solicitud_expiry: expiryDate
                };

                // Enviar petición AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    beforeSend: function() {
                        $message.removeClass('notice-success notice-error').hide();
                        $button.prop('disabled', true);
                        $spinner.addClass('is-active');
                    },
                    success: function(response) {
                        if (response.success) {
                            $message
                                .addClass('notice-success')
                                .html('<span class="dashicons dashicons-yes-alt"></span> ' + response.data.message)
                                .show();
                            
                            originalStatus = newStatus;
                            originalExpiry = expiryDate;
                            updateStatusDisplay(newStatus);
                            
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        } else {
                            $message
                                .addClass('notice-error')
                                .html('<span class="dashicons dashicons-warning"></span> ' + (response.data.message || 'Error desconocido'))
                                .show();
                                
                            setCurrentStatus(originalStatus);
                            $('#_solicitud_expiry').val(originalExpiry);
                            updateStatusDisplay(originalStatus);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('[RFQ] Error AJAX al actualizar estado:', error);
                        $message
                            .addClass('notice-error')
                            .html('<span class="dashicons dashicons-warning"></span> <?php esc_html_e('Error al actualizar. Por favor, intenta de nuevo.', 'rfq-manager-woocommerce'); ?>')
                            .show();
                            
                        setCurrentStatus(originalStatus);
                        $('#_solicitud_expiry').val(originalExpiry);
                        updateStatusDisplay(originalStatus);
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                        $spinner.removeClass('is-active');
                    }
                });
            }

            // Función para actualizar la visualización del estado
            function updateStatusDisplay(newStatus) {
                var $statusLabel = $('.status-label');
                var statusText = $select.find('option[value="' + newStatus + '"]').text();
                
                $statusLabel.removeClass(function (index, className) {
                    return (className.match(/(^|\s)status-\S+/g) || []).join(' ');
                });
                $statusLabel.addClass('status-' + newStatus);
                $statusLabel.text(statusText);
            }

            // Manejar cambio del select
            $select.on('change input', function(e) {
                var eventValue = e.target.value;
                var selectValue = $(this).val();
                var selectedOptionValue = $(this).find('option:selected').val();
                
                // Obtener el nuevo estado con prioridad al valor del evento
                var newStatus = eventValue || selectValue || selectedOptionValue;
                
                // Verificar que el nuevo estado es válido antes de aceptarlo
                if (newStatus && newStatus !== '' && newStatus !== null) {
                    // VALIDACIÓN CRÍTICA: Solo aceptar estados válidos
                    var validStates = ['rfq-pending', 'rfq-active', 'rfq-accepted', 'rfq-closed', 'rfq-historic'];
                    if (validStates.includes(newStatus)) {
                        currentSelectedStatus = newStatus;
                        updateStatusDisplay(newStatus);
                        
                        // Asegurar que el select mantenga el valor correcto
                        setTimeout(function() {
                            setCurrentStatus(newStatus);
                        }, 10);
                    } else {
                        console.warn('[RFQ] Estado INVÁLIDO rechazado:', newStatus, '- Manteniendo estado actual');
                        // Rechazar el cambio y mantener el estado válido
                        setCurrentStatus(currentSelectedStatus || originalStatus);
                    }
                } else {
                    console.warn('[RFQ] Estado vacío detectado, manteniendo estado actual');
                    // Si el estado no es válido, mantener el estado actual
                    setCurrentStatus(currentSelectedStatus || originalStatus);
                }
            });

            // Manejar clic del botón guardar
            $('#rfq-save-status').on('click', function(e) {
                e.preventDefault();
                executeStatusAndExpiryChange();
            });
        });
        </script>
        <?php
    }

    /**
     * Guarda los datos de los metaboxes
     *
     * @since  0.1.0
     * @param  int     $post_id ID del post
     * @param  \WP_Post $post   Objeto post
     * @param  bool    $update  Si es una actualización
     * @return void
     */
    public static function save_meta_boxes(int $post_id, \WP_Post $post, bool $update): void {
        // No procesar si estamos en la papelera
        if ($post->post_status === 'trash') {
            return;
        }

        // Verificar si es autoguardado
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Verificar tipo de post
        if (get_post_type($post_id) !== 'solicitud') {
            return;
        }

        // Solo verificar nonce si estamos procesando datos del metabox de estado
        $has_status_data = isset($_POST['solicitud_status']) || isset($_POST['_solicitud_expiry']);
        
        if ($has_status_data) {
            // Verificar nonce para cambios de estado
            if (!isset($_POST['rfq_nonce']) || !wp_verify_nonce(sanitize_key($_POST['rfq_nonce']), 'rfq_solicitud_status_nonce')) {
                error_log('[RFQ] Error crítico: Nonce inválido para actualización de estado');
                return;
            }

            // Verificar permisos - Solo admins pueden cambiar estado desde el backend
            if (!current_user_can('manage_options')) {
                error_log('[RFQ] Error crítico: Permisos insuficientes para cambiar estado desde el backend');
                return;
            }

            // Obtener estado actual y nuevo estado
            $current_status = $post->post_status;
            $new_status = isset($_POST['solicitud_status']) ? sanitize_key($_POST['solicitud_status']) : $current_status;

            // Usar SolicitudStatusHandler para actualizar el estado
            if ($current_status !== $new_status) {
                $update_result = \GiVendor\GiPlugin\Solicitud\SolicitudStatusHandler::update_status($post_id, $new_status);
                
                if (!$update_result) {
                    error_log(sprintf('[RFQ] Error crítico: No se pudo actualizar estado de solicitud #%d', $post_id));
                    return;
                }
            }

            // Manejar fecha de expiración
            if (isset($_POST['_solicitud_expiry'])) {
                $expiry_date = sanitize_text_field($_POST['_solicitud_expiry']);
                // Convertir de formato datetime-local a Y-m-d H:i:s
                if (strtotime($expiry_date)) {
                    $expiry_date = date('Y-m-d H:i:s', strtotime($expiry_date));
                    update_post_meta($post_id, '_solicitud_expiry', $expiry_date);
                    
                    // Reprogramar el cambio de estado si es necesario
                    if (in_array($new_status, ['rfq-pending', 'rfq-active'])) {
                        \GiVendor\GiPlugin\Solicitud\Scheduler\StatusScheduler::schedule_change_to_historic($post_id);
                    }
                }
            }

            // Si el cambio es de histórico a otro estado, establecer nueva fecha
            if ($current_status === 'rfq-historic' && $new_status !== 'rfq-historic') {
                // Obtener el tiempo de vencimiento por defecto
                $default_expiry_hours = \GiVendor\GiPlugin\Solicitud\Scheduler\StatusScheduler::get_default_expiry_hours();
                
                // Establecer nueva fecha de vencimiento
                $new_expiry_timestamp = current_time('timestamp') + ($default_expiry_hours * HOUR_IN_SECONDS);
                $new_expiry = date('Y-m-d H:i:s', $new_expiry_timestamp);
                update_post_meta($post_id, '_solicitud_expiry', $new_expiry);
                
                // Reprogramar el cambio de estado
                \GiVendor\GiPlugin\Solicitud\Scheduler\StatusScheduler::schedule_change_to_historic($post_id);
            }
        }
    }

    /**
     * Mantiene el estado personalizado al guardar
     *
     * @since  0.1.0
     * @param  array $data    Datos del post
     * @param  array $postarr Datos del post original
     * @return array Datos modificados
     */
    public static function maintain_custom_status(array $data, array $postarr): array {
        // Solo procesar solicitudes
        if ($data['post_type'] !== 'solicitud') {
            return $data;
        }

        // Si es una petición AJAX de actualización de estado, no procesar aquí
        if (wp_doing_ajax() && isset($_POST['action']) && $_POST['action'] === 'update_solicitud_status') {
            return $data;
        }

        // Si hay un estado personalizado en el formulario, usarlo
        if (isset($_POST['solicitud_status'])) {
            $custom_status = sanitize_key($_POST['solicitud_status']);
            $valid_statuses = ['rfq-pending', 'rfq-active', 'rfq-accepted', 'rfq-closed', 'rfq-historic'];
            
            if (in_array($custom_status, $valid_statuses, true)) {
                $data['post_status'] = $custom_status;
            }
        }

        return $data;
    }

    /**
     * Maneja las transiciones de estado
     *
     * @since  0.1.0
     * @param  array  $transitions Transiciones disponibles
     * @param  string $old_status  Estado anterior
     * @param  string $new_status  Nuevo estado
     * @return array  Transiciones modificadas
     */
    public static function handle_status_transition(array $transitions, string $old_status, string $new_status): array {
        return $transitions;
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
            'rfq-accepted' => __('Aceptada', 'rfq-manager-woocommerce'),
            'rfq-closed'   => __('Cerrada', 'rfq-manager-woocommerce'),
            'rfq-historic' => __('Histórica', 'rfq-manager-woocommerce'),
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * Remueve el metabox nativo de publicar
     *
     * @since  0.1.0
     * @return void
     */
    public static function remove_publish_meta_box(): void {
        remove_meta_box('submitdiv', 'solicitud', 'side');
        remove_meta_box('submitdiv', 'solicitud', 'normal');
        remove_meta_box('submitdiv', 'solicitud', 'advanced');
    }

    /**
     * Handler AJAX para actualizar el estado de la solicitud
     *
     * @since  0.1.0
     * @return void
     */
    public static function ajax_update_status(): void {
        // Verificar nonce
        if (!isset($_POST['rfq_nonce']) || !wp_verify_nonce(sanitize_key($_POST['rfq_nonce']), 'rfq_solicitud_status_nonce')) {
            error_log('[RFQ] ERROR CRÍTICO: Nonce inválido en ajax_update_status');
            wp_send_json_error(['message' => __('Nonce inválido', 'rfq-manager-woocommerce')]);
        }

        // Obtener datos
        $solicitud_id = absint($_POST['solicitud_id'] ?? 0);
        $new_status = sanitize_key($_POST['solicitud_status'] ?? '');
        $expiry_date = sanitize_text_field($_POST['_solicitud_expiry'] ?? '');

        if (!$solicitud_id) {
            error_log('[RFQ] ERROR CRÍTICO: ID de solicitud inválido');
            wp_send_json_error(['message' => __('ID de solicitud inválido', 'rfq-manager-woocommerce')]);
        }

        // NUEVA PROTECCIÓN: Establecer transient para evitar condición de carrera
        set_transient('rfq_ajax_status_update_' . $solicitud_id, true, 30);

        // Verificar que la solicitud existe
        $solicitud = get_post($solicitud_id);
        if (!$solicitud || $solicitud->post_type !== 'solicitud') {
            error_log(sprintf('[RFQ] ERROR CRÍTICO: Solicitud #%d no encontrada', $solicitud_id));
            delete_transient('rfq_ajax_status_update_' . $solicitud_id);
            wp_send_json_error(['message' => __('Solicitud no encontrada', 'rfq-manager-woocommerce')]);
        }

        $current_status = $solicitud->post_status;
        $user = wp_get_current_user();

        // Verificación granular de permisos
        $is_admin = current_user_can('manage_options');
        $is_author = ((int)$solicitud->post_author === (int)$user->ID);
        $is_cancellation = ($new_status === 'rfq-historic');

        // Determinar si el usuario tiene permisos para esta acción específica
        $has_permission = false;

        if ($is_admin) {
            // Los administradores pueden hacer cualquier cambio
            $has_permission = true;
        } elseif ($is_author && $is_cancellation) {
            // Los autores pueden cancelar (cambiar a histórico) sus propias solicitudes
            // pero solo si están en estado pendiente o activo
            if (in_array($current_status, ['rfq-pending', 'rfq-active'])) {
                $has_permission = true;
            } else {
                wp_send_json_error(['message' => __('Esta solicitud no puede ser cancelada en su estado actual', 'rfq-manager-woocommerce')]);
            }
        }

        if (!$has_permission) {
            error_log('[RFQ] ERROR CRÍTICO: Usuario sin permisos para actualizar estado');
            delete_transient('rfq_ajax_status_update_' . $solicitud_id);
            wp_send_json_error(['message' => __('No tienes permisos para realizar esta acción', 'rfq-manager-woocommerce')]);
        }

        // Actualizar estado si cambió
        if ($current_status !== $new_status && !empty($new_status)) {
            $valid_statuses = ['rfq-pending', 'rfq-active', 'rfq-accepted', 'rfq-closed', 'rfq-historic'];
            
            if (!in_array($new_status, $valid_statuses, true)) {
                error_log(sprintf('[RFQ] ERROR CRÍTICO: Estado inválido "%s" recibido', $new_status));
                delete_transient('rfq_ajax_status_update_' . $solicitud_id);
                wp_send_json_error(['message' => __('Estado inválido', 'rfq-manager-woocommerce')]);
            }

            // Usar SolicitudStatusHandler para actualizar el estado
            $update_result = \GiVendor\GiPlugin\Solicitud\SolicitudStatusHandler::update_status($solicitud_id, $new_status);
            
            if (!$update_result) {
                error_log(sprintf('[RFQ] ERROR CRÍTICO: No se pudo actualizar estado de solicitud #%d', $solicitud_id));
                delete_transient('rfq_ajax_status_update_' . $solicitud_id);
                wp_send_json_error(['message' => __('Error al actualizar el estado', 'rfq-manager-woocommerce')]);
            }
        }

        // Actualizar fecha de expiración si se proporcionó (solo admins)
        if (!empty($expiry_date) && $is_admin) {
            $expiry_timestamp = strtotime($expiry_date);
            
            if ($expiry_timestamp) {
                $expiry_formatted = date('Y-m-d H:i:s', $expiry_timestamp);
                update_post_meta($solicitud_id, '_solicitud_expiry', $expiry_formatted);
                
                // Reprogramar el cambio de estado si es necesario
                if (in_array($new_status, ['rfq-pending', 'rfq-active'])) {
                    \GiVendor\GiPlugin\Solicitud\Scheduler\StatusScheduler::schedule_change_to_historic($solicitud_id);
                }
            } else {
                error_log(sprintf('[RFQ] ERROR: Fecha de expiración inválida: %s', $expiry_date));
            }
        }

        // Si el cambio es de histórico a otro estado (solo admins)
        if ($is_admin && $current_status === 'rfq-historic' && $new_status !== 'rfq-historic') {
            // Obtener el tiempo de vencimiento por defecto
            $default_expiry_hours = \GiVendor\GiPlugin\Solicitud\Scheduler\StatusScheduler::get_default_expiry_hours();
            
            // Establecer nueva fecha de vencimiento
            $new_expiry_timestamp = current_time('timestamp') + ($default_expiry_hours * HOUR_IN_SECONDS);
            $new_expiry = date('Y-m-d H:i:s', $new_expiry_timestamp);
            update_post_meta($solicitud_id, '_solicitud_expiry', $new_expiry);
            
            // Reprogramar el cambio de estado
            \GiVendor\GiPlugin\Solicitud\Scheduler\StatusScheduler::schedule_change_to_historic($solicitud_id);
        }

        // LIMPIEZA: Eliminar transient de protección al completar exitosamente
        delete_transient('rfq_ajax_status_update_' . $solicitud_id);

        wp_send_json_success(['message' => __('Estado actualizado correctamente', 'rfq-manager-woocommerce')]);
    }

    /**
     * Handler AJAX para actualizar solo la fecha de expiración
     *
     * @since  0.1.0
     * @return void
     */
    public static function ajax_update_expiry(): void {
        // Verificar nonce
        if (!isset($_POST['rfq_nonce']) || !wp_verify_nonce(sanitize_key($_POST['rfq_nonce']), 'rfq_solicitud_status_nonce')) {
            wp_send_json_error(['message' => __('Nonce inválido', 'rfq-manager-woocommerce')]);
        }

        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('No tienes permisos para realizar esta acción', 'rfq-manager-woocommerce')]);
        }

        // Obtener datos
        $solicitud_id = absint($_POST['solicitud_id'] ?? 0);
        $expiry_date = sanitize_text_field($_POST['expiry_date'] ?? '');

        if (!$solicitud_id) {
            wp_send_json_error(['message' => __('ID de solicitud inválido', 'rfq-manager-woocommerce')]);
        }

        // Verificar que la solicitud existe
        $solicitud = get_post($solicitud_id);
        if (!$solicitud || $solicitud->post_type !== 'solicitud') {
            wp_send_json_error(['message' => __('Solicitud no encontrada', 'rfq-manager-woocommerce')]);
        }

        if (empty($expiry_date)) {
            wp_send_json_error(['message' => __('Fecha de expiración requerida', 'rfq-manager-woocommerce')]);
        }

        $expiry_timestamp = strtotime($expiry_date);
        if (!$expiry_timestamp) {
            wp_send_json_error(['message' => __('Formato de fecha inválido', 'rfq-manager-woocommerce')]);
        }

        $expiry_formatted = date('Y-m-d H:i:s', $expiry_timestamp);
        update_post_meta($solicitud_id, '_solicitud_expiry', $expiry_formatted);
        
        // Reprogramar el cambio de estado si es necesario
        $current_status = $solicitud->post_status;
        if (in_array($current_status, ['rfq-pending', 'rfq-active'])) {
            \GiVendor\GiPlugin\Solicitud\Scheduler\StatusScheduler::schedule_change_to_historic($solicitud_id);
        }

        wp_send_json_success(['message' => __('Fecha de expiración actualizada correctamente', 'rfq-manager-woocommerce')]);
    }

    /**
     * Muestra los estados personalizados en la lista del backend
     *
     * @since  0.1.0
     * @param  array $post_states Estados actuales del post
     * @param  \WP_Post $post Objeto post
     * @return array Estados modificados
     */
    public static function display_post_states(array $post_states, \WP_Post $post): array {
        if ($post->post_type !== 'solicitud') {
            return $post_states;
        }

        // Limpiar todos los estados anteriores para solicitudes
        $post_states = [];

        // Mapear estados personalizados
        $status_labels = [
            'rfq-pending' => __('Pendiente', 'rfq-manager-woocommerce'),
            'rfq-active' => __('Activa', 'rfq-manager-woocommerce'),
            'rfq-accepted' => __('Aceptada', 'rfq-manager-woocommerce'),
            'rfq-closed' => __('Cerrada', 'rfq-manager-woocommerce'),
            'rfq-historic' => __('Histórica', 'rfq-manager-woocommerce'),
        ];

        $status_classes = [
            'rfq-pending' => 'status-pending',
            'rfq-active' => 'status-active',
            'rfq-accepted' => 'status-accepted',
            'rfq-closed' => 'status-closed',
            'rfq-historic' => 'status-historic',
        ];

        if (isset($status_labels[$post->post_status])) {
            $class = $status_classes[$post->post_status] ?? '';
            $post_states[] = '<span class="' . esc_attr($class) . '">' . esc_html($status_labels[$post->post_status]) . '</span>';
        }

        return $post_states;
    }

    /**
     * Obtiene el label del estado de la cotización
     *
     * @since  0.1.0
     * @param  string $status Estado de la cotización
     * @return string         Label del estado
     */
    private static function get_cotizacion_status_label(string $status): string {
        $labels = [
            'publish'     => __('Pendiente', 'rfq-manager-woocommerce'),
            'rfq-accepted' => __('Aceptada', 'rfq-manager-woocommerce'),
            'rfq-historic' => __('Histórica', 'rfq-manager-woocommerce'),
            'rfq-closed' => __('Cerrada', 'rfq-manager-woocommerce'),
        ];

        return $labels[$status] ?? $status;
    }
} 