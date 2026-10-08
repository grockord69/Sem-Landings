# InnovaPro — instalación en Dinahosting

## Antes de empezar

- La landing requiere PHP 8.2+ con pdo_mysql, openssl, iconv y JSON.
- **demo.innovapro.es comparte hosting con la web principal**: en los planes de Dinahosting puede existir UNA sola versión PHP por hosting. Antes de modificarla, verifica la versión existente en **Hosting > Servidor > PHP** y la compatibilidad de innovapro.es. Si es anterior a 8.2, NO la cambies sin coordinarlo con el administrador; solicita alternativas al proveedor.
- La instalación no usa Plesk, Composer, WordPress, vendor, cron ni carpetas exteriores obligatorias.

## 1. Subdominio y archivos

1. En Panel > **Hosting > Dominios > Subdominios**, comprobar demo.innovapro.es, el directorio web asignado y Let's Encrypt.
2. Subir **el contenido** del paquete, no una carpeta extra, al directorio del subdominio. Su nombre puede variar; no sobrescribir la raíz de innovapro.es.
3. Mantener .htaccess y las carpetas includes, assets, leadspanel y sql. Index.php debe quedar en la raíz del subdominio.
4. Abrir https://demo.innovapro.es/ y comprobar SSL.

Guía oficial: https://dinahosting.com/ayuda/hosting-subdominios/

## 2. Base de datos

1. En **Hosting > Bases de datos**, crear una BD MariaDB/MySQL nueva y usuario específico, sin usar la BD de WordPress del sitio principal.
2. Dar al usuario del sitio permisos SELECT, INSERT y UPDATE; usar un usuario con CREATE para importar el esquema SQL la primera vez.
3. Importar sql/schema.sql desde phpMyAdmin (Administrar BD).
4. Completar db.name, db.user y db.password en includes/config.php. Para MariaDB local de Dinahosting, db.host = localhost; para bases externas, usar el hostname que indique el panel.

Guía oficial: https://dinahosting.com/ayuda/bases-datos/

## 3. SMTP y contactos

Editar includes/config.php (incluido en el ZIP y listo para rellenar):

- smtp.host, smtp.port, smtp.encryption, smtp.username, smtp.password
- smtp.from_email (remitente autorizado), smtp.recipients (todos los destinatarios)
- contact.phone_e164, contact.whatsapp_e164, contact.email
- app.url debe ser https://demo.innovapro.es

**No se sabe con certeza si el correo también está en Dinahosting.** Si lo está, en **Hosting > Correo > Sobre este correo** figuran servidor de salida y usuario. Utiliza el nombre EXACTO y **SMTPS puerto 465 con encryption = ssl**, según su guía. Otros SMTP pueden usar 587 con tls/STARTTLS. Nunca desactivar la verificación TLS ni usar SMTP sin cifrado.

Guía oficial: https://dinahosting.com/ayuda/configuracion-ssl-cuenta-de-correo/

## 4. Panel privado: /leadspanel/

El panel ahora reside en **/leadspanel/**, no en /admin/. La ruta antigua está bloqueada y debe **eliminarse su carpeta físicamente** si todavía existe en el servidor.

No se necesita la función de carpetas protegidas de Dinahosting: el PHP del panel presenta el diálogo nativo HTTP Basic y contrasta las credenciales cifradas con bcrypt en **includes/.htpasswd**. No hay sesiones ni login HTML.

- El ZIP privado contiene includes/.htpasswd y se entrega una contraseña por separado de los archivos web.
- Sin credenciales, /leadspanel/ devuelve HTTP 401 y pide usuario/contraseña.
- Con credenciales válidas, abre listado, detalle y CSV.
- Sin credenciales, /leadspanel/export.php tampoco puede descargar datos.
- Verificar que /includes/.htpasswd devuelve 403/404 y no se descarga.
- NO almacenar contraseña en texto plano dentro de la carpeta web ni versionar .htpasswd.

La directiva Apache AuthUserFile exige una ruta absoluta del servidor, desconocida antes del despliegue en Dinahosting. Esta versión utiliza el mismo fichero .htpasswd pero **lo valida desde PHP**, lo que evita editar rutas en el hosting.

## 5. Seguridad

Las carpetas internas incluyen .htaccess que debe impedir acceso web directo. En navegador, comprobar que **no se descargan**:
- /includes/config.php
- /sql/schema.sql

Si alguna es accesible, detener la puesta en marcha y consultar soporte. Mantener HTTPS y las reglas .htaccess, incluyendo la excepción /.well-known/acme-challenge/ para Let's Encrypt.

## 6. Comprobaciones

Opcional por SSH desde la raíz del subdominio: php includes/check.php

Probar un lead real controlado:
- guardado de BD previo al intento de correo;
- correo recibido por destinatarios;
- detalle en /leadspanel y exportación CSV;
- error SMTP: lead conservado;
- enlaces de llamada y WhatsApp correctos.

Google Tag Assistant: comprobar las tres conversiones y valores, Consent Mode v2 avanzado (denied inicial, aceptado granted en ad_storage y ad_user_data) y Enhanced Conversions **solo** con consentimiento.

- Formulario: 30 EUR, AW-763034950/bu_ECPvC9PkBEMb66-sC.
- Clic teléfono: 10 EUR, AW-763034950/evT9CI2-wfwZEMb66-sC.
- Clic WhatsApp: 10 EUR, AW-763034950/a58xCJOfqo8dEMb66-sC.

## 7. Textos legales y retención

Responsable: SHR LAXER BUSINESS S.L. Alojamiento confirmado: **Dinahosting, S.L.** (sociedad española). No deducimos por ello la ubicación física de los servidores. La identidad del SMTP y las garantías contractuales/transferencias se verifican con los servicios efectivamente contratados.

La política de privacidad contiene criterios de conservación, pero **el panel no borra automáticamente leads**. La empresa necesita procedimientos efectivos de revisión, supresión y bloqueo cuando proceda. También debe validar la licitud de las señales cookieless del Consent Mode avanzado.

## Documentación oficial

- PHP único por hosting: https://dinahosting.com/ayuda/versiones-de-php-en-el-mismo-hosting/
- Subdominios: https://dinahosting.com/ayuda/hosting-subdominios/
- Base de datos: https://dinahosting.com/ayuda/bases-datos/
- Protección de carpetas: https://dinahosting.com/ayuda/proteccion-carpetas/
- SMTP con SSL: https://dinahosting.com/ayuda/configuracion-ssl-cuenta-de-correo/
- Titular Dinahosting: https://dinahosting.com/legal/aviso-legal

### Actualización sobre producción

Si la landing ya funciona, instalar solo el parche de esta versión; **no sobrescribir includes/config.php**, que contiene las credenciales operativas, y **no volver a importar sql/schema.sql**. Borrar /admin/ y comprobar después /leadspanel/. Los cambios no afectan a BD, SMTP ni conversiones.

## Parche de corrección de octubre de 2026

- El JavaScript del formulario ahora se referencia como `/assets/js/app.js?v=...`, con un valor calculado desde la modificación del archivo. Esto evita que un navegador conserve durante siete días la versión antigua que mostraba la referencia del lead.
- El usuario ve únicamente **«Solicitud recibida. Nuestro equipo contactará contigo.»**. El `lead_id` permanece internamente en BD y Ads.
- Para HTTP Basic, Apache retransmite `Authorization` a PHP-FPM/FastCGI mediante `.htaccess`. El código comprueba `PHP_AUTH_USER`, `HTTP_AUTHORIZATION`, `REDIRECT_HTTP_AUTHORIZATION` y alternativas; siempre verifica el hash bcrypt de `includes/.htpasswd`.
- El fichero `includes/.htpasswd` debe reemplazarse junto con la contraseña facilitada por separado. Si el navegador recuerda un acceso anterior, usa una ventana privada.
- El correo contiene exclusivamente fecha y hora, nombre, teléfono y email, con asunto literal **«Nueva contacto desde GAds»**.
- Se actualizan los tests de navegador, validación CGI, SMTP y HTML/texto del correo.
