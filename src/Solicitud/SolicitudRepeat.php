<?php
namespace GiVendor\GiPlugin\Solicitud;

if (!defined('ABSPATH')) {
    exit;
}

class SolicitudRepeat
{
    /**
     * Agrega los productos de una solicitud anterior al carrito de WooCommerce.
     *
     * @param int $solicitud_id
     * @return array|\WP_Error
     */
    public static function add_to_cart(int $solicitud_id)
    {
        // Validar post
        $solicitud = get_post($solicitud_id);
        if (!$solicitud || $solicitud->post_type !== 'solicitud') {
            return new \WP_Error('invalid_solicitud', __('La solicitud no existe o no es válida.', 'rfq-manager-woocommerce'));
        }
        // Leer items
        $items_json = get_post_meta($solicitud_id, '_solicitud_items', true);
        if (empty($items_json)) {
            return new \WP_Error('no_items', __('No hay productos en la solicitud.', 'rfq-manager-woocommerce'));
        }
        $items = json_decode($items_json, true);
        if (!is_array($items) || empty($items)) {
            return new \WP_Error('invalid_items', __('Los productos de la solicitud no son válidos.', 'rfq-manager-woocommerce'));
        }
        if (!function_exists('WC') || !WC()->cart) {
            return new \WP_Error('no_cart', __('El carrito de WooCommerce no está disponible.', 'rfq-manager-woocommerce'));
        }
        $added = [];
        $failed = [];
        foreach ($items as $item) {
            $product_id = isset($item['product_id']) ? (int)$item['product_id'] : 0;
            $qty = isset($item['qty']) ? (int)$item['qty'] : 1;
            if ($product_id <= 0 || $qty <= 0) {
                $failed[] = [
                    'product_id' => $product_id,
                    'reason' => __('ID o cantidad inválida.', 'rfq-manager-woocommerce')
                ];
                continue;
            }
            $product = wc_get_product($product_id);
            if (!$product || $product->get_status() !== 'publish' || !$product->is_purchasable()) {
                $failed[] = [
                    'product_id' => $product_id,
                    'reason' => __('Producto no disponible.', 'rfq-manager-woocommerce')
                ];
                continue;
            }
            // Intentar agregar al carrito
            $cart_item_key = WC()->cart->add_to_cart($product_id, $qty);
            if ($cart_item_key) {
                $added[] = $product_id;
            } else {
                $failed[] = [
                    'product_id' => $product_id,
                    'reason' => __('No se pudo agregar al carrito.', 'rfq-manager-woocommerce')
                ];
            }
        }
        return [
            'added' => $added,
            'failed' => $failed
        ];
    }
}
