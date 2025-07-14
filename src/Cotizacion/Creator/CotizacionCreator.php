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
        
        // Crear el título de la cotización con formato personalizado
        $proveedor_id = get_current_user_id();
        $cotizacionTitle = self::generate_cotizacion_title($proveedor_id, $solicitud_id);
        
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

    /**
     * Genera el título de la cotización en formato proveedor-YYYY-MM-DD-cliente-n
     *
     * @param int $proveedor_id ID del proveedor
     * @param int $solicitud_id ID de la solicitud
     * @return string Título generado
     */
    private static function generate_cotizacion_title(int $proveedor_id, int $solicitud_id): string {
        $proveedor = get_userdata($proveedor_id);
        if (!$proveedor) {
            return 'proveedor-desconocido-' . current_time('Y-m-d') . '-cliente-1';
        }

        $solicitud = get_post($solicitud_id);
        if (!$solicitud || $solicitud->post_type !== 'solicitud') {
            return sanitize_title($proveedor->user_login) . '-' . current_time('Y-m-d') . '-solicitud-invalida-1';
        }

        $cliente = get_userdata($solicitud->post_author);
        if (!$cliente) {
            return sanitize_title($proveedor->user_login) . '-' . current_time('Y-m-d') . '-cliente-desconocido-1';
        }

        $proveedor_name = sanitize_title($proveedor->user_login);
        $cliente_name = sanitize_title($cliente->user_login);
        $date = current_time('Y-m-d');
        
        // Contar cotizaciones del proveedor para este cliente en la fecha actual
        $count = self::get_daily_cotizacion_count($proveedor_id, $solicitud->post_author, $date);
        $n = $count + 1;

        return sprintf('%s-%s-%s-%d', $proveedor_name, $date, $cliente_name, $n);
    }

    /**
     * Cuenta las cotizaciones de un proveedor para un cliente específico en una fecha
     *
     * @param int $proveedor_id ID del proveedor
     * @param int $cliente_id ID del cliente
     * @param string $date Fecha en formato Y-m-d
     * @return int Número de cotizaciones
     */
    private static function get_daily_cotizacion_count(int $proveedor_id, int $cliente_id, string $date): int {
        $args = [
            'post_type'      => 'cotizacion',
            'post_status'    => 'any',
            'author'         => $proveedor_id,
            'date_query'     => [
                [
                    'year'  => date('Y', strtotime($date)),
                    'month' => date('m', strtotime($date)),
                    'day'   => date('d', strtotime($date)),
                ]
            ],
            'meta_query'     => [
                [
                    'key'     => '_solicitud_parent',
                    'value'   => '',
                    'compare' => '!='
                ]
            ],
            'fields'         => 'ids',
            'posts_per_page' => -1
        ];

        $query = new \WP_Query($args);
        
        // Filtrar por cliente específico verificando el autor de cada solicitud padre
        $count = 0;
        if ($query->have_posts()) {
            foreach ($query->posts as $cotizacion_id) {
                $solicitud_parent_id = get_post_meta($cotizacion_id, '_solicitud_parent', true);
                if ($solicitud_parent_id) {
                    $solicitud_parent = get_post($solicitud_parent_id);
                    if ($solicitud_parent && (int)$solicitud_parent->post_author === $cliente_id) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }
}