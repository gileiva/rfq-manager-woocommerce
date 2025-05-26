<?php
/**
 * RequestHelper - Clase helper para manejar datos de la solicitud actual
 *
 * @package    GiVendor\GiPlugin\Helpers
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Helpers;

/**
 * RequestHelper - Clase que proporciona métodos para obtener datos de la solicitud actual
 *
 * @package    GiVendor\GiPlugin\Helpers
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class RequestHelper {
    
    /**
     * Obtiene el slug (GUID) del post actual
     *
     * @since  0.1.0
     * @return string|null El slug del post o null si no hay post
     */
    public static function get_current_slug(): ?string {
        $post = get_queried_object();
        return $post ? $post->post_name : null;
    }
    
    /**
     * Obtiene el ID del post actual
     *
     * @since  0.1.0
     * @return int|null El ID del post o null si no hay post
     */
    public static function get_current_id(): ?int {
        $post = get_queried_object();
        return $post ? $post->ID : null;
    }
} 