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
            'rfq-pending'  => 0,
            'rfq-active'   => 0,
            'rfq-accepted' => 0,
            'rfq-closed'   => 0,
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
        $output .= sprintf(
            '<button class="rfq-status-tab%s" data-status="">%s</button>',
            ($selected === '' ? ' active' : ''),
            esc_html__('Todos', 'rfq-manager-woocommerce')
        );
        foreach ($counts as $status => $count) {
            if ($count > 0) {
                $active = ($status === $selected) ? ' active' : '';
                $output .= sprintf(
                    '<button class="rfq-status-tab%s" data-status="%s">%s (%d)</button>',
                    $active,
                    esc_attr($status),
                    esc_html(self::get_status_label($status)),
                    $count
                );
            }
        }
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
            'rfq-pending'  => __('Pendiente de cotización', 'rfq-manager-woocommerce'),
            'rfq-active'   => __('Activa', 'rfq-manager-woocommerce'),
            'rfq-accepted' => __('Aceptada', 'rfq-manager-woocommerce'),
            'rfq-closed'   => __('Cerrada', 'rfq-manager-woocommerce'),
            'rfq-historic' => __('Histórica', 'rfq-manager-woocommerce'),
        ];
        return $labels[$status] ?? $status;
    }
}