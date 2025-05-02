<?php
/**
 * Creador de solicitudes
 *
 * @package    GiVendor\GiPlugin\Solicitud
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Solicitud;

use WC_Order;

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
     * Crea una solicitud a partir de una orden de WooCommerce
     * 
     * @since 0.1.0
     * @param int $order_id ID de la orden de WooCommerce
     * @return int|false ID de la solicitud creada o false en caso de error
     */
    public static function create_from_order(int $order_id) {
        // Obtener la orden
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            error_log('RFQ Manager - Error al obtener la orden ' . $order_id);
            return false;
        }
        
        // Extraer datos de los items
        $items = [];
        
        foreach ($order->get_items() as $item) {
            $product_id = absint($item->get_product_id());
            $qty = absint($item->get_quantity());
            $name = sanitize_text_field($item->get_name());
            $subtotal = floatval($item->get_subtotal());
            
            $items[] = [
                'product_id' => $product_id,
                'qty'        => $qty,
                'name'       => $name,
                'subtotal'   => $subtotal,
            ];
        }

        // Estructurar datos del cliente para metadatos
        $customerData = [
            'first_name' => sanitize_text_field($order->get_billing_first_name()),
            'last_name'  => sanitize_text_field($order->get_billing_last_name()),
            'email'      => sanitize_email($order->get_billing_email()),
            'phone'      => sanitize_text_field($order->get_billing_phone()),
        ];

        // Estructurar datos de envío para metadatos
        $shippingData = [
            'address_1' => sanitize_text_field($order->get_shipping_address_1()),
            'address_2' => sanitize_text_field($order->get_shipping_address_2()),
            'city'      => sanitize_text_field($order->get_shipping_city()),
            'state'     => sanitize_text_field($order->get_shipping_state()),
            'postcode'  => sanitize_text_field($order->get_shipping_postcode()),
            'country'   => sanitize_text_field($order->get_shipping_country()),
        ];

        // Crear la solicitud
        $solicitudTitle = sprintf(
            /* translators: %d: order ID */
            __('Solicitud #%d', 'rfq-manager-woocommerce'),
            $order_id
        );
        
        error_log('RFQ Manager - Creando solicitud con título: ' . $solicitudTitle);
        
        $solicitudId = wp_insert_post([
            'post_type'    => 'solicitud',
            'post_title'   => $solicitudTitle,
            'post_status'  => 'rfq-pending',
            'post_content' => '', // No necesitamos contenido HTML
            'meta_input'   => [
                '_solicitud_order_id' => $order_id,
            ],
        ]);

        if (is_wp_error($solicitudId)) {
            error_log(sprintf('RFQ Manager - Error al crear solicitud: %s', $solicitudId->get_error_message()));
            return false;
        }

        error_log('RFQ Manager - Solicitud creada exitosamente con ID: ' . $solicitudId);

        // Guardar metadatos
        update_post_meta($solicitudId, '_solicitud_items', wp_json_encode($items));
        update_post_meta($solicitudId, '_solicitud_customer', wp_json_encode($customerData));
        update_post_meta($solicitudId, '_solicitud_shipping', wp_json_encode($shippingData));
        update_post_meta($solicitudId, '_solicitud_total', $order->get_total());
        update_post_meta($solicitudId, '_solicitud_date', current_time('mysql'));
        update_post_meta($solicitudId, '_solicitud_expiry', date('Y-m-d H:i:s', strtotime('+24 hours')));
        
        return $solicitudId;
    }
}