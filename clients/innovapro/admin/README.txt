PANEL PRIVADO INNOVAPRO - DINAHOSTING

1. Ir a Panel de Control > Hosting > Seguridad > Proteccion de carpetas.
2. Proteger EXCLUSIVAMENTE /admin dentro de demo.innovapro.es.
3. Crear usuario y contrasena fuerte.

TEST:
- Sin credenciales, /admin/ debe solicitar autenticacion o denegar acceso.
- Con credenciales, debe aparecer el listado de leads.
- /admin/export.php NO debe permitir descargar leads sin autenticacion.

El PHP del panel requiere REMOTE_USER o REDIRECT_REMOTE_USER. Si
aparece HTTP 403 despues del login, pide al hosting que propague la
identidad de usuario autenticado. No se debe eliminar ese control.

Si no hay opcion en el panel, usar admin/.htaccess.example con ruta
absoluta a un .htpasswd PRIVADO y fuera de la raiz web.

Nunca publicar usuarios, contrasenas ni archivos .htpasswd en Git.
