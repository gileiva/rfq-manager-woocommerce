<?php
/**
 * Creador de solicitudes
 *
 * @package    GiVendor\GiPlugin\Solicitud
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Solicitud;

use WC_Order;
use WP_Error;

/**
 * SolicitudCreator - Crea solicitudes a partir de órdenes de WooCommerce
 *
 * Esta clase es responsable de crear entradas en el CPT 'solicitud'
 * a partir de los datos de una orden de WooCommerce.
 *
 * @package    GiVendor\GiPlugin\Solicitud
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class SolicitudCreator {
    
    /**
     * Constantes para el manejo de fechas
     */
    private const DATE_FORMAT_DISPLAY = 'd/m/Y H:i';
    private const DATE_FORMAT_DB = 'Y-m-d H:i:s';
    private const DEFAULT_EXPIRY_HOURS = 24;
    
    /**
     * Crea una solicitud a partir de una orden de WooCommerce
     * 
     * @since 0.1.0
     * @param int $order_id ID de la orden de WooCommerce
     * @return int|false ID de la solicitud creada o false en caso de error
     */
    public static function create_from_order(int $order_id) {
        try {
        // Obtener la orden
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
                error_log('[RFQ] Error al obtener la orden ' . $order_id);
                return false;
            }

            // Verificar que la orden no tenga ya una solicitud asociada
            $existing_solicitud = self::get_solicitud_by_order($order_id);
            if ($existing_solicitud) {
                error_log(sprintf(
                    '[RFQ] La orden #%d ya tiene una solicitud asociada (#%d)',
                    $order_id,
                    $existing_solicitud
                ));
            return false;
        }
        
        // Extraer datos de los items
            $items = self::extract_order_items($order);
            if (empty($items)) {
                error_log('[RFQ] La orden no contiene items: ' . $order_id);
                return false;
            }

            // Estructurar datos del cliente
            $customerData = self::extract_customer_data($order);

            // Estructurar datos de envío
            $shippingData = self::extract_shipping_data($order);

            // Generar UUID
            $uuid = wp_generate_uuid4();
            
            // Crear la solicitud
            $solicitudTitle = self::generate_solicitud_title($uuid, $customerData);
            
            error_log(sprintf(
                '[RFQ] Creando solicitud con UUID: %s para orden #%d',
                $uuid,
                $order_id
            ));
            
            $solicitudId = wp_insert_post([
                'post_type'    => 'solicitud',
                'post_title'   => $solicitudTitle,
                'post_name'    => $uuid,
                'post_status'  => 'rfq-pending',
                'post_content' => '',
                'meta_input'   => [
                    '_solicitud_order_id' => absint($order_id),
                    '_solicitud_uuid'     => $uuid,
                ],
            ]);

            if (is_wp_error($solicitudId)) {
                error_log(sprintf(
                    '[RFQ] Error al crear solicitud: %s',
                    $solicitudId->get_error_message()
                ));
                return false;
            }

            // Guardar metadatos de forma segura
            self::save_solicitud_meta($solicitudId, $items, $customerData, $shippingData, $order);
            
            // Limpiar caché
            clean_post_cache($solicitudId);
            
            error_log(sprintf(
                '[RFQ] Solicitud #%d creada exitosamente para orden #%d',
                $solicitudId,
                $order_id
            ));
            
            // Disparar acción para que los sistemas de notificación puedan actuar
            do_action('rfq_solicitud_created', $solicitudId, [
                'order_id' => $order_id,
                'items' => $items,
                'customer' => $customerData,
                'shipping' => $shippingData,
                'uuid' => $uuid
            ]);
            
            error_log(sprintf(
                '[RFQ] Evento rfq_solicitud_created disparado para solicitud #%d',
                $solicitudId
            ));
            
            return $solicitudId;

        } catch (\Exception $e) {
            error_log(sprintf(
                '[RFQ] Error al crear solicitud: %s',
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Extrae los items de una orden
     *
     * @param WC_Order $order Orden de WooCommerce
     * @return array
     */
    private static function extract_order_items(WC_Order $order): array {
        $items = [];
        
        foreach ($order->get_items() as $item) {
            $product_id = absint($item->get_data()['product_id']);
            $qty = absint($item->get_quantity());
            $name = sanitize_text_field($item->get_name());
            $subtotal = floatval($order->get_item_subtotal($item, false));
            
            $items[] = [
                'product_id' => $product_id,
                'qty'        => $qty,
                'name'       => $name,
                'subtotal'   => $subtotal,
            ];
        }

        return $items;
    }

    /**
     * Extrae los datos del cliente de una orden
     *
     * @param WC_Order $order Orden de WooCommerce
     * @return array
     */
    private static function extract_customer_data(WC_Order $order): array {
        return [
            'first_name' => sanitize_text_field($order->get_billing_first_name()),
            'last_name'  => sanitize_text_field($order->get_billing_last_name()),
            'email'      => sanitize_email($order->get_billing_email()),
            'phone'      => sanitize_text_field($order->get_billing_phone()),
        ];
    }

    /**
     * Extrae los datos de envío de una orden
     *
     * @param WC_Order $order Orden de WooCommerce
     * @return array
     */
    private static function extract_shipping_data(WC_Order $order): array {
        return [
            'address_1' => sanitize_text_field($order->get_shipping_address_1()),
            'address_2' => sanitize_text_field($order->get_shipping_address_2()),
            'city'      => sanitize_text_field($order->get_shipping_city()),
            'state'     => sanitize_text_field($order->get_shipping_state()),
            'postcode'  => sanitize_text_field($order->get_shipping_postcode()),
            'country'   => sanitize_text_field($order->get_shipping_country()),
        ];
    }

    /**
     * Genera el título de la solicitud
     *
     * @param string $uuid UUID de la solicitud
     * @param array $customerData Datos del cliente
     * @return string
     */
    private static function generate_solicitud_title(string $uuid, array $customerData): string {
        return sprintf(
            __('Solicitud #%s - %s', 'rfq-manager-woocommerce'),
            $uuid,
            sanitize_text_field($customerData['first_name'] . ' ' . $customerData['last_name'])
        );
    }

    /**
     * Guarda los metadatos de la solicitud
     *
     * @param int $solicitudId ID de la solicitud
     * @param array $items Items de la orden
     * @param array $customerData Datos del cliente
     * @param array $shippingData Datos de envío
     * @param WC_Order $order Orden de WooCommerce
     * @return void
     */
    private static function save_solicitud_meta(int $solicitudId, array $items, array $customerData, array $shippingData, WC_Order $order): void {
        update_post_meta($solicitudId, '_solicitud_items', wp_json_encode($items));
        update_post_meta($solicitudId, '_solicitud_customer', wp_json_encode($customerData));
        update_post_meta($solicitudId, '_solicitud_shipping', wp_json_encode($shippingData));
        update_post_meta($solicitudId, '_solicitud_total', floatval($order->get_total()));
        update_post_meta($solicitudId, '_solicitud_date', current_time(self::DATE_FORMAT_DB));
        
        $expiry_timestamp = current_time('timestamp') + (self::DEFAULT_EXPIRY_HOURS * HOUR_IN_SECONDS);
        update_post_meta($solicitudId, '_solicitud_expiry', date(self::DATE_FORMAT_DB, $expiry_timestamp));
    }

    /**
     * Obtiene una solicitud por ID de orden
     *
     * @param int $order_id ID de la orden
     * @return int|false ID de la solicitud o false si no existe
     */
    private static function get_solicitud_by_order(int $order_id): int|false {
        $args = [
            'post_type' => 'solicitud',
            'post_status' => 'any',
            'meta_query' => [
                [
                    'key' => '_solicitud_order_id',
                    'value' => $order_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
            'fields' => 'ids'
        ];

        $query = new \WP_Query($args);
        return $query->have_posts() ? $query->posts[0] : false;
    }

    /**
     * Obtiene la fecha de expiración en formato de visualización
     *
     * @param string $date Fecha en formato DB
     * @return string Fecha en formato de visualización
     */
    public static function get_expiry_date_display(string $date): string {
        return date_i18n(
            self::DATE_FORMAT_DISPLAY,
            strtotime($date)
        );
    }
}