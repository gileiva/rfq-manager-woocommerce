# Correcciones de Checkout RFQ - Resumen de Cambios

## Problemas identificados y solucionados

### 1. ❌ **PROBLEMA**: Totales no se muestran en checkout
**Causa:** Los precios de los items no se estaban estableciendo correctamente en la orden.
**Solución:** Modificación en `OfferOrderCreator.php`

```php
// ANTES: Solo se pasaban en el array de argumentos
$item_id = $order->add_product($product, $quantity, [
    'subtotal' => $subtotal,
    'total' => $subtotal,
]);

// DESPUÉS: Se establece explícitamente en el item tras crearlo
$item = $order->get_item($item_id);
if ($item) {
    $item->set_subtotal($subtotal);
    $item->set_total($subtotal);
    $item->save();
}
```

**Archivo:** `src/Order/OfferOrderCreator.php` - Líneas ~100-120
**Log:** `[RFQ-CHECKOUT] Precios establecidos en item`

### 2. ❌ **PROBLEMA**: Botón "Pagar" redirige a URL 404
**Causa:** JavaScript tenía fallback a URLs obsoletas `/pagar-cotizacion/`
**Solución:** Modificación en `rfq-manager.js`

```javascript
// ANTES: Fallback a URL obsoleta
var paymentUrl = window.location.origin + '/pagar-cotizacion/' + cotizacionId + '/';

// DESPUÉS: Error claro al usuario, sin fallback obsoleto
showToast('Error: No se pudo encontrar la orden de pago. Por favor, contacte con soporte.', true);
```

**Archivo:** `assets/js/rfq-manager.js` - Líneas ~200-250
**Log:** `[RFQ-PAGO] Click en botón pagar`, `[RFQ-PAGO] URL de pago obtenida`

### 3. ❌ **PROBLEMA**: Expiración de pago no implementada
**Causa:** Faltaba validación de las 24 horas límite para pago.
**Solución:** Implementación completa en `RFQCheckoutProtectionManager.php`

**Nuevos métodos agregados:**
- `validate_rfq_order_expiry()`: Validación durante proceso de pago
- `check_order_pay_expiry()`: Validación en páginas order-pay
- Mensaje visual con tiempo restante en checkout

**Archivo:** `src/Services/RFQCheckoutProtectionManager.php`
**Log:** `[RFQ-PROTECCION] ORDEN EXPIRADA`, `[RFQ-PROTECCION] Orden RFQ válida - Horas restantes: X`

## Implementación de expiración

### Flujo de expiración:
1. **Creación:** Orden se crea con meta `_rfq_order_acceptance_expiry` (timestamp + 24h)
2. **Validación:** Cada intento de pago verifica si timestamp actual > expiración
3. **Cancelación:** Si expiró, orden se cambia a `cancelled` automáticamente
4. **Redirección:** Usuario es redirigido a `/mis-solicitudes/` con mensaje de error

### Puntos de verificación:
- `woocommerce_checkout_process`: Antes de procesar pago
- `wp` action en order-pay: Al acceder a página de pago
- Mensaje visual en checkout con horas restantes

## Nuevos hooks registrados

```php
// En RFQCheckoutProtectionManager::init_hooks()
add_action('woocommerce_checkout_process', [self::class, 'validate_rfq_order_expiry']);
add_action('wp', [self::class, 'check_order_pay_expiry']);
```

## Mejoras visuales

### Mensaje dinámico en checkout:
- ⚠️ Orden no puede modificarse
- ⏰ Tiempo restante: X horas
- 🚫 ORDEN EXPIRADA (si aplica)

## Testing recomendado

### Caso 1: Totales en checkout
1. Aceptar oferta → Verificar que totales se muestran correctamente
2. Completar pago → Verificar que amounts en orden son correctos

### Caso 2: Botón "Pagar"
1. Aceptar oferta → No completar pago
2. Verificar botón "Pagar" aparece
3. Click en "Pagar" → Debe redirigir a checkout correcto

### Caso 3: Expiración de pago
1. Aceptar oferta → Esperar >24 horas (simular cambiando timestamp)
2. Intentar pagar → Debe mostrar error y cancelar orden
3. Verificar redirección a `/mis-solicitudes/`

## Archivos modificados

1. ✅ `src/Order/OfferOrderCreator.php` - Corrección totales checkout
2. ✅ `assets/js/rfq-manager.js` - Corrección botón "Pagar"
3. ✅ `src/Services/RFQCheckoutProtectionManager.php` - Implementación expiración

## Logs de debugging

- `[RFQ-CHECKOUT]`: Relacionados con precios en checkout
- `[RFQ-PAGO]`: Relacionados con botón y URLs de pago
- `[RFQ-PROTECCION]`: Relacionados con validaciones de expiración

---

**Fecha:** Agosto 7, 2025  
**Estado:** ✅ Implementado y listo para testing  
**Impacto:** Corrección de 3 problemas críticos del flujo de pago RFQ
