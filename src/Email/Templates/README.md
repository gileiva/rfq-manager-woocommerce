# Sistema de Plantillas de Notificaciones por Email

Este sistema implementa un conjunto de plantillas HTML personalizadas para enviar notificaciones por email a diferentes roles de usuario en el plugin RFQ Manager.

## Estructura de Directorios

```
Templates/
├── User/                   # Plantillas para notificaciones a usuarios
├── Supplier/               # Plantillas para notificaciones a proveedores
├── Admin/                  # Plantillas para notificaciones a administradores
└── NotificationTemplateFactory.php  # Factory para crear plantillas
```

## Patrón Factory

El sistema utiliza el patrón de diseño Factory para crear plantillas HTML de manera flexible y reutilizable. La clase `NotificationTemplateFactory` se encarga de:

1. Proporcionar una interfaz unificada para crear diferentes tipos de plantillas
2. Aplicar estilos consistentes según el tipo de destinatario
3. Reutilizar componentes comunes como encabezados, contenido y pies de página
4. Facilitar la personalización de mensajes según el contexto de la notificación

## Tipos de Notificaciones

El sistema gestiona las siguientes notificaciones:

### Para Usuarios
- Solicitud creada
- Cotización recibida
- Cambios de estado:
  - Activa (cuando recibe al menos una cotización)
  - Aceptada (cuando se acepta una cotización)
  - Histórica (cuando vence sin aceptar ninguna cotización)

### Para Proveedores
- Nueva solicitud disponible
- Cotización aceptada

### Para Administradores
- Solicitud creada
- Cotización enviada
- Cotización aceptada

## Personalización

Las plantillas pueden personalizarse de dos maneras:

1. **Reemplazando los archivos de plantilla**: Puede crear versiones personalizadas en su tema activo en la carpeta `rfq-manager/emails/`.
2. **Utilizando filtros de WordPress**: Cada parte del contenido de email puede modificarse mediante los filtros correspondientes.

## Método de Selección de Plantillas

1. Primero se busca en el tema activo para permitir la personalización
2. Si no existe, se utiliza la plantilla del plugin
3. Si no se encuentra ninguna plantilla específica, se utiliza una plantilla genérica

## Estados de una Solicitud

Las solicitudes pasan por los siguientes estados:
- **Pendiente de Cotización**: Estado inicial al crear la solicitud
- **Activa**: Cuando recibe al menos una cotización de un proveedor
- **Aceptada**: Cuando el usuario acepta una cotización
- **Histórica**: Cuando vence sin aceptar ninguna cotización

## Ampliación del Sistema

Para agregar nuevos tipos de notificaciones:

1. Crear las plantillas correspondientes en la carpeta apropiada
2. Registrar los hooks necesarios en las clases de notificación
3. Extender el NotificationTemplateFactory si se requieren nuevos métodos de creación de plantillas

## Seguridad

Todas las plantillas implementan:
- Comprobación de acceso directo a los archivos
- Escapado apropiado de datos utilizando funciones como `esc_html()`, `esc_url()`, etc.
- Sanitización de datos de entrada 