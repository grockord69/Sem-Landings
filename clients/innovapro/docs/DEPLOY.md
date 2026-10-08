# InnovaPro — instalación en Dinahosting

## Antes de empezar

- La landing requiere PHP 8.2+ con pdo_mysql, openssl, iconv y JSON.
- **demo.innovapro.es comparte hosting con la web principal**: en los planes de Dinahosting puede existir UNA sola versión PHP por hosting. Antes de modificarla, verifica la versión existente en **Hosting > Servidor > PHP** y la compatibilidad de innovapro.es. Si es anterior a 8.2, NO la cambies sin coordinarlo con el administrador; solicita alternativas al proveedor.
- La instalación no usa Plesk, Composer, WordPress, vendor, cron ni carpetas exteriores obligatorias.

## 1. Subdominio y archivos

1. En Panel > **Hosting > Dominios > Subdominios**, comprobar demo.innovapro.es, el directorio web asignado y Let's Encrypt.
2. Subir **el contenido** del paquete, no una carpeta extra, al directorio del subdominio. Su nombre puede variar; no sobrescribir la raíz de innovapro.es.
3. Mantener .htaccess y las carpetas includes, assets, admin y sql. Index.php debe quedar en la raíz del subdominio.
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

## 4. Proteger el panel

1. En **Hosting > Seguridad > Protección de carpetas**, proteger **solo /admin del subdominio** con usuario y contraseña fuertes.
2. Probar desde una ventana privada /admin/ sin credenciales: el servidor debe pedirlas o denegar acceso.
3. Probar con credenciales válidas: debe entrar al listado y permitir CSV.
4. Si después de autenticarse aparece 403, comprobar con Dinahosting la entrega de REMOTE_USER al PHP. No anular esa segunda verificación. Como alternativa, admin/.htaccess.example permite configurar .htpasswd fuera de la carpeta pública.

Guía oficial: https://dinahosting.com/ayuda/proteccion-carpetas/

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
- detalle en /admin y exportación CSV;
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
