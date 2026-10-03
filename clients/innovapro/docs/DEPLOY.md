# Despliegue en demo.innovapro.es

Compatible con Apache 2.4/LiteSpeed o Nginx + PHP-FPM. No se ha intervenido en el hosting del cliente: necesita acceso y configuración privada. Git no contiene credenciales operativas.

## 1. Requisitos

- PHP 8.2+ actualizado; preferible 8.4. Extensiones pdo, pdo_mysql, openssl, iconv, session y funciones estándar JSON/filter/hash habilitadas. Composer CLI suele usar curl/zip.
- MariaDB 10.6+ o MySQL 8.0+, InnoDB y utf8mb4. CI con MariaDB 11.4.
- Composer 2 y acceso a Packagist para preparar vendor; se puede generar vendor antes y subirlo con el release.
- Salida al SMTP autenticado: 587 STARTTLS (`tls`) o 465 TLS implícito (`ssl`). CA/certificados válidos; no desactivar verificación.
- DNS/certificado HTTPS de demo.innovapro.es. Sesiones PHP en directorio privado escribible por PHP.

## 2. Release y dependencias

Ejemplo Linux, adaptar usuario/rutas y referencia al commit entregado:

```sh
git clone https://github.com/grockord69/Sem-Landings.git /srv/sem-landings
cd /srv/sem-landings
git checkout COMMIT_ENTREGADO
cd clients/innovapro
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
composer validate --strict
composer audit --no-interaction
```

No subir .local, adjuntos, fixtures, dumps o logs a la raíz pública. No usar php -S en producción.

## 3. Base de datos

Como administrador crear una BD vacía y usuario propio. Introducir la contraseña real en un gestor privado del servidor, sin historial ni Git:

```sql
CREATE DATABASE innovapro_leads CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'innovapro_app'@'localhost' IDENTIFIED BY 'SUSTITUIR_EN_ENTORNO_PRIVADO';
GRANT SELECT, INSERT, UPDATE ON innovapro_leads.* TO 'innovapro_app'@'localhost';
```

Importar schema con usuario de administración/despliegue que tenga CREATE; la aplicación no necesita CREATE/DROP/DELETE:

```sh
mariadb -u USUARIO_DESPLIEGUE -p innovapro_leads < sql/schema.sql
```

PDO establece UTC y el panel/email muestran Europe/Madrid. Si la BD es remota, configurar db.ssl_ca y TLS verificado. Crear backups privados y probar restauración conforme a la conservación aprobada.

## 4. Configuración privada

```sh
cp config/config.example.php config/config.php
chmod 640 config/config.php
php bin/console.php password
```

La herramienta password lee por stdin; no pasar contraseña como argumento. No oculta la entrada: ejecutarla en consola privada. Copiar exclusivamente el hash a admin.users.

| Campo | Valor privado/confirmación |
| --- | --- |
| app.url | https://demo.innovapro.es |
| db.* | host, puerto, BD, usuario/contraseña propios |
| smtp.* | host, puerto/cifrado, usuario/contraseña, remitente autorizado, todos los destinatarios |
| admin.users | usuarios y hashes, nunca contraseña en claro |
| legal.hosting_provider | proveedor/ubicación verificados |
| legal.email_provider | proveedor SMTP/ubicación verificados |
| legal.retention_policy | plazo y criterios concretos aprobados |
| legal.privacy_email | confirmar buzón; valor oficial info@innovapro.es |

Contactos y datos societarios contrastados con web oficial el 03/10/2026. Confirmar atención del WhatsApp +34 606 681 297 para esta campaña. IDs Ads ya configurados; sin flags para activar tracking.

Alternativa: config fuera de releases, p.ej. /etc/sem-landings/innovapro.php, con SEM_CONFIG en PHP-FPM/panel. Es un selector normal de configuración, no un modo de tests. Debe poder leerlo PHP sin ser accesible por HTTP.

## 5. Document root, HTTPS y CSP

Configurar el subdominio con raíz:

```text
/srv/sem-landings/clients/innovapro/public/
```

config/src/sql/templates/vendor/bin quedan fuera. Si el hosting obliga public_html, poner allí solo public y las demás carpetas un nivel arriba preservando rutas relativas. No poner el cliente completo en public_html.

Apache/LiteSpeed: DirectoryIndex index.php, .htaccess permitido y Authorization transmitida a PHP. Si PHP-FPM no recibe Basic Auth, configurar CGIPassAuth On en el contexto apropiado o transmisión equivalente del proveedor. OpenLiteSpeed requiere equivalencias del vhost; no asumir todas las directivas .htaccess.

Nginx: adaptar [nginx.example.conf](../deployment/nginx.example.conf), TLS, socket y rutas. Transmite HTTP_AUTHORIZATION y usa try_files. Sin caché de HTML, API o admin.

HTTP redirige a HTTPS. PHP exige HTTPS fuera de loopback y emite HSTS. Tras proxy TLS, app.trusted_proxy_ips solo admite IP exactas de proxies que sobrescriben X-Forwarded-Proto, nunca cabeceras arbitrarias del visitante.

PHP emite CSP con nonce/dominos Ads, nosniff, Referrer-Policy y Permissions-Policy. No imponer otra CSP incompatible desde hosting. Validar realmente con Tag Assistant; endpoints regionales pueden requerir un ajuste concreto.

## 6. Permisos y registros

Directorios 750/755 y archivos 640/644 según usuario/grupo; config 640 o 600 si PHP es propietario. Código/assets de solo lectura para PHP. Solo sesiones privadas necesitan escritura; no hay archivos de leads/colas.

Logs de aplicación: únicamente códigos seguros, sin payloads ni errores SMTP crudos. Configurar hosting para no registrar cuerpos POST, Authorization ni queries; limitar acceso/conservación de logs técnicos. Sin IP en BD. display_errors y SMTPDebug desactivados.

## 7. Validación e incidencias

```sh
php bin/console.php check
```

Debe terminar con código 0. Revisa extensiones/vendor/BD/schema/admin/config y legales pendientes; no certifica recepción SMTP. Completar [checklist de producción](PRODUCTION-CHECKLIST.md) con lead real controlado.

**No hay cron.** SMTP se intenta después del INSERT dentro de la petición, con timeout configurable de hasta 8 segundos por operación de transporte. Dejar margen en PHP/FPM/proxy, p.ej. 30 s. Una interrupción tras INSERT deja pending visible; un reintento idempotente recupera pending sin duplicar. Para recuperación manual de pending de más de 10 minutos:

```sh
php bin/console.php notify-pending ID_INTERNO
```

Usa lock SMTP y no Ads. Los errores se reenvían desde admin. SMTP no permite prometer entrega exactamente una vez: una interrupción después de aceptar el correo y antes de actualizar BD puede dejar incierto si se envió. Contrastar con el proveedor antes de recuperar ese caso. El lead siempre sigue guardado.

## 8. Actualización/reversión

Preparar release con Composer y CI verde, conservar config/BD, cambiar raíz/release y repetir controles. No reimportar schema sobre leads existentes. Revertir al release anterior compatible sin borrar leads. Este schema corresponde a instalación nueva; una versión desplegada del ZIP necesita migración explícita.
