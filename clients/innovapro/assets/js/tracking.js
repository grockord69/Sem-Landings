/* Google Ads directo. Dedupe técnico sin datos personales; EC solo en formulario. */
(() => {
  'use strict';
  const config = window.INNOVA;
  if (!config) return;
  const sent = new Map();
  const storageKey = 'sem:innova:converted-leads';
  let completed = [];
  try { completed = JSON.parse(sessionStorage.getItem(storageKey) || '[]'); } catch (_) { /* Memoria como respaldo. */ }
  if (!Array.isArray(completed)) completed = [];
  const hash = async value => Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(value))), b => b.toString(16).padStart(2, '0')).join('');
  const normalizePhone = value => {
    let phone = String(value || '').trim().replace(/[\s().-]/g, '').replace(/^00/, '+');
    if (/^[6-9][0-9]{8}$/.test(phone)) phone = '+34' + phone;
    return /^\+[1-9][0-9]{7,14}$/.test(phone) ? phone : '';
  };
  async function enhanced(data) {
    if (!window.InnovaConsent.canUseUserData() || !crypto.subtle || !data?.email) return null;
    let email = data.email.trim().toLowerCase();
    const parts = email.split('@');
    if (parts.length === 2 && ['gmail.com', 'googlemail.com'].includes(parts[1])) email = parts[0].replace(/\./g, '') + '@' + parts[1];
    const userData = {sha256_email_address: await hash(email)};
    const phone = normalizePhone(data.phone);
    if (phone) userData.sha256_phone_number = await hash(phone);
    return window.InnovaConsent.canUseUserData() ? userData : null;
  }
  function convert(kind, id, data = null) {
    const key = kind + ':' + String(id);
    if (sent.has(key)) return sent.get(key);
    if (kind === 'form' && completed.includes(String(id))) return Promise.resolve('duplicate');
    const task = (async () => {
      if (!config.tracking || !config.ads.conversions[kind]) return 'not_configured';
      let userData = null;
      if (kind === 'form') {
        try { userData = await enhanced(data); } catch (_) { /* EC no impide la conversión estándar. */ }
      }
      if (!window.InnovaConsent.canUseUserData()) userData = null;
      return new Promise(resolve => {
        let done = false;
        const finish = state => { if (!done) { done = true; clearTimeout(timer); resolve(state); } };
        const timer = setTimeout(() => finish('timeout'), 700);
        const event = {send_to: config.ads.conversions[kind], event_callback: () => finish('callback'), event_timeout: 600};
        if (kind === 'form') event.transaction_id = String(id);
        const value = Number(config.ads.values?.[kind]);
        if (Number.isFinite(value)) { event.value = value; event.currency = config.ads.currency; }
        try {
          if (userData) window.gtag('set', 'user_data', userData);
          window.gtag('event', 'conversion', event);
          if (kind === 'form') {
            completed = [...completed, String(id)].slice(-100);
            try { sessionStorage.setItem(storageKey, JSON.stringify(completed)); } catch (_) {}
          }
        } catch (_) { finish('script_error'); }
        finally { if (userData) window.gtag('set', 'user_data', {}); }
      });
    })();
    sent.set(key, task);
    return task;
  }
  function attribution() {
    const params = new URLSearchParams(location.search);
    const data = {landing_url: location.href, referrer: document.referrer};
    for (const key of ['gclid', 'wbraid', 'gbraid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content']) {
      const value = params.get(key); if (value) data[key] = value.slice(0, 512);
    }
    return data; // Metadatos de la solicitud actual; sin cookies de atribución propias.
  }
  window.InnovaAds = Object.freeze({convert, attribution, normalizePhone});
})();
