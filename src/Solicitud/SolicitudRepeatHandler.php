<?php
namespace GiVendor\GiPlugin\Solicitud;

use WP_Post;
use WP_User;

class SolicitudRepeatHandler
{
    /**
     * Determina si el usuario puede repetir una solicitud.
     *
     * @param WP_User $user
     * @param WP_Post $solicitud
     * @return bool
     */
    public static function can_repeat(WP_User $user, WP_Post $solicitud): bool
    {
        $is_author = ((int) $solicitud->post_author === (int) $user->ID);
        $is_customer = in_array('customer', (array) $user->roles, true) || in_array('subscriber', (array) $user->roles, true);
        $repeatable_statuses = ['rfq-historic', 'rfq-accepted'];
        $is_repeatable_status = in_array($solicitud->post_status, $repeatable_statuses, true);
        return $is_author && $is_customer && $is_repeatable_status;
    }

    /**
     * Ejecuta la repetición de una solicitud (agrega productos al carrito).
     *
     * @param int $solicitud_id
     * @param WP_User $user
     * @return array|true|\WP_Error
     */
    public static function repeat(int $solicitud_id, WP_User $user)
    {
        $solicitud = get_post($solicitud_id);
        if (!$solicitud || $solicitud->post_type !== 'solicitud') {
            return new \WP_Error('invalid_solicitud', 'La solicitud no existe o no es válida.');
        }
        if (!self::can_repeat($user, $solicitud)) {
            return new \WP_Error('not_allowed', 'No tienes permisos para repetir esta solicitud.');
        }
        // Delegar a la lógica existente
        return \GiVendor\GiPlugin\Solicitud\SolicitudRepeat::add_to_cart($solicitud_id);
    }
}
