# RFQ Checkout Protection System - Documentación

## Resumen
Sistema de protección completo para el proceso de checkout de ofertas RFQ aceptadas, implementado siguiendo principios SRP y buenas prácticas de WordPress/WooCommerce.

## Componentes Implementados

### 1. RFQCheckoutProtectionManager
**Ubicación:** `src/Services/RFQCheckoutProtectionManager.php`

**Responsabilidades:**
- Validación de integridad de órdenes RFQ antes del pago
- Bloqueo de controles de edición en páginas order-pay
- Redirección automática post-pago a página /gracias
- Logging de seguridad con prefijo [RFQ-PROTECCION]

**Métodos principales:**
- `validate_rfq_order_integrity()`: Valida que productos, cantidades y precios no hayan sido manipulados
- `redirect_rfq_thankyou()`: Redirección automática tras pago exitoso de orden RFQ  
- `enqueue_protection_styles()`: Inyecta CSS para bloquear controles de edición
- `disable_order_pay_editing()`: Remueve hooks de edición para órdenes RFQ

### 2. Mejoras en OfferOrderCreator
**Ubicación:** `src/Order/OfferOrderCreator.php`

**Nuevas funcionalidades:**
- Guarda datos originales de la orden en meta `_rfq_original_items`
- Almacena total original en meta `_rfq_original_total`  
- Logging de protección con datos guardados

### 3. Estilos CSS de Protección
**Ubicación:** `assets/css/rfq-manager.css`

**Características:**
- Oculta controles de cantidad/eliminar en order-pay
- Mensaje informativo visual para usuario
- Icono de seguridad en tabla de productos
- Estilos para notificaciones de seguridad

### 4. Página de Gracias Automática
**Ubicación:** Función en `GiHandler.php`

**Funcionalidad:**
- Creación automática de página /gracias si no existe
- Contenido HTML prediseñado con información útil
- Enlaces de navegación post-pago

## Flujo de Protección

### Pre-Pago (Validación)
1. Hook en `woocommerce_checkout_process`
2. Verificar si orden tiene meta `_rfq_offer_order = yes`
3. Comparar items actuales con `_rfq_original_items`
4. Abortar proceso si hay diferencias detectadas
5. Log de cualquier intento de manipulación

### Durante Pago (Bloqueo Visual)
1. CSS inyectado en páginas order-pay de órdenes RFQ
2. Controles de edición ocultados completamente
3. Mensaje informativo mostrado al usuario
4. Hooks de modificación deshabilitados

### Post-Pago (Redirección)
1. Hook en `woocommerce_thankyou`
2. Verificar si orden procesada es RFQ
3. Redirección automática a /gracias
4. Preservar funcionalidad normal para otras órdenes

## Logging y Monitoreo

### Prefijos de Log
- `[RFQ-PROTECCION]`: Eventos de seguridad y validación
- `[RFQ-PROTECCION] MANIPULACIÓN DETECTADA`: Intentos de fraude

### Eventos Registrados
- Validación exitosa de integridad
- Detección de manipulación con IP del usuario
- Aplicación de estilos de protección
- Redirecciones post-pago
- Guardado de datos originales

## Configuración de Hooks

### Hooks Registrados
```php
// Validación pre-pago
add_action('woocommerce_checkout_process', 'validate_rfq_order_integrity');

// Redirección post-pago  
add_action('woocommerce_thankyou', 'redirect_rfq_thankyou', 10, 1);

// Estilos de protección
add_action('wp_enqueue_scripts', 'enqueue_protection_styles');

// Deshabilitación de edición
add_action('wp', 'disable_order_pay_editing');
```

### Filtros Aplicados
```php
// Prevenir modificación de items
add_filter('woocommerce_order_item_needs_processing', 'prevent_rfq_order_modification');

// Remover links de edición
add_filter('woocommerce_cart_item_remove_link', '__return_empty_string');
add_filter('woocommerce_cart_item_quantity', '__return_empty_string');
```

## Testing y QA

### Casos de Prueba
1. **Integridad de Orden**: Verificar que cambios en productos/cantidades/precios abortan el pago
2. **Bloqueo Visual**: Confirmar que controles de edición no son visibles en order-pay
3. **Redirección**: Validar redirección automática a /gracias solo para órdenes RFQ
4. **Logging**: Revisar que eventos se registran correctamente en debug.log
5. **Compatibilidad**: Confirmar que órdenes normales no se ven afectadas

### Escenarios de Seguridad
- Manipulación directa de HTML/JavaScript
- Modificación de requests POST
- Cambios en sesión/cookies
- Ataques CSRF en proceso de pago

## Mantenimiento

### Archivos a Monitorear
- `debug.log` para eventos [RFQ-PROTECCION]
- Métricas de órdenes RFQ vs normales
- Reportes de usuario sobre problemas de pago

### Actualizaciones Futuras
- Configuración de horas de vencimiento de pago
- Personalización de página /gracias por admin
- Integración con sistemas de notificación
- Dashboard de seguridad para administradores

## Impacto en Rendimiento
- **Mínimo**: Validaciones solo se ejecutan en órdenes RFQ
- **CSS inline**: Solo se inyecta en páginas order-pay relevantes  
- **Hooks**: Registrados condicionalmente basado en contexto
- **Logging**: Asíncrono, no bloquea flujo principal

## Compatibilidad
- **WordPress**: 5.0+
- **WooCommerce**: 4.0+
- **Themes**: Compatible con temas estándar y personalizados
- **Plugins**: No conflictos conocidos con plugins de pago

---

**Versión:** 1.0  
**Última actualización:** Agosto 2025  
**Responsable:** RFQ Manager WooCommerce Plugin
