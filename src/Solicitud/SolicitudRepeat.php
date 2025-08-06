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
     * MODIFICACIÓN RFQ: Este método ahora activa el contexto RFQ para permitir
     * que productos sin precio o con precio 0 sean considerados comprables
     * mediante la clase RFQPurchasableOverride.
     *
     * @param int $solicitud_id
     * @return array|\WP_Error
     */
    public static function add_to_cart(int $solicitud_id)
    {
        error_log('[RFQ] SolicitudRepeat::add_to_cart iniciado para solicitud: ' . $solicitud_id);
        
        // Validar post
        $solicitud = get_post($solicitud_id);
        if (!$solicitud || $solicitud->post_type !== 'solicitud') {
            error_log('[RFQ] Solicitud no válida: ' . $solicitud_id);
            return new \WP_Error('invalid_solicitud', __('La solicitud no existe o no es válida.', 'rfq-manager-woocommerce'));
        }
        
        // Leer items
        $items_json = get_post_meta($solicitud_id, '_solicitud_items', true);
        error_log('[RFQ] Items JSON obtenido: ' . $items_json);
        
        if (empty($items_json)) {
            error_log('[RFQ] No hay items en la solicitud');
            return new \WP_Error('no_items', __('No hay productos en la solicitud.', 'rfq-manager-woocommerce'));
        }
        
        $items = json_decode($items_json, true);
        error_log('[RFQ] Items decodificados: ' . print_r($items, true));
        
        if (!is_array($items) || empty($items)) {
            error_log('[RFQ] Items no válidos después de decodificar');
            return new \WP_Error('invalid_items', __('Los productos de la solicitud no son válidos.', 'rfq-manager-woocommerce'));
        }
        
        if (!function_exists('WC') || !WC()->cart) {
            error_log('[RFQ] WooCommerce o carrito no disponibles');
            return new \WP_Error('no_cart', __('El carrito de WooCommerce no está disponible.', 'rfq-manager-woocommerce'));
        }
        
        error_log('[RFQ] Iniciando proceso de agregar productos al carrito');
        $added = [];
        $failed = [];
        
        foreach ($items as $item) {
            $product_id = isset($item['product_id']) ? (int)$item['product_id'] : 0;
            $variation_id = isset($item['variation_id']) ? (int)$item['variation_id'] : 0;
            $qty = isset($item['qty']) ? (int)$item['qty'] : 1;
            $variation_data = isset($item['variation_data']) ? $item['variation_data'] : [];
            
            error_log('[RFQ] Procesando producto: ' . $product_id . ' (variación: ' . $variation_id . ') con cantidad: ' . $qty);
            
            if ($product_id <= 0 || $qty <= 0) {
                error_log('[RFQ] Producto con ID o cantidad inválida: ' . $product_id . ', qty: ' . $qty);
                $failed[] = [
                    'product_id' => $product_id,
                    'reason' => __('ID o cantidad inválida.', 'rfq-manager-woocommerce')
                ];
                continue;
            }
            
            // Usar variation_id si existe, sino product_id
            $target_product_id = $variation_id > 0 ? $variation_id : $product_id;
            $product = wc_get_product($target_product_id);
            error_log('[RFQ] Producto obtenido: ' . ($product ? 'Sí' : 'No') . ', ID: ' . $target_product_id);
            
            // Activar contexto RFQ para permitir productos sin precio
            \GiVendor\GiPlugin\WooCommerce\RFQPurchasableOverride::set_rfq_context(true);
            
            // LIMPIEZA AGRESIVA: Establecer flags para nueva solicitud RFQ
            \GiVendor\GiPlugin\Services\RFQFlagsManager::set_rfq_request_context('SolicitudRepeat_add_to_cart');
            
            // Validaciones críticas manteniendo seguridad
            if (!$product || $product->get_status() !== 'publish') {
                error_log('[RFQ] Producto no disponible: ' . $target_product_id . ', status: ' . ($product ? $product->get_status() : 'N/A'));
                $failed[] = [
                    'product_id' => $product_id,
                    'reason' => __('Producto no disponible.', 'rfq-manager-woocommerce')
                ];
                continue;
            }
            
            // Verificar stock si se gestiona (mantener verificación crítica)
            if ($product->managing_stock() && !$product->is_in_stock()) {
                error_log('[RFQ] Producto sin stock: ' . $target_product_id);
                $failed[] = [
                    'product_id' => $product_id,
                    'reason' => __('Producto sin stock.', 'rfq-manager-woocommerce')
                ];
                continue;
            }
            
            // Verificar comprable en contexto RFQ (ahora permite productos sin precio)
            if (!$product->is_purchasable()) {
                error_log('[RFQ] Producto no comprable incluso en contexto RFQ: ' . $target_product_id);
                $failed[] = [
                    'product_id' => $product_id,
                    'reason' => __('Producto no disponible para solicitud.', 'rfq-manager-woocommerce')
                ];
                continue;
            }
            
            // Intentar agregar al carrito con información de variación
            error_log('[RFQ] Intentando agregar al carrito: ' . $product_id . ' (variación: ' . $variation_id . ') x ' . $qty);
            $cart_item_key = WC()->cart->add_to_cart($product_id, $qty, $variation_id, $variation_data);
            
            if ($cart_item_key) {
                error_log('[RFQ] Producto agregado exitosamente: ' . $product_id . ', key: ' . $cart_item_key);
                $added[] = $product_id;
            } else {
                error_log('[RFQ] Error al agregar producto al carrito: ' . $product_id);
                $failed[] = [
                    'product_id' => $product_id,
                    'reason' => __('No se pudo agregar al carrito.', 'rfq-manager-woocommerce')
                ];
            }
        }
        
        // Desactivar contexto RFQ al finalizar procesamiento
        \GiVendor\GiPlugin\WooCommerce\RFQPurchasableOverride::set_rfq_context(false);
        
        // LIMPIEZA AGRESIVA: Limpiar flags si el carrito está vacío o hay errores
        if (WC()->session && WC()->cart && WC()->cart->is_empty()) {
            \GiVendor\GiPlugin\Services\RFQFlagsManager::clear_all_flags('SolicitudRepeat_empty_cart_after_add');
        }
        
        $result = [
            'added' => $added,
            'failed' => $failed
        ];
        
        error_log('[RFQ] Resultado final de add_to_cart: ' . print_r($result, true));
        return $result;
    }
}
