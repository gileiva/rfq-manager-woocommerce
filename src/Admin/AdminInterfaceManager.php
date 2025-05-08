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

        add_action('admin_menu', [self::class, 'add_menu_pages']);
        add_action('admin_init', [self::class, 'register_settings']);
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

    /**
     * Registra las páginas del menú de administración
     *
     * @since  0.1.0
     * @return void
     */
    public static function add_menu_pages(): void {
        add_menu_page(
            __('RFQ Manager', 'rfq-manager-woocommerce'),
            __('RFQ Manager', 'rfq-manager-woocommerce'),
            'manage_options',
            'rfq-manager',
            [self::class, 'render_main_page'],
            'dashicons-cart',
            56
        );

        add_submenu_page(
            'rfq-manager',
            __('Configuración', 'rfq-manager-woocommerce'),
            __('Configuración', 'rfq-manager-woocommerce'),
            'manage_options',
            'rfq-manager-settings',
            [self::class, 'render_settings_page']
        );
    }

    /**
     * Registra las configuraciones del plugin
     *
     * @since  0.1.0
     * @return void
     */
    public static function register_settings(): void {
        register_setting('rfq_manager_settings', 'rfq_cotizar_page_id');
    }

    /**
     * Renderiza la página de configuración
     *
     * @since  0.1.0
     * @return void
     */
    public static function render_settings_page(): void {
        // Verificar si la página de cotización existe
        $cotizar_page_id = get_option('rfq_cotizar_page_id');
        $cotizar_page = $cotizar_page_id ? get_post($cotizar_page_id) : null;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="rfq-settings-section">
                <h2><?php _e('Páginas Requeridas', 'rfq-manager-woocommerce'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Página de Cotización', 'rfq-manager-woocommerce'); ?></th>
                        <td>
                            <?php if ($cotizar_page && $cotizar_page->post_status === 'publish'): ?>
                                <p class="rfq-status-ok">
                                    <?php _e('Página creada correctamente.', 'rfq-manager-woocommerce'); ?>
                                    <br>
                                    <a href="<?php echo get_edit_post_link($cotizar_page_id); ?>">
                                        <?php _e('Editar página', 'rfq-manager-woocommerce'); ?>
                                    </a> |
                                    <a href="<?php echo get_permalink($cotizar_page_id); ?>" target="_blank">
                                        <?php _e('Ver página', 'rfq-manager-woocommerce'); ?>
                                    </a>
                                </p>
                            <?php else: ?>
                                <p class="rfq-status-error">
                                    <?php _e('La página de cotización no está configurada correctamente.', 'rfq-manager-woocommerce'); ?>
                                </p>
                                <form method="post" action="">
                                    <?php wp_nonce_field('rfq_create_pages', 'rfq_create_pages_nonce'); ?>
                                    <input type="hidden" name="action" value="rfq_create_pages">
                                    <input type="submit" class="button button-primary" value="<?php _e('Crear Página de Cotización', 'rfq-manager-woocommerce'); ?>">
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Renderiza la página principal del plugin
     *
     * @since  0.1.0
     * @return void
     */
    public static function render_main_page(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="rfq-dashboard">
                <div class="rfq-dashboard-section">
                    <h2><?php _e('Resumen de Solicitudes', 'rfq-manager-woocommerce'); ?></h2>
                    <p><?php _e('Bienvenido al panel de control de RFQ Manager. Aquí podrás gestionar todas las solicitudes de cotización.', 'rfq-manager-woocommerce'); ?></p>
                    
                    <div class="rfq-quick-links">
                        <a href="<?php echo admin_url('edit.php?post_type=solicitud'); ?>" class="button button-primary">
                            <?php _e('Ver todas las solicitudes', 'rfq-manager-woocommerce'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=rfq-manager-settings'); ?>" class="button">
                            <?php _e('Configuración', 'rfq-manager-woocommerce'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}