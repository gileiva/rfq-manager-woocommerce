<?php
/**
 * Shortcode para mostrar el carrito RFQ personalizado
 *
 * @package    GiVendor\GiPlugin\WooCommerce
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\WooCommerce;

/**
 * CartShortcode - Genera el shortcode [rfq_cart]
 *
 * Esta clase implementa un shortcode para mostrar un carrito de productos
 * sin precios, específicamente diseñado para solicitudes de cotización.
 *
 * @package    GiVendor\GiPlugin\WooCommerce
 * @since      0.1.0
 */
class CartShortcode {

    /**
     * Registra el shortcode y hooks necesarios
     *
     * @since 0.1.0
     * @return void
     */
    public static function register() {
        // Registrar el shortcode
        add_shortcode('rfq_cart', [__CLASS__, 'render']);
        
        // Hook para encolar CSS solo cuando el shortcode esté presente
        add_action('wp_head', [__CLASS__, 'maybe_enqueue_styles']);
    }

    /**
     * Renderiza el contenido del shortcode
     *
     * @since 0.1.0
     * @param array $atts Atributos del shortcode
     * @return string HTML del carrito RFQ
     */
    public static function render($atts = []) {
        // Verificar que WooCommerce esté disponible
        if (!function_exists('WC') || !WC()->cart) {
            return '<p class="rfq-cart-error">WooCommerce no está disponible.</p>';
        }

        $cart = WC()->cart;
        $cart_items = $cart->get_cart();

        // Si el carrito está vacío
        if (empty($cart_items)) {
            return self::render_empty_cart();
        }

        // Renderizar carrito con productos
        return self::render_cart_with_items($cart_items);
    }

    /**
     * Renderiza el carrito vacío
     *
     * @since 0.1.0
     * @return string HTML del carrito vacío
     */
    private static function render_empty_cart() {
        return '<div class="rfq-cart-container">
            <div class="rfq-cart-empty">
                <p class="rfq-cart-empty-message">Tu carrito está vacío</p>
                <p class="rfq-cart-empty-subtitle">Agrega productos para solicitar una cotización</p>
            </div>
        </div>';
    }

    /**
     * Renderiza el carrito con productos
     *
     * @since 0.1.0
     * @param array $cart_items Items del carrito
     * @return string HTML del carrito con productos
     */
    private static function render_cart_with_items($cart_items) {
        ob_start();
        ?>
        <div class="rfq-cart-container">
            <div class="rfq-cart-header">
                <div class="rfq-cart-header-product">PRODUCTO</div>
                <div class="rfq-cart-header-quantity">CANTIDAD</div>
            </div>
            
            <div class="rfq-cart-items">
                <?php foreach ($cart_items as $cart_item_key => $cart_item): ?>
                    <?php 
                    $product = $cart_item['data'];
                    $product_id = $cart_item['product_id'];
                    $quantity = $cart_item['quantity'];
                    $product_name = $product->get_name();
                    $product_image = wp_get_attachment_image_src(get_post_thumbnail_id($product_id), 'thumbnail');
                    $image_url = $product_image ? $product_image[0] : wc_placeholder_img_src();
                    ?>
                    
                    <div class="rfq-cart-item" data-cart-key="<?php echo esc_attr($cart_item_key); ?>">
                        <div class="rfq-cart-item-product">
                            <div class="rfq-cart-item-image">
                                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($product_name); ?>" />
                            </div>
                            <div class="rfq-cart-item-name">
                                <?php echo esc_html($product_name); ?>
                            </div>
                        </div>
                        
                        <div class="rfq-cart-item-controls">
                            <div class="rfq-cart-quantity-control">
                                <button type="button" class="rfq-cart-quantity-btn rfq-cart-quantity-minus" data-action="decrease">-</button>
                                <input type="number" 
                                       class="rfq-cart-quantity-input" 
                                       value="<?php echo esc_attr($quantity); ?>" 
                                       min="1" />
                                <button type="button" class="rfq-cart-quantity-btn rfq-cart-quantity-plus" data-action="increase">+</button>
                            </div>
                            <button type="button" class="rfq-cart-remove-item" data-action="remove" title="Eliminar producto">
                                <img src="https://thecleverdentist.com/wp-content/uploads/2025/07/cart_delete.png" alt="Eliminar" width="24" height="24" style="display:block;margin:auto;" />
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            
        </div>
        <div class="rfq-cart-actions">
                <button type="button" class="rfq-cart-submit-btn">
                    Finalizar solicitud →
                </button>
            </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Encola los estilos CSS solo si el shortcode está presente
     *
     * @since 0.1.0
     * @return void
     */
    public static function maybe_enqueue_styles() {
        global $post;
        // Encolar solo si el shortcode está presente en el contenido del post
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'rfq_cart')) {
            wp_enqueue_style(
                'rfq-cart-shortcode',
                plugin_dir_url((dirname(__DIR__))) . 'assets/css/rfq-cart.css',
                [],
                '1.0.0'
            );
            wp_enqueue_script(
                'rfq-cart-js',
                plugin_dir_url((dirname(__DIR__))) . 'assets/js/rfq-cart.js',
                ['jquery'],
                '1.0.0',
                true
            );
            wp_localize_script(
                'rfq-cart-js',
                'rfq_cart_data',
                [
                    'ajax_url'     => admin_url('admin-ajax.php'),
                    'nonce'        => wp_create_nonce('rfq_cart_ajax'),
                    'checkout_url' => wc_get_checkout_url()
                ]
            );
        }
    }
}
