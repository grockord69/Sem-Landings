# InnovaPro SEM landing

Landing Google Ads para `https://demo.innovapro.es/`, basada en el ZIP aprobado. Se conservan composición, tipografía, bloques, cuatro equipos, imágenes WebP y CTA principal. Sin navegación general, antispam, frameworks frontend, GA4, GTM, otros trackers ni modos de test en la aplicación.

```text
.github/workflows/ci.yml          CI común, descubre clientes
scripts/check_hygiene.py          archivos privados y posibles secretos
clients/innovapro/
  config/config.example.php      cliente, IDs Ads, contactos y legales
  public/                        document root
    index.php                    presentación original
    api/lead.php                 captación JSON
    admin/                       listado, detalle, filtros, CSV, reenvío
    assets/{css,js,img}/          CSS original, vanilla JS, imágenes
    {aviso-legal,privacidad,cookies}.php
  src/
    bootstrap.php, core.php       configuración y utilidades
    Database.php                 PDO MySQL
    LeadRepository.php           persistencia/idempotencia
    LeadService.php              BD → SMTP, estados y locks
    Mailer.php                   PHPMailer SMTP autenticado
    admin.php, view.php           autenticación/CSRF y presentación
  templates/emails/new-lead.php   plantilla de email
  sql/schema.sql                 tabla leads, índices/restricciones
  bin/console.php                checks y hash de contraseña
  deployment/                    ejemplos servidor web
  tests/                         PHP, tracking, BD, SMTP, HTTP, browser
  docs/                          despliegue, checklist y fuentes
  composer.json, composer.lock
```

## Captación

`POST /api/lead.php` recibe `name`, `phone`, `email`, `privacy`, `request_id` (32 caracteres hexadecimales) y `attribution`. Valida, inserta con estado pending, obtiene lead_id, intenta SMTP y devuelve JSON ok=true, lead_id y duplicate. Un fallo SMTP deja error, suma intentos y registra un código seguro. No deshace el INSERT ni devuelve error de formulario por fallo SMTP.

request_id se conserva en reintentos y en la pestaña tras respuesta de red incierta. UNIQUE resuelve carreras concurrentes. Un mismo ID con otro contenido responde 409; el frontend permite iniciar otra solicitud conservando los datos corregidos. No deduplicamos personas por teléfono/email: dos consultas distintas se guardan.

Sin cola ni cron. El lock MySQL por conexión evita notificaciones concurrentes del mismo lead. El panel reenvía solo error, por POST con CSRF. Un pending antiguo refleja una interrupción y puede recuperarse con el comando documentado. SMTP aceptado no garantiza recepción en bandeja.

## Medición

IDs en config, sin duplicaciones en módulos. Consent Mode avanzado establece denied antes de Google. Aceptar concede solo ad_storage/ad_user_data. Rechazar/ignorar permite conversión estándar. EC solo usa email y teléfono SHA-256 con permiso vigente, tras éxito backend. lead_id se usa como transaction_id; memoria/sessionStorage técnico evitan repetir el evento del mismo lead en la sesión.

Un manejador delegado cubre teléfono/WhatsApp, callback y timeout. Doble clic o CTA simultáneos producen una acción. WhatsApp conserva 1.0 EUR. Admin y páginas legales no cargan Google tag.

## Pruebas y reutilización

CI: PHP 8.2/8.4, php -l, Composer validate/audit, sintaxis JS y contratos Node. Integración: MariaDB 11.4, SMTP STARTTLS autenticado y dos destinatarios, error/reenvío/estados. HTTP: API, admin, CSRF, búsqueda, fechas, CSV/escape. Chromium: 320, 390, 768, 1440 px; CMP, conversiones/EC, errores y navegación con/sin callback. Capturas en artifacts de Actions.

```sh
composer install
composer validate --strict
composer audit
php tests/unit.php
node --test tests/tracking.cjs
```

Para integración seguir `.github/workflows/ci.yml`. La BD **desechable sem_ci** se elimina/recrea; el test se niega a usar otro nombre. SEM_CONFIG selecciona una configuración externa como en producción, sin modo test. Google y errores de backend se simulan exclusivamente en la suite.

Copiar a `clients/<cliente>/`, conservar módulos/contratos, sustituir presentación/assets y configurar el cliente. Cambiar namespace/nombres de almacenamiento para evitar colisiones si comparten origen; normalmente cada landing usa origen y BD propios. La CI descubre composer.json. No hacen falta paquetes compartidos ni framework.

Schema para una BD nueva, no migración del ZIP. Si existe una instalación previa con leads, preservarlos y preparar migración antes de cambiar schema.

Documentación: [despliegue](docs/DEPLOY.md), [validación real](docs/PRODUCTION-CHECKLIST.md), [medición](docs/MEASUREMENT.md), [fuentes y pendientes](docs/SOURCES.md).
