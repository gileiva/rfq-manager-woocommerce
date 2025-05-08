<?php
/**
 * Vista de cotizaciones
 *
 * @package    GiVendor\GiPlugin\Cotizacion\View
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Cotizacion\View;

/**
 * CotizacionView - Maneja la visualización de cotizaciones
 *
 * Esta clase es responsable de mostrar las cotizaciones en el panel de administración.
 *
 * @package    GiVendor\GiPlugin\Cotizacion\View
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class CotizacionView {
    
    /**
     * Inicializa la vista
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        // Registrar los metaboxes directamente en el hook add_meta_boxes
        add_action('add_meta_boxes_cotizacion', [__CLASS__, 'register_meta_boxes']);
        
        // Registrar los scripts y estilos
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);

        // Agregar acción para guardar el historial después de que se guarden los datos de la cotización
        add_action('updated_post_meta', [__CLASS__, 'check_and_save_historial'], 10, 4);
        
        // Agregar acción para registrar la primera cotización en el historial
        add_action('save_post_cotizacion', [__CLASS__, 'register_first_historial'], 10, 3);
    }

    /**
     * Configura la vista de cotización
     *
     * @since  0.1.0
     * @return void
     */
    public static function setup_cotizacion_view(): void {
        global $post_type;
                
        if ($post_type === 'cotizacion') {
            add_action('add_meta_boxes', [__CLASS__, 'register_meta_boxes']);
            add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
        }
    }

    /**
     * Registra los metaboxes para la cotización
     *
     * @since  0.1.0
     * @return void
     */
    public static function register_meta_boxes(): void {
                
        add_meta_box(
            'rfq_cotizacion_detalles',
            __('Detalles de la Cotización', 'rfq-manager-woocommerce'),
            [__CLASS__, 'render_detalles_metabox'],
            'cotizacion',
            'normal',
            'high'
        );

        add_meta_box(
            'rfq_cotizacion_solicitud',
            __('Solicitud Relacionada', 'rfq-manager-woocommerce'),
            [__CLASS__, 'render_solicitud_metabox'],
            'cotizacion',
            'side',
            'high'
        );

        add_meta_box(
            'rfq_cotizacion_historial',
            __('Historial de Cambios', 'rfq-manager-woocommerce'),
            [__CLASS__, 'render_historial_metabox'],
            'cotizacion',
            'normal',
            'default'
        );
    }

    /**
     * Carga los scripts y estilos necesarios
     *
     * @since  0.1.0
     * @param  string $hook Hook actual
     * @return void
     */
    public static function enqueue_admin_scripts($hook): void {
        global $post_type;
        
        // Solo cargar en la pantalla de edición de cotizaciones
        if (!($hook == 'post.php' || $hook == 'post-new.php') || $post_type !== 'cotizacion') {
            return;
        }

        
        wp_enqueue_style(
            'rfq-cotizacion-styles',
            plugins_url('assets/css/admin-cotizacion.css', dirname(dirname(dirname(__FILE__)))),
            [],
            '0.1.0'
        );
    }

    /**
     * Renderiza el metabox de detalles
     *
     * @since  0.1.0
     * @param  \WP_Post $post Objeto post actual
     * @return void
     */
    public static function render_detalles_metabox(\WP_Post $post): void {
        $precio_items = get_post_meta($post->ID, '_precio_items', true);
        $total = get_post_meta($post->ID, '_total', true);
        $observaciones = get_post_meta($post->ID, '_observaciones', true);

        if (empty($precio_items)) {
            echo '<p>' . __('No hay productos en esta cotización.', 'rfq-manager-woocommerce') . '</p>';
            return;
        }

        echo '<div class="rfq-cotizacion-detalles">';
        echo '<table class="widefat">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Producto', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('Cantidad', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('Precio Unit.', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('IVA', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('Subtotal sin IVA', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('IVA Monto', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('Subtotal', 'rfq-manager-woocommerce') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($precio_items as $product_id => $item) {
            $product = wc_get_product($product_id);
            
            // Asegurarnos de que todos los valores numéricos sean float
            $precio = isset($item['precio']) ? floatval($item['precio']) : 0;
            $qty = isset($item['qty']) ? intval($item['qty']) : 0;
            $iva = isset($item['iva']) ? floatval($item['iva']) : 0;
            $subtotal_sin_iva = isset($item['subtotal_sin_iva']) ? floatval($item['subtotal_sin_iva']) : 0;
            $iva_amount = isset($item['iva_amount']) ? floatval($item['iva_amount']) : 0;
            $subtotal = isset($item['subtotal']) ? floatval($item['subtotal']) : 0;
            
            echo '<tr>';
            echo '<td>' . esc_html($product ? $product->get_name() : 'Producto #' . $product_id) . '</td>';
            echo '<td>' . esc_html($qty) . '</td>';
            echo '<td>' . wc_price($precio) . '</td>';
            echo '<td>' . esc_html($iva) . '%</td>';
            echo '<td>' . wc_price($subtotal_sin_iva) . '</td>';
            echo '<td>' . wc_price($iva_amount) . '</td>';
            echo '<td>' . wc_price($subtotal) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '<tfoot>';
        echo '<tr class="rfq-total-row">';
        echo '<td colspan="6" class="rfq-total-label">' . __('Total', 'rfq-manager-woocommerce') . '</td>';
        echo '<td class="rfq-total-amount">' . wc_price(floatval($total)) . '</td>';
        echo '</tr>';
        echo '</tfoot>';
        echo '</table>';

        if (!empty($observaciones)) {
            echo '<div class="rfq-observaciones">';
            echo '<h4>' . __('Observaciones:', 'rfq-manager-woocommerce') . '</h4>';
            echo '<p>' . esc_html($observaciones) . '</p>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Renderiza el metabox de solicitud relacionada
     *
     * @since  0.1.0
     * @param  \WP_Post $post Objeto post actual
     * @return void
     */
    public static function render_solicitud_metabox(\WP_Post $post): void {
        $solicitud_id = get_post_meta($post->ID, '_solicitud_parent', true);
        
        if (!$solicitud_id) {
            echo '<p>' . __('No hay solicitud relacionada.', 'rfq-manager-woocommerce') . '</p>';
            return;
        }

        $solicitud = get_post($solicitud_id);
        if (!$solicitud) {
            echo '<p>' . __('La solicitud relacionada no existe.', 'rfq-manager-woocommerce') . '</p>';
            return;
        }

        echo '<div class="rfq-solicitud-info">';
        echo '<p><strong>' . __('Solicitud #', 'rfq-manager-woocommerce') . '</strong> ';
        echo '<a href="' . esc_url(get_edit_post_link($solicitud_id)) . '">' . esc_html($solicitud->post_title) . '</a></p>';
        
        echo '<p><strong>' . __('Estado:', 'rfq-manager-woocommerce') . '</strong> ';
        echo esc_html(self::get_status_label($solicitud->post_status)) . '</p>';
        
        echo '<p><strong>' . __('Fecha:', 'rfq-manager-woocommerce') . '</strong> ';
        echo esc_html(get_the_date('', $solicitud_id)) . '</p>';
        echo '</div>';
    }

    /**
     * Renderiza el metabox de historial de cambios
     *
     * @since  0.1.0
     * @param  \WP_Post $post Objeto post actual
     * @return void
     */
    public static function render_historial_metabox(\WP_Post $post): void {
        $historial = get_post_meta($post->ID, '_cotizacion_historial', true);
        
        if (empty($historial) || !is_array($historial)) {
            // Si no hay historial, intentar crear uno con los datos actuales
            $precio_items = get_post_meta($post->ID, '_precio_items', true);
            if (!empty($precio_items)) {
                $totales = self::calcular_totales($precio_items);
                $historial = [
                    [
                        'fecha' => get_the_date('Y-m-d H:i:s', $post->ID),
                        'totales' => $totales,
                        'tipo' => 'inicial'
                    ]
                ];
                update_post_meta($post->ID, '_cotizacion_historial', $historial);
            } else {
                echo '<p>' . __('No hay cambios registrados en esta cotización.', 'rfq-manager-woocommerce') . '</p>';
                return;
            }
        }

        // Ordenar el historial de más reciente a más antiguo
        usort($historial, function($a, $b) {
            return strtotime($b['fecha']) - strtotime($a['fecha']);
        });

        echo '<div class="rfq-historial-container">';
        echo '<table class="widefat">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Fecha', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('Subtotal sin IVA', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('IVA', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('Total con IVA', 'rfq-manager-woocommerce') . '</th>';
        echo '<th>' . __('Tipo', 'rfq-manager-woocommerce') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($historial as $cambio) {
            $fecha = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($cambio['fecha']));
            $totales = $cambio['totales'];
            $tipo = isset($cambio['tipo']) && $cambio['tipo'] === 'inicial' 
                ? __('Cotización Inicial', 'rfq-manager-woocommerce')
                : __('Actualización', 'rfq-manager-woocommerce');
            
            echo '<tr>';
            echo '<td>' . esc_html($fecha) . '</td>';
            echo '<td>' . wc_price($totales['subtotal_sin_iva']) . '</td>';
            echo '<td>' . wc_price($totales['total_iva']) . '</td>';
            echo '<td>' . wc_price($totales['total_con_iva']) . '</td>';
            echo '<td>' . esc_html($tipo) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
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
            case 'rfq-active':
                return __('Activa', 'rfq-manager-woocommerce');
            case 'rfq-accepted':
                return __('Propuesta aceptada', 'rfq-manager-woocommerce');
            case 'rfq-historic':
                return __('Histórico', 'rfq-manager-woocommerce');
            default:
                return $status;
        }
    }

    /**
     * Verifica y guarda el historial cuando se actualizan los datos de la cotización
     *
     * @since  0.1.0
     * @param  int    $meta_id    ID del meta
     * @param  int    $post_id    ID del post
     * @param  string $meta_key   Clave del meta
     * @param  mixed  $meta_value Valor del meta
     * @return void
     */
    public static function check_and_save_historial(int $meta_id, int $post_id, string $meta_key, $meta_value): void {
        // Solo procesar cuando se actualiza _precio_items
        if ($meta_key !== '_precio_items') {
            return;
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'cotizacion') {
            return;
        }

        // Verificar permisos
        $user = wp_get_current_user();
        $user_roles = (array) $user->roles;

        // Permitir a administradores y proveedores
        if (!in_array('administrator', $user_roles) && !in_array('proveedor', $user_roles)) {
            return;
        }

        // Obtener el historial actual
        $historial = get_post_meta($post_id, '_cotizacion_historial', true);
        if (!is_array($historial)) {
            $historial = [];
        }

        // Calcular totales
        $totales = self::calcular_totales($meta_value);

        // Crear nuevo registro con los datos específicos
        $nuevo_registro = [
            'fecha' => current_time('mysql'),
            'totales' => $totales
        ];

        // Agregar nuevo registro al historial
        $historial[] = $nuevo_registro;

        // Guardar el historial actualizado
        update_post_meta($post_id, '_cotizacion_historial', $historial);
    }

    /**
     * Calcula los totales de la cotización
     *
     * @since  0.1.0
     * @param  array $precio_items Array de items con precios
     * @return array Array con los totales calculados
     */
    private static function calcular_totales($precio_items): array {
        $subtotal_sin_iva = 0;
        $total_iva = 0;
        
        if (is_array($precio_items) && !empty($precio_items)) {
            foreach ($precio_items as $item) {
                if (is_array($item)) {
                    $subtotal_sin_iva += isset($item['subtotal_sin_iva']) ? floatval($item['subtotal_sin_iva']) : 0;
                    $total_iva += isset($item['iva_amount']) ? floatval($item['iva_amount']) : 0;
                }
            }
        }

        return [
            'subtotal_sin_iva' => $subtotal_sin_iva,
            'total_iva' => $total_iva,
            'total_con_iva' => $subtotal_sin_iva + $total_iva
        ];
    }

    /**
     * Registra la primera cotización en el historial
     *
     * @since  0.1.0
     * @param  int     $post_id ID del post
     * @param  \WP_Post $post   Objeto post
     * @param  bool    $update  Si es una actualización
     * @return void
     */
    public static function register_first_historial(int $post_id, \WP_Post $post, bool $update): void {
        // Solo procesar si es una nueva cotización
        if ($update) {
            return;
        }

        // Verificar permisos
        $user = wp_get_current_user();
        $user_roles = (array) $user->roles;

        // Permitir a administradores y proveedores
        if (!in_array('administrator', $user_roles) && !in_array('proveedor', $user_roles)) {
            return;
        }

        // Obtener los items de la cotización
        $precio_items = get_post_meta($post_id, '_precio_items', true);
        if (empty($precio_items)) {
            return;
        }

        // Calcular totales
        $totales = self::calcular_totales($precio_items);

        // Crear el primer registro del historial
        $historial = [
            [
                'fecha' => current_time('mysql'),
                'totales' => $totales,
                'tipo' => 'inicial'
            ]
        ];

        // Guardar el historial
        update_post_meta($post_id, '_cotizacion_historial', $historial);
    }
} 