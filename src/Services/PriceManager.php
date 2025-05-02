<?php
/**
 * Gestor de precios
 *
 * @package    GiVendor\GiPlugin\Services
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Services;

/**
 * PriceManager - Gestiona la ocultación de precios
 *
 * Esta clase es responsable de ocultar los precios de los productos
 * en el front-end para clientes y proveedores, manteniendo visibles
 * los precios para administradores.
 *
 * @package    GiVendor\GiPlugin\Services
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class PriceManager {

    /**
     * Inicializa los hooks relacionados con precios
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        // Solo aplicar filtros si NO estamos en el admin o si no es un usuario administrador
        if (!is_admin() || !current_user_can('manage_options')) {
            // Ocultar precios en catálogos y Single Product
            add_filter('woocommerce_get_price_html', [__CLASS__, 'hidePriceHtml'], 10, 2);
            add_filter('woocommerce_cart_item_price', [__CLASS__, 'hideCartItemPrice'], 10, 3);
            
            // Ocultar precios en la REST API para no-admins
            add_filter('woocommerce_rest_prepare_product_object', [__CLASS__, 'filterRestProduct'], 10, 3);
            
            // Ocultar precios en los widgets de Elementor
            add_filter('woocommerce_after_shop_loop_item_title', [__CLASS__, 'removeProductPrice'], 5);
            add_filter('woocommerce_single_product_summary', [__CLASS__, 'removeProductPrice'], 5);
            
            // Ocultar subtotales, totales y descuentos en carrito y checkout
            add_filter('woocommerce_cart_item_subtotal', [__CLASS__, 'hideCartItemSubtotal'], 10, 3);
            add_filter('woocommerce_cart_subtotal', [__CLASS__, 'hideCartSubtotal'], 10, 3);
            add_filter('woocommerce_cart_totals_order_total_html', [__CLASS__, 'hideOrderTotal'], 10);
            add_filter('woocommerce_order_formatted_line_subtotal', [__CLASS__, 'hideOrderLineSubtotal'], 10, 3);
            
            // Ocultar precios en los bloques de Gutenberg de WooCommerce
            add_filter('render_block_woocommerce/cart-totals-block', [__CLASS__, 'hideBlockHTML'], 10);
            add_filter('render_block_woocommerce/checkout-order-summary-block', [__CLASS__, 'hideBlockHTML'], 10);
            add_filter('render_block_woocommerce/checkout-totals-block', [__CLASS__, 'hideBlockHTML'], 10);
            
            // Ocultar etiquetas de descuento/oferta
            add_filter('woocommerce_sale_flash', [__CLASS__, 'hideSaleBadge'], 10, 3);
            
            // Ocultar totales en páginas de orden recibida (thank you) para no-admins
            add_action('woocommerce_before_thankyou', [__CLASS__, 'hideThankYouOrderDetails']);
            add_filter('woocommerce_get_order_item_totals', [__CLASS__, 'hideOrderItemTotals'], 10, 2);
            
            // Aplicar CSS personalizado para ocultar elementos de precio adicionales solo para no-admins
            add_action('wp_head', [__CLASS__, 'addHidePriceCSS']);
        }
        
        // Hook de depuración
        add_action('init', function() {
            // error_log('RFQ Manager - PriceManager hooks inicializados');
        }, 999);
    }

    /**
     * Oculta el HTML del precio
     * 
     * @since 0.1.0
     * @param string $price El HTML del precio
     * @param object $product El producto
     * @return string HTML vacío
     */
    public static function hidePriceHtml($price, $product): string {
        return '';  // Devuelve una cadena vacía en lugar del HTML del precio
    }

    /**
     * Oculta el precio del ítem en el carrito
     * 
     * @since 0.1.0
     * @param string $price El HTML del precio
     * @param array $cart_item El ítem del carrito
     * @param string $cart_item_key La clave del ítem del carrito
     * @return string HTML vacío
     */
    public static function hideCartItemPrice($price, $cart_item, $cart_item_key): string {
        return '';  // Elimina el precio en el carrito
    }

    /**
     * Oculta el subtotal del ítem en el carrito
     * 
     * @since 0.1.0
     * @param string $subtotal El HTML del subtotal
     * @param array $cart_item El ítem del carrito
     * @param string $cart_item_key La clave del ítem del carrito
     * @return string HTML vacío
     */
    public static function hideCartItemSubtotal($subtotal, $cart_item, $cart_item_key): string {
        return '';  // Elimina el subtotal en el carrito
    }
    
    /**
     * Oculta el subtotal del carrito
     * 
     * @since 0.1.0
     * @param string $subtotal El HTML del subtotal
     * @param bool $compound Si es un impuesto compuesto
     * @param object $cart Objeto del carrito
     * @return string HTML vacío
     */
    public static function hideCartSubtotal($subtotal, $compound, $cart): string {
        return '';  // Elimina el subtotal del carrito
    }
    
    /**
     * Oculta el total de la orden
     * 
     * @since 0.1.0
     * @param string $html El HTML del total
     * @return string HTML vacío
     */
    public static function hideOrderTotal($html): string {
        return '';  // Elimina el total de la orden
    }
    
    /**
     * Oculta el subtotal de línea en la orden
     * 
     * @since 0.1.0
     * @param string $subtotal El HTML del subtotal
     * @param array $item El ítem de la orden
     * @param object $order La orden
     * @return string HTML vacío
     */
    public static function hideOrderLineSubtotal($subtotal, $item, $order): string {
        return '';  // Elimina el subtotal de línea
    }
    
    /**
     * Oculta el HTML de los bloques de WooCommerce
     * 
     * @since 0.1.0
     * @param string $block_content El contenido del bloque
     * @return string Contenido modificado
     */
    public static function hideBlockHTML($block_content): string {
        // Eliminar componentes específicos que muestran precios mientras mantiene la estructura
        $block_content = preg_replace('/<span class="wc-block-formatted-money-amount[^>]*>[^<]*<\/span>/', '', $block_content);
        $block_content = preg_replace('/<div class="wc-block-components-product-badge[^>]*>Save[^<]*<\/div>/', '', $block_content);
        
        return $block_content;
    }
    
    /**
     * Oculta la etiqueta de descuento/oferta
     * 
     * @since 0.1.0
     * @param string $html El HTML de la etiqueta
     * @param object $post El post
     * @param object $product El producto
     * @return string HTML vacío
     */
    public static function hideSaleBadge($html, $post, $product): string {
        return '';  // Elimina la etiqueta de descuento
    }
    
    /**
     * Oculta los detalles de la orden en la página de agradecimiento
     * 
     * @since 0.1.0
     * @param int $order_id ID de la orden
     * @return void
     */
    public static function hideThankYouOrderDetails($order_id): void {
        // Añadir inline script para ocultar los detalles de la orden, pero solo para no-admins
        if (!current_user_can('manage_options')) {
            echo '<script type="text/javascript">
                document.addEventListener("DOMContentLoaded", function() {
                    // Ocultar totales en la página de agradecimiento
                    var orderDetails = document.querySelectorAll(".woocommerce-order-details, .woocommerce-order-overview__total, .woocommerce-table__line-item .woocommerce-Price-amount");
                    for(var i = 0; i < orderDetails.length; i++) {
                        orderDetails[i].style.display = "none";
                    }
                });
            </script>';
        }
    }
    
    /**
     * Oculta los totales de los ítems de la orden
     * 
     * @since 0.1.0
     * @param array $total_rows Filas de totales
     * @param object $order La orden
     * @return array Filas de totales modificadas
     */
    public static function hideOrderItemTotals($total_rows, $order): array {
        // Solo ocultar para no-admins
        if (!current_user_can('manage_options')) {
            // Eliminar filas de totales relacionadas con precios
            unset($total_rows['cart_subtotal']);
            unset($total_rows['order_total']);
            unset($total_rows['payment_method']);
            unset($total_rows['subtotal']);
            unset($total_rows['discount']);
            unset($total_rows['shipping']);
            unset($total_rows['fee']);
            unset($total_rows['tax']);
        }
        
        return $total_rows;
    }
    
    /**
     * Añade CSS personalizado para ocultar elementos de precio adicionales
     * 
     * @since 0.1.0
     * @return void
     */
    public static function addHidePriceCSS(): void {
        echo '<style type="text/css">
            /* Ocultar precios en WooCommerce */
            .woocommerce-Price-amount,
            .price,
            .woocommerce-subtotal,
            .woocommerce-total,
            .amount,
            .wc-block-components-totals-item__value,
            .wc-block-components-totals-footer-item-tax-value,
            .wc-block-formatted-money-amount,
            .wc-block-components-product-badge,
            .wc-block-components-product-price,
            .wc-block-components-order-summary-item__total-price,
            .wc-block-components-totals-item,
            .wc-block-order-summary__total,
            .woocommerce-mini-cart__total {
                display: none !important;
            }
            
            /* Mantener visibles etiquetas importantes en el checkout */
            .wc-block-components-checkout-step__heading {
                display: block !important;
            }
            
            /* Ocultar específicamente badges de descuento */
            .onsale, 
            .wc-block-components-product-sale-badge {
                display: none !important;
            }
        </style>';
    }

    /**
     * Filtra los datos de producto en la API REST
     * 
     * @since 0.1.0
     * @param object $response Respuesta de la API
     * @param object $product El producto
     * @param object $request La solicitud
     * @return object Respuesta modificada
     */
    public static function filterRestProduct($response, $product, $request) {
        // Elimina campos relacionados con precio de la respuesta de la API
        // Solo para usuarios no administradores
        if (!current_user_can('manage_options')) {
            $data = $response->get_data();
            unset($data['price'], $data['regular_price'], $data['sale_price'], $data['price_html']);
            unset($data['on_sale'], $data['total_sales']);
            $response->set_data($data);
        }
        return $response;
    }
    
    /**
     * Elimina la acción que muestra el precio en plantillas de WooCommerce
     * 
     * @since 0.1.0
     * @return void
     */
    public static function removeProductPrice(): void {
        remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
    }
}