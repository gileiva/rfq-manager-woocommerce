<?php
/**
 * Template para mostrar una cotización individual
 *
 * @package    GiVendor\GiPlugin\Templates
 * @since      0.1.0
 */

defined('ABSPATH') || exit;

get_header();
?>

<main class="rfq-single-container">
    <article <?php post_class('rfq-cotizacion'); ?>>
        <header class="rfq-header">
            <h1 class="rfq-title"><?php echo esc_html(get_the_title()); ?></h1>
        </header>

        <div class="rfq-content">
            <?php 
            echo '<div class="rfq-notice">' . esc_html__('Esta es una cotización enviada. El acceso principal es desde la solicitud correspondiente.', 'rfq-manager-woocommerce') . '</div>';
            ?>
        </div>
    </article>
</main>

<?php
get_footer(); 