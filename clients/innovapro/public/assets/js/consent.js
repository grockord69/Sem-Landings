/* CMP mínimo: Consent Mode v2 avanzado, siempre denied antes de Google. */
(() => {
  'use strict';
  const node = document.getElementById('innova-config');
  if (!node) return;
  let config;
  try { config = JSON.parse(node.textContent); } catch (_) { return; }
  window.INNOVA = config;
  const settings = config.consent;
  const cookieName = 'innova_consent';
  const maxAge = Math.min(365, Math.max(1, Number(settings.days) || 365)) * 86400;
  const banner = document.getElementById('consent-banner');
  const close = banner?.querySelector('[data-consent-close]');
  let lastFocus = null;
  let loaded = false;
  let state = {status: 'pending', version: settings.version, decided_at: ''};
  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
  const rawCookie = () => {
    try { return document.cookie.split(';').map(v => v.trim()).find(v => v.startsWith(cookieName + '='))?.slice(cookieName.length + 1) || ''; }
    catch (_) { return ''; }
  };
  const parseCookie = raw => {
    try {
      const v = JSON.parse(decodeURIComponent(raw));
      if (v.v !== settings.version || !['accepted', 'rejected'].includes(v.s) || !Number.isFinite(v.t) || v.t > Date.now() || Date.now() - v.t > maxAge * 1000) return null;
      return {status: v.s, version: v.v, decided_at: new Date(v.t).toISOString()};
    } catch (_) { return null; }
  };
  let seenCookie = rawCookie();
  const saved = parseCookie(seenCookie);
  function payload(accepted) {
    return {ad_storage: accepted ? 'granted' : 'denied', ad_user_data: accepted ? 'granted' : 'denied',
      ad_personalization: 'denied', analytics_storage: 'denied', functionality_storage: 'denied',
      personalization_storage: 'denied', security_storage: 'granted'};
  }
  // SIEMPRE default denied antes de cualquier config o descarga de Google.
  window.gtag('consent', 'default', {...payload(false), wait_for_update: 500});
  window.gtag('set', 'allow_ad_personalization_signals', false);
  function loadGoogle() {
    if (loaded || !config.tracking || !/^AW-\d+$/.test(config.ads.id || '')) return;
    loaded = true;
    window.gtag('js', new Date());
    window.gtag('config', config.ads.id, {
      allow_google_signals: false, allow_ad_personalization_signals: false,
      cookie_domain: location.hostname, cookie_expires: 7776000,
      allow_enhanced_conversions: true
    });
    const script = document.createElement('script');
    script.async = true;
    if (node.nonce) script.setAttribute('nonce', node.nonce);
    script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(config.ads.id);
    script.dataset.innovaGoogle = '1';
    script.addEventListener('error', () => { window.INNOVA_GOOGLE_ERROR = true; });
    document.head.appendChild(script);
  }
  function sizeBanner() {
    document.documentElement.style.setProperty('--cmp-height', banner && !banner.hidden ? banner.getBoundingClientRect().height + 'px' : '0px');
    const rail = document.querySelector('.mobile-contact');
    document.documentElement.style.setProperty('--rail-height', rail && getComputedStyle(rail).display !== 'none' ? rail.getBoundingClientRect().height + 'px' : '0px');
  }
  function show(reopen = false) {
    if (!banner) return;
    banner.hidden = false;
    if (close) close.hidden = !reopen;
    if (reopen) { lastFocus = document.activeElement; banner.querySelector('[data-consent-reject]')?.focus({preventScroll: true}); }
    sizeBanner();
  }
  function hide() {
    if (banner) banner.hidden = true;
    sizeBanner();
    lastFocus?.focus?.({preventScroll: true}); lastFocus = null;
  }
  function purgeAdsCookies() {
    // Solo cookies de Ads accesibles en ESTE host. No manipular cookies de la web corporativa.
    let names = [];
    try { names = document.cookie.split(';').map(c => c.trim().split('=')[0]).filter(n => /^_gcl_|^_gac_/.test(n)); } catch (_) { return; }
    for (const name of names) for (const domain of ['', ';Domain=' + location.hostname]) {
      try { document.cookie = name + '=;Max-Age=0;Path=/;SameSite=Lax' + domain + (location.protocol === 'https:' ? ';Secure' : ''); } catch (_) {}
    }
  }
  function apply(status, persist) {
    state = {status, version: settings.version, decided_at: new Date().toISOString()};
    window.gtag('consent', 'update', payload(status === 'accepted'));
    if (status !== 'accepted') { window.gtag('set', 'user_data', {}); purgeAdsCookies(); }
    if (persist) {
      const value = encodeURIComponent(JSON.stringify({v: settings.version, s: status, t: Date.now()}));
      try { document.cookie = cookieName + '=' + value + ';Max-Age=' + maxAge + ';Path=/;SameSite=Lax' + (location.protocol === 'https:' ? ';Secure' : ''); } catch (_) {}
      seenCookie = rawCookie();
    }
    loadGoogle();
    window.dispatchEvent(new CustomEvent('innova:consent', {detail: {...state}}));
  }
  function sync() {
    const current = rawCookie();
    if (current === seenCookie && !(state.status !== 'pending' && Date.now() - Date.parse(state.decided_at) > maxAge * 1000)) return;
    seenCookie = current;
    const other = parseCookie(current);
    if (other) { apply(other.status, false); state.decided_at = other.decided_at; hide(); }
    else { apply('pending', false); show(); }
  }
  if (saved) { state = saved; window.gtag('consent', 'update', payload(saved.status === 'accepted')); }
  loadGoogle();
  const description = document.getElementById('consent-description');
  if (description) {
    description.textContent = 'Utilizamos Google Ads para medir qué anuncios generan contactos y mejorar nuestras campañas. Sin aceptar enviamos señales sin cookies publicitarias. Al aceptar y enviar el formulario, usamos también datos de contacto con hash para conversiones mejoradas.';
  }
  banner?.querySelector('[data-consent-accept]')?.addEventListener('click', () => { apply('accepted', true); hide(); });
  banner?.querySelector('[data-consent-reject]')?.addEventListener('click', () => { apply('rejected', true); hide(); });
  close?.addEventListener('click', hide);
  document.querySelectorAll('[data-consent-open]').forEach(b => b.addEventListener('click', () => show(true)));
  document.addEventListener('visibilitychange', () => { if (!document.hidden) sync(); });
  window.addEventListener('pageshow', sync);
  window.addEventListener('resize', sizeBanner);
  if (window.ResizeObserver && banner) new ResizeObserver(sizeBanner).observe(banner);
  if (!saved) show(); else hide();
  window.InnovaConsent = Object.freeze({
    get: () => { sync(); return {...state}; },
    open: () => show(true),
    canMeasure: () => config.tracking,
    canUseUserData: () => { sync(); return state.status === 'accepted'; }
  });
})();
