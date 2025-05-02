<?php
/**
 * Gestor de interfaz de administración
 *
 * @package    GiVendor\GiPlugin\Admin
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Admin;

/**
 * AdminInterfaceManager - Gestiona la interfaz de administración
 *
 * Esta clase es responsable de gestionar aspectos específicos de la
 * interfaz de administración para el plugin RFQ.
 *
 * @package    GiVendor\GiPlugin\Admin
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class AdminInterfaceManager {
    
    /**
     * Inicializa los hooks relacionados con la interfaz de administración
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        // Añadir columnas personalizadas a la lista de solicitudes
        add_filter('manage_solicitud_posts_columns', [__CLASS__, 'add_solicitud_columns']);
        add_action('manage_solicitud_posts_custom_column', [__CLASS__, 'display_solicitud_column_content'], 10, 2);
        
        // Hook de depuración
        add_action('init', function() {
            // error_log('RFQ Manager - AdminInterfaceManager hooks inicializados');
        }, 999);
    }
    
    /**
     * Añade columnas personalizadas al listado de solicitudes
     * 
     * @since 0.1.0
     * @param array $columns Columnas actuales
     * @return array Columnas modificadas
     */
    public static function add_solicitud_columns($columns): array {
        $new_columns = array();
        
        // Insertamos columnas personalizadas después de la columna de título
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            if ($key === 'title') {
                $new_columns['order_id'] = __('Orden Original', 'rfq-manager-woocommerce');
                $new_columns['customer'] = __('Cliente', 'rfq-manager-woocommerce');
                $new_columns['total'] = __('Total', 'rfq-manager-woocommerce');
                $new_columns['expiry'] = __('Expira', 'rfq-manager-woocommerce');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Muestra el contenido de las columnas personalizadas para solicitudes
     * 
     * @since 0.1.0
     * @param string $column Nombre de la columna
     * @param int $post_id ID del post
     * @return void
     */
    public static function display_solicitud_column_content($column, $post_id): void {
        switch ($column) {
            case 'order_id':
                $order_id = get_post_meta($post_id, '_solicitud_order_id', true);
                if ($order_id) {
                    echo esc_html('#' . $order_id);
                } else {
                    echo '—';
                }
                break;
                
            case 'customer':
                $customer_data = json_decode(get_post_meta($post_id, '_solicitud_customer', true), true);
                if ($customer_data && isset($customer_data['email'])) {
                    $name = $customer_data['first_name'] . ' ' . $customer_data['last_name'];
                    echo esc_html($name) . '<br>';
                    echo '<a href="mailto:' . esc_attr($customer_data['email']) . '">' . esc_html($customer_data['email']) . '</a>';
                } else {
                    echo '—';
                }
                break;
                
            case 'total':
                $total = get_post_meta($post_id, '_solicitud_total', true);
                if ($total) {
                    // Corregido: usar number_format en lugar de wc_price para evitar HTML sin escapar
                    echo esc_html(number_format($total, 2, ',', '.')) . ' €';
                } else {
                    echo '—';
                }
                break;
                
            case 'expiry':
                $expiry = get_post_meta($post_id, '_solicitud_expiry', true);
                if ($expiry) {
                    $expiry_date = new \DateTime($expiry);
                    $now = new \DateTime();
                    
                    if ($expiry_date < $now) {
                        echo '<span style="color:red;">' . esc_html(date_i18n(get_option('date_format'), strtotime($expiry))) . '</span>';
                    } else {
                        echo esc_html(date_i18n(get_option('date_format'), strtotime($expiry)));
                    }
                } else {
                    echo '—';
                }
                break;
        }
    }
}