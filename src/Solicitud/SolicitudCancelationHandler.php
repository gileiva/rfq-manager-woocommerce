<?php
namespace GiVendor\GiPlugin\Solicitud;

use WP_Post;
use WP_User;

class SolicitudCancelationHandler
{
    /**
     * Determina si el usuario tiene permiso para cancelar una solicitud.
     *
     * @param WP_User $user
     * @param WP_Post $solicitud
     * @return bool
     */
    public static function can_cancel(WP_User $user, WP_Post $solicitud): bool
    {
        // Solo el autor o un administrador puede cancelar
        $is_author = ((int) $solicitud->post_author === (int) $user->ID);
        $is_admin = in_array('administrator', (array) $user->roles, true);
        $is_customer = in_array('customer', (array) $user->roles, true) || in_array('subscriber', (array) $user->roles, true);

        // Solo si la solicitud está en estado pendiente o activa
        $cancelable_statuses = ['rfq-pending', 'rfq-active'];
        $is_cancelable_status = in_array($solicitud->post_status, $cancelable_statuses, true);

        return ($is_author && $is_customer && $is_cancelable_status) || $is_admin;
    }

    /**
     * Ejecuta la cancelación de una solicitud (cambia su estado a rfq-historic).
     *
     * @param int $solicitud_id
     * @param WP_User $user
     * @return bool|\WP_Error
     */
    public static function cancel(int $solicitud_id, WP_User $user)
    {
        $solicitud = get_post($solicitud_id);
        if (!$solicitud || $solicitud->post_type !== 'solicitud') {
            return new \WP_Error('invalid_solicitud', 'La solicitud no existe o no es válida.');
        }

        if (!self::can_cancel($user, $solicitud)) {
            return new \WP_Error('not_allowed', 'No tienes permisos para cancelar esta solicitud.');
        }

        // Cambiar el estado
        return wp_update_post([
            'ID' => $solicitud_id,
            'post_status' => 'rfq-historic',
        ], true);
    }
}
