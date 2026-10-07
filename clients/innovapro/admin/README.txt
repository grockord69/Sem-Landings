PROTECCIÓN DEL PANEL

Protege el directorio /admin/ desde Plesk:

1. Websites & Domains / Sitios web y dominios.
2. Password-Protected Directories / Directorios protegidos con contraseña.
3. Añade /admin y crea un usuario con contraseña fuerte.

El PHP del panel no implementa un segundo login. Como medida de seguridad, devuelve 403 si el servidor no le informa de un usuario autenticado mediante REMOTE_USER.

No subas archivos .htpasswd al repositorio.
