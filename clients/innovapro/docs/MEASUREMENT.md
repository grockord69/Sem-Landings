# Contrato de medición

IDs en config/config.example.php. La URL googletagmanager.com/gtag/js distribuye gtag.js; no instala un contenedor GTM.

consent.js crea dataLayer/gtag, establece cuatro denied, restaura preferencia válida con update y carga Google tag incluso con denied. Aceptar concede solo ad_storage/ad_user_data. Analytics/personalización permanecen denied.

Formulario: POST validado → INSERT → lead_id → SMTP → JSON ok → EC opcional con SHA-256/permiso vigente → conversion con transaction_id y valor 30 EUR → éxito visual. SMTP fallido no cambia el evento. Se vuelve a comprobar consentimiento después de calcular hashes. user_data se limpia tras el evento y no se establece con contacto con denied.

Email: trim/lowercase y sin puntos antes de gmail.com/googlemail.com. Teléfono: quitar separadores, 00→+, nueve dígitos españoles→+34. Si no cumple E.164 se omite solo teléfono EC; no se bloquea el lead o la conversión estándar.

IDs convertidos quedan en memoria/sessionStorage técnico sin PII. BD usa request_id UNIQUE y request_hash. Ese hash sirve a idempotencia, no se envía al navegador/Google. Teléfono y WhatsApp envían 10 EUR por conversión y comparten manejador; popup WhatsApp se abre vacío durante el gesto, luego navega por callback/timeout. Popup bloqueado navega en pestaña actual. Ventana corta evita acciones simultáneas; un clic posterior independiente puede ser otra conversión. Los clics no se guardan como leads.

Metadatos de campaña provienen de la solicitud actual, sin cookie propia de atribución. URL/referrer guardan solo parámetros permitidos, sin fragmentos o parámetros arbitrarios. No introducir PII en URLs de campañas. Privacidad del formulario y decisión publicitaria son independientes. Sin IP en BD; configurar logs del proveedor por separado.

Limitaciones: Google se simula en tests, sin acreditar recepción/atribución. Bloqueadores, fallos de red o JavaScript desactivado pueden impedir medición aunque el lead exista. No se reenvían conversiones desde el servidor ni se reconstruye EC tras denegación. Completar condiciones/configuración de cuenta según [Google](https://support.google.com/google-ads/answer/13258081?hl=es).
