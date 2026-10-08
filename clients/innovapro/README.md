# InnovaPro · landing SEM simplificada

Landing PHP para https://demo.innovapro.es/, alojada en Dinahosting, con instalación directa en la carpeta web del subdominio. Sin Composer, WordPress ni cron.

## Estructura

- index.php, form.php, páginas legales.
- assets/: CSS, JS e imágenes.
- includes/: configuración y PHP.
- leadspanel/: listado, filtros, detalle y CSV, protegido por autenticación HTTP Basic y el fichero includes/.htpasswd (bcrypt).
- sql/schema.sql: base de datos.

## Despliegue

Guías: [README-UPLOAD.txt](README-UPLOAD.txt) y [docs/DEPLOY.md](docs/DEPLOY.md).

**Importante:** en Dinahosting la versión de PHP puede ser común para todo el hosting. Antes de tocar PHP, confirmar que la versión actual sea >=8.2; no cambiarla sin comprobar la web principal.

El directorio raíz lo determina la configuración del subdominio y puede no llamarse httpdocs. Nunca sobrescribir la web innovapro.es.

1. Subir archivos a la carpeta del subdominio.
2. Crear BD separada, importar SQL y editar includes/config.php.
3. Añadir includes/.htpasswd (distribuido solo en el ZIP privado) e iniciar sesión en /leadspanel/ con el usuario y contraseña facilitados en el paquete.
4. Probar seguridad de includes, SMTP y conversiones Ads.

Hosting identificado: Dinahosting, S.L.; servidor SMTP todavía sin confirmar.

## Medición

Formulario 30 EUR; llamada 10 EUR; WhatsApp 10 EUR. Consent Mode v2 avanzado y conversiones mejoradas con consentimiento. Se confirma el INSERT del lead antes del evento de conversión.

## Actualizar instalación anterior

No sobrescribir includes/config.php ni reimportar el esquema SQL. Eliminar del servidor el antiguo directorio /admin/ y comprobar el bloqueo de la ruta antigua.

Apache AuthUserFile necesita ruta absoluta real y no es portable sin conocer la ruta del hosting. Por ello, el cuadro nativo HTTP Basic se sirve desde PHP con bcrypt leyendo el archivo .htpasswd, sin panel de control, cookies de sesión ni login visual propio.
