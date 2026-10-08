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
6. Rellena `includes/config.php`. La política de conservación ya incluye criterios contrastados con la web corporativa; los nombres de proveedores solo se publican si se verifican.
7. Protege `/admin/` con la función **Directorios protegidos con contraseña** de Plesk.
8. Comprueba por SSH, si está disponible: `php includes/check.php`.

No hay Composer, `vendor/`, framework, migraciones ni cron.

## Información legal y obligaciones operativas

- El aviso legal usa los datos de SHR LAXER BUSINESS S.L. publicados en su sitio corporativo.
- La política de privacidad ya incorpora la base jurídica del consentimiento para consultas, los criterios corporativos de conservación y las categorías de proveedores. No se atribuye nombre o país al hosting o SMTP sin verificarlo.
- **Antes de ponerla en producción:** comprobar el hosting y SMTP realmente contratados, su ubicación, acuerdos de encargado y eventuales transferencias fuera del EEE. Si se desea publicar sus nombres, completar `legal.hosting_provider` y `legal.email_provider`.
- **Conservación:** el esquema actual no realiza borrados automáticos. La empresa deberá revisar periódicamente los leads y ejecutar las supresiones/bloqueos correspondientes (también en copias y notificaciones, según proceda). No se ha elegido un plazo ficticio de 12 o 24 meses.
- **Google Ads:** el Consent Mode avanzado transmite señales sin cookies incluso al rechazar o ignorar el aviso. Este punto debe validarse jurídicamente según los tratamientos efectivos y la política del responsable; informar en la web no sustituye determinar su licitud.
- En la tabla de cookies ya no aparece `sem_admin`, porque el panel está protegido por Plesk y no usa sesiones PHP. Confirmar con Tag Assistant y navegador las cookies que aparezcan en producción.

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

El lead se inserta primero. Después se intenta el correo y se actualiza `email_status` a `sent` o `error`. La aplicación rechaza SMTP sin cifrado: usa únicamente `tls` (STARTTLS/587) o `ssl` (TLS implícito/465).

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
