(() => {
  'use strict';
  if (!window.INNOVA) return;
  const form = document.getElementById('lead-form');
  const status = document.getElementById('form-status');
  const requestKey = 'sem:innova:pending-request';
  let busy = false;
  let completed = false;
  const contacts = new Map();
  if (form) {
    try { const pending = sessionStorage.getItem(requestKey); if (/^[a-f0-9]{32}$/.test(pending || '')) form.elements.request_id.value = pending; } catch (_) {}
  }
  function message(text, type) {
    if (status) { status.textContent = text; status.className = 'form-status ' + type; }
  }
  function fieldErrors(errors = {}) {
    for (const name of ['name', 'phone', 'email', 'privacy']) {
      const field = form?.elements[name];
      const label = document.getElementById('error-' + name);
      if (label) label.textContent = errors[name] || '';
      if (field) field.setAttribute('aria-invalid', errors[name] ? 'true' : 'false');
    }
  }
  function validate() {
    const errors = {};
    if (!form.elements.name.value.trim()) errors.name = 'Indica tu nombre.';
    if (!/^\+?[0-9]{7,15}$/.test(form.elements.phone.value.replace(/[\s().-]/g, '').replace(/^00/, '+'))) errors.phone = 'Revisa el teléfono.';
    if (!form.elements.email.validity.valid || !form.elements.email.value.trim()) errors.email = 'Revisa el correo electrónico.';
    if (!form.elements.privacy.checked) errors.privacy = 'Autoriza el uso de tus datos para atender la solicitud.';
    return errors;
  }
  form?.addEventListener('submit', async event => {
    event.preventDefault();
    if (busy || completed) return;
    fieldErrors();
    const errors = validate();
    if (Object.keys(errors).length) { fieldErrors(errors); form.elements[Object.keys(errors)[0]].focus(); return; }
    const button = form.querySelector('[type=submit]');
    const data = {name: form.elements.name.value, phone: form.elements.phone.value, email: form.elements.email.value,
      privacy: form.elements.privacy.checked, request_id: form.elements.request_id.value, attribution: window.InnovaAds.attribution()};
    try { sessionStorage.setItem(requestKey, data.request_id); } catch (_) {}
    busy = true;
    button.disabled = true;
    button.textContent = 'Enviando solicitud…';
    message('', '');
    try {
      const response = await fetch(form.action, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json'}, body: JSON.stringify(data)});
      let result;
      try { result = await response.json(); } catch (_) { throw new Error('No se ha podido confirmar el envío. Conservamos tus datos; vuelve a intentarlo.'); }
      if (!response.ok || !result.ok || !Number.isSafeInteger(result.lead_id) || result.lead_id <= 0) {
        fieldErrors(result.errors);
        if (result.code === 'idempotency_conflict') {
          form.elements.request_id.value = crypto.randomUUID().replace(/-/g, '');
          try { sessionStorage.removeItem(requestKey); } catch (_) {}
          throw new Error('La solicitud anterior ya se guardó. Pulsa enviar de nuevo para registrar los datos nuevos.');
        }
        throw new Error(result.message || 'No se ha podido confirmar el envío. Vuelve a intentarlo o llámanos.');
      }
      completed = true;
      // Solo BD confirmada; el estado SMTP no participa en la conversión.
      try { await window.InnovaAds.convert('form', result.lead_id, {email: data.email, phone: data.phone}); }
      catch (_) { /* Una incidencia de medición nunca invalida un lead ya guardado. */ }
      message('Solicitud recibida. Nuestro equipo contactará contigo.', 'success');
      button.textContent = 'Solicitud recibida';
      form.reset(); fieldErrors();
      try { sessionStorage.removeItem(requestKey); } catch (_) {}
      status.focus({preventScroll: true});
    } catch (error) {
      message(error.message || 'No se ha podido confirmar el envío. Conservamos tus datos; vuelve a intentarlo.', 'error');
      button.disabled = false;
      button.innerHTML = 'Solicitar mi demo gratuita <span aria-hidden="true">→</span>';
    } finally { busy = false; }
  });
  document.querySelectorAll('[data-form-focus]').forEach(link => link.addEventListener('click', event => {
    if (!form) return;
    event.preventDefault();
    document.getElementById('contacto').scrollIntoView({behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start'});
    setTimeout(() => (completed ? status : form.elements.name).focus({preventScroll: true}), 350);
  }));
  function contact(event) {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || (event.type === 'auxclick' && event.button !== 1)) return;
    const kind = link.href.startsWith('tel:') ? 'phone' : /^https:\/\/(wa\.me|api\.whatsapp\.com)\//.test(link.href) ? 'whatsapp' : null;
    if (!kind) return;
    event.preventDefault();
    // Mismo manejador en todos los CTA; doble clic no genera otro evento ni pestaña.
    if (contacts.has(kind)) return;
    contacts.set(kind, true);
    const newTab = kind === 'whatsapp' || event.ctrlKey || event.metaKey || event.button === 1;
    const popup = newTab ? window.open('about:blank', '_blank') : null;
    if (popup) popup.opener = null;
    let navigated = false;
    let timer;
    const navigate = () => {
      if (navigated) return;
      navigated = true; clearTimeout(timer);
      if (popup && !popup.closed) popup.location.replace(link.href);
      else location.href = link.href;
      setTimeout(() => contacts.delete(kind), 1000);
    };
    timer = setTimeout(navigate, 750);
    try { window.InnovaAds.convert(kind, crypto.randomUUID()).then(navigate, navigate); }
    catch (_) { navigate(); }
  }
  document.addEventListener('click', contact);
  document.addEventListener('auxclick', contact);
})();
