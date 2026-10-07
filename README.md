# SEM Landings

Repositorio de landings de captación SEM.

Cada cliente vive en `clients/<cliente>/` y declara un `client.json` para que GitHub Actions descubra y pruebe la aplicación.

## InnovaPro

`clients/innovapro/` contiene una landing PHP simplificada y lista para alojarse directamente en el document root de `demo.innovapro.es`:

- sin framework;
- sin Composer;
- cliente SMTP propio, sin dependencias;
- MariaDB/MySQL;
- panel protegido por Plesk/Apache;
- Google Ads, Consent Mode y Enhanced Conversions;
- paquete automático listo para subir.
