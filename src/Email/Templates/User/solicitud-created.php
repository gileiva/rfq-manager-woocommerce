<?php
/**
 * Plantilla de email para usuario cuando se crea una solicitud
 *
 * @package    GiVendor\GiPlugin\Email\Templates\User
 * @since      0.1.0
 *
 * Variables disponibles:
 * $solicitud_id: ID de la solicitud
 * $user: Objeto de usuario
 * $data: Datos adicionales de la solicitud
 */

// Asegurar que no se accede directamente
if (!defined('ABSPATH')) {
    exit;
}

// Obtener detalles adicionales de la solicitud
$solicitud_title = get_the_title($solicitud_id);
$solicitud_uuid = get_post_meta($solicitud_id, '_solicitud_uuid', true);
$solicitud_date = get_post_meta($solicitud_id, '_solicitud_date', true);
$solicitud_expiry = get_post_meta($solicitud_id, '_solicitud_expiry', true);
$items = json_decode(get_post_meta($solicitud_id, '_solicitud_items', true), true);

// URL para ver la solicitud
$view_url = home_url('ver-solicitud/' . get_post_field('post_name', $solicitud_id));

// Formato de fecha localizado
$date_format = get_option('date_format') . ' ' . get_option('time_format');
$formatted_date = date_i18n($date_format, strtotime($solicitud_date));
$formatted_expiry = date_i18n($date_format, strtotime($solicitud_expiry));
?>

<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title><?php echo esc_html(get_bloginfo('name')); ?></title>
    <style type="text/css">
        body {
            background-color: #f7f7f7;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
            border: 1px solid #e5e5e5;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e5e5e5;
        }
        .content {
            color: #555555;
            line-height: 1.5;
        }
        .solicitud-info {
            background-color: #f9f9f9;
            padding: 15px;
            margin: 20px 0;
            border-left: 3px solid #23282d;
        }
        .solicitud-info p {
            margin: 5px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .items-table th {
            text-align: left;
            background-color: #f2f2f2;
            padding: 8px;
            border: 1px solid #ddd;
        }
        .items-table td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        .button {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            margin: 20px 0;
            border-radius: 3px;
            text-align: center;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            color: #999999;
            font-size: 12px;
            border-top: 1px solid #e5e5e5;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo esc_html(get_bloginfo('name')); ?></h1>
        </div>
        
        <div class="content">
            <p><?php echo sprintf(esc_html__('Hola %s,', 'rfq-manager-woocommerce'), esc_html($user->display_name)); ?></p>
            
            <p><?php echo esc_html__('¡Tu solicitud de cotización ha sido recibida correctamente!', 'rfq-manager-woocommerce'); ?></p>
            
            <p><?php echo esc_html__('Estamos revisando tu solicitud y pronto recibirás cotizaciones de nuestros proveedores. Te notificaremos cuando haya actualizaciones importantes.', 'rfq-manager-woocommerce'); ?></p>
            
            <div class="solicitud-info">
                <h3><?php echo esc_html__('Detalles de tu solicitud:', 'rfq-manager-woocommerce'); ?></h3>
                <p><strong><?php echo esc_html__('Número de solicitud:', 'rfq-manager-woocommerce'); ?></strong> <?php echo esc_html($solicitud_uuid); ?></p>
                <p><strong><?php echo esc_html__('Fecha de creación:', 'rfq-manager-woocommerce'); ?></strong> <?php echo esc_html($formatted_date); ?></p>
                <p><strong><?php echo esc_html__('Válida hasta:', 'rfq-manager-woocommerce'); ?></strong> <?php echo esc_html($formatted_expiry); ?></p>
            </div>
            
            <?php if (!empty($items)) : ?>
                <h3><?php echo esc_html__('Productos solicitados:', 'rfq-manager-woocommerce'); ?></h3>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Producto', 'rfq-manager-woocommerce'); ?></th>
                            <th><?php echo esc_html__('Cantidad', 'rfq-manager-woocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item) : ?>
                            <tr>
                                <td><?php echo esc_html($item['name']); ?></td>
                                <td><?php echo esc_html($item['qty']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <p><?php echo esc_html__('Puedes ver todos los detalles de tu solicitud y las cotizaciones recibidas en el siguiente enlace:', 'rfq-manager-woocommerce'); ?></p>
            
            <a href="<?php echo esc_url($view_url); ?>" class="button"><?php echo esc_html__('Ver mi solicitud', 'rfq-manager-woocommerce'); ?></a>
            
            <p><?php echo esc_html__('Si tienes alguna pregunta o necesitas asistencia, no dudes en contactarnos.', 'rfq-manager-woocommerce'); ?></p>
            
            <p><?php echo esc_html__('¡Gracias por confiar en nosotros!', 'rfq-manager-woocommerce'); ?></p>
        </div>
        
        <div class="footer">
            <p><?php echo esc_html__('Este es un mensaje automático, por favor no responda a este correo.', 'rfq-manager-woocommerce'); ?></p>
            <p>&copy; <?php echo esc_html(date('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. <?php echo esc_html__('Todos los derechos reservados.', 'rfq-manager-woocommerce'); ?></p>
        </div>
    </div>
</body>
</html> 