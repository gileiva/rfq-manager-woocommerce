<?php
/**
 * Creador de órdenes a partir de ofertas aceptadas
 *
 * @package    GiVendor\GiPlugin\Order
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Order;

use WC_Order;
use WP_Post;
use WP_User;
use WP_Error;
use Exception;

/**
 * OfferOrderCreator - Crea órdenes de WooCommerce a partir de cotizaciones aceptadas
 *
 * Esta clase es responsable de crear una nueva orden de WooCommerce cuando
 * un usuario acepta una cotización, copiando exactamente los productos,
 * cantidades y precios de la oferta sin usar el carrito.
 *
 * @package    GiVendor\GiPlugin\Order
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class OfferOrderCreator {

    /**
     * Configuración por defecto para el vencimiento del pago (en horas)
     */
    const DEFAULT_PAYMENT_EXPIRY_HOURS = 24;

    /**
     * Crea una nueva orden de WooCommerce a partir de una cotización aceptada
     *
     * @param WP_User $user Usuario que acepta la cotización
     * @param WP_Post $solicitud Solicitud asociada
     * @param WP_Post $cotizacion Cotización aceptada
     * @return int|WP_Error ID de la orden creada o WP_Error si falla
     */
    public static function create_from_accepted_offer(WP_User $user, WP_Post $solicitud, WP_Post $cotizacion) {
        error_log('[RFQ] OfferOrderCreator::create_from_accepted_offer iniciado para cotización: ' . $cotizacion->ID);

        // Obtener datos de la cotización
        $precio_items = get_post_meta($cotizacion->ID, '_precio_items', true);
        $total_cotizacion = get_post_meta($cotizacion->ID, '_total', true);
        $solicitud_items = get_post_meta($solicitud->ID, '_solicitud_items', true);

        if (empty($precio_items) || empty($solicitud_items)) {
            error_log('[RFQ] Error: Datos de cotización o solicitud faltantes');
            return new WP_Error('missing_data', __('Datos de cotización incompletos.', 'rfq-manager-woocommerce'));
        }

        // Decodificar items de solicitud si es necesario
        if (is_string($solicitud_items)) {
            $solicitud_items = json_decode($solicitud_items, true);
        }

        // Crear nueva orden
        $order = wc_create_order([
            'customer_id' => $user->ID,
            'status' => 'pending'
        ]);

        if (is_wp_error($order)) {
            error_log('[RFQ] Error creando orden: ' . $order->get_error_message());
            return $order;
        }

        // LIMPIEZA AGRESIVA: Establecer contexto de pago de oferta aceptada
        \GiVendor\GiPlugin\Services\RFQFlagsManager::set_offer_payment_context('OfferOrderCreator_create_order');

        try {
            // Agregar productos a la orden
            $order_total = 0.0;
            foreach ($solicitud_items as $item) {
                $product_id = absint($item['product_id']);
                $quantity = absint($item['qty']);

                // Verificar que existe precio para este producto en la cotización
                if (!isset($precio_items[$product_id])) {
                    error_log('[RFQ] Precio no encontrado para producto: ' . $product_id);
                    continue;
                }

                $precio_data = $precio_items[$product_id];
                $precio_unitario = floatval($precio_data['precio']);
                $subtotal = floatval($precio_data['subtotal']); // Ya incluye impuestos

                // Obtener producto
                $product = wc_get_product($product_id);
                if (!$product) {
                    error_log('[RFQ] Producto no encontrado: ' . $product_id);
                    continue;
                }

                // SOLUCIÓN WOOCOMMERCE NATIVA: Agregar item con precios personalizados usando parámetros add_product()
                $item_id = $order->add_product($product, $quantity, [
                    'subtotal' => $subtotal,
                    'total' => $subtotal,
                ]);

                if (!$item_id) {
                    error_log('[RFQ] Error agregando producto a orden: ' . $product_id);
                    continue;
                }

                // SOLUCIÓN NATIVA: Solo agregar meta datos, NO modificar precios después 
                // (Los precios ya están correctamente establecidos por add_product con parámetros)
                wc_add_order_item_meta($item_id, '_rfq_cotized_price', $precio_unitario);
                wc_add_order_item_meta($item_id, '_rfq_cotized_subtotal', $subtotal);

                $order_total += $subtotal;

                error_log('[RFQ-NATIVE-SOLUTION] Producto agregado con precios nativos - ID: ' . $product_id . ', Cantidad: ' . $quantity . ', Subtotal: ' . $subtotal);
            }

            // SOLUCIÓN WOOCOMMERCE NATIVA: Recalcular totales después de todos los add_product()
            // Esto es crítico para que WooCommerce procese correctamente los parámetros subtotal/total
            $order->calculate_totals();
            
            // Establecer total de la orden (usar el total de la cotización para mayor precisión)
            $final_total = !empty($total_cotizacion) ? floatval($total_cotizacion) : $order_total;
            $order->set_total($final_total);

            // Agregar meta datos personalizados
            self::add_order_meta($order, $solicitud, $cotizacion);

            // Calcular fecha de vencimiento del pago
            $expiry_timestamp = self::calculate_payment_expiry();
            $order->update_meta_data('_rfq_order_acceptance_expiry', $expiry_timestamp);

            // SOLUCIÓN NATIVA: Guardar la orden después de calculate_totals()
            $order->save();

            error_log('[RFQ-NATIVE-SOLUTION] Orden creada con solución nativa - ID: ' . $order->get_id() . ', Total: ' . $final_total);

            return $order->get_id();

        } catch (Exception $e) {
            error_log('[RFQ] Excepción creando orden: ' . $e->getMessage());
            
            // Limpiar orden parcial si existe
            if ($order && $order->get_id()) {
                wp_delete_post($order->get_id(), true);
            }

            return new WP_Error('order_creation_failed', __('Error creando la orden: ', 'rfq-manager-woocommerce') . $e->getMessage());
        }
    }

    /**
     * Agrega meta datos personalizados a la orden
     *
     * @param WC_Order $order Orden de WooCommerce
     * @param WP_Post $solicitud Solicitud asociada
     * @param WP_Post $cotizacion Cotización aceptada
     */
    private static function add_order_meta(WC_Order $order, WP_Post $solicitud, WP_Post $cotizacion): void {
        // Marcar como orden de oferta aceptada
        $order->update_meta_data('_rfq_offer_order', 'yes');
        
        // Referencias a solicitud y cotización
        $order->update_meta_data('_rfq_solicitud_id', $solicitud->ID);
        $order->update_meta_data('_rfq_cotizacion_id', $cotizacion->ID);
        
        // UUID de la solicitud para referencia
        $solicitud_uuid = get_post_meta($solicitud->ID, '_solicitud_uuid', true);
        if ($solicitud_uuid) {
            $order->update_meta_data('_rfq_solicitud_uuid', $solicitud_uuid);
        }

        // Timestamp de aceptación
        $order->update_meta_data('_rfq_acceptance_timestamp', current_time('timestamp'));

        // Proveedor de la cotización
        $proveedor_id = $cotizacion->post_author;
        $order->update_meta_data('_rfq_proveedor_id', $proveedor_id);

        // METAS DE TRAZABILIDAD: Información de aceptación por cliente
        $current_user_id = get_current_user_id();
        if ($current_user_id) {
            $order->update_meta_data('_rfq_aceptada_por_cliente', $current_user_id);
        }
        $order->update_meta_data('_rfq_aceptacion_timestamp', current_time('mysql'));
        $order->update_meta_data('_rfq_contexto_pago', 'oferta_aceptada');

        // PROTECCIÓN: Guardar datos originales para validación de integridad
        $original_items = [];
        foreach ($order->get_items() as $item_id => $item) {
            $original_items[] = [
                'product_id' => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'quantity' => $item->get_quantity(),
                'price' => $item->get_total(),
                'name' => $item->get_name()
            ];
        }
        $order->update_meta_data('_rfq_original_items', $original_items);
        $order->update_meta_data('_rfq_original_total', $order->get_total());
        
        error_log('[RFQ-PROTECCION] Datos originales guardados para orden: ' . $order->get_id() . ' - Items: ' . count($original_items));
        error_log('[RFQ] Meta datos agregados a orden: ' . $order->get_id() . ' (aceptada por usuario: ' . $current_user_id . ')');
    }

    /**
     * Calcula la fecha de vencimiento del pago
     *
     * @return int Timestamp de vencimiento
     */
    private static function calculate_payment_expiry(): int {
        $expiry_hours = self::DEFAULT_PAYMENT_EXPIRY_HOURS;
        
        // Permitir configuración futura del vencimiento
        $expiry_hours = apply_filters('rfq_payment_expiry_hours', $expiry_hours);
        
        $expiry_timestamp = current_time('timestamp') + ($expiry_hours * HOUR_IN_SECONDS);
        
        error_log('[RFQ] Vencimiento de pago calculado: ' . date('Y-m-d H:i:s', $expiry_timestamp) . ' (' . $expiry_hours . ' horas)');
        
        return $expiry_timestamp;
    }

    /**
     * Obtiene la URL del checkout para una orden específica
     *
     * @param int $order_id ID de la orden
     * @return string URL del checkout
     */
    public static function get_checkout_url(int $order_id): string {
        $order = wc_get_order($order_id);
        if (!$order) {
            return wc_get_checkout_url();
        }

        return $order->get_checkout_payment_url();
    }

    /**
     * Inicializa hooks para limpieza de sesión y precios en checkout
     */
    public static function init_hooks(): void {
        // Limpiar contexto RFQ cuando la orden cambia de estado
        add_action('woocommerce_order_status_changed', [__CLASS__, 'maybe_clear_rfq_session'], 10, 3);
        
        // SOLUCIÓN: Hook más específico para visualización en checkout
        add_action('wp_loaded', function() {
            add_filter('woocommerce_order_formatted_line_subtotal', [__CLASS__, 'ensure_rfq_line_subtotal_display'], 100, 3);
            error_log('[RFQ-CHECKOUT-INIT] Hook woocommerce_order_formatted_line_subtotal registrado en wp_loaded');
        });
        
        // NUEVO: Hook directo para la tabla de order-pay
        add_action('woocommerce_order_details_before_order_table', [__CLASS__, 'inject_order_pay_scripts']);
        
        // HOOKS ALTERNATIVOS: Para otros contextos
        add_filter('woocommerce_order_item_subtotal', [__CLASS__, 'override_rfq_item_subtotal'], 100, 3);
        add_filter('woocommerce_order_item_total', [__CLASS__, 'override_rfq_item_total'], 100, 3);
        
        // NUEVO: Hook de diagnóstico específico para order-pay
        add_action('template_redirect', [__CLASS__, 'diagnostic_order_pay_context']);
        
        // NUEVO: Hooks adicionales para detectar contexto de checkout
        add_action('woocommerce_checkout_order_review', function() {
            error_log('[RFQ-CHECKOUT-CONTEXT] woocommerce_checkout_order_review ejecutándose');
        });
        
        add_action('woocommerce_order_details_after_order_table', function($order) {
            error_log('[RFQ-CHECKOUT-CONTEXT] woocommerce_order_details_after_order_table ejecutándose para orden: ' . $order->get_id());
        });
        
        add_filter('woocommerce_get_formatted_order_total', function($formatted_total, $order) {
            error_log('[RFQ-CHECKOUT-CONTEXT] woocommerce_get_formatted_order_total ejecutándose para orden: ' . $order->get_id() . ' - Total: ' . $formatted_total);
            return $formatted_total;
        }, 10, 2);
        
        error_log('[RFQ-CHECKOUT-INIT] Hooks de precios de checkout inicializados');
    }

    /**
     * Limpia el contexto RFQ de la sesión cuando es apropiado
     */
    public static function maybe_clear_rfq_session(int $order_id, string $old_status, string $new_status): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Si la orden tiene meta de RFQ y está completada, cancelada o fallida, limpiar sesión
        if ($order->get_meta('_rfq_cotizacion_id') && in_array($new_status, ['completed', 'cancelled', 'failed', 'processing'])) {
            \GiVendor\GiPlugin\Services\RFQFlagsManager::clear_all_flags("OfferOrderCreator_order_status_changed_{$old_status}_to_{$new_status}_order_{$order_id}");
        }
    }

    /**
     * DIAGNÓSTICO: Verifica el contexto de order-pay y los datos de la orden
     */
    public static function diagnostic_order_pay_context(): void {
        if (!is_wc_endpoint_url('order-pay')) {
            return;
        }

        global $wp;
        $order_id = absint($wp->query_vars['order-pay'] ?? 0);
        
        if (!$order_id) {
            error_log('[RFQ-DIAGNOSTIC] ❌ No se encontró order_id en order-pay');
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('[RFQ-DIAGNOSTIC] ❌ No se pudo cargar la orden #' . $order_id);
            return;
        }

        error_log('[RFQ-DIAGNOSTIC] ========== DIAGNÓSTICO ORDEN #' . $order_id . ' ==========');
        error_log('[RFQ-DIAGNOSTIC] Estado: ' . $order->get_status());
        error_log('[RFQ-DIAGNOSTIC] Total: ' . $order->get_total());
        error_log('[RFQ-DIAGNOSTIC] Moneda: ' . $order->get_currency());
        error_log('[RFQ-DIAGNOSTIC] RFQ Cotización ID: ' . $order->get_meta('_rfq_cotizacion_id'));
        error_log('[RFQ-DIAGNOSTIC] RFQ Offer Order: ' . $order->get_meta('_rfq_offer_order'));

        $items = $order->get_items();
        error_log('[RFQ-DIAGNOSTIC] Número de items: ' . count($items));

        foreach ($items as $item_id => $item) {
            error_log('[RFQ-DIAGNOSTIC] --- ITEM #' . $item_id . ' ---');
            error_log('[RFQ-DIAGNOSTIC] Nombre: ' . $item->get_name());
            error_log('[RFQ-DIAGNOSTIC] Cantidad: ' . $item->get_quantity());
            error_log('[RFQ-DIAGNOSTIC] Subtotal: ' . $item->get_subtotal());
            error_log('[RFQ-DIAGNOSTIC] Total: ' . $item->get_total());
            error_log('[RFQ-DIAGNOSTIC] RFQ Meta _rfq_cotized_price: ' . $item->get_meta('_rfq_cotized_price'));
            error_log('[RFQ-DIAGNOSTIC] RFQ Meta _rfq_cotized_subtotal: ' . $item->get_meta('_rfq_cotized_subtotal'));
        }

        error_log('[RFQ-DIAGNOSTIC] ========== FIN DIAGNÓSTICO ==========');
    }

    /**
     * SOLUCIÓN DIRECTA: Inyecta JavaScript para corregir totales en order-pay
     */
    public static function inject_order_pay_scripts($order): void {
        // Solo ejecutar en pages order-pay
        if (!is_wc_endpoint_url('order-pay')) {
            return;
        }

        // Solo para órdenes RFQ
        $is_rfq_order = !empty($order->get_meta('_rfq_cotizacion_id')) || $order->get_meta('_rfq_offer_order') === 'yes';
        if (!$is_rfq_order) {
            return;
        }

        error_log('[RFQ-CHECKOUT-JS] Inyectando script para corregir totales en order-pay para orden: ' . $order->get_id());

        // Obtener datos de items para JavaScript
        $items_data = [];
        foreach ($order->get_items() as $item_id => $item) {
            $rfq_subtotal = $item->get_meta('_rfq_cotized_subtotal');
            if (!empty($rfq_subtotal)) {
                $items_data[] = [
                    'item_id' => $item_id,
                    'name' => $item->get_name(),
                    'price' => floatval($rfq_subtotal),
                    'formatted_price' => wc_price($rfq_subtotal, array('currency' => $order->get_currency()))
                ];
            }
        }

        if (empty($items_data)) {
            return;
        }

        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log('[RFQ-CHECKOUT-FIX] Iniciando corrección de totales para orden RFQ #<?php echo $order->get_id(); ?>');
            
            var itemsData = <?php echo json_encode($items_data); ?>;
            console.log('[RFQ-CHECKOUT-FIX] Datos de items:', itemsData);
            
            // Función para corregir los totales
            function fixRFQTotals() {
                var fixed = 0;
                
                // Buscar celdas de total en la tabla de order-pay
                $('.shop_table.order_details tr').each(function(index, row) {
                    var $row = $(row);
                    var $nameCell = $row.find('.wc-item-meta, .product-name');
                    var $totalCell = $row.find('.product-total, .woocommerce-table__product-total');
                    
                    if ($nameCell.length && $totalCell.length) {
                        var productName = $nameCell.text().trim();
                        console.log('[RFQ-CHECKOUT-FIX] Examinando fila:', productName, 'Total actual:', $totalCell.html());
                        
                        // Buscar match por nombre de producto
                        for (var i = 0; i < itemsData.length; i++) {
                            if (itemsData[i].name === productName) {
                                // Solo actualizar si la celda está vacía o contiene precio no válido
                                var currentContent = $totalCell.text().trim();
                                if (!currentContent || currentContent === '' || currentContent === '0' || !currentContent.includes('€')) {
                                    $totalCell.html(itemsData[i].formatted_price);
                                    console.log('[RFQ-CHECKOUT-FIX] ✅ Total corregido para:', productName, '→', itemsData[i].formatted_price);
                                    fixed++;
                                }
                                break;
                            }
                        }
                    }
                });
                
                // También buscar por clases más específicas de WooCommerce
                $('.woocommerce-table__line-item').each(function(index, row) {
                    var $row = $(row);
                    var $totalCell = $row.find('.woocommerce-table__product-total');
                    
                    if ($totalCell.length && (!$totalCell.text().trim() || !$totalCell.text().includes('€'))) {
                        if (itemsData[index] && itemsData[index].formatted_price) {
                            $totalCell.html(itemsData[index].formatted_price);
                            console.log('[RFQ-CHECKOUT-FIX] ✅ Total corregido por índice:', index, '→', itemsData[index].formatted_price);
                            fixed++;
                        }
                    }
                });
                
                console.log('[RFQ-CHECKOUT-FIX] Totales corregidos:', fixed);
                return fixed;
            }
            
            // Ejecutar corrección múltiples veces para asegurar que funcione
            fixRFQTotals();
            setTimeout(fixRFQTotals, 100);
            setTimeout(fixRFQTotals, 500);
            setTimeout(fixRFQTotals, 1000);
        });
        </script>
        <?php

        error_log('[RFQ-CHECKOUT-JS] Script de corrección inyectado con ' . count($items_data) . ' items');
    }

    /**
     * HOOK PRINCIPAL: Asegura que los precios RFQ se muestren correctamente en checkout usando el hook correcto
     */
    public static function ensure_rfq_line_subtotal_display($subtotal, $item, $order): string {
        error_log('[RFQ-CHECKOUT-MAIN] ========== ensure_rfq_line_subtotal_display ==========');
        error_log('[RFQ-CHECKOUT-MAIN] - Order ID: ' . $order->get_id());
        error_log('[RFQ-CHECKOUT-MAIN] - Item ID: ' . $item->get_id());
        error_log('[RFQ-CHECKOUT-MAIN] - Subtotal recibido: ' . $subtotal);
        error_log('[RFQ-CHECKOUT-MAIN] - Current URL: ' . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
        error_log('[RFQ-CHECKOUT-MAIN] - Is order-pay: ' . (is_wc_endpoint_url('order-pay') ? 'SÍ' : 'NO'));
        
        // Verificar si es orden RFQ usando múltiples métodos
        $rfq_cotizacion_id = $order->get_meta('_rfq_cotizacion_id');
        $rfq_offer_order = $order->get_meta('_rfq_offer_order');
        error_log('[RFQ-CHECKOUT-MAIN] - RFQ Cotización ID: ' . $rfq_cotizacion_id);
        error_log('[RFQ-CHECKOUT-MAIN] - RFQ Offer Order: ' . $rfq_offer_order);
        
        $is_rfq_order = !empty($rfq_cotizacion_id) || $rfq_offer_order === 'yes';
        
        if (!$is_rfq_order) {
            error_log('[RFQ-CHECKOUT-MAIN] - ❌ No es orden RFQ, retornando subtotal original');
            return $subtotal;
        }

        error_log('[RFQ-CHECKOUT-MAIN] - ✅ ES ORDEN RFQ - Buscando precios...');

        // Si el item tiene precio RFQ guardado, usarlo
        $rfq_subtotal = $item->get_meta('_rfq_cotized_subtotal');
        $rfq_price = $item->get_meta('_rfq_cotized_price');
        error_log('[RFQ-CHECKOUT-MAIN] - Meta _rfq_cotized_subtotal: ' . $rfq_subtotal);
        error_log('[RFQ-CHECKOUT-MAIN] - Meta _rfq_cotized_price: ' . $rfq_price);
        
        if (!empty($rfq_subtotal) && $rfq_subtotal > 0) {
            $formatted_price = wc_price($rfq_subtotal, array('currency' => $order->get_currency()));
            error_log('[RFQ-CHECKOUT-MAIN] - ✅ APLICANDO PRECIO RFQ SUBTOTAL: ' . $formatted_price);
            return $formatted_price;
        }

        if (!empty($rfq_price) && $rfq_price > 0) {
            $total_price = floatval($rfq_price) * $item->get_quantity();
            $formatted_price = wc_price($total_price, array('currency' => $order->get_currency()));
            error_log('[RFQ-CHECKOUT-MAIN] - ✅ APLICANDO PRECIO RFQ CALCULADO: ' . $formatted_price . ' (' . $rfq_price . ' x ' . $item->get_quantity() . ')');
            return $formatted_price;
        }

        // Fallback: Si no hay precio RFQ específico, usar el subtotal/total del item
        $item_subtotal = $item->get_subtotal();
        $item_total = $item->get_total();
        error_log('[RFQ-CHECKOUT-MAIN] - Item subtotal nativo: ' . $item_subtotal . ', Item total nativo: ' . $item_total);
        
        if ($item_total > 0) {
            $formatted_price = wc_price($item_total, array('currency' => $order->get_currency()));
            error_log('[RFQ-CHECKOUT-MAIN] - ✅ APLICANDO TOTAL ITEM NATIVO: ' . $formatted_price);
            return $formatted_price;
        }

        if ($item_subtotal > 0) {
            $formatted_price = wc_price($item_subtotal, array('currency' => $order->get_currency()));
            error_log('[RFQ-CHECKOUT-MAIN] - ✅ APLICANDO SUBTOTAL ITEM NATIVO: ' . $formatted_price);
            return $formatted_price;
        }

        error_log('[RFQ-CHECKOUT-MAIN] - ❌ No se encontraron precios válidos, retornando subtotal original: ' . $subtotal);
        error_log('[RFQ-CHECKOUT-MAIN] ========== FIN ensure_rfq_line_subtotal_display ==========');
        return $subtotal;
    }

    /**
     * Asegura que los precios de items RFQ se muestren correctamente en checkout
     */
    public static function override_rfq_item_subtotal($subtotal, $item, $order): string {
        error_log('[RFQ-CHECKOUT-HOOK] override_rfq_item_subtotal llamado');
        error_log('[RFQ-CHECKOUT-HOOK] - Order ID: ' . $order->get_id());
        error_log('[RFQ-CHECKOUT-HOOK] - Item ID: ' . $item->get_id());
        error_log('[RFQ-CHECKOUT-HOOK] - Subtotal recibido: ' . $subtotal);
        
        // Solo aplicar para órdenes RFQ
        $rfq_cotizacion_id = $order->get_meta('_rfq_cotizacion_id');
        error_log('[RFQ-CHECKOUT-HOOK] - RFQ Cotización ID: ' . $rfq_cotizacion_id);
        
        if (!$rfq_cotizacion_id) {
            error_log('[RFQ-CHECKOUT-HOOK] - No es orden RFQ, retornando subtotal original');
            return $subtotal;
        }

        // Si el item tiene precio RFQ guardado, usarlo
        $rfq_subtotal = $item->get_meta('_rfq_cotized_subtotal');
        error_log('[RFQ-CHECKOUT-HOOK] - RFQ Subtotal guardado: ' . $rfq_subtotal);
        
        if (!empty($rfq_subtotal)) {
            $formatted_price = wc_price($rfq_subtotal);
            error_log('[RFQ-CHECKOUT-HOOK] - Aplicando precio RFQ: ' . $formatted_price);
            return $formatted_price;
        }

        // Si no hay precio RFQ específico, usar el subtotal del item
        $item_subtotal = $item->get_subtotal();
        error_log('[RFQ-CHECKOUT-HOOK] - Subtotal del item: ' . $item_subtotal);
        
        if ($item_subtotal > 0) {
            $formatted_price = wc_price($item_subtotal);
            error_log('[RFQ-CHECKOUT-HOOK] - Aplicando subtotal item: ' . $formatted_price);
            return $formatted_price;
        }

        error_log('[RFQ-CHECKOUT-HOOK] - Retornando subtotal original: ' . $subtotal);
        return $subtotal;
    }

    /**
     * Asegura que los totales de items RFQ se muestren correctamente en checkout
     */
    public static function override_rfq_item_total($total, $item, $order): string {
        error_log('[RFQ-CHECKOUT-HOOK] override_rfq_item_total llamado');
        error_log('[RFQ-CHECKOUT-HOOK] - Order ID: ' . $order->get_id());
        error_log('[RFQ-CHECKOUT-HOOK] - Item ID: ' . $item->get_id());
        error_log('[RFQ-CHECKOUT-HOOK] - Total recibido: ' . $total);
        
        // Solo aplicar para órdenes RFQ
        $rfq_cotizacion_id = $order->get_meta('_rfq_cotizacion_id');
        error_log('[RFQ-CHECKOUT-HOOK] - RFQ Cotización ID: ' . $rfq_cotizacion_id);
        
        if (!$rfq_cotizacion_id) {
            error_log('[RFQ-CHECKOUT-HOOK] - No es orden RFQ, retornando total original');
            return $total;
        }

        // Si el item tiene precio RFQ guardado, usarlo
        $rfq_subtotal = $item->get_meta('_rfq_cotized_subtotal');
        error_log('[RFQ-CHECKOUT-HOOK] - RFQ Subtotal guardado: ' . $rfq_subtotal);
        
        if (!empty($rfq_subtotal)) {
            $formatted_price = wc_price($rfq_subtotal);
            error_log('[RFQ-CHECKOUT-HOOK] - Aplicando precio RFQ para total: ' . $formatted_price);
            return $formatted_price;
        }

        // Si no hay precio RFQ específico, usar el total del item
        $item_total = $item->get_total();
        error_log('[RFQ-CHECKOUT-HOOK] - Total del item: ' . $item_total);
        
        if ($item_total > 0) {
            $formatted_price = wc_price($item_total);
            error_log('[RFQ-CHECKOUT-HOOK] - Aplicando total item: ' . $formatted_price);
            return $formatted_price;
        }

        error_log('[RFQ-CHECKOUT-HOOK] - Retornando total original: ' . $total);
        return $total;
    }
}
