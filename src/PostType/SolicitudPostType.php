<?php
/**
 * SolicitudPostType - Registers the 'solicitud' custom post type
 *
 * @package    GiVendor\GiPlugin\PostType
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\PostType;

/**
 * SolicitudPostType - Class responsible for registering the 'solicitud' custom post type
 * and its associated statuses.
 *
 * @package    GiVendor\GiPlugin\PostType
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
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
            'not_found_in_trash'    => __('No hay solicitudes en la papelera.', 'rfq-manager-woocommerce'),
            'featured_image'        => _x('Imagen destacada', 'Overrides the "Featured Image" phrase', 'rfq-manager-woocommerce'),
            'set_featured_image'    => _x('Establecer imagen destacada', 'Overrides the "Set featured image" phrase', 'rfq-manager-woocommerce'),
            'remove_featured_image' => _x('Quitar imagen destacada', 'Overrides the "Remove featured image" phrase', 'rfq-manager-woocommerce'),
            'use_featured_image'    => _x('Usar como imagen destacada', 'Overrides the "Use as featured image" phrase', 'rfq-manager-woocommerce'),
            'archives'              => _x('Archivo de solicitudes', 'The post type archive label used in nav menus', 'rfq-manager-woocommerce'),
            'attributes'            => _x('Atributos de la solicitud', 'The post type attributes label', 'rfq-manager-woocommerce'),
            'insert_into_item'      => _x('Insertar en solicitud', 'Overrides the "Insert into post"/"Insert into page" phrase', 'rfq-manager-woocommerce'),
            'uploaded_to_this_item' => _x('Subido a esta solicitud', 'Overrides the "Uploaded to this post"/"Uploaded to this page" phrase', 'rfq-manager-woocommerce'),
            'filter_items_list'     => _x('Filtrar lista de solicitudes', 'Screen reader text for the filter links heading on the post type listing screen', 'rfq-manager-woocommerce'),
            'items_list_navigation' => _x('Navegación de la lista de solicitudes', 'Screen reader text for the pagination heading on the post type listing screen', 'rfq-manager-woocommerce'),
            'items_list'            => _x('Lista de solicitudes', 'Screen reader text for the items list heading on the post type listing screen', 'rfq-manager-woocommerce'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_icon'          => 'dashicons-cart',
            'query_var'          => true,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 56,
            'supports'           => ['title'],
            'show_in_rest'       => false,
            'rest_base'          => 'solicitudes',
            'map_meta_cap'       => true,
        ];

        register_post_type('solicitud', $args);
    }

    /**
     * Register custom post statuses for the 'solicitud' post type
     *
     * @since  0.1.0
     * @return void
     */
    public static function registerStatuses(): void {
        register_post_status('rfq-pending', [
            'label'                     => __('Pendiente de cotización', 'rfq-manager-woocommerce'),
            'public'                    => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Pendiente (%s)', 'Pendientes (%s)', 'rfq-manager-woocommerce'),
        ]);

        register_post_status('rfq-accepted', [
            'label'                     => __('Propuesta aceptada', 'rfq-manager-woocommerce'),
            'public'                    => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Aceptada (%s)', 'Aceptadas (%s)', 'rfq-manager-woocommerce'),
        ]);

        register_post_status('rfq-historic', [
            'label'                     => __('Histórico', 'rfq-manager-woocommerce'),
            'public'                    => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Histórico (%s)', 'Históricos (%s)', 'rfq-manager-woocommerce'),
        ]);
    }
}