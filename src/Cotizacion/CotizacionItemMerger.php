<?php
namespace GiVendor\GiPlugin\Cotizacion;

class CotizacionItemMerger
{
    /**
     * Fusiona los items base de la solicitud con los valores previos cotizados por el proveedor.
     *
     * @param array $solicitud_items  Items base de la solicitud
     * @param array $precios_guardados Array asociativo [product_id => [precio, iva, subtotal...]]
     * @return array Items con los valores previos integrados
     */
    public static function merge_with_previous_prices(array $solicitud_items, array $precios_guardados): array
    {
        foreach ($solicitud_items as &$item) {
            $pid = isset($item['product_id']) ? $item['product_id'] : (isset($item['ID']) ? $item['ID'] : null);
            if ($pid && isset($precios_guardados[$pid])) {
                $cot = $precios_guardados[$pid];
                $item['precio'] = isset($cot['precio']) ? $cot['precio'] : '';
                $item['iva'] = isset($cot['iva']) ? $cot['iva'] : '';
                $item['subtotal'] = isset($cot['subtotal']) ? $cot['subtotal'] : '';
                $item['original_price'] = isset($cot['precio']) ? $cot['precio'] : '';
            }
        }
        unset($item);
        return $solicitud_items;
    }
}
