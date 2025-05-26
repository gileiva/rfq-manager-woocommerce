<?php
/**
 * AjaxHandler - Maneja las peticiones AJAX del plugin
 *
 * @package    GiVendor\GiPlugin\Ajax
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Ajax;

/**
 * AjaxHandler - Clase que maneja las peticiones AJAX
 *
 * @package    GiVendor\GiPlugin\Ajax
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class AjaxHandler {
    
    /**
     * Inicializa el manejador AJAX
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        // Acciones para usuarios autenticados
        add_action('wp_ajax_update_solicitud_status', [self::class, 'update_solicitud_status']);
        add_action('wp_ajax_update_solicitud_expiry', [self::class, 'update_solicitud_expiry']);
    }

    /**
     * Actualiza el estado de una solicitud
     *
     * @since  0.1.0
     * @return void
     */
    public static function update_solicitud_status(): void {
        // Agregar log al inicio de la función para verificar la llamada
        error_log('[RFQ] Llamada AJAX para actualizar estado de solicitud recibida.');

        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'rfq_solicitud_status_nonce')) {
            wp_send_json_error(['message' => __('Nonce inválido', 'rfq-manager-woocommerce')]);
        }

        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permisos insuficientes', 'rfq-manager-woocommerce')]);
        }

        // Obtener y validar datos
        $solicitud_id = isset($_POST['solicitud_id']) ? absint($_POST['solicitud_id']) : 0;
        $new_status = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';

        if (!$solicitud_id || !$new_status) {
            wp_send_json_error(['message' => __('Datos inválidos', 'rfq-manager-woocommerce')]);
        }

        // Verificar que la solicitud existe
        $solicitud = get_post($solicitud_id);
        if (!$solicitud || $solicitud->post_type !== 'solicitud') {
            wp_send_json_error(['message' => __('Solicitud no encontrada', 'rfq-manager-woocommerce')]);
        }

        // Verificar que el estado es válido
        $valid_statuses = ['rfq-pending', 'rfq-active', 'rfq-accepted', 'rfq-historic'];
        if (!in_array($new_status, $valid_statuses, true)) {
            wp_send_json_error(['message' => __('Estado no válido', 'rfq-manager-woocommerce')]);
        }

        // Actualizar el estado
        $update_result = wp_update_post([
            'ID' => $solicitud_id,
            'post_status' => $new_status
        ], true);

        if (is_wp_error($update_result)) {
            wp_send_json_error([
                'message' => $update_result->get_error_message()
            ]);
        }

        // Limpiar caché
        clean_post_cache($solicitud_id);

        // Registrar en el log
        error_log(sprintf('[RFQ] Estado de solicitud #%d actualizado a %s vía AJAX', $solicitud_id, $new_status));

        wp_send_json_success([
            'message' => __('Estado actualizado correctamente', 'rfq-manager-woocommerce'),
            'new_status' => $new_status
        ]);
    }

    /**
     * Actualiza la fecha de expiración de una solicitud
     *
     * @since  0.1.0
     * @return void
     */
    public static function update_solicitud_expiry(): void {
        // Agregar log al inicio de la función para verificar la llamada
        error_log('[RFQ] Llamada AJAX para actualizar fecha de expiración de solicitud recibida.');

        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'rfq_solicitud_status_nonce')) {
            wp_send_json_error(['message' => __('Nonce inválido', 'rfq-manager-woocommerce')]);
        }

        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permisos insuficientes', 'rfq-manager-woocommerce')]);
        }

        // Obtener y validar datos
        $solicitud_id = isset($_POST['solicitud_id']) ? absint($_POST['solicitud_id']) : 0;
        $expiry_date = isset($_POST['expiry_date']) ? sanitize_text_field($_POST['expiry_date']) : '';

        if (!$solicitud_id || !$expiry_date) {
            wp_send_json_error(['message' => __('Datos inválidos', 'rfq-manager-woocommerce')]);
        }

        // Verificar que la solicitud existe
        $solicitud = get_post($solicitud_id);
        if (!$solicitud || $solicitud->post_type !== 'solicitud') {
            wp_send_json_error(['message' => __('Solicitud no encontrada', 'rfq-manager-woocommerce')]);
        }

        // Actualizar la fecha de expiración
        update_post_meta($solicitud_id, '_solicitud_expiry', $expiry_date);

        // Reprogramar el cambio de estado si es necesario
        if (in_array($solicitud->post_status, ['rfq-pending', 'rfq-active'])) {
            \GiVendor\GiPlugin\Solicitud\Scheduler\StatusScheduler::schedule_change_to_historic($solicitud_id);
        }

        // Registrar en el log
        error_log(sprintf('[RFQ] Fecha de expiración de solicitud #%d actualizada a %s vía AJAX', $solicitud_id, $expiry_date));

        wp_send_json_success([
            'message' => __('Fecha de expiración actualizada correctamente', 'rfq-manager-woocommerce'),
            'new_expiry' => $expiry_date
        ]);
    }
} 