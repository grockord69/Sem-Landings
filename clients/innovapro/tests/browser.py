"""Playwright sobre la landing real; Google y la respuesta de form.php se mockean en navegador."""
import argparse, hashlib, json, os
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

spy = """
window.__qa={calls:[],callback:true};
window.dataLayer=[];
window.gtag=function(...args){
  window.dataLayer.push(args);
  window.__qa.calls.push(args);
  if(args[0]==='event'&&window.__qa.callback){setTimeout(()=>args[2].event_callback?.(),10);}
};
window.__csp=[];
document.addEventListener('securitypolicyviolation',e=>window.__csp.push(e.violatedDirective));
"""

def check(condition, message):
    assert condition, message
    checks.append(message)
    print('OK ' + message)

def fill(page):
    page.locator('#name').fill('Prueba Browser')
    page.locator('#phone').fill('600111222')
    page.locator('#email').fill('PERSON@EXAMPLE.TEST')
    page.locator('#privacy').check()

def conversions(page):
    return page.evaluate("__qa.calls.filter(c=>c[0]==='event'&&c[1]==='conversion').map(c=>c[2])")

with sync_playwright() as pl:
    launch = {}
    if os.environ.get('PLAYWRIGHT_CHROMIUM_EXECUTABLE'):
        launch['executable_path'] = os.environ['PLAYWRIGHT_CHROMIUM_EXECUTABLE']
    browser = pl.chromium.launch(**launch)
    contexts = []

    def fresh(width=1440):
        context = browser.new_context(viewport={'width': width, 'height': 950 if width > 760 else 844})
        contexts.append(context)
        context.add_init_script(spy)

        def external(route):
            host = urlsplit(route.request.url).netloc
            if host == urlsplit(base).netloc:
                route.continue_()
            elif 'gtag/js' in route.request.url:
                route.fulfill(status=200, content_type='application/javascript', body='/* Google mocked */')
            else:
                route.fulfill(status=200, content_type='text/html', body='<!doctype html><title>External fixture</title>')

        context.route('**/*', external)
        page = context.new_page()
        page.goto(base + '/?gclid=browser-click&utm_campaign=browser-demo')
        page.wait_for_function('() => window.InnovaAds && window.InnovaConsent')
        return page

    for width in [320, 390, 768, 1440]:
        page = fresh(width)
        check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'), f'sin overflow {width}px')
        check(page.locator('#lead-form input:not([type=hidden])').count() == 4, f'tres campos y RGPD {width}px')
        check(page.evaluate("InnovaConsent.get().status==='pending'"), f'ignorar CMP mantiene pending/denied {width}px')
        page.screenshot(path=str(out / f'{width}-cmp.png'))
        page.locator('[data-consent-reject]').click()
        page.screenshot(path=str(out / f'{width}-landing.png'), full_page=True)
        check(page.evaluate('__csp.length===0'), f'CSP sin violaciones {width}px')
        page.close()

    for consent in ['pending', 'rejected', 'accepted']:
        page = fresh()
        if consent == 'rejected':
            page.locator('[data-consent-reject]').click()
        elif consent == 'accepted':
            page.locator('[data-consent-accept]').click()

        calls = page.evaluate('__qa.calls')
        default = calls[0]
        check(default[:2] == ['consent', 'default'], 'default consent antes de Google: ' + consent)
        check(all(default[2][key] == 'denied' for key in ['ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage']), 'cuatro denied iniciales: ' + consent)
        check(page.locator('script[data-innova-google]').count() == 1, 'Google carga en Consent Mode avanzado: ' + consent)

        pending = []
        page.route('**/form.php', lambda route: pending.append(route))
        fill(page)
        page.locator('.form-submit').click()
        page.wait_for_timeout(100)
        check(not conversions(page) and len(pending) == 1, 'sin conversión antes del backend: ' + consent)

        pending[0].fulfill(status=200, content_type='application/json', body=json.dumps({'ok': True, 'lead_id': 42, 'duplicate': False}))
        page.wait_for_function("() => document.getElementById('form-status').classList.contains('success')")
        events = conversions(page)
        check(len(events) == 1 and events[0]['transaction_id'] == '42', 'una conversión con lead_id: ' + consent)
        check(events[0]['value'] == 30 and events[0]['currency'] == 'EUR', 'formulario 30 EUR: ' + consent)

        user_data = page.evaluate("__qa.calls.filter(c=>c[0]==='set'&&c[1]==='user_data'&&Object.keys(c[2]).length)")
        if consent == 'accepted':
            check(len(user_data) == 1, 'EC solo con aceptación')
            check(user_data[0][2]['sha256_email_address'] == hashlib.sha256(b'person@example.test').hexdigest(), 'hash email correcto')
            check(user_data[0][2]['sha256_phone_number'] == hashlib.sha256(b'+34600111222').hexdigest(), 'hash teléfono correcto')
        else:
            check(not user_data, 'sin EC con ' + consent)
        page.close()

    page = fresh()
    page.route('**/form.php', lambda route: route.fulfill(status=503, content_type='application/json', body='{"ok":false,"message":"Fallo backend simulado"}'))
    fill(page)
    page.locator('.form-submit').click()
    page.wait_for_function("() => document.getElementById('form-status').classList.contains('error')")
    check(not conversions(page), 'error backend no convierte')
    check(page.locator('#email').input_value() == 'PERSON@EXAMPLE.TEST', 'error conserva datos')
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
            page.evaluate("selector=>{const links=document.querySelectorAll(selector);links[0].click();links[1].click();}", selector)
            page.wait_for_timeout(950)
            events = conversions(page)
            check(len(events) == 1, f'{kind} un disparo, callback={callback}')
            check(events[0]['value'] == 10 and events[0]['currency'] == 'EUR', f'{kind} 10 EUR')
            check(not page.evaluate("__qa.calls.some(c=>c[1]==='user_data'&&Object.keys(c[2]).length)"), f'{kind} sin EC')
            if kind == 'whatsapp':
                popups = [p for p in page.context.pages if p != page]
                check(len(popups) == 1 and popups[0].url.startswith('https://wa.me/'), f'WhatsApp abre una vez, callback={callback}')
            else:
                check(len([url for url in destinations if url.startswith('tel:')]) == 1, f'teléfono abre una vez, callback={callback}')
            page.close()

    page = fresh()
    page.locator('[data-consent-accept]').click()
    page.reload()
    page.wait_for_function("() => InnovaConsent.get().status==='accepted'")
    check(page.locator('#consent-banner').is_hidden(), 'preferencia CMP persistida')
    page.locator('[data-consent-open]').click()
    page.locator('[data-consent-reject]').click()
    update = page.evaluate("__qa.calls.filter(c=>c[0]==='consent').at(-1)[2]")
    check(all(update[key] == 'denied' for key in ['ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage']), 'footer revoca a denied')

    for context in contexts:
        context.close()
    browser.close()

(out / 'browser-results.json').write_text(json.dumps({
    'passed': len(checks),
    'checks': checks,
    'scope': 'DOM/CMP/tracking con Google y form.php simulados; BD/SMTP/HTTP se verifican por separado.'
}, ensure_ascii=False, indent=2), encoding='utf-8')
print('TOTAL', len(checks), 'comprobaciones browser')
