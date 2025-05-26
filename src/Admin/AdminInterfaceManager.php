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
        
        // Agregar estilos para los estados
        add_action('admin_head', [__CLASS__, 'add_admin_styles']);
        
        // Hooks para manejar la eliminación
        add_action('before_delete_post', [__CLASS__, 'handle_solicitud_deletion'], 10, 1);
        add_action('trash_post', [__CLASS__, 'handle_solicitud_trash'], 10, 1);
        
        add_action('admin_menu', [self::class, 'add_menu_pages']);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_scripts']);
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
                $new_columns['status'] = __('Estado', 'rfq-manager-woocommerce');
                $new_columns['cotizaciones'] = __('Cotizaciones', 'rfq-manager-woocommerce');
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

            case 'status':
                $status = get_post_status($post_id);
                $status_labels = [
                    'rfq-pending' => __('Pendiente', 'rfq-manager-woocommerce'),
                    'rfq-active' => __('Activa', 'rfq-manager-woocommerce'),
                    'rfq-accepted' => __('Aceptada', 'rfq-manager-woocommerce'),
                    'rfq-historic' => __('Histórica', 'rfq-manager-woocommerce'),
                    'trash' => __('Papelera', 'rfq-manager-woocommerce')
                ];
                
                $status_class = 'rfq-status-' . sanitize_html_class($status);
                echo '<span class="rfq-status ' . esc_attr($status_class) . '">' . 
                     esc_html($status_labels[$status] ?? $status) . 
                     '</span>';
                break;

            case 'cotizaciones':
                $cotizaciones = get_posts([
                    'post_type' => 'cotizacion',
                    'posts_per_page' => -1,
                    'meta_query' => [
                        [
                            'key' => '_solicitud_parent',
                            'value' => $post_id
                        ]
                    ]
                ]);
                
                echo count($cotizaciones);
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
            24
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

        // Nuevas opciones para Stripe
        register_setting('rfq_manager_settings', 'stripe_secret_key');
        register_setting('rfq_manager_settings', 'stripe_publishable_key');
    }

    /**
     * Renderiza la página de configuración
     *
     * @since  0.1.0
     * @return void
     */
    public static function render_settings_page(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form method="post" action="options.php">
            <?php
            // Genera los campos nonce y persiste las opciones
            settings_fields('rfq_manager_settings');
            do_settings_sections('rfq_manager_settings');
            ?>

            <div class="rfq-settings-section">
                <h2><?php _e('Configuración General', 'rfq-manager-woocommerce'); ?></h2>
                <div class="rfq-settings-content">
                    <!-- Aquí tu campo existente para rfq_cotizar_page_id -->
                </div>
            </div>

            <div class="rfq-settings-section">
                <h2><?php _e('Configuración de Notificaciones', 'rfq-manager-woocommerce'); ?></h2>
                <div class="rfq-settings-content">
                    <!-- Contenido de notificaciones -->
                </div>
            </div>

            <div class="rfq-settings-section">
                <h2><?php _e('Configuración Avanzada', 'rfq-manager-woocommerce'); ?></h2>
                <div class="rfq-settings-content">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="stripe_secret_key"><?php _e('Clave Secreta de Stripe', 'rfq-manager-woocommerce'); ?></label>
                            </th>
                            <td>
                                <input
                                name="stripe_secret_key"
                                type="text"
                                id="stripe_secret_key"
                                value="<?php echo esc_attr(get_option('stripe_secret_key')); ?>"
                                class="regular-text"
                                />
                                <p class="description">
                                <?php _e('Tu clave secreta (sk_test_…) se usa en el servidor para crear PaymentIntents.', 'rfq-manager-woocommerce'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="stripe_publishable_key"><?php _e('Clave Publicable de Stripe', 'rfq-manager-woocommerce'); ?></label>
                            </th>
                            <td>
                                <input
                                name="stripe_publishable_key"
                                type="text"
                                id="stripe_publishable_key"
                                value="<?php echo esc_attr(get_option('stripe_publishable_key')); ?>"
                                class="regular-text"
                                />
                                <p class="description">
                                <?php _e('Tu clave publicable (pk_test_…) se usa en Stripe.js para el frontend.', 'rfq-manager-woocommerce'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <?php submit_button(); ?>
            </form>
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

    /**
     * Agrega estilos CSS para los estados
     *
     * @since  0.1.0
     * @return void
     */
    public static function add_admin_styles(): void {
        ?>
        <style>
            .rfq-status {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                line-height: 1.4;
            }
            .rfq-status-rfq-pending {
                background-color: #f0b849;
                color: #fff;
            }
            .rfq-status-rfq-active {
                background-color: #46b450;
                color: #fff;
            }
            .rfq-status-rfq-closed {
                background-color: #dc3232;
                color: #fff;
            }
            .rfq-status-rfq-historic {
                background-color: #999;
                color: #fff;
            }
            .rfq-status-trash {
                background-color: #dc3232;
                color: #fff;
            }
        </style>
        <?php
    }

    /**
     * Maneja la eliminación de una solicitud
     *
     * @since  0.1.0
     * @param  int $post_id ID del post
     * @return void
     */
    public static function handle_solicitud_deletion($post_id): void {
        if (get_post_type($post_id) !== 'solicitud') {
            return;
        }

        // Eliminar metadatos relacionados
        delete_post_meta($post_id, '_solicitud_order_id');
        delete_post_meta($post_id, '_solicitud_customer');
        delete_post_meta($post_id, '_solicitud_shipping');
        delete_post_meta($post_id, '_solicitud_total');
        delete_post_meta($post_id, '_solicitud_date');
        delete_post_meta($post_id, '_solicitud_expiry');
        delete_post_meta($post_id, '_solicitud_uuid');
    }

    /**
     * Maneja el movimiento a la papelera de una solicitud
     *
     * @since  0.1.0
     * @param  int $post_id ID del post
     * @return void
     */
    public static function handle_solicitud_trash($post_id): void {
        if (get_post_type($post_id) !== 'solicitud') {
            return;
        }

        // Aquí puedes agregar lógica adicional cuando una solicitud se mueve a la papelera
        error_log('RFQ Manager - Solicitud #' . $post_id . ' movida a la papelera');
    }

    /**
     * Carga los scripts y estilos necesarios
     *
     * @since  0.1.0
     * @return void
     */
    public static function enqueue_scripts(): void {
        $screen = get_current_screen();
        
        // Solo cargar en páginas de solicitudes o cotizaciones
        if (!in_array($screen->post_type, ['solicitud', 'cotizacion'], true)) {
            return;
        }

        // Flatpickr para el selector de fecha y hora
        wp_enqueue_style(
            'flatpickr',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
            [],
            '4.6.13'
        );

        wp_enqueue_script(
            'flatpickr',
            'https://cdn.jsdelivr.net/npm/flatpickr',
            [],
            '4.6.13',
            true
        );

        // Localización de Flatpickr
        wp_enqueue_script(
            'flatpickr-es',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js',
            ['flatpickr'],
            '4.6.13',
            true
        );

        // Estilos personalizados
        wp_enqueue_style(
            'rfq-admin',
            RFQ_MANAGER_WOO_PLUGIN_URL . 'assets/css/admin.css',
            [],
            RFQ_MANAGER_WOO_VERSION
        );

        wp_localize_script('rfq-pago', 'RFQData', [
            'endpoint'    => rest_url('rfq/v1/create-payment-intent'),
            'publishable' => get_option('stripe_publishable_key', ''),
        ]);
    }
}