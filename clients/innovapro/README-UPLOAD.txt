INNOVAPRO · INSTALACIÓN RÁPIDA

Este directorio es el contenido completo que debe subirse al document root de demo.innovapro.es.
No usa Composer, no necesita cron y no requiere colocar carpetas fuera de httpdocs.

1. Sube todos los archivos y carpetas al document root del subdominio.
2. Copia includes/config.example.php como includes/config.php.
3. Completa en includes/config.php:
   - base de datos;
   - SMTP y destinatarios;
   - proveedor de hosting/correo y conservación;
   - confirma teléfono y WhatsApp.
4. Crea una base de datos MariaDB/MySQL e importa sql/schema.sql.
5. En Plesk, protege el directorio /admin/ con usuario y contraseña.
6. Ejecuta por SSH, si está disponible:
      php includes/check.php
7. Prueba formulario, correo, panel, teléfono, WhatsApp y Google Tag Assistant.

IMPORTANTE
- La raíz del subdominio es este mismo directorio; no existe una carpeta public/.
- .htaccess bloquea el acceso web a includes/, lib/, sql/, tests/ y docs/.
- El panel devuelve 403 si Plesk/Apache no informa de un usuario autenticado.
- Los leads se guardan antes de intentar enviar el email.
- Valores Google Ads: formulario 30 EUR, llamada 10 EUR, WhatsApp 10 EUR.
