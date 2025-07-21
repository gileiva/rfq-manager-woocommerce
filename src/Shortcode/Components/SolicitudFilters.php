<?php

namespace GiVendor\GiPlugin\Shortcode\Components;

class SolicitudFilters
{
    /**
     * Devuelve un array con el conteo de solicitudes por estado.
     *
     * @param int|null $user_id Si se pasa, filtra por autor.
     * @return array
     */
    public static function get_status_counts($user_id = null): array
    {
        $statuses = [
            // 'rfq-pending'  => 0,
            'rfq-active'   => 0,
            'rfq-accepted' => 0,
            // 'rfq-closed'   => 0,
            'rfq-historic' => 0
        ];

        $query_args = [
            'post_type'      => 'solicitud',
            'posts_per_page' => -1,
            'post_status'    => array_keys($statuses),
            'fields'         => 'ids'
        ];

        if ($user_id) {
            $query_args['author'] = $user_id;
        }

        $query = new \WP_Query($query_args);

        foreach ($query->posts as $post_id) {
            $status = get_post_status($post_id);
            if (isset($statuses[$status])) {
                $statuses[$status]++;
            }
        }

        return $statuses;
    }

    /**
     * Renderiza los tabs de filtro de estado como botones.
     *
     * @param array $counts Array de estados y cantidades.
     * @param string $selected Estado actualmente seleccionado.
     * @return string HTML de los tabs.
     */
    public static function render_status_tabs(array $counts, string $selected = ''): string
    {
        $output = '<div class="rfq-status-tabs">';
        // Mostrar solo los 4 filtros requeridos
        // 1. Todos
        $output .= sprintf(
            '<button class="rfq-status-tab%s" data-status="">%s</button>',
            ($selected === '' ? ' active' : ''),
            esc_html__('Todos', 'rfq-manager-woocommerce')
        );
        // 2. Activas
        if (isset($counts['rfq-active'])) {
            $active = ($selected === 'rfq-active') ? ' active' : '';
            $output .= sprintf(
                '<button class="rfq-status-tab%s" data-status="rfq-active">%s</button>',
                $active,
                esc_html(self::get_status_label('rfq-active'))
            );
        }
        // 3. Históricas
        if (isset($counts['rfq-historic'])) {
            $active = ($selected === 'rfq-historic') ? ' active' : '';
            $output .= sprintf(
                '<button class="rfq-status-tab%s" data-status="rfq-historic">%s</button>',
                $active,
                esc_html(self::get_status_label('rfq-historic'))
            );
        }
        // 4. Aceptadas
        if (isset($counts['rfq-accepted'])) {
            $active = ($selected === 'rfq-accepted') ? ' active' : '';
            $output .= sprintf(
                '<button class="rfq-status-tab%s" data-status="rfq-accepted">%s</button>',
                $active,
                esc_html(self::get_status_label('rfq-accepted'))
            );
        }
        // // Pendiente y Cerrada (comentados, no visibles)
        // if (isset($counts['rfq-pending'])) {
        //     $active = ($selected === 'rfq-pending') ? ' active' : '';
        //     $output .= sprintf(
        //         '<button class="rfq-status-tab%s" data-status="rfq-pending">%s</button>',
        //         $active,
        //         esc_html(self::get_status_label('rfq-pending'))
        //     );
        // }
        // if (isset($counts['rfq-closed'])) {
        //     $active = ($selected === 'rfq-closed') ? ' active' : '';
        //     $output .= sprintf(
        //         '<button class="rfq-status-tab%s" data-status="rfq-closed">%s</button>',
        //         $active,
        //         esc_html(self::get_status_label('rfq-closed'))
        //     );
        // }
        $output .= '</div>';
        return $output;
    }

    /**
     * Devuelve el label legible para un estado.
     *
     * @param string $status
     * @return string
     */
    private static function get_status_label(string $status): string
    {
        $labels = [
            'rfq-pending'  => __('Pendiente', 'rfq-manager-woocommerce'),
            'rfq-active'   => __('Activa', 'rfq-manager-woocommerce'),
            'rfq-historic' => __('Histórica', 'rfq-manager-woocommerce'),
            'rfq-accepted' => __('Aceptada', 'rfq-manager-woocommerce'),
            'rfq-closed'   => __('Cerrada', 'rfq-manager-woocommerce'),
        ];
        return $labels[$status] ?? $status;
    }

    /**
     * Renderiza el dropdown de orden.
     *
     * @param string $selected Valor seleccionado ('desc' o 'asc').
     * @return string HTML del dropdown
     */
    public static function render_order_dropdown(string $selected = ''): string
    {
        $options = [
            'desc' => esc_html__('Más recientes', 'rfq-manager-woocommerce'),
            'asc'  => esc_html__('Más antiguas', 'rfq-manager-woocommerce'),
        ];
        $output = '<select class="rfq-order-dropdown" name="rfq_order">';
        // Opción por defecto, siempre habilitada y seleccionada solo si $selected está vacío
        $output .= '<option value=""' . ($selected === '' ? ' selected' : '') . '>' . esc_html__('Ordenar por', 'rfq-manager-woocommerce') . '</option>';
        foreach ($options as $value => $label) {
            $is_selected = ($selected === $value) ? ' selected' : '';
            $output .= sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr($value),
                $is_selected,
                esc_html($label)
            );
        }
        $output .= '</select>';
        return $output;
    }

    /**
     * Renderiza la cabecera visual de filtros de solicitudes.
     *
     * @param array $counts Conteo de estados
     * @param string $selected_status Estado seleccionado
     * @param string $selected_order Orden seleccionado ('desc' o 'asc')
     * @return string HTML de la cabecera
     */
    public static function render_filter_header(array $counts, string $selected_status = '', string $selected_order = ''): string
    {
        $output  = '<div class="rfq-filters-wrapper">';
        $output .= '<div class="rfq-filters-left">';
        $output .= '<h4 class="rfq-section-title">' . esc_html__('Solicitudes', 'rfq-manager-woocommerce') . '</h4>';
        $output .= '</div>';
        $output .= '<div class="rfq-filters-right">';
        $output .= self::render_status_tabs($counts, $selected_status);
        $output .= '<div class="rfq-order-wrapper">' . self::render_order_dropdown($selected_order) . '</div>';
        $output .= '</div>';
        $output .= '</div>';
        return $output;
    }

    /**
     * Renderiza los tabs de filtro para proveedor/admin.
     *
     * @param string $selected Estado actualmente seleccionado.
     * @return string HTML de los tabs.
     */
    public static function render_provider_tabs(string $selected = ''): string
    {
        // Orden: Todas - Cotizadas - No cotizadas - Históricas - Aceptadas
        $tabs = [
            '' => __('Todas', 'rfq-manager-woocommerce'),
            'no-cotizadas' => __('No cotizadas', 'rfq-manager-woocommerce'),
            'cotizadas' => __('Cotizadas', 'rfq-manager-woocommerce'),
            // 'historicas' => __('Históricas', 'rfq-manager-woocommerce'),
            'aceptadas' => __('Aceptadas', 'rfq-manager-woocommerce'),
        ];
        $output = '<div class="rfq-status-tabs">';
        foreach ($tabs as $key => $label) {
            $active = ($selected === $key) ? ' active' : '';
            $output .= sprintf(
                '<button class="rfq-status-tab%s" data-status="%s">%s</button>',
                $active,
                esc_attr($key),
                esc_html($label)
            );
        }
        $output .= '</div>';
        return $output;
    }

    /**
     * Renderiza la cabecera visual de filtros para proveedor/admin.
     *
     * @param string $selected_status Estado seleccionado
     * @param string $selected_order Orden seleccionado ('desc' o 'asc')
     * @return string HTML de la cabecera
     */
    public static function render_provider_header(string $selected_status = '', string $selected_order = 'desc'): string
    {
        $output  = '<div class="rfq-filters-wrapper">';
        $output .= '<div class="rfq-filters-left">';
        $output .= '<h4 class="rfq-section-title">' . esc_html__('Solicitudes', 'rfq-manager-woocommerce') . '</h4>';
        $output .= '</div>';
        $output .= '<div class="rfq-filters-right">';
        $output .= self::render_provider_tabs($selected_status);
        $output .= '<div class="rfq-order-wrapper">' . self::render_order_dropdown($selected_order) . '</div>';
        $output .= '</div>';
        $output .= '</div>';
        return $output;
    }

    /**
     * Devuelve un array de IDs de solicitudes filtradas para un proveedor/admin según el filtro seleccionado.
     *
     * @param string $filtro Filtro seleccionado: 'cotizadas', 'no-cotizadas', 'historicas', 'aceptadas', ''
     * @param int $proveedor_id ID del usuario proveedor
     * @return array Array de IDs de solicitudes
     */
    public static function get_provider_filtered_solicitudes(string $filtro, int $proveedor_id): array
    {
        // Estados de solicitudes visibles para proveedores - solo pendientes y activas
        $visible_statuses = ['rfq-pending', 'rfq-active'];

        // Todas las solicitudes visibles (pendientes y activas)
        $all_solicitudes = get_posts([
            'post_type'      => 'solicitud',
            'post_status'    => $visible_statuses,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        // Todas las cotizaciones del proveedor
        $cotizaciones = get_posts([
            'post_type'      => 'cotizacion',
            'post_status'    => ['publish', 'rfq-accepted', 'rfq-historic'],
            'posts_per_page' => -1,
            'author'         => $proveedor_id,
            'fields'         => 'ids',
        ]);
        $cotizadas = [];
        $aceptadas = [];
        $historicas_cot = [];
        foreach ($cotizaciones as $cotizacion_id) {
            $solicitud_id = get_post_meta($cotizacion_id, '_solicitud_parent', true);
            if ($solicitud_id) {
                $cotizadas[] = $solicitud_id;
                $status = get_post_status($cotizacion_id);
                if ($status === 'rfq-accepted') {
                    $aceptadas[] = $solicitud_id;
                }
                if ($status === 'rfq-historic') {
                    $historicas_cot[] = $solicitud_id;
                }
            }
        }
        $cotizadas = array_unique($cotizadas);
        $aceptadas = array_unique($aceptadas);
        $historicas_cot = array_unique($historicas_cot);

        switch ($filtro) {
            case 'cotizadas':
                // Solicitudes con al menos una cotización del proveedor
                return array_values(array_intersect($all_solicitudes, $cotizadas));
            case 'no-cotizadas':
                // Solo solicitudes pendientes y activas sin cotización del proveedor
                $open_solicitudes = get_posts([
                    'post_type'      => 'solicitud',
                    'post_status'    => ['rfq-pending', 'rfq-active'],
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                ]);
                $no_cotizadas = array_diff($open_solicitudes, $cotizadas);
                return array_values($no_cotizadas);
            case 'aceptadas':
                // Solo solicitudes donde el proveedor ganó
                return self::get_solicitudes_ganadas_por_proveedor($proveedor_id);
            case '':
            default:
                // Solicitudes pendientes y activas + aceptadas donde el proveedor ganó
                $base_solicitudes = get_posts([
                    'post_type'      => 'solicitud',
                    'post_status'    => ['rfq-pending', 'rfq-active'],
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                ]);
                
                // Agregar solicitudes aceptadas donde el proveedor ganó
                $solicitudes_ganadas = self::get_solicitudes_ganadas_por_proveedor($proveedor_id);
                
                return array_values(array_unique(array_merge($base_solicitudes, $solicitudes_ganadas)));
        }
    }

    /**
     * Filtra un array de IDs de solicitudes por estado.
     *
     * @param array $ids
     * @param string $status
     * @return array
     */
    private static function filter_by_status(array $ids, string $status): array
    {
        $filtered = [];
        foreach ($ids as $id) {
            if (get_post_status($id) === $status) {
                $filtered[] = $id;
            }
        }
        return $filtered;
    }

    /**
     * Obtiene solicitudes donde el proveedor específico ganó (tiene cotización aceptada).
     *
     * @param int $proveedor_id ID del usuario proveedor
     * @return array Array de IDs de solicitudes
     */
    private static function get_solicitudes_ganadas_por_proveedor(int $proveedor_id): array
    {
        $solicitudes_aceptadas = get_posts([
            'post_type'      => 'solicitud', 
            'post_status'    => 'rfq-accepted',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        
        $ganadas = [];
        foreach ($solicitudes_aceptadas as $solicitud_id) {
            $cotizacion_ganadora = get_posts([
                'post_type'      => 'cotizacion',
                'post_status'    => 'rfq-accepted',
                'posts_per_page' => 1,
                'author'         => $proveedor_id,
                'meta_query'     => [
                    [
                        'key'   => '_solicitud_parent',
                        'value' => $solicitud_id,
                        'compare' => '=',
                        'type' => 'NUMERIC',
                    ],
                ],
                'fields' => 'ids',
            ]);
            
            if (!empty($cotizacion_ganadora)) {
                $ganadas[] = $solicitud_id;
            }
        }
        
        return $ganadas;
    }
}