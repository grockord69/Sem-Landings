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
6. Subir includes/.htpasswd (hash bcrypt) en la misma carpeta que config.php.
   Entrar en https://demo.innovapro.es/leadspanel/ con las claves entregadas aparte.
   Autenticacion HTTP Basic valida por PHP: no depende del panel Dinahosting.
7. Sin credenciales /leadspanel/ debe devolver 401 y solicitar contrasena.
   Probar el listado, detalle y CSV con las claves validas.
   Eliminar la carpeta antigua /admin/ de produccion si sigue existiendo.
8. Verificar que /includes/config.php, /includes/.htpasswd y /sql/schema.sql NO se pueden descargar.
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

ACTUALIZACION DE UNA WEB EN PRODUCCION:
NO sobrescribir includes/config.php; NO reimportar sql/schema.sql;
conservar credenciales BD/SMTP existentes. Solo actualizar archivos del parche.
La contrasena en texto claro nunca debe subirse a la carpeta web ni a GitHub.

CORRECCION OPERATIVA DE OCTUBRE 2026:
- app.js se carga con un ?v= variable para invalidar la cache del navegador.
- HTTP Basic reenvia Authorization a PHP en FastCGI; includes/.htpasswd nuevo es obligatorio.
- Correo simplificado: asunto Nueva contacto desde GAds. Cuerpo solo cuatro campos.
- La contraseña se facilita por separado del parche y no se guarda en Git.
- NO sobrescribir includes/config.php ni reimportar sql/schema.sql en produccion.
