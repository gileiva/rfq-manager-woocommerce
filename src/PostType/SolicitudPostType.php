<?php
/**
 * SolicitudPostType - Registers the 'solicitud' custom post type
 *
 * @package    GiVendor\GiPlugin\PostType
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\PostType;

class SolicitudPostType {
    
    /**
     * Register the 'solicitud' custom post type
     *
     * @since  0.1.0
     * @return void
     */
    public static function register(): void {
        $labels = [
            'name'                  => _x('Solicitudes', 'Post type general name', 'rfq-manager-woocommerce'),
            'singular_name'         => _x('Solicitud', 'Post type singular name', 'rfq-manager-woocommerce'),
            'menu_name'             => _x('Solicitudes', 'Admin Menu text', 'rfq-manager-woocommerce'),
            'name_admin_bar'        => _x('Solicitud', 'Add New on Toolbar', 'rfq-manager-woocommerce'),
            'add_new'               => __('Añadir nueva', 'rfq-manager-woocommerce'),
            'add_new_item'          => __('Añadir nueva solicitud', 'rfq-manager-woocommerce'),
            'new_item'              => __('Nueva solicitud', 'rfq-manager-woocommerce'),
            'edit_item'             => __('Editar solicitud', 'rfq-manager-woocommerce'),
            'view_item'             => __('Ver solicitud', 'rfq-manager-woocommerce'),
            'all_items'             => __('Todas las solicitudes', 'rfq-manager-woocommerce'),
            'search_items'          => __('Buscar solicitudes', 'rfq-manager-woocommerce'),
            'parent_item_colon'     => __('Solicitud padre:', 'rfq-manager-woocommerce'),
            'not_found'             => __('No se encontraron solicitudes.', 'rfq-manager-woocommerce'),
            'not_found_in_trash'    => __('No se encontraron solicitudes en la papelera.', 'rfq-manager-woocommerce'),
            'featured_image'        => _x('Imagen destacada de la solicitud', 'Overrides the "Featured Image" phrase', 'rfq-manager-woocommerce'),
            'set_featured_image'    => _x('Establecer imagen destacada', 'Overrides the "Set featured image" phrase', 'rfq-manager-woocommerce'),
            'remove_featured_image' => _x('Eliminar imagen destacada', 'Overrides the "Remove featured image" phrase', 'rfq-manager-woocommerce'),
            'use_featured_image'    => _x('Usar como imagen destacada', 'Overrides the "Use as featured image" phrase', 'rfq-manager-woocommerce'),
            'archives'              => _x('Archivos de solicitudes', 'The post type archive label used in nav menus', 'rfq-manager-woocommerce'),
            'insert_into_item'      => _x('Insertar en la solicitud', 'Overrides the "Insert into post" phrase', 'rfq-manager-woocommerce'),
            'uploaded_to_this_item' => _x('Subido a esta solicitud', 'Overrides the "Uploaded to this post" phrase', 'rfq-manager-woocommerce'),
            'filter_items_list'     => _x('Filtrar lista de solicitudes', 'Screen reader text for the filter links heading on the post type listing screen', 'rfq-manager-woocommerce'),
            'items_list_navigation' => _x('Navegación de lista de solicitudes', 'Screen reader text for the pagination heading on the post type listing screen', 'rfq-manager-woocommerce'),
            'items_list'            => _x('Lista de solicitudes', 'Screen reader text for the items list heading on the post type listing screen', 'rfq-manager-woocommerce'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_icon'          => 'dashicons-cart',
            'query_var'          => true,
            'capability_type'    => ['solicitud', 'solicitudes'],
            'map_meta_cap'       => true,
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 23,
            'supports'           => ['title'],
            'show_in_rest'       => false,
            'rest_base'          => 'solicitudes',
            'delete_with_user'   => false,
            'rewrite'            => [
                'slug'       => 'ver-solicitud',
                'with_front' => false,
                'pages'      => true,
                'feeds'      => false,
            ],
        ];

        register_post_type('solicitud', $args);
        
        // Registrar capacidades
        self::register_caps();

        // Eliminar el botón "Añadir nueva"
        add_action('admin_menu', function() {
            remove_submenu_page('edit.php?post_type=solicitud', 'post-new.php?post_type=solicitud');
        });
    }

    /**
     * Registra las capacidades para el tipo de post 'solicitud'
     *
     * @since  0.1.0
     * @return void
     */
    public static function register_caps(): void {
        // Obtener el rol de administrador
        $admin_role = get_role('administrator');
        
        if (!$admin_role) {
            return;
        }

        // Capacidades básicas
        $caps = [
            'edit_post'              => 'edit_solicitud',
            'read_post'              => 'read_solicitud',
            'delete_post'            => 'delete_solicitud',
            'edit_posts'             => 'edit_solicitudes',
            'edit_others_posts'      => 'edit_others_solicitudes',
            'publish_posts'          => 'publish_solicitudes',
            'read_private_posts'     => 'read_private_solicitudes',
            'delete_posts'           => 'delete_solicitudes',
            'delete_private_posts'   => 'delete_private_solicitudes',
            'delete_published_posts' => 'delete_published_solicitudes',
            'delete_others_posts'    => 'delete_others_solicitudes',
            'edit_private_posts'     => 'edit_private_solicitudes',
            'edit_published_posts'   => 'edit_published_solicitudes',
            'read'                   => 'read_solicitudes',
        ];

        // Asignar capacidades al rol de administrador
        foreach ($caps as $cap) {
            if (!empty($cap)) {
                $admin_role->add_cap($cap);
            }
        }
    }

    /**
     * Elimina las capacidades registradas
     *
     * @since  0.1.0
     * @return void
     */
    public static function remove_caps(): void {
        $admin_role = get_role('administrator');
        
        if (!$admin_role) {
            return;
        }

        $caps = [
            'edit_solicitud',
            'read_solicitud',
            'delete_solicitud',
            'edit_solicitudes',
            'edit_others_solicitudes',
            'publish_solicitudes',
            'read_private_solicitudes',
            'delete_solicitudes',
            'delete_private_solicitudes',
            'delete_published_solicitudes',
            'delete_others_solicitudes',
            'edit_private_solicitudes',
            'edit_published_solicitudes',
        ];

        foreach ($caps as $cap) {
            $admin_role->remove_cap($cap);
        }
    }

    public static function init(): void {
        add_action('init', [self::class, 'register']);
        self::register_activation_hooks();
    }

    /**
     * Registra los hooks de activación y desactivación
     *
     * @since  0.1.0
     * @return void
     */
    public static function register_activation_hooks(): void {
        register_activation_hook(RFQ_MANAGER_WOO_PLUGIN_BASENAME, [self::class, 'register_caps']);
        register_deactivation_hook(RFQ_MANAGER_WOO_PLUGIN_BASENAME, [self::class, 'remove_caps']);
    }
}