# Validación final de producción

Actions comprueba contratos; no certifica Google Ads, entrega real SMTP, HTTPS o CSP del hosting. Completar después de instalar config real y antes de abrir campañas.

## Hosting/panel

- [ ] HTTPS/certificado correcto, HTTP redirigido y release esperado.
- [ ] Raíz clients/innovapro/public; config/src/vendor/sql no descargables.
- [ ] php bin/console.php check termina con 0.
- [ ] noindex,follow y sin sitemap añadido.
- [ ] /admin/ protegido: listado, detalle, búsqueda, fechas y CSV.
- [ ] Proveedores/ubicación/conservación/buzón privacidad confirmados; sin texto PENDIENTE en legales.
- [ ] WhatsApp atendido y destinatarios/remitente SMTP confirmados.
- [ ] Responsive revisado en móvil real y formulario como CTA prioritario.

## Tag Assistant y CMP

Usar perfiles limpios, revisar consola/red sin bloqueadores. En Google Ads habilitar conversiones mejoradas, aceptar sus términos y configurar suministro explícito mediante código. Desactivar detección automática/form interactions para mantener EC exclusivamente en formulario confirmado; no añadir selectores que recojan datos antes del éxito.

| Escenario | ad_storage | ad_user_data | ad_personalization | analytics_storage |
| --- | --- | --- | --- | --- |
| Sin tocar CMP | denied | denied | denied | denied |
| Rechazar | denied | denied | denied | denied |
| Aceptar | granted | granted | denied | denied |
| Aceptar → footer → Rechazar | denied | denied | denied | denied |

- [ ] Default denied antes de config/event y carga de Google tag.
- [ ] Tag carga también al ignorar/rechazar; Consent Mode avanzado.
- [ ] Denied permite ping estándar/cookieless, sin email/teléfono ni hashes.
- [ ] Aceptación permite SHA-256 de email/teléfono E.164 solo tras guardar formulario. Sin nombre/dirección.
- [ ] Sin ads_data_redaction=true, GA4, GTM u otros trackers.
- [ ] Sin errores CSP de dominios Google reales del hosting.
- [ ] Preferencia persistida y footer permite cambiarla.

## Tres conversiones

- [ ] Formulario válido: un evento tras confirmar BD, transaction_id igual al ID interno. Nada ante validación/error BD ni al pulsar antes del éxito.
- [ ] Doble clic/reintento incierto: un lead y una conversión en la sesión lógica.
- [ ] Teléfono: un evento y apertura tel:, también sin callback.
- [ ] WhatsApp: un evento con 1.0 EUR y apertura, también sin callback.
- [ ] Repetir CTA de header, formulario, footer/barra móvil sin disparos duplicados.
- [ ] Destinos coinciden con ads.conversions. Contrastar diagnósticos/recepción en Ads, registrar hora/evidencia; la atribución puede tardar.

## SMTP y lead

- [ ] Enviar solicitud real controlada: comprobar ID/fecha/contacto/RGPD/campaña/identificadores/URL en BD y panel.
- [ ] Todos los destinatarios reciben email con ID, hora Europe/Madrid y campaña. Reply-To apunta al solicitante.
- [ ] email_status=sent, email_attempts=1 y email_sent_at informado.
- [ ] En ventana controlada provocar rechazo SMTP desde config privada: éxito frontend, único lead guardado y error/código seguro en panel.
- [ ] Restaurar SMTP y reenviar desde admin con CSRF: mismo lead, sent e intentos incrementados.
- [ ] Reenvío no inserta ni emite Ads; admin no carga tag.
- [ ] Comprobar SPF/DKIM/DMARC y spam con proveedor si recepción falla.
- [ ] Suprimir/clasificar el lead de prueba conforme a la política, sin convertirlo en contacto comercial real.
