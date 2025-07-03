<?php 

namespace GiVendor\GiPlugin\Solicitud;

class SolicitudStatusHelper
{
    const STATUS_PENDING  = 'rfq-pending';
    const STATUS_ACTIVE   = 'rfq-active';
    const STATUS_ACCEPTED = 'rfq-accepted';
    const STATUS_CLOSED   = 'rfq-closed';
    const STATUS_HISTORIC = 'rfq-historic';

    public static function is_historic($solicitud): bool {
        $status = is_object($solicitud) ? $solicitud->post_status : (string)$solicitud;
        return $status === self::STATUS_HISTORIC;
    }

    public static function is_pending($solicitud): bool {
        $status = is_object($solicitud) ? $solicitud->post_status : (string)$solicitud;
        return $status === self::STATUS_PENDING;
    }

    public static function is_active($solicitud): bool {
        $status = is_object($solicitud) ? $solicitud->post_status : (string)$solicitud;
        return $status === self::STATUS_ACTIVE;
    }

    public static function is_accepted($solicitud): bool {
        $status = is_object($solicitud) ? $solicitud->post_status : (string)$solicitud;
        return $status === self::STATUS_ACCEPTED;
    }

    public static function is_closed($solicitud): bool {
        $status = is_object($solicitud) ? $solicitud->post_status : (string)$solicitud;
        return $status === self::STATUS_CLOSED;
    }

    public static function is_repeatable($solicitud): bool {
        $status = is_object($solicitud) ? $solicitud->post_status : (string)$solicitud;
        return in_array($status, [self::STATUS_HISTORIC, self::STATUS_ACCEPTED], true);
    }

    public static function is_cancelable($solicitud): bool {
        $status = is_object($solicitud) ? $solicitud->post_status : (string)$solicitud;
        return in_array($status, [self::STATUS_PENDING, self::STATUS_ACTIVE], true);
    }
}
