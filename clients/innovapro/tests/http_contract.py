"""Contrato HTTP con BD y SMTP reales; únicamente datos sintéticos de la suite."""
import argparse, base64, concurrent.futures, csv, io, json, re, secrets
from datetime import datetime
from http.cookiejar import CookieJar
from pathlib import Path
from urllib.error import HTTPError
from urllib.request import Request, build_opener, HTTPCookieProcessor

p = argparse.ArgumentParser()
p.add_argument('--base', default='http://127.0.0.1:8765')
p.add_argument('--smtp-dir', required=True)
a = p.parse_args()
base = a.base
smtp = Path(a.smtp_dir)
auth = 'Basic ' + base64.b64encode(b'qa:admin-test-only').decode()
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
check(status == 200 and 'noindex,follow' in html and 'noindex, follow' in headers['X-Robots-Tag'], 'landing noindex y seguridad HTTP')
check('Content-Security-Policy' in headers and "object-src 'none'" in headers['Content-Security-Policy'], 'CSP emitida por PHP')
check('name="website"' not in html and 'localidad' not in html.lower(), 'sin honeypot ni localidad')
for path in ['/privacidad.php', '/cookies.php', '/aviso-legal.php']:
    check(request(path)[0] == 200, 'legal ' + path)
for path in ['/admin/', '/admin/lead.php?id=1', '/admin/export.php']:
    check(request(path)[0] == 401, 'panel protegido ' + path)
check(request('/config/config.php')[0] == 404, 'config inaccesible por HTTP')
check(request('/api/lead.php')[0] == 405, 'endpoint exige POST')
status, _, body = request('/api/lead.php', {})
check(status == 422 and not json.loads(body)['ok'], 'validación servidor')
status, _, body = request('/api/lead.php', raw=b'{broken', headers={'Content-Type': 'application/json'})
check(status == 422 and json.loads(body)['code'] == 'json', 'JSON inválido controlado')
status, _, body = request('/api/lead.php', raw=b'hello', headers={'Content-Type': 'text/plain'})
check(status == 415 and json.loads(body)['code'] == 'content_type', 'tipo no soportado controlado')
valid = {'name': '=Prueba <b>HTTP</b>', 'phone': '600111222', 'email': 'http@example.test', 'privacy': True, 'request_id': secrets.token_hex(16), 'attribution': {'utm_source': 'google', 'utm_campaign': 'qa-http', 'landing_url': base + '/?utm_campaign=qa-http&email=discard', 'referrer': 'https://example.test/?private=discard'}}
status, _, body = request('/api/lead.php', valid)
saved = json.loads(body)
check(status == 200 and saved['ok'] and saved['lead_id'] > 0, 'lead guardado y SMTP intentado')
status, _, body = request('/api/lead.php', valid)
check(json.loads(body)['lead_id'] == saved['lead_id'] and json.loads(body)['duplicate'], 'reintento idempotente')
status, _, body = request('/api/lead.php', {**valid, 'email': 'changed@example.test'})
check(status == 409 and json.loads(body)['code'] == 'idempotency_conflict', 'colisión de solicitud con datos diferentes')
concurrent_payload = {**valid, 'request_id': secrets.token_hex(16)}
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
    results = list(pool.map(lambda _: json.loads(request('/api/lead.php', concurrent_payload)[2]), range(4)))
check(len({r['lead_id'] for r in results}) == 1, 'cuatro envíos concurrentes generan un lead')
# Provocar fallo en el SMTP de pruebas, sin introducir modos en la aplicación.
(smtp / 'fail').touch()
status, _, body = request('/api/lead.php', {**valid, 'request_id': secrets.token_hex(16)})
failed = json.loads(body)
check(status == 200 and failed['ok'], 'SMTP real fallido devuelve éxito de persistencia')
(smtp / 'fail').unlink()
status, _, detail = request('/admin/lead.php?id=' + str(failed['lead_id']), headers={'Authorization': auth})
check(status == 200 and 'Error SMTP' in detail and 'SMTP_SEND_FAILED' in detail, 'detalle muestra estado y fallo')
csrf = re.search(r'name="csrf" value="([a-f0-9]+)"', detail).group(1)
status, _, _ = request('/admin/lead.php?id=' + str(failed['lead_id']), raw=b'action=retry_email&csrf=invalid', headers={'Authorization': auth, 'Content-Type': 'application/x-www-form-urlencoded'})
check(status == 403, 'reenvío protegido por CSRF')
status, _, detail = request('/admin/lead.php?id=' + str(failed['lead_id']), raw=('action=retry_email&csrf=' + csrf).encode(), headers={'Authorization': auth, 'Content-Type': 'application/x-www-form-urlencoded'})
check(status == 200 and 'Aceptado por SMTP' in detail and '2 intentos' in detail, 'reenvío actualiza solo correo')
check('gtag' not in detail and 'googletagmanager' not in detail and 'Reenviar notificación' not in detail, 'panel sin Ads ni reenvío de sent')
status, _, listing = request('/admin/?q=qa-http&mail=sent', headers={'Authorization': auth})
check(status == 200 and '&lt;b&gt;HTTP&lt;/b&gt;' in listing, 'listado, búsqueda campaña, estado y escape')
status, _, listing = request('/admin/?from=2099-01-01&to=2099-12-31', headers={'Authorization': auth})
check('No hay solicitudes' in listing, 'filtro por fechas')
status, headers, export = request('/admin/export.php?q=qa-http', headers={'Authorization': auth})
rows = list(csv.DictReader(io.StringIO(export), delimiter=';'))
check(status == 200 and len(rows) == 3 and all(r['nombre'].startswith("'=") for r in rows), 'CSV exporta resultados filtrados y neutraliza fórmulas')
check(all('email=discard' not in r['landing_url'] and 'private=discard' not in r['referrer'] for r in rows), 'URL y referrer no conservan parámetros personales')
print('TOTAL', checks, 'comprobaciones HTTP reales')
