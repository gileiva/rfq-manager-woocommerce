<?php
namespace GiVendor\GiPlugin\Solicitud\View;

if (!defined('ABSPATH')) exit;

class SolicitudItemsRenderer
{
    /**
     * Renderiza el bloque de productos de una solicitud en modo lectura o formulario.
     *
     * @param \WP_Post $solicitud
     * @param array $items
     * @param string $modo 'lectura' | 'formulario'
     * @param array $options
     * @return string
     */
    public static function render(\WP_Post $solicitud, array $items, string $modo = 'lectura', array $options = []): string
    {
        if (empty($items)) {
            return '<div class="rfq-productos-wrapper rfq-productos-vacia">No hay productos en la solicitud.</div>';
        }

        ob_start();
        ?>
        <div class="rfq-productos-wrapper">
            <div class="rfq-productos-header-row">
                <div class="rfq-productos-header-col rfq-productos-header-producto">Producto</div>
                <div class="rfq-productos-header-col rfq-productos-header-cantidad">Cantidad</div>
                <?php if ($modo === 'formulario'): ?>
                    <div class="rfq-productos-header-col rfq-productos-header-precio">Precio unidad</div>
                    <div class="rfq-productos-header-col rfq-productos-header-iva">IVA</div>
                    <div class="rfq-productos-header-col rfq-productos-header-subtotal">Subtotal</div>
                <?php endif; ?>
            </div>
            <?php foreach ($items as $item):
                $product_id = isset($item['product_id']) ? $item['product_id'] : (isset($item['ID']) ? $item['ID'] : null);
                $product_name = esc_html($item['name'] ?? '');
                // Obtener imagen real de producto si no viene en $item['image']
                if (!empty($item['image'])) {
                    $product_img = $item['image'];
                } else {
                    $product_img = '';
                    if ($product_id) {
                        if (function_exists('wc_get_product')) {
                            $wc_product = wc_get_product($product_id);
                            if ($wc_product && method_exists($wc_product, 'get_image_id')) {
                                $img_id = $wc_product->get_image_id();
                                if ($img_id) {
                                    $product_img = wp_get_attachment_image_url($img_id, 'thumbnail');
                                }
                            }
                        }
                    }
                }
                $cantidad = esc_html($item['quantity'] ?? $item['qty'] ?? '');
                $precio = $item['precio'] ?? '';
                $iva = $item['iva'] ?? '';
                $subtotal = $item['subtotal'] ?? '';
                $original_price = $item['original_price'] ?? ($options['original_price'][$product_id] ?? '');
            ?>
            <div class="rfq-producto-row">
                <div class="rfq-producto-col rfq-producto-col-producto">
                    <span class="rfq-producto-thumb">
                        <?php if ($product_img): ?>
                            <img src="<?php echo esc_url($product_img); ?>" alt="<?php echo $product_name; ?>" />
                        <?php else: ?>
                            <span class="rfq-producto-thumb-placeholder"></span>
                        <?php endif; ?>
                    </span>
                    <span class="rfq-producto-nombre"><?php echo $product_name; ?></span>
                </div>
                <div class="rfq-producto-col rfq-producto-col-cantidad">
                    <?php echo $cantidad; ?>
                </div>
                <?php if ($modo === 'formulario'): ?>
                    <div class="rfq-producto-col rfq-producto-col-precio">
                        <input type="number" step="0.01" min="0" name="precios[<?php echo esc_attr($product_id); ?>]" value="<?php echo esc_attr($precio); ?>" class="rfq-input-precio" <?php if ($original_price) echo 'data-original-price="' . esc_attr($original_price) . '"'; ?> />
                    </div>
                    <div class="rfq-producto-col rfq-producto-col-iva">
                        <select name="iva[<?php echo esc_attr($product_id); ?>]" class="rfq-input-iva" required>
                            <option value="" <?php selected($iva, ''); ?>>-</option>
                            <option value="4" <?php selected($iva, '4'); ?>>4%</option>
                            <option value="10" <?php selected($iva, '10'); ?>>10%</option>
                            <option value="21" <?php selected($iva, '21'); ?>>21%</option>
                        </select>
                    </div>
                    <div class="rfq-producto-col rfq-producto-col-subtotal">
                        <output class="rfq-output-subtotal" for="precios[<?php echo esc_attr($product_id); ?>],iva[<?php echo esc_attr($product_id); ?>]">
                            <?php 
                            if ($subtotal) {
                                // Mostrar subtotal con símbolo de moneda
                                $currency_symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '€';
                                echo esc_html($subtotal) . ' ' . $currency_symbol;
                            } else {
                                echo esc_html($subtotal);
                            }
                            ?>
                        </output>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
