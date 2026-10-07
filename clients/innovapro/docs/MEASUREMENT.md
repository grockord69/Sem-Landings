# Medición Google Ads

La landing carga Google Ads directamente mediante `gtag.js`, sin GA4 ni GTM.

## Consent Mode

Antes de cargar Google se establece:

```text
ad_storage = denied
ad_user_data = denied
ad_personalization = denied
analytics_storage = denied
```

- Sin interacción o con rechazo: pings cookieless, sin Enhanced Conversions.
- Con aceptación: `ad_storage` y `ad_user_data` pasan a `granted`; Analytics y personalización permanecen denegados.

## Conversiones

| Acción | Destino | Valor |
| --- | --- | ---: |
| Formulario | `AW-763034950/bu_ECPvC9PkBEMb66-sC` | 30 EUR |
| Clic teléfono | `AW-763034950/evT9CI2-wfwZEMb66-sC` | 10 EUR |
| Clic WhatsApp | `AW-763034950/a58xCJOfqo8dEMb66-sC` | 10 EUR |

El formulario dispara Ads únicamente después de que `form.php` confirme el INSERT. El ID interno se usa como `transaction_id`.

Enhanced Conversions utiliza email y teléfono normalizados y hasheados únicamente cuando `ad_user_data=granted`.
