# Fix TinyMCE: Pestañas Duplicadas y Texto Blanco

## Problema Identificado
- **Pestañas duplicadas**: Los editores se inicializaban dos veces (WordPress automático + JavaScript personalizado)
- **Texto blanco en modo Code**: Falta de estilos CSS para el modo HTML/texto
- **Elementos anidados**: HTML duplicado causando problemas de visualización

## Soluciones Implementadas

### 1. Eliminación de Inicialización Duplicada en JavaScript
**Archivo**: `NotificationManager.php` - Función JavaScript
**Cambio**: 
- ❌ **ANTES**: `initializeTinyMCEForTab()` reinicializaba editores ya creados por `wp_editor()`
- ✅ **AHORA**: Solo maneja eventos y funcionalidad adicional, sin reinicializar editores

```javascript
// ANTES: Inicialización duplicada
function initializeTinyMCEForTab(tabName) {
    wp.editor.initialize(realId, {...}); // PROBLEMA: Ya inicializado
}

// AHORA: Solo eventos
jQuery(function($) {
    // Los editores TinyMCE ya están inicializados por wp_editor()
    // Solo necesitamos manejar eventos y funcionalidad adicional
```

### 2. CSS para Corregir Modo Code
**Archivo**: `NotificationManager.php` - `enqueue_editor_scripts()`
**Añadido**:
```css
/* Corregir problema de texto blanco en modo Code */
.rfq-templates-form .wp-editor-wrap .wp-editor-area {
    color: #23282d !important;
    background: #fff !important;
}
.rfq-templates-form .wp-editor-wrap.html-active .wp-editor-area {
    color: #23282d !important;
    background: #f9f9f9 !important;
    font-family: Consolas, Monaco, monospace;
}
/* Evitar duplicación de editores */
.rfq-templates-form .wp-editor-wrap .wp-editor-wrap {
    display: none;
}
```

### 3. Mejora en Configuración TinyMCE
**Archivo**: `NotificationManager.php` - `wp_editor()` config
**Añadido**:
- Callback de `setup` para logging de inicialización
- Mejor manejo de configuración para evitar conflictos

### 4. JavaScript Simplificado para Placeholders
**Mejorado**:
- Mejor detección de editores activos (TinyMCE vs textarea)
- Fallback más robusto para inserción de placeholders
- Manejo mejorado de foco y selección

## Resultado Esperado
✅ **Una sola pestaña Visual/Code** por editor
✅ **Texto negro visible** en modo Code con fondo gris claro
✅ **Funcionalidad completa** de TinyMCE sin duplicaciones
✅ **Inserción de placeholders** funcionando correctamente
✅ **Sin elementos HTML anidados** o duplicados

## Archivos Modificados
- `src/Email/Notifications/Custom/NotificationManager.php`

## Testing
1. Ir a RFQ Manager > Notifications
2. Seleccionar cualquier pestaña (User/Supplier/Admin)
3. Verificar que cada editor tiene solo UNA pestaña Visual/Code
4. Cambiar a modo "Code" y verificar que el texto sea visible (negro sobre gris)
5. Insertar placeholders desde la barra lateral
6. Cambiar entre pestañas de roles y verificar funcionamiento

## Notas Técnicas
- WordPress maneja automáticamente la inicialización de `wp_editor()`
- No es necesario reinicializar con `wp.editor.initialize()`
- Los estilos CSS con `!important` son necesarios para sobrescribir los estilos predeterminados de WP
- La regla CSS `.wp-editor-wrap .wp-editor-wrap { display: none; }` previene anidación visual
