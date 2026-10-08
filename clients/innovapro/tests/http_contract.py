"""Contratos HTTP con BD y SMTP reales. Solo usa datos sintéticos."""
import argparse, base64, csv, io, json, re, secrets
from http.cookiejar import CookieJar
from pathlib import Path
from urllib.error import HTTPError
from urllib.request import Request, build_opener, HTTPCookieProcessor

parser = argparse.ArgumentParser()
parser.add_argument('--base', default='http://127.0.0.1:8765')
parser.add_argument('--smtp-dir', required=True)
args = parser.parse_args()
base = args.base
smtp = Path(args.smtp_dir)
auth = 'Basic ' + base64.b64encode(b'qa:any-password-is-validated-by-plesk').decode()
opener = build_opener(HTTPCookieProcessor(CookieJar()))
checks = 0

def request(path, data=None, headers=None, raw=None):
    h = headers or {}
    if data is not None:
        raw = json.dumps(data).encode()
        h = {'Content-Type': 'application/json', 'Accept': 'application/json', 'Origin': base, **h}
    req = Request(base + path, data=raw, headers=h)
    try:
        response = opener.open(req)
    except HTTPError as error:
        response = error
    return response.status, response.headers, response.read().decode('utf-8-sig')

def check(value, message):
    global checks
    assert value, message
    checks += 1
    print('OK ' + message)

status, headers, html = request('/')
check(status == 200 and 'noindex,follow' in html and 'noindex, follow' in headers.get('X-Robots-Tag', ''), 'landing noindex')
check('Content-Security-Policy' in headers and "object-src 'none'" in headers['Content-Security-Policy'], 'CSP emitida')
check('localidad' not in html.lower() and 'name="website"' not in html, 'sin localidad ni honeypot')
check('Compra · Alquiler · Financiación' in html, 'modalidades comerciales visibles')

for path in ['/privacidad.php', '/cookies.php', '/aviso-legal.php']:
    check(request(path)[0] == 200, 'legal ' + path)
status, _, privacy = request('/privacidad.php')
check('artículo 6.1.a' in privacy and 'Conservación' in privacy, 'privacidad con base jurídica y conservación')
check('PENDIENTE DE CONFIRMACIÓN' not in privacy, 'sin placeholders legales publicados')
check('proveedores de alojamiento web' in privacy and 'Google Ireland Limited' in privacy, 'categorías de proveedores y Google')
status, _, cookies = request('/cookies.php')
check('sem_admin' not in cookies and 'Google Ireland Limited' in cookies, 'cookies actualizadas sin sesión PHP del administrador')

for path in ['/includes/config.example.php', '/sql/schema.sql', '/tests/unit.php', '/docs/DEPLOY.md']:
    check(request(path)[0] == 403, 'directorio sensible bloqueado ' + path)

for path in ['/admin/', '/admin/lead.php?id=1', '/admin/export.php']:
    check(request(path)[0] == 403, 'admin rechaza sin auth ' + path)

status, _, listing = request('/admin/', headers={'Authorization': auth})
check(status == 200 and 'Solicitudes' in listing, 'admin acepta usuario autenticado por servidor')

check(request('/form.php')[0] == 405, 'form exige POST')
status, _, body = request('/form.php', {})
check(status == 422 and not json.loads(body)['ok'], 'validación servidor')
status, _, body = request('/form.php', raw=b'{broken', headers={'Content-Type': 'application/json', 'Accept': 'application/json', 'Origin': base})
check(status == 422 and json.loads(body)['code'] == 'json', 'JSON inválido controlado')
status, _, body = request('/form.php', raw=b'hello', headers={'Content-Type': 'text/plain', 'Accept': 'application/json', 'Origin': base})
check(status == 415 and json.loads(body)['code'] == 'content_type', 'content-type no soportado')

valid = {
    'name': '=Prueba <b>HTTP</b>',
    'phone': '600111222',
    'email': 'http@example.test',
    'privacy': True,
    'request_id': secrets.token_hex(16),
    'attribution': {
        'utm_source': 'google',
        'utm_campaign': 'qa-http',
        'gclid': 'http-click',
        'landing_url': base + '/?utm_campaign=qa-http&email=discard',
        'referrer': 'https://example.test/?private=discard',
    },
}
status, _, body = request('/form.php', valid)
saved = json.loads(body)
check(status == 200 and saved['ok'] and saved['lead_id'] > 0, 'lead guardado y SMTP intentado')
status, _, body = request('/form.php', valid)
duplicate = json.loads(body)
check(duplicate['duplicate'] and duplicate['lead_id'] == saved['lead_id'], 'reintento idempotente')
status, _, body = request('/form.php', {**valid, 'email': 'changed@example.test'})
check(status == 409 and json.loads(body)['code'] == 'idempotency_conflict', 'colisión idempotente controlada')

(smtp / 'fail').touch()
failed_payload = {**valid, 'request_id': secrets.token_hex(16), 'email': 'smtp-fail@example.test'}
status, _, body = request('/form.php', failed_payload)
failed = json.loads(body)
(smtp / 'fail').unlink()
check(status == 200 and failed['ok'], 'fallo SMTP no pierde formulario')
status, _, detail = request('/admin/lead.php?id=' + str(failed['lead_id']), headers={'Authorization': auth})
check(status == 200 and 'Error SMTP' in detail and 'SMTP_SEND_FAILED' in detail, 'panel muestra error SMTP')
check('Reenviar' not in detail, 'panel simplificado sin acción de reenvío')

status, _, listing = request('/admin/?q=qa-http&mail=sent', headers={'Authorization': auth})
check(status == 200 and '&lt;b&gt;HTTP&lt;/b&gt;' in listing, 'búsqueda, filtro y escape')
status, _, listing = request('/admin/?from=2099-01-01&to=2099-12-31', headers={'Authorization': auth})
check('No hay solicitudes' in listing, 'filtro por fechas')

status, headers, export = request('/admin/export.php?q=qa-http', headers={'Authorization': auth})
rows = list(csv.DictReader(io.StringIO(export), delimiter=';'))
check(status == 200 and rows and all(row['nombre'].startswith("'=") for row in rows), 'CSV neutraliza fórmulas')
check(all('email=discard' not in row['landing_url'] and 'private=discard' not in row['referrer'] for row in rows), 'URL/referrer eliminan parámetros arbitrarios')

print('TOTAL', checks, 'comprobaciones HTTP')
