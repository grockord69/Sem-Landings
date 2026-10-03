"""Servidor SMTP exclusivo de la suite: STARTTLS y AUTH reales, mensajes sintéticos."""
import argparse, base64, email, json, socketserver, ssl, threading, time
from email import policy
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--dir', required=True)
args = parser.parse_args()
root = Path(args.dir)
root.mkdir(parents=True, exist_ok=True)
tls = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
tls.load_cert_chain(root / 'cert.pem', root / 'key.pem')

class Handler(socketserver.BaseRequestHandler):
    def handle(self):
        connection = self.request
        stream = connection.makefile('rb')
        secured = authenticated = False
        recipients = []
        def send(line):
            connection.sendall((line + '\r\n').encode())
        send('220 localhost test SMTP')
        try:
            while True:
                line = stream.readline().decode().strip()
                if not line:
                    break
                command, _, value = line.partition(' ')
                command = command.upper()
                if command in ('EHLO', 'HELO'):
                    send('250-localhost')
                    if not secured:
                        send('250-STARTTLS')
                    send('250-AUTH LOGIN PLAIN')
                    send('250 SIZE 65536')
                elif command == 'STARTTLS':
                    send('220 Ready for TLS')
                    stream.close()
                    connection = tls.wrap_socket(connection, server_side=True)
                    stream = connection.makefile('rb')
                    secured = True
                elif command == 'AUTH':
                    if value.upper().startswith('LOGIN'):
                        send('334 ' + base64.b64encode(b'Username:').decode())
                        username = base64.b64decode(stream.readline().strip()).decode()
                        send('334 ' + base64.b64encode(b'Password:').decode())
                        password = base64.b64decode(stream.readline().strip()).decode()
                    else:
                        encoded = value.split(' ', 1)[1] if ' ' in value else ''
                        if not encoded:
                            send('334 ')
                            encoded = stream.readline().strip()
                        _, username, password = base64.b64decode(encoded).decode().split('\x00')
                    authenticated = secured and username == 'sender@example.test' and password == 'smtp-test-only'
                    send('235 Authenticated' if authenticated else '535 Authentication failed')
                elif command == 'MAIL':
                    recipients = []
                    send('250 OK' if authenticated else '530 Authentication required')
                elif command == 'RCPT':
                    recipients.append(value.split('<', 1)[1].split('>', 1)[0])
                    send('250 OK')
                elif command == 'DATA':
                    if (root / 'fail').exists():
                        send('451 Simulated transient SMTP failure')
                        continue
                    send('354 End with dot')
                    lines = []
                    while True:
                        raw = stream.readline()
                        if raw in (b'.\r\n', b''):
                            break
                        lines.append(raw[1:] if raw.startswith(b'..') else raw)
                    parsed = email.message_from_bytes(b''.join(lines), policy=policy.default)
                    html = next((part.get_content() for part in parsed.walk() if part.get_content_type() == 'text/html'), '')
                    (root / f'message-{time.time_ns()}.json').write_text(json.dumps({'recipients': recipients, 'tls': secured, 'authenticated': authenticated, 'html': html, 'subject': str(parsed['Subject'])}), encoding='utf-8')
                    send('250 Accepted')
                elif command == 'QUIT':
                    send('221 Bye')
                    break
                else:
                    send('250 OK')
        except (ConnectionError, ssl.SSLError):
            pass
        finally:
            stream.close()
            connection.close()

class Server(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True

with Server(('127.0.0.1', 1025), Handler) as server:
    print('SMTP fixture ready on 1025', flush=True)
    server.serve_forever()
