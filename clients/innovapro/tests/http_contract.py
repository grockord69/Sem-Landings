"""Contratos HTTP con BD y SMTP reales. Solo usa datos sintéticos."""
import argparse, base64, csv, io, json, re, secrets
from http.cookiejar import CookieJar
from pathlib import Path
from urllib.error import HTTPError
from urllib.request import Request, build_opener, HTTPCookieProcessor
from urllib.parse import urlencode

parser = argparse.ArgumentParser()
parser.add_argument('--base', default='http://127.0.0.1:8765')
parser.add_argument('--smtp-dir', required=True)
args = parser.parse_args()
base = args.base
smtp = Path(args.smtp_dir)
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
check(re.search(r'/assets/js/app\.js\?v=\d+', html) is not None, 'form JS con version distinta a ruta cacheada')

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

check(request('/admin/')[0] == 403, 'ruta /admin antigua bloqueada')
for path in ['/leadspanel/', '/leadspanel/lead.php?id=1', '/leadspanel/export.php']:
    status, headers, body = request(path)
    check(status == 200 and 'Acceso al panel de leads' in body and 'text/html' in headers.get('Content-Type', ''),
          'rutas privadas solo muestran login sin sesión: ' + path)
status, headers, login = request('/leadspanel/')
csrf_match = re.search(r'name="csrf" value="([a-f0-9]{64})"', login)
check(csrf_match is not None, 'login protege POST mediante token CSRF')
csrf = csrf_match.group(1)
form_headers = {'Content-Type': 'application/x-www-form-urlencoded'}
status, _, wrong = request('/leadspanel/', raw=urlencode({
    'csrf': csrf, 'panel_login': '1', 'username': 'leadsmanager', 'password': 'wrong-password'
}).encode(), headers=form_headers)
check(status == 200 and 'Usuario o contraseña incorrectos.' in wrong, 'credenciales falsas rechazadas')
status, _, listing = request('/leadspanel/', raw=urlencode({
    'csrf': csrf, 'panel_login': '1', 'username': 'leadsmanager', 'password': 'qa-admin-test-only'
}).encode(), headers=form_headers)
check(status == 200 and 'Solicitudes' in listing, 'login correcto abre el panel con sesión')
check(request('/includes/.htpasswd')[0] == 403, 'hash no descargable por navegador')

check(request('/form.php')[0] == 405, 'form exige POST')
status, _, blocked = request('/form.php', {}, headers={'Origin': 'https://evil.example'})
check(status == 403 and json.loads(blocked)['code'] == 'origin', 'origen externo recibe 403')
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
status, _, detail = request('/leadspanel/lead.php?id=' + str(failed['lead_id']))
check(status == 200 and 'Error SMTP' in detail and 'SMTP_SEND_FAILED' in detail, 'panel muestra error SMTP')
check('Reenviar' not in detail, 'panel simplificado sin acción de reenvío')

status, _, listing = request('/leadspanel/?q=qa-http&mail=sent')
check(status == 200 and '&lt;b&gt;HTTP&lt;/b&gt;' in listing, 'búsqueda, filtro y escape')
status, _, listing = request('/leadspanel/?from=2099-01-01&to=2099-12-31')
check('No hay solicitudes' in listing, 'filtro por fechas')

status, headers, export = request('/leadspanel/export.php?q=qa-http')
rows = list(csv.DictReader(io.StringIO(export), delimiter=';'))
check(status == 200 and rows and all(row['nombre'].startswith("'=") for row in rows), 'CSV neutraliza fórmulas')
check(all('email=discard' not in row['landing_url'] and 'private=discard' not in row['referrer'] for row in rows), 'URL/referrer eliminan parámetros arbitrarios')

status, _, signed_out = request('/leadspanel/', raw=urlencode({
    'csrf': csrf, 'panel_logout': '1'
}).encode(), headers=form_headers)
check(status == 200 and 'Acceso al panel de leads' in signed_out, 'salir elimina sesión privada')
status, _, protected = request('/leadspanel/export.php')
check('Acceso al panel de leads' in protected, 'CSV bloqueado después de cerrar sesión')

print('TOTAL', checks, 'comprobaciones HTTP')
