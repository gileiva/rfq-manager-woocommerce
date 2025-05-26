<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title><?php echo esc_html($site_name); ?></title>
</head>
<body style="background-color: #f7f7f7; padding: 20px; font-family: Arial, sans-serif;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border: 1px solid #e5e5e5;">
        <div style="text-align: center; margin-bottom: 20px;">
            <h1 style="color: #3c3c3c; margin: 0; padding: 0;"><?php echo esc_html($site_name); ?></h1>
            <p style="color: #666; margin: 10px 0 0 0;"><?php echo esc_html(sprintf(__('Notificación de %s', 'rfq-manager-woocommerce'), $site_name)); ?></p>
        </div>
        <div style="color: #5d5d5d; font-size: 15px; line-height: 22px;">
