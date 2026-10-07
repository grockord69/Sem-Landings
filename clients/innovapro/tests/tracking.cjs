'use strict';
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const {webcrypto, createHash} = require('node:crypto');
const {execFileSync} = require('node:child_process');

const config = JSON.parse(execFileSync(process.env.PHP_BINARY || 'php', [
  '-r', "echo json_encode(require 'includes/config.example.php');"
], {encoding: 'utf8'}));
const source = fs.readFileSync('assets/js/tracking.js', 'utf8');

function fixture(granted = false, storage = new Map(), crypto = webcrypto) {
  const calls = [];
  const window = {
    INNOVA: {ads: config.ads, tracking: true},
    InnovaConsent: {canUseUserData: () => granted},
    gtag: (...args) => {
      calls.push(args);
      if (args[0] === 'event') queueMicrotask(args[2].event_callback);
    }
  };
  vm.runInNewContext(source, {
    window, crypto, TextEncoder, URLSearchParams,
    document: {referrer: 'https://example.test/'},
    location: {search: '?utm_campaign=demo&gclid=click', href: 'https://demo.innovapro.es/?utm_campaign=demo&gclid=click'},
    sessionStorage: {getItem: k => storage.get(k) || null, setItem: (k, v) => storage.set(k, v)},
    setTimeout, clearTimeout
  });
  return {ads: window.InnovaAds, calls, revoke: () => { granted = false; }, storage};
}

test('IDs y valores reales centralizados', () => {
  assert.equal(config.ads.id, 'AW-763034950');
  assert.deepEqual(config.ads.conversions, {
    form: 'AW-763034950/bu_ECPvC9PkBEMb66-sC',
    phone: 'AW-763034950/evT9CI2-wfwZEMb66-sC',
    whatsapp: 'AW-763034950/a58xCJOfqo8dEMb66-sC'
  });
  assert.deepEqual(config.ads.values, {form: 30, phone: 10, whatsapp: 10});
  assert.equal(config.ads.currency, 'EUR');
});

test('denied conserva formulario estándar, valor y dedupe', async () => {
  const f = fixture();
  await Promise.all([
    f.ads.convert('form', 12, {email: 'person@example.test', phone: '600111222'}),
    f.ads.convert('form', 12)
  ]);
  const events = f.calls.filter(c => c[0] === 'event');
  assert.equal(events.length, 1);
  assert.equal(events[0][2].transaction_id, '12');
  assert.equal(events[0][2].value, 30);
  assert.equal(events[0][2].currency, 'EUR');
  assert.equal(f.calls.filter(c => c[1] === 'user_data').length, 0);

  const reload = fixture(false, f.storage);
  await reload.ads.convert('form', 12);
  assert.equal(reload.calls.length, 0);
});

test('granted hashea solo email y teléfono normalizados', async () => {
  const f = fixture(true);
  await f.ads.convert('form', 13, {email: ' TEST.Person@GMAIL.COM ', phone: '600 111 222', name: 'never sent'});
  const data = f.calls.find(c => c[1] === 'user_data')[2];
  assert.equal(data.sha256_email_address, createHash('sha256').update('testperson@gmail.com').digest('hex'));
  assert.equal(data.sha256_phone_number, createHash('sha256').update('+34600111222').digest('hex'));
  assert.deepEqual(Object.keys(data).sort(), ['sha256_email_address', 'sha256_phone_number']);
});

test('revocación durante hash omite EC pero conserva conversión', async () => {
  let f;
  const crypto = {subtle: {digest: async (...args) => {
    f.revoke();
    return webcrypto.subtle.digest(...args);
  }}};
  f = fixture(true, new Map(), crypto);
  await f.ads.convert('form', 14, {email: 'person@example.test', phone: '600111222'});
  assert.equal(f.calls.filter(c => c[1] === 'user_data').length, 0);
  assert.equal(f.calls.filter(c => c[0] === 'event').length, 1);
});

test('teléfono y WhatsApp envían 10 EUR sin EC', async () => {
  const f = fixture(true);
  await f.ads.convert('phone', 'phone-1');
  await f.ads.convert('whatsapp', 'wa-1');
  const events = f.calls.filter(c => c[0] === 'event');
  assert.equal(events[0][2].value, 10);
  assert.equal(events[0][2].currency, 'EUR');
  assert.equal(events[1][2].value, 10);
  assert.equal(events[1][2].currency, 'EUR');
  assert.equal(f.calls.filter(c => c[1] === 'user_data').length, 0);
});

test('sin callback termina mediante timeout', async () => {
  const window = {INNOVA: {ads: config.ads, tracking: true}, InnovaConsent: {canUseUserData: () => false}, gtag: () => {}};
  vm.runInNewContext(source, {
    window, crypto: webcrypto, TextEncoder, URLSearchParams,
    document: {referrer: ''}, location: {search: '', href: 'https://demo.innovapro.es/'},
    sessionStorage: {getItem: () => null, setItem: () => {}}, setTimeout, clearTimeout
  });
  assert.equal(await window.InnovaAds.convert('phone', 'timeout'), 'timeout');
});
