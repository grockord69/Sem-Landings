# Despliegue sencillo en Plesk

## Requisitos

- PHP 8.2 o superior con `pdo_mysql`, `openssl`, `iconv` y JSON.
- MariaDB/MySQL.
- Cuenta SMTP autenticada.
- HTTPS para `demo.innovapro.es`.

## Pasos

1. Crea el subdominio en Plesk.
2. Sube todo el contenido de esta carpeta a su document root (`httpdocs`, o el nombre que asigne Plesk).
3. Copia `includes/config.example.php` como `includes/config.php`.
4. Crea una BD y un usuario con permisos `SELECT`, `INSERT` y `UPDATE`.
5. Importa `sql/schema.sql` desde phpMyAdmin/Plesk.
6. Rellena `includes/config.php`.
7. Protege `/admin/` con la función **Directorios protegidos con contraseña** de Plesk.
8. Comprueba por SSH, si está disponible: `php includes/check.php`.

No hay Composer, `vendor/`, framework, migraciones ni cron.

## Seguridad práctica

La aplicación vive dentro del document root para simplificar el despliegue. Se protege mediante:

- `.htaccess` raíz que bloquea `includes/`, `lib/`, `sql/`, `tests/` y `docs/`;
- `.htaccess` adicional dentro de los directorios sensibles;
- PDO con consultas preparadas;
- panel que exige autenticación previa del servidor;
- sin contraseñas en el repositorio;
- HTTPS y cabeceras de seguridad.

En Plesk con Nginx como proxy y Apache como backend, verifica que `.htaccess` esté habilitado. Si el hosting usa Nginx sin Apache, replica esos bloqueos en la configuración del vhost.

## SMTP

Recomendado:

- puerto 587 + `tls` para STARTTLS; o
- puerto 465 + `ssl` para TLS implícito.

El lead se inserta primero. Después se intenta el correo y se actualiza `email_status` a `sent` o `error`.

## Panel

`/admin/` no tiene un login PHP propio. Plesk/Apache realiza la autenticación Basic Auth. El código comprueba `REMOTE_USER`; si la carpeta no está protegida, responde 403.

Funciones:

- listado;
- filtros;
- búsqueda;
- detalle;
- CSV;
- estado SMTP.

## Validación final

- formulario real guardado en BD;
- email recibido;
- lead visible en `/admin/`;
- rechazo SMTP conserva el lead;
- CMP: pendiente/rechazado/aceptado;
- formulario 30 EUR;
- llamada 10 EUR;
- WhatsApp 10 EUR;
- Enhanced Conversions solo con consentimiento;
- Tag Assistant sin bloqueos CSP.
