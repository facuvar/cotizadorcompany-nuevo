# Configuración de Correos Electrónicos - Sistema de Presupuestos Company - -----------

## Funcionalidad Implementada

Se ha agregado la funcionalidad de envío automático de correos electrónicos cuando se genera un nuevo presupuesto en el sistema. Los correos se envían usando SendGrid como proveedor de servicios de email.

## Configuración Actual

### Proveedor de Email: SendGrid
- **Servidor SMTP**: smtp.sendgrid.net
- **Puerto**: 587 (con STARTTLS)
- **Usuario**: apikey
- **API Key**: Configurada via variable de entorno (SENDGRID_API_KEY)

### Direcciones de Correo
- **Email de origen**: noreply@ascensorescompany.com
- **Nombre del remitente**: Sistema de Presupuestos Company
- **Email de destino**: facundo@maberik.com

## Variables de Entorno

El sistema usa variables de entorno para mayor seguridad:

- `SENDGRID_API_KEY`: API Key de SendGrid
- `FROM_EMAIL`: Email remitente (ej: noreply@ascensorescompany.com)
- `FROM_NAME`: Nombre del remitente (ej: Sistema de Presupuestos Company)
- `NOTIFICATION_EMAIL`: Email donde llegan las notificaciones (ej: facundo@maberik.com)

## Archivos del Sistema

### Nuevos Archivos:
1. `includes/email_handler.php` - Manejador principal de correos
2. `includes/email_config.php` - Configuración de variables
3. `sistema/includes/email_handler.php` - Copia para compatibilidad
4. `sistema/includes/email_config.php` - Configuración para sistema/
5. `CONFIGURACION_EMAIL.md` - Esta documentación

### Archivos Modificados:
1. `api/generate_quote.php` - Agregado envío de correo al generar presupuesto
2. `sistema/api/generate_quote.php` - Agregado envío de correo al generar presupuesto

## Funcionamiento

### Cuándo se Envía un Correo
- Automáticamente cada vez que se genera un nuevo presupuesto
- Se envía después de que el presupuesto se guarda exitosamente en la base de datos
- Si hay algún error en el envío, no afecta la generación del presupuesto

### Contenido del Correo
El correo incluye:
- Número del presupuesto
- Fecha y hora de generación
- Datos del cliente (nombre, email, teléfono, empresa)
- Resumen financiero (subtotal, descuentos, total)
- Formato tanto en HTML como en texto plano

### Ejemplo de Asunto
```
Nuevo Presupuesto Generado - PRES-2024-1234
```

## Configuración en Railway

### Variables de Entorno Requeridas
En el panel de Railway, agregar estas variables:

```
SENDGRID_API_KEY=tu_api_key_de_sendgrid
FROM_EMAIL=noreply@ascensorescompany.com
FROM_NAME=Sistema de Presupuestos Company
NOTIFICATION_EMAIL=facundo@maberik.com
```

### Pasos para Configurar:
1. Ve a tu proyecto en Railway
2. Accede a la sección "Variables"
3. Agrega cada variable con su valor correspondiente
4. Redeploya la aplicación

## Desarrollo Local

### En Local (Desarrollo)
Puedes crear un archivo `.env` en la raíz del proyecto o configurar las variables directamente:

```bash
# Variables necesarias
SENDGRID_API_KEY=tu_sendgrid_api_key_aqui
FROM_EMAIL=noreply@ascensorescompany.com
FROM_NAME=Sistema de Presupuestos Company
NOTIFICATION_EMAIL=facundo@maberik.com
```

Si no se configuran, se usan los valores por defecto en `email_config.php` (API key vacía requerirá configuración manual).

## Seguridad

✅ **Implementado**: El sistema usa variables de entorno para proteger credenciales:

1. ✅ API Key de SendGrid en variable de entorno `SENDGRID_API_KEY`
2. ✅ Archivos de configuración con valores por defecto para desarrollo
3. ✅ Sin credenciales hardcodeadas en el código versionado
4. ⚠️ Recuerda rotar la API Key periódicamente por seguridad

## Logs y Depuración

### Logs de Error
- Los errores de envío se registran en el log de errores de PHP
- Busca mensajes como: "Error enviando correo para presupuesto..."
- También se registran mensajes de éxito: "Correo enviado exitosamente..."

### Respuesta JSON
El endpoint de generación de presupuestos ahora incluye un campo adicional:
```json
{
    "success": true,
    "email_enviado": true,
    "quote_id": 123,
    "numero_presupuesto": "PRES-2024-1234",
    ...
}
```

## Solución de Problemas

### Correo No se Envía
1. Verifica que la API Key de SendGrid sea válida
2. Revisa los logs de error de PHP
3. Verifica que cURL esté habilitado en el servidor
4. Confirma que las variables de entorno estén configuradas

### Correo Va a Spam
1. Verifica que el dominio remitente esté verificado en SendGrid
2. Considera configurar SPF, DKIM y DMARC
3. Revisa la reputación del dominio remitente

### Error en Presupuesto
Si hay error en el envío de correo, el presupuesto se genera normalmente. El error solo se registra en los logs.

## Monitoreo

### Verificar Funcionamiento
- Revisa los logs del servidor tras generar un presupuesto
- Confirma que lleguen los correos a facundo@maberik.com
- Usa las estadísticas de SendGrid para monitorear entregas 
