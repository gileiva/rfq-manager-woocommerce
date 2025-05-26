<?php
namespace GiVendor\GiPlugin\Email\Notifications\Custom;

class NotificationAjaxHandler {
    public static function init(): void {
        add_action('wp_ajax_preview_template', [self::class, 'handlePreviewTemplate']);
        add_action('wp_ajax_get_template_versions', [self::class, 'handleGetTemplateVersions']);
    }

    public static function handlePreviewTemplate(): void {
        check_ajax_referer('preview_template', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permiso denegado');
        }

        $role = sanitize_text_field($_POST['role']);
        $event = sanitize_text_field($_POST['event']);
        $template = wp_kses_post($_POST['template']);

        $notification_manager = NotificationManager::getInstance();
        $preview = $notification_manager->previewTemplate($role, $event, $template);

        wp_send_json_success($preview);
    }

    public static function handleGetTemplateVersions(): void {
        check_ajax_referer('get_template_versions', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permiso denegado');
        }

        $role = sanitize_text_field($_POST['role']);
        $event = sanitize_text_field($_POST['event']);

        $versions = get_option("rfq_notification_{$role}_{$event}_versions", []);
        
        $html = '<table class="wp-list-table widefat fixed striped">';
        $html .= '<thead><tr>';
        $html .= '<th>' . esc_html__('Versión', 'rfq-manager-woocommerce') . '</th>';
        $html .= '<th>' . esc_html__('Fecha', 'rfq-manager-woocommerce') . '</th>';
        $html .= '<th>' . esc_html__('Usuario', 'rfq-manager-woocommerce') . '</th>';
        $html .= '<th>' . esc_html__('Acciones', 'rfq-manager-woocommerce') . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($versions as $index => $version) {
            $user = get_userdata($version['user']);
            $html .= '<tr>';
            $html .= '<td>' . esc_html($index + 1) . '</td>';
            $html .= '<td>' . esc_html($version['date']) . '</td>';
            $html .= '<td>' . esc_html($user ? $user->display_name : 'N/A') . '</td>';
            $html .= '<td>';
            $html .= '<button class="button restore-version" data-version="' . esc_attr($index) . '">';
            $html .= esc_html__('Restaurar', 'rfq-manager-woocommerce');
            $html .= '</button>';
            $html .= '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        wp_send_json_success($html);
    }
} 