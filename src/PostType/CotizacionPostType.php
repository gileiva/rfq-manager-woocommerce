<?php
/**
 * CotizacionPostType - Registra el CPT 'cotizacion'
 *
 * @package    GiVendor\GiPlugin\PostType
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\PostType;

/**
 * CotizacionPostType - Clase responsable de registrar el CPT 'cotizacion'
 *
 * @package    GiVendor\GiPlugin\PostType
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class CotizacionPostType {
    
    /**
     * Registra el CPT 'cotizacion'
     *
     * @since  0.1.0
     * @return void
     */
    public static function register(): void {
        $labels = [
            'name'                  => _x('Cotizaciones', 'Post type general name', 'rfq-manager-woocommerce'),
            'singular_name'         => _x('Cotización', 'Post type singular name', 'rfq-manager-woocommerce'),
            'menu_name'             => _x('Cotizaciones', 'Admin Menu text', 'rfq-manager-woocommerce'),
            'name_admin_bar'        => _x('Cotización', 'Add New on Toolbar', 'rfq-manager-woocommerce'),
            'add_new'               => __('Añadir nueva', 'rfq-manager-woocommerce'),
            'add_new_item'          => __('Añadir nueva cotización', 'rfq-manager-woocommerce'),
            'new_item'              => __('Nueva cotización', 'rfq-manager-woocommerce'),
            'edit_item'             => __('Editar cotización', 'rfq-manager-woocommerce'),
            'view_item'             => __('Ver cotización', 'rfq-manager-woocommerce'),
            'all_items'             => __('Todas las cotizaciones', 'rfq-manager-woocommerce'),
            'search_items'          => __('Buscar cotizaciones', 'rfq-manager-woocommerce'),
            'parent_item_colon'     => __('Cotización padre:', 'rfq-manager-woocommerce'),
            'not_found'             => __('No se encontraron cotizaciones.', 'rfq-manager-woocommerce'),
            'not_found_in_trash'    => __('No hay cotizaciones en la papelera.', 'rfq-manager-woocommerce'),
            'filter_items_list'     => _x('Filtrar lista de cotizaciones', 'Screen reader text for the filter links heading on the post type listing screen', 'rfq-manager-woocommerce'),
            'items_list_navigation' => _x('Navegación de la lista de cotizaciones', 'Screen reader text for the pagination heading on the post type listing screen', 'rfq-manager-woocommerce'),
            'items_list'            => _x('Lista de cotizaciones', 'Screen reader text for the items list heading on the post type listing screen', 'rfq-manager-woocommerce'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => ['title', 'author'],
            'show_in_rest'       => false,
            'rest_base'          => 'cotizaciones',
            'map_meta_cap'       => true,
            'menu_icon'          => 'dashicons-money-alt',
            'menu_position'      => 56,
            'rewrite'            => ['slug' => 'cotizaciones'],
        ];

        register_post_type('cotizacion', $args);
        
        // Registrar capacidades
        self::register_caps();
    }
    
    /**
     * Registra los campos de metadatos para el CPT 'cotizacion'
     *
     * @since  0.1.0
     * @return void
     */
    public static function register_meta_fields(): void {
        

        // Registrar campo para la solicitud padre (ID de la solicitud relacionada)
        register_post_meta('cotizacion', '_solicitud_parent', [
            'type'              => 'integer',
            'description'       => 'ID de la solicitud de cotización padre',
            'single'            => true,
            'sanitize_callback' => 'absint',
            'auth_callback'     => function() {
                return current_user_can('edit_posts');
            },
            'show_in_rest'      => false,
        ]);
        
        // Registrar campo para los precios de los ítems
        register_post_meta('cotizacion', '_precio_items', [
            'type'              => 'array',
            'description'       => 'Precios cotizados para cada ítem',
            'single'            => true,
            'sanitize_callback' => [self::class, 'sanitize_precio_items'],
            'auth_callback'     => function() {
                return current_user_can('edit_posts');
            },
            'show_in_rest'      => false,
        ]);
        
        // Registrar campo para el total de la cotización
        register_post_meta('cotizacion', '_total', [
            'type'              => 'number',
            'description'       => 'Total de la cotización',
            'single'            => true,
            'sanitize_callback' => function($value) {
                return floatval($value);
            },
            'auth_callback'     => function() {
                return current_user_can('edit_posts');
            },
            'show_in_rest'      => false,
        ]);

        // Registrar campo para observaciones
        register_post_meta('cotizacion', '_observaciones', [
            'type'              => 'string',
            'description'       => 'Observaciones de la cotización',
            'single'            => true,
            'sanitize_callback' => 'sanitize_textarea_field',
            'auth_callback'     => function() {
                return current_user_can('edit_posts');
            },
            'show_in_rest'      => false,
        ]);
        
    }
    
    /**
     * Sanitiza el array de precios de ítems asegurando que todos son floats válidos
     *
     * @since  0.1.0
     * @param  array $precio_items Array de precios de ítems
     * @return array Array sanitizado
     */
    public static function sanitize_precio_items($precio_items): array {
        if (!is_array($precio_items)) {
            return [];
        }
        
        $sanitized = [];
        foreach ($precio_items as $product_id => $item) {
            // Sanitizar el product_id como entero
            $sanitized_id = absint($product_id);
            
            if ($sanitized_id <= 0) {
                continue;
            }

            // Sanitizar los valores del item
            $sanitized[$sanitized_id] = [
                'precio' => floatval($item['precio']),
                'iva' => floatval($item['iva']),
                'qty' => absint($item['qty']),
                'subtotal_sin_iva' => floatval($item['subtotal_sin_iva']),
                'iva_amount' => floatval($item['iva_amount']),
                'subtotal' => floatval($item['subtotal'])
            ];
        }
        
        return $sanitized;
    }
    
    /**
     * Registra las capacidades para el tipo de publicación 'cotizacion'
     * 
     * @since 0.1.0
     * @return void
     */
    public static function register_caps(): void {
        // Obtener el rol de administrador para darle acceso completo
        $admin_role = get_role('administrator');
        
        // Si el rol no existe por alguna razón, salimos
        if (!$admin_role) {
            return;
        }
        
        // Asignar todas las capacidades al rol de administrador
        foreach (self::get_caps() as $cap) {
            $admin_role->add_cap($cap);
        }

        // Asignar capacidades al rol 'proveedor' si existe
        $proveedor_role = get_role('proveedor');
        if ($proveedor_role) {
            // Capacidades para cotizaciones
            foreach (self::get_caps() as $cap) {
                $proveedor_role->add_cap($cap);
            }
            
            // Capacidades para ver solicitudes
            $proveedor_role->add_cap('read_solicitud');
            $proveedor_role->add_cap('read_private_solicitudes');
            $proveedor_role->add_cap('edit_solicitud');
            $proveedor_role->add_cap('edit_others_solicitudes');
        }
    }
    
    /**
     * Elimina las capacidades registradas para el tipo de publicación 'cotizacion'
     * 
     * @since 0.1.0
     * @return void
     */
    public static function remove_caps(): void {
        // Obtener todos los roles con capacidades
        $roles = ['administrator', 'editor', 'proveedor'];
        
        // Iterar cada rol y eliminar las capacidades
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach (self::get_caps() as $cap) {
                    $role->remove_cap($cap);
                }

                // Si es el rol proveedor, también eliminamos las capacidades de solicitud
                if ($role_name === 'proveedor') {
                    $role->remove_cap('read_solicitud');
                    $role->remove_cap('read_private_solicitudes');
                    $role->remove_cap('edit_solicitud');
                    $role->remove_cap('edit_others_solicitudes');
                }
            }
        }
    }
    
    /**
     * Registra los hooks de activación y desactivación
     * 
     * @since 0.1.0
     * @return void
     */
    public static function register_activation_hooks(): void {
        register_activation_hook(RFQ_MANAGER_WOO_PLUGIN_BASENAME, [self::class, 'register_caps']);
        register_deactivation_hook(RFQ_MANAGER_WOO_PLUGIN_BASENAME, [self::class, 'remove_caps']);
    }
    
    /**
     * Obtiene el listado de capacidades para el tipo de publicación 'cotizacion'
     * 
     * @since 0.1.0
     * @return array Listado de capacidades
     */
    public static function get_caps(): array {
        return [
            // Capacidades de lectura
            'read_cotizacion',
            'read_private_cotizaciones',
            
            // Capacidades de edición
            'edit_cotizacion',
            'edit_cotizaciones',
            'edit_others_cotizaciones',
            'edit_private_cotizaciones',
            'edit_published_cotizaciones',
            
            // Capacidades de publicación
            'publish_cotizaciones',
            
            // Capacidades de eliminación
            'delete_cotizacion',
            'delete_others_cotizaciones',
        ];
    }
}