# InnovaPro · landing SEM simplificada

Landing PHP para https://demo.innovapro.es/, alojada en Dinahosting, con instalación directa en la carpeta web del subdominio. Sin Composer, WordPress ni cron.

## Estructura

- index.php, form.php, páginas legales.
- assets/: CSS, JS e imágenes.
- includes/: configuración y PHP.
- admin/: listado, filtros, detalle y CSV, protegido por autenticación del servidor.
- sql/schema.sql: base de datos.

## Despliegue

Guías: [README-UPLOAD.txt](README-UPLOAD.txt) y [docs/DEPLOY.md](docs/DEPLOY.md).

**Importante:** en Dinahosting la versión de PHP puede ser común para todo el hosting. Antes de tocar PHP, confirmar que la versión actual sea >=8.2; no cambiarla sin comprobar la web principal.

El directorio raíz lo determina la configuración del subdominio y puede no llamarse httpdocs. Nunca sobrescribir la web innovapro.es.

1. Subir archivos a la carpeta del subdominio.
2. Crear BD separada, importar SQL y editar includes/config.php.
3. Proteger /admin desde Dinahosting > Seguridad > Protección de carpetas.
4. Probar seguridad de includes, SMTP y conversiones Ads.

Hosting identificado: Dinahosting, S.L.; servidor SMTP todavía sin confirmar.

## Medición

Formulario 30 EUR; llamada 10 EUR; WhatsApp 10 EUR. Consent Mode v2 avanzado y conversiones mejoradas con consentimiento. Se confirma el INSERT del lead antes del evento de conversión.
