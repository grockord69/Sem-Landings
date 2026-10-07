# InnovaPro · landing SEM simplificada

Landing PHP sin framework ni Composer para `demo.innovapro.es`.

## Estructura

```text
index.php / form.php             Landing y envío
assets/                          CSS, JS e imágenes
includes/                        Configuración y funciones PHP
admin/                           Panel solo lectura protegido por Plesk/Apache
sql/schema.sql                   Tabla de leads
aviso-legal.php
privacidad.php
cookies.php
```

## Instalación

1. Subir el contenido de `clients/innovapro/` al document root del subdominio.
2. Copiar `includes/config.example.php` como `includes/config.php`.
3. Crear BD e importar `sql/schema.sql`.
4. Completar BD, SMTP, destinatarios y legales en `config.php`.
5. Proteger `/admin/` desde Plesk.
6. Ejecutar `php includes/check.php` y la checklist de `docs/DEPLOY.md`.

No necesita Composer ni cron. El formulario guarda en BD antes de SMTP. Un fallo de correo no elimina el lead.

## Medición

- Formulario: `AW-763034950/bu_ECPvC9PkBEMb66-sC` · 30 EUR.
- Teléfono: `AW-763034950/evT9CI2-wfwZEMb66-sC` · 10 EUR.
- WhatsApp: `AW-763034950/a58xCJOfqo8dEMb66-sC` · 10 EUR.
- Consent Mode v2 avanzado.
- Enhanced Conversions únicamente para formularios confirmados y con `ad_user_data=granted`.
