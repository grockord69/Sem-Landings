"""Playwright sobre HTML/CSS/JS y CSP reales. Google y API de estas pruebas se mockean en la suite."""
import argparse, hashlib, json, time
from pathlib import Path
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright

parser = argparse.ArgumentParser()
parser.add_argument('--base', default='http://127.0.0.1:8765')
parser.add_argument('--out', required=True)
args = parser.parse_args()
base = args.base
out = Path(args.out)
out.mkdir(parents=True, exist_ok=True)
checks = []
spy = """window.__qa={calls:[],callback:true};window.dataLayer=[];
window.gtag=function(...args){window.dataLayer.push(args);window.__qa.calls.push(args);
if(args[0]==='event'&&window.__qa.callback)setTimeout(()=>args[2].event_callback?.(),10);};
window.__csp=[];document.addEventListener('securitypolicyviolation',e=>__csp.push(e.violatedDirective));"""

def check(condition, message):
    assert condition, message
    checks.append(message)
    print('OK ' + message)

def fill(page):
    page.locator('#name').fill('Prueba Browser')
    page.locator('#phone').fill('600111222')
    page.locator('#email').fill('PERSON@EXAMPLE.TEST')
    page.locator('#privacy').check()

def events(page):
    return page.evaluate("__qa.calls.filter(c=>c[0]==='event'&&c[1]==='conversion').map(c=>c[2])")

with sync_playwright() as pl:
    browser = pl.chromium.launch()
    contexts = []
    def fresh(width=1440):
        context = browser.new_context(viewport={'width': width, 'height': 950 if width > 760 else 844})
        contexts.append(context)
        context.add_init_script(spy)
        def external(route):
            if urlsplit(route.request.url).netloc == urlsplit(base).netloc:
                route.continue_()
            else:
                route.fulfill(status=200, content_type='application/javascript' if 'gtag/js' in route.request.url else 'text/html', body='/* Google mocked by test suite */' if 'gtag/js' in route.request.url else '<!doctype html><title>External navigation fixture</title>')
        context.route('**/*', external)
        page = context.new_page()
        page.goto(base + '/?gclid=browser-click&utm_campaign=browser-demo')
        page.wait_for_function('() => window.InnovaAds && window.InnovaConsent')
        return page
    for width in [320, 390, 768, 1440]:
        page = fresh(width)
        check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'), f'responsive sin overflow {width}px')
        check(page.locator('#lead-form input:not([type=hidden])').count() == 4, f'tres campos y consentimiento {width}px')
        check(page.evaluate("InnovaConsent.get().status==='pending'"), f'ignorar CMP sigue denied {width}px')
        page.screenshot(path=str(out / f'{width}-cmp.png'))
        page.locator('[data-consent-reject]').click()
        page.screenshot(path=str(out / f'{width}-landing.png'), full_page=True)
        check(page.evaluate('__csp.length===0'), f'scripts y assets compatibles con CSP {width}px')
        page.close()
    for consent in ['pending', 'rejected', 'accepted']:
        page = fresh()
        if consent != 'pending':
            page.locator('[data-consent-' + ('accept' if consent == 'accepted' else 'reject') + ']').click()
        calls = page.evaluate('__qa.calls')
        default = calls[0]
        check(default[:2] == ['consent','default'] and all(default[2][key] == 'denied' for key in ['ad_storage','ad_user_data','ad_personalization','analytics_storage']), 'default denied antes de config: ' + consent)
        check(page.locator('script[data-innova-google]').count() == 1, 'Google carga con cualquier consentimiento: ' + consent)
        check(all(c[2]['ad_personalization'] == 'denied' and c[2]['analytics_storage'] == 'denied' for c in calls if c[0] == 'consent'), 'sin Analytics ni personalización: ' + consent)
        check(not any(c[0:2] == ['set','ads_data_redaction'] for c in calls), 'no ads_data_redaction: ' + consent)
        pending = []
        page.route('**/api/lead.php', lambda route: pending.append(route))
        fill(page)
        page.locator('.form-submit').click()
        page.wait_for_timeout(100)
        check(not events(page) and len(pending) == 1, 'sin conversión antes de backend: ' + consent)
        page.locator('#lead-form').evaluate("f=>f.dispatchEvent(new Event('submit',{cancelable:true,bubbles:true}))")
        check(len(pending) == 1, 'doble submit bloqueado: ' + consent)
        pending[0].fulfill(status=200, content_type='application/json', body=json.dumps({'ok':True,'lead_id':42,'duplicate':False}))
        page.wait_for_function("() => document.getElementById('form-status').classList.contains('success')")
        ev = events(page)
        config = page.evaluate('INNOVA.ads')
        check(len(ev) == 1 and ev[0]['send_to'] == config['conversions']['form'] and ev[0]['transaction_id'] == '42', 'form una conversión con lead_id: ' + consent)
        user_data = page.evaluate("__qa.calls.filter(c=>c[0]==='set'&&c[1]==='user_data'&&Object.keys(c[2]).length)")
        if consent == 'accepted':
            check(len(user_data) == 1 and user_data[0][2]['sha256_email_address'] == hashlib.sha256(b'person@example.test').hexdigest() and user_data[0][2]['sha256_phone_number'] == hashlib.sha256(b'+34600111222').hexdigest(), 'EC granted normalizadas, hash email/teléfono')
        else:
            check(not user_data, 'sin EC con ' + consent)
        check(page.locator('#email').input_value() == '', 'limpia formulario tras éxito: ' + consent)
        check(page.evaluate('__csp.length===0'), 'CSP en flujo formulario: ' + consent)
        page.close()
    page = fresh()
    attempts = []
    def failing_then_success(route):
        attempts.append(route.request.post_data_json)
        route.fulfill(status=503 if len(attempts) == 1 else 200, content_type='application/json', body=json.dumps({'ok':False,'message':'Fallo backend simulado'} if len(attempts) == 1 else {'ok':True,'lead_id':43,'duplicate':True}))
    page.route('**/api/lead.php', failing_then_success)
    page.locator('.form-submit').click()
    check(not events(page) and not attempts, 'validación frontend no envía ni convierte')
    fill(page)
    page.locator('.form-submit').click()
    page.wait_for_function("() => document.getElementById('form-status').classList.contains('error')")
    check(not events(page) and page.locator('#email').input_value() == 'PERSON@EXAMPLE.TEST' and page.locator('.form-submit').is_enabled(), 'error backend conserva datos, botón y cero conversiones')
    page.locator('.form-submit').click()
    page.wait_for_function("() => document.getElementById('form-status').classList.contains('success')")
    check(attempts[0]['request_id'] == attempts[1]['request_id'] and len(events(page)) == 1, 'reintento idempotente emite una conversión')
    page.goto(base)
    page.wait_for_function('() => window.InnovaAds')
    page.route('**/api/lead.php', lambda route: route.fulfill(status=200, content_type='application/json', body='{"ok":true,"lead_id":43,"duplicate":true}'))
    fill(page); page.locator('.form-submit').click()
    page.wait_for_function("() => document.getElementById('form-status').classList.contains('success')")
    check(not events(page), 'mismo lead_id después de recarga no convierte otra vez')
    page.close()
    page = fresh()
    attempts = []
    def conflict_then_success(route):
        attempts.append(route.request.post_data_json)
        route.fulfill(status=409 if len(attempts) == 1 else 200, content_type='application/json', body=json.dumps({'ok':False,'code':'idempotency_conflict'} if len(attempts) == 1 else {'ok':True,'lead_id':44}))
    page.route('**/api/lead.php', conflict_then_success)
    fill(page); page.locator('.form-submit').click()
    page.wait_for_function("() => document.getElementById('form-status').classList.contains('error')")
    check(not events(page) and page.locator('#email').input_value() == 'PERSON@EXAMPLE.TEST', 'conflicto idempotente conserva nuevos datos')
    page.locator('.form-submit').click()
    page.wait_for_function("() => document.getElementById('form-status').classList.contains('success')")
    check(attempts[0]['request_id'] != attempts[1]['request_id'] and len(events(page)) == 1, 'conflicto inicia otra solicitud sin bloquear contacto legítimo')
    page.close()
    for callback in [True, False]:
        for kind in ['phone', 'whatsapp']:
            page = fresh()
            page.evaluate('value=>__qa.callback=value', callback)
            destinations = []
            cdp = page.context.new_cdp_session(page)
            cdp.send('Page.enable')
            cdp.on('Page.frameRequestedNavigation', lambda event: destinations.append(event['url']))
            selector = 'a[href^="tel:"]' if kind == 'phone' else 'a[href^="https://wa.me/"]'
            # Dos CTA simultáneos: una conversión y una navegación.
            page.evaluate("selector=>{const links=document.querySelectorAll(selector);links[0].click();links[1].click();}", selector)
            page.wait_for_timeout(950)
            ev = events(page)
            config = page.evaluate('INNOVA.ads')
            check(len(ev) == 1 and ev[0]['send_to'] == config['conversions'][kind], f'{kind} manejador único, callback={callback}')
            if kind == 'whatsapp':
                check(ev[0]['value'] == 1 and ev[0]['currency'] == 'EUR', 'WhatsApp mantiene valor/moneda')
                popups = [p for p in page.context.pages if p != page]
                check(len(popups) == 1 and popups[0].url.startswith('https://wa.me/'), f'WhatsApp navega una vez, callback={callback}')
            else:
                check(len([url for url in destinations if url.startswith('tel:')]) == 1, f'teléfono navega una vez, callback={callback}')
            check(not page.evaluate("__qa.calls.some(c=>c[1]==='user_data'&&Object.keys(c[2]).length)"), 'CTA secundarios sin EC')
            page.close()
    page = fresh()
    page.locator('[data-consent-accept]').click()
    page.reload()
    page.wait_for_function("() => InnovaConsent.get().status==='accepted'")
    check(page.locator('#consent-banner').is_hidden(), 'elección persistida en cookie real')
    page.locator('[data-consent-open]').click()
    page.locator('[data-consent-reject]').click()
    update = page.evaluate("__qa.calls.filter(c=>c[0]==='consent').at(-1)[2]")
    check(all(update[key] == 'denied' for key in ['ad_storage','ad_user_data','ad_personalization','analytics_storage']), 'footer permite revocar a denied')
    check(page.context.cookies()[0]['name'] == 'innova_consent', 'cookie técnica del CMP real')
    for context in contexts:
        context.close()
    browser.close()
(out / 'browser-results.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'scope':'Servidor PHP/CSP/DOM/cookies reales. API/Google simulados desde Playwright. BD/SMTP verificados separadamente.'},ensure_ascii=False,indent=2),encoding='utf-8')
print('TOTAL', len(checks), 'comprobaciones browser')
