# RFQ Manager para WooCommerce

## Descripción
Plugin para gestionar solicitudes de cotización (RFQ - Request for Quote) en WooCommerce.

## Estados de Solicitud

El plugin maneja cuatro estados principales para las solicitudes:

### 1. Pendiente (rfq-pending)
- Estado inicial de una solicitud
- No ha recibido ninguna cotización u oferta
- La fecha de vencimiento es futura
- La solicitud está vigente y esperando cotizaciones

### 2. Activa (rfq-active)
- La solicitud ha recibido al menos una cotización u oferta
- La fecha de vencimiento es futura
- La solicitud está vigente y puede recibir más cotizaciones
- Los vendedores pueden seguir enviando ofertas

### 3. Aceptada (rfq-accepted)
- Una cotización ha sido aceptada por el cliente
- La solicitud se considera cerrada con éxito
- No se pueden aceptar más cotizaciones
- Se puede proceder con la creación de la orden

### 4. Histórica (rfq-historic)
- La solicitud ha llegado a su fecha de vencimiento
- No recibió ofertas o cotizaciones
- O recibió ofertas pero ninguna fue aceptada
- La solicitud ya no está vigente

## Flujo de Estados

1. **Creación de Solicitud**
   - Se crea en estado "Pendiente"
   - Se establece una fecha de vencimiento (24 horas por defecto)

2. **Recepción de Cotizaciones**
   - Cuando se recibe la primera cotización, pasa a estado "Activa"
   - Puede recibir múltiples cotizaciones mientras esté activa

3. **Aceptación de Cotización**
   - Al aceptar una cotización, la solicitud pasa a estado "Aceptada"
   - Solo se puede aceptar una cotización por solicitud
   - Solo se puede aceptar si la solicitud está en estado "Activa"

4. **Vencimiento**
   - Si la solicitud llega a su fecha de vencimiento sin cotizaciones aceptadas, pasa a estado "Histórica"
   - Las solicitudes históricas no pueden recibir nuevas cotizaciones

## Seguridad

El plugin implementa las siguientes medidas de seguridad:

- Validación de permisos de usuario
- Verificación de nonces para acciones críticas
- Sanitización de datos de entrada
- Escape de datos de salida
- Validación de tipos de datos
- Manejo seguro de metadatos
- Limpieza de caché apropiada

## Requisitos

- WordPress 5.0 o superior
- WooCommerce 5.0 o superior
- PHP 7.4 o superior

## Instalación

1. Subir el directorio `rfq-manager-woocommerce` a `/wp-content/plugins/`
2. Activar el plugin a través del menú 'Plugins' en WordPress
3. Configurar los ajustes del plugin en WooCommerce > RFQ Manager

## Soporte

Para soporte técnico, por favor contactar a [email@example.com]
