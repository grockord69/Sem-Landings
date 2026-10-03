# SEM Landings

Repositorio base para landings de captación SEM de clientes.

Cada cliente vive en `clients/<cliente>/` con su código, documentación y pruebas. La CI se ejecuta con GitHub Actions sobre las landings incluidas.

## Clientes

- `clients/innovapro/` — landing de depilación profesional para `demo.innovapro.es`.

Consulta [arquitectura y pruebas](clients/innovapro/README.md), [despliegue](clients/innovapro/docs/DEPLOY.md) y [validación final](clients/innovapro/docs/PRODUCTION-CHECKLIST.md).

La CI descubre cada `clients/*/composer.json`. Una nueva landing puede copiar el directorio de cliente y conservar el mismo contrato de tests; no requiere herramientas de monorepo.
