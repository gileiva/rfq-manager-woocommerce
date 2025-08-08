<?php
/**
 * Manejador de cotizaciones
 *
 * @package    GiVendor\GiPlugin\Cotizacion
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Cotizacion;

use GiVendor\GiPlugin\Cotizacion\Creator\CotizacionCreator;

/**
 * CotizacionHandler - Maneja el procesamiento de cotizaciones
 *
 * Esta clase se encarga de procesar y validar las cotizaciones enviadas.
 *
 * @package    GiVendor\GiPlugin\Cotizacion
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class CotizacionHandler {
    
    /**
     * Inicializa el manejador
     *
     * @since  0.1.0
     * @return void
     */
    public static function init(): void {
        add_action('admin_post_submit_cotizacion', [self::class, 'handle_submit_cotizacion']);
        add_action('admin_post_nopriv_submit_cotizacion', [self::class, 'handle_redirect_login']);
    }

    /**
     * Procesa el envío del formulario de cotización
     *
     * @since  0.1.0
     * @return void
     */
    public static function handle_submit_cotizacion(): void {
        // Verificar nonce
        if (!isset($_POST['rfq_cotizar_nonce']) || !wp_verify_nonce($_POST['rfq_cotizar_nonce'], 'rfq_cotizar_nonce')) {
            wp_die(__('Error de seguridad. Por favor, intenta nuevamente.', 'rfq-manager-woocommerce'));
        }

        // Verificar permisos
        if (!self::check_permissions()) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'rfq-manager-woocommerce'));
        }

        // Obtener y validar datos
        $solicitud_id = isset($_POST['solicitud_id']) ? absint($_POST['solicitud_id']) : 0;
        if (!$solicitud_id) {
            wp_die(__('ID de solicitud inválido.', 'rfq-manager-woocommerce'));
        }

        // Validar solicitud (incluyendo expiración)
        $solicitud = self::validate_solicitud($solicitud_id);
        if (is_wp_error($solicitud)) {
            // Redirigir con mensaje de error en lugar de wp_die para mejor UX
            $redirect_url = add_query_arg([
                'rfq_error' => $solicitud->get_error_code(),
                'rfq_message' => urlencode($solicitud->get_error_message())
            ], wp_get_referer() ?: get_permalink());
            wp_redirect($redirect_url);
            exit;
        }

        // Verificar si ya existe una cotización del mismo proveedor para esta solicitud
        $existing_cotizacion = get_posts([
            'post_type'      => 'cotizacion',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_solicitud_parent',
                    'value' => $solicitud_id,
                ],
            ],
            'author'         => get_current_user_id(),
        ]);

        // Procesar precios y calcular total
        $precios = self::process_precios($solicitud_id, !empty($existing_cotizacion));
        if (is_wp_error($precios)) {
            // En lugar de wp_die, redirigir con parámetros de error
            $redirect_url = add_query_arg([
                'rfq_error' => $precios->get_error_code(),
                'rfq_message' => urlencode($precios->get_error_message())
            ], wp_get_referer() ?: get_permalink());
            wp_redirect($redirect_url);
            exit;
        }

        // Crear o actualizar cotización
        if (!empty($existing_cotizacion)) {
            $cotizacion_id = self::update_cotizacion($existing_cotizacion[0]->ID, $precios['precio_items'], $precios['total']);
            $is_update = true;
        } else {
            $cotizacion_id = CotizacionCreator::create(
                $solicitud_id,
                $precios['precio_items'],
                $precios['total']
            );
            $is_update = false;
        }

        if (!$cotizacion_id) {
            wp_die(__('Error al procesar la cotización.', 'rfq-manager-woocommerce'));
        }

        // Notificar
        self::send_notifications($cotizacion_id, $solicitud_id);

        // Redirección tras envío exitoso de oferta por proveedor
        // Solo para usuarios autenticados y ofertas procesadas exitosamente
        if (is_user_logged_in() && !headers_sent()) {
            $redirect_url = site_url('oferta-creada-gracias/');
            wp_redirect($redirect_url);
            exit;
        }

        // Fallback: redirigir con parámetro de éxito (mantener lógica anterior)
        wp_redirect(add_query_arg('cotizacion_sent', 'true', wp_get_referer()));
        exit;
    }

    /**
     * Verifica los permisos del usuario
     *
     * @since  0.1.0
     * @return bool
     */
    private static function check_permissions(): bool {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        return in_array('proveedor', $user->roles);
    }

    /**
     * Valida la solicitud
     *
     * @since  0.1.0
     * @param  int         $solicitud_id ID de la solicitud
     * @return \WP_Post|\WP_Error
     */
    private static function validate_solicitud(int $solicitud_id) {
        $solicitud = get_post($solicitud_id);
        
        if (!$solicitud || $solicitud->post_type !== 'solicitud') {
            return new \WP_Error('not_found', __('La solicitud no existe.', 'rfq-manager-woocommerce'));
        }

        // Verificar estado de la solicitud
        if (!in_array($solicitud->post_status, ['rfq-pending', 'rfq-active'])) {
            return new \WP_Error('not_available', __('Esta solicitud ya no está disponible para cotizar.', 'rfq-manager-woocommerce'));
        }

        // VALIDACIÓN CRÍTICA DE EXPIRACIÓN
        $expiry_date = get_post_meta($solicitud_id, '_solicitud_expiry', true);
        if (!empty($expiry_date)) {
            $expiry_timestamp = strtotime($expiry_date);
            $current_timestamp = current_time('timestamp');
            
            if ($expiry_timestamp && $current_timestamp >= $expiry_timestamp) {
                error_log(sprintf(
                    '[RFQ-SECURITY] Intento de cotización bloqueado por expiración - Solicitud: %d, Expiró: %s, Actual: %s',
                    $solicitud_id,
                    date('Y-m-d H:i:s', $expiry_timestamp),
                    date('Y-m-d H:i:s', $current_timestamp)
                ));
                return new \WP_Error('expired', __('Esta solicitud ha expirado y no puede recibir más cotizaciones.', 'rfq-manager-woocommerce'));
            }
        }

        // Verificación adicional: si el estado es histórico por alguna razón
        if ($solicitud->post_status === 'rfq-historic') {
            return new \WP_Error('expired', __('Esta solicitud ha expirado y no puede recibir más cotizaciones.', 'rfq-manager-woocommerce'));
        }

        return $solicitud;
    }

    /**
     * Actualiza una cotización existente
     *
     * @since  0.1.0
     * @param  int    $cotizacion_id ID de la cotización
     * @param  array  $precio_items  Array de precios por producto
     * @param  float  $total         Total de la cotización
     * @return int|false
     */
    private static function update_cotizacion(int $cotizacion_id, array $precio_items, float $total) {
        // Actualizar los meta datos
        update_post_meta($cotizacion_id, '_precio_items', $precio_items);
        update_post_meta($cotizacion_id, '_total', $total);

        // Registrar acción
        do_action('rfq_cotizacion_updated', $cotizacion_id, $precio_items);

        return $cotizacion_id;
    }

    /**
     * Procesa los precios enviados
     *
     * @since  0.1.0
     * @param  int    $solicitud_id ID de la solicitud
     * @param  bool   $is_update    Si es una actualización de cotización existente
     * @return array|\WP_Error
     */
    private static function process_precios(int $solicitud_id, bool $is_update = false): array|\WP_Error {
        $precio_items = [];
        $total = 0.0;
        $items = json_decode(get_post_meta($solicitud_id, '_solicitud_items', true), true);
        
        if (!is_array($items)) {
            return new \WP_Error('invalid_items', __('Los items de la solicitud no son válidos', 'rfq-manager-woocommerce'));
        }

        // Si es una actualización, obtener los precios actuales
        $current_prices = [];
        if ($is_update) {
            $existing_cotizacion = get_posts([
                'post_type'      => 'cotizacion',
                'posts_per_page' => 1,
                'meta_query'     => [
                    [
                        'key'   => '_solicitud_parent',
                        'value' => $solicitud_id,
                    ],
                ],
                'author'         => get_current_user_id(),
            ]);

            if (!empty($existing_cotizacion)) {
                $current_prices = get_post_meta($existing_cotizacion[0]->ID, '_precio_items', true);
            }
        }

        foreach ($items as $item) {
            $product_id = absint($item['product_id']);
            $qty = absint($item['qty']);
            
            if (!isset($_POST['precios'][$product_id]) || !isset($_POST['iva'][$product_id])) {
                continue;
            }

            $precio = floatval($_POST['precios'][$product_id]);
            $iva = $_POST['iva'][$product_id];
            
            if ($precio <= 0) {
                continue;
            }

            // Validar que el IVA esté seleccionado
            if (empty($iva) || !in_array($iva, ['4', '10', '21'])) {
                return new \WP_Error(
                    'missing_tax',
                    __('Debe seleccionar un tipo de IVA para todos los productos.', 'rfq-manager-woocommerce')
                );
            }

            $iva = floatval($iva);

            // Validar que el nuevo precio no sea mayor que el actual
            if ($is_update && isset($current_prices[$product_id])) {
                $current_price = floatval($current_prices[$product_id]['precio']);
                if ($precio > $current_price) {
                    return new \WP_Error(
                        'invalid_price',
                        sprintf(
                            __('El precio para %s no puede ser mayor que el precio actual (%s)', 'rfq-manager-woocommerce'),
                            $item['name'],
                            wc_price($current_price)
                        )
                    );
                }
            }

            $subtotal_sin_iva = $precio * $qty;
            $iva_amount = $subtotal_sin_iva * ($iva / 100);
            $subtotal = $subtotal_sin_iva + $iva_amount;

            $precio_items[$product_id] = [
                'precio' => $precio,
                'iva' => $iva,
                'qty' => $qty,
                'subtotal_sin_iva' => $subtotal_sin_iva,
                'iva_amount' => $iva_amount,
                'subtotal' => $subtotal
            ];

            $total += $subtotal;
        }

        if (empty($precio_items)) {
            return new \WP_Error('no_prices', __('Debe proporcionar al menos un precio válido', 'rfq-manager-woocommerce'));
        }

        return [
            'precio_items' => $precio_items,
            'total' => $total
        ];
    }

    /**
     * Envía notificaciones
     *
     * @since  0.1.0
     * @param  int    $cotizacion_id ID de la cotización
     * @param  int    $solicitud_id  ID de la solicitud
     * @return void
     */
    private static function send_notifications(int $cotizacion_id, int $solicitud_id): void {
        error_log("[RFQ-FLOW] Iniciando send_notifications - Cotización: {$cotizacion_id}, Solicitud: {$solicitud_id}");
        
        $solicitud = get_post($solicitud_id);
        $cotizacion = get_post($cotizacion_id);
        
        if (!$solicitud || !$cotizacion) {
            error_log("[RFQ-ERROR] No se pudieron encontrar solicitud o cotización para enviar notificaciones");
            return;
        }

        error_log("[RFQ-FLOW] Disparando acción 'rfq_cotizacion_submitted' para notificaciones");
        
        // Disparar acción para que las clases de notificación puedan actuar
        do_action('rfq_cotizacion_submitted', $cotizacion_id, $solicitud_id);
        
        error_log("[RFQ-FLOW] Acción 'rfq_cotizacion_submitted' disparada con éxito");
    }

    /**
     * Redirige a los usuarios no logueados a la página de inicio de sesión
     *
     * @since  0.1.0
     * @return void
     */
    public static function handle_redirect_login(): void {
        wp_redirect(wp_login_url(wp_get_referer()));
        exit;
    }
} 