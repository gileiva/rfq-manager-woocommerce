<?php
namespace GiVendor\GiPlugin\WooCommerce;

add_action('wp_ajax_rfq_cart_update_item', [AjaxCart::class, 'update_item']);
add_action('wp_ajax_nopriv_rfq_cart_update_item', [AjaxCart::class, 'update_item']);
add_action('wp_ajax_rfq_cart_remove_item', [AjaxCart::class, 'remove_item']);
add_action('wp_ajax_nopriv_rfq_cart_remove_item', [AjaxCart::class, 'remove_item']);

class AjaxCart {
    public static function update_item() {
        check_ajax_referer('rfq_cart_ajax');
        if (!isset($_POST['cart_key'], $_POST['quantity'])) {
            wp_send_json_error('Datos incompletos');
        }
        $cart_key = sanitize_text_field($_POST['cart_key']);
        $quantity = max(1, intval($_POST['quantity']));
        if (!function_exists('WC') || !WC()->cart) {
            wp_send_json_error('Carrito no disponible');
        }
        WC()->cart->set_quantity($cart_key, $quantity, true);
        $cart_html = CartShortcode::render();
        wp_send_json_success(['cart_html' => $cart_html]);
    }

    public static function remove_item() {
        check_ajax_referer('rfq_cart_ajax');
        if (!isset($_POST['cart_key'])) {
            wp_send_json_error('Datos incompletos');
        }
        $cart_key = sanitize_text_field($_POST['cart_key']);
        if (!function_exists('WC') || !WC()->cart) {
            wp_send_json_error('Carrito no disponible');
        }
        WC()->cart->remove_cart_item($cart_key);
        $cart_html = CartShortcode::render();
        wp_send_json_success(['cart_html' => $cart_html]);
    }
}
