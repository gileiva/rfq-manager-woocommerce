<?php
/**
 * Creador de cotizaciones
 *
 * @package    GiVendor\GiPlugin\Cotizacion\Creator
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Cotizacion\Creator;

/**
 * CotizacionCreator - Crea y maneja cotizaciones
 *
 * Esta clase es responsable de crear y manejar cotizaciones
 * para las solicitudes de RFQ.
 *
 * @package    GiVendor\GiPlugin\Cotizacion\Creator
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class CotizacionCreator {
    
    /**
     * Crea una nueva cotización
     * 
     * @since 0.1.0
     * @param int   $solicitud_id ID de la solicitud
     * @param array $precio_items Array de precios por producto
     * @param float $total        Total de la cotización
     * @return int|false ID de la cotización creada o false en caso de error
     */
    public static function create(int $solicitud_id, array $precio_items, float $total) {
        // Verificar que la solicitud existe
        $solicitud = get_post($solicitud_id);
        if (!$solicitud || $solicitud->post_type !== 'solicitud') {
            return false;
        }

        

        // Sanitizar los precios
        $precio_items = self::sanitize_precio_items($precio_items);
        
                
        // Generar UUID seguro
        $uuid = wp_generate_uuid4();
        // Validar que el UUID no exista como slug
        $existing = get_page_by_path($uuid, OBJECT, 'cotizacion');
        if ($existing) {
            return false;
        }
        
        // Crear el título de la cotización
        $cotizacionTitle = $uuid;
        
        // Crear la cotización
        $cotizacionId = wp_insert_post([
            'post_type'    => 'cotizacion',
            'post_title'   => $cotizacionTitle,
            'post_name'    => $uuid,
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
        ]);

        if (is_wp_error($cotizacionId)) {
            error_log('RFQ Error - Error al crear cotización: ' . $cotizacionId->get_error_message());
            return false;
        }

        // Guardar los meta datos por separado
        update_post_meta($cotizacionId, '_solicitud_parent', $solicitud_id);
        update_post_meta($cotizacionId, '_precio_items', $precio_items);
        update_post_meta($cotizacionId, '_total', $total);
        update_post_meta($cotizacionId, '_observaciones', sanitize_textarea_field($_POST['observaciones'] ?? ''));
        update_post_meta($cotizacionId, '_cotizacion_uuid', $uuid);

        // Debug: Verificar que los datos se guardaron correctamente
        $saved_precios = get_post_meta($cotizacionId, '_precio_items', true);

        // Forzar actualización de estado de la solicitud
        if (class_exists('GiVendor\\GiPlugin\\Solicitud\\SolicitudStatusHandler')) {
            \GiVendor\GiPlugin\Solicitud\SolicitudStatusHandler::check_and_update_status($solicitud_id, get_post($solicitud_id), true);
        }

        return $cotizacionId;
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
     * Obtiene las cotizaciones para una solicitud específica
     *
     * @since  0.1.0
     * @param  int $solicitud_id ID de la solicitud
     * @return array Array de cotizaciones
     */
    public static function get_cotizaciones_for_solicitud(int $solicitud_id): array {
        $args = [
            'post_type'      => 'cotizacion',
            'posts_per_page' => -1,
            'meta_key'       => '_solicitud_parent',
            'meta_value'     => $solicitud_id,
            'post_status'    => 'publish',
        ];

        $query = new \WP_Query($args);
        return $query->posts;
    }

    /**
     * Calcula el total de una cotización basado en los precios de los ítems
     *
     * @since  0.1.0
     * @param  array $precio_items Array de precios por producto
     * @param  array $items        Array de items de la solicitud
     * @return float Total calculado
     */
    public static function calculate_total(array $precio_items, array $items): float {
        $total = 0.0;

        foreach ($items as $item) {
            $product_id = absint($item['product_id']);
            $qty = absint($item['qty']);

            if (isset($precio_items[$product_id])) {
                $total += floatval($precio_items[$product_id]) * $qty;
            }
        }

        return $total;
    }
} 