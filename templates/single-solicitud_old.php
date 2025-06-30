<?php
/**
 * Template para mostrar una solicitud individual
 *
 * @package    GiVendor\GiPlugin\Templates
 * @since      0.1.0
 */

defined('ABSPATH') || exit;

get_header();
?>

<main class="rfq-single-container">
    <article <?php post_class('rfq-solicitud'); ?>>
        <header class="rfq-header">
            <h1 class="rfq-title"><?php echo esc_html__('Solicitud de ', 'rfq-manager-woocommerce') . esc_html(get_the_author_meta('display_name', get_post_field('post_author', get_the_ID()))); ?></h1>
        </header>

        <div class="rfq-content">
            <?php 
            if (is_user_logged_in() && in_array('proveedor', (array)wp_get_current_user()->roles, true)) {
                // Vista especial para proveedores: formulario de cotización
                echo do_shortcode('[rfq_cotizar]');
            } else {
                // Vista para usuario/customer: detalles de la solicitud y cotizaciones recibidas
                echo do_shortcode('[rfq_view_solicitud]');
            }
            ?>
        </div>
    </article>
</main>

<?php
get_footer(); 