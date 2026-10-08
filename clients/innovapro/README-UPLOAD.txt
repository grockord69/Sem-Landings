INNOVAPRO | INSTALACION RAPIDA EN DINAHOSTING

ATENCION: demo.innovapro.es comparte alojamiento con la web principal.
No subas archivos a la raiz de innovapro.es, ni cambies PHP sin comprobar su compatibilidad.
Esta landing requiere PHP 8.2+; en Dinahosting puede haber una unica version por hosting.

1. Hosting > Servidor > PHP: comprobar version >= 8.2. No cambiarla a ciegas.
2. Hosting > Dominios > Subdominios: comprobar demo.innovapro.es, ruta y certificado HTTPS.
3. Subir el CONTENIDO de esta carpeta a la ruta web asignada al subdominio;
   el directorio puede llamarse www, demo o de otra forma. Conservar .htaccess.
4. Hosting > Bases de datos: BD MariaDB nueva e importar sql/schema.sql.
5. Rellenar includes/config.php (ya creado): BD, SMTP, destinatarios, contacto.
   Para MariaDB integrada suele usarse db.host = localhost.
   El SMTP de Dinahosting, si se confirma que la cuenta es suya, admite 465 + ssl;
   copiar el host exacto de Hosting > Correo > Sobre este correo.
6. Hosting > Seguridad > Proteccion de carpetas: proteger SOLO la carpeta /admin
   del subdominio con usuario y contrasena fuerte.
7. Probar /admin sin credenciales y despues autenticado. Si tras autenticar devuelve
   HTTP 403, preguntar al hosting por REMOTE_USER; no retirar esa comprobacion.
8. Verificar que /includes/config.php y /sql/schema.sql NO se pueden descargar.
   Si son visibles, detener el despliegue.
9. Enviar un formulario de prueba y revisar BD, email, panel, Google Tag Assistant.

Comprobacion opcional por SSH desde la raiz web: php includes/check.php

VALORES DE CONVERSION GOOGLE ADS
Formulario: 30 EUR. Llamada: 10 EUR. WhatsApp: 10 EUR.

DATOS LEGALES
Alojamiento: Dinahosting, S.L. (confirmado).
Proveedor SMTP y ubicacion fisica del centro de datos: verificar, no inventar.
El panel no elimina leads automaticamente: planificar revision y supresion.

No WordPress, no Composer, no cron ni carpetas fuera de la web obligatorias.
Consulta docs/DEPLOY.md del repositorio para la guia completa.
