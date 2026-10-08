"""Revisa ficheros versionados sin imprimir contenido potencialmente sensible."""
from pathlib import Path, PurePosixPath
import re, subprocess, sys

files = subprocess.check_output(['git', 'ls-files', '-z']).decode().split('\0')
problems = []
secret_patterns = [
    re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'),
    re.compile(r'\b(?:gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{30,}|sk_live_[A-Za-z0-9]{20,}|AIza[A-Za-z0-9_-]{30,})\b'),
]

for name in filter(None, files):
    path = PurePosixPath(name)
    if (path.name in {'config.php', '.htpasswd'} and ('includes' in path.parts or path.name == '.htpasswd')) or path.name.startswith('.env'):
        problems.append(name + ': configuración privada')
    if path.suffix in {'.log', '.pem', '.key', '.p12', '.pfx', '.pyc'}:
        problems.append(name + ': fichero privado o generado')
    if any(part in {'__pycache__', '.local', '.codex-remote-attachments'} for part in path.parts):
        problems.append(name + ': fichero generado')
    if path.suffix == '.sql' and path.name != 'schema.sql':
        problems.append(name + ': posible dump')
    if path.suffix not in {'.php', '.js', '.cjs', '.py', '.md', '.yml', '.yaml', '.json', '.txt', '.example'}:
        continue
    try:
        content = Path(name).read_text(encoding='utf-8')
    except UnicodeDecodeError:
        continue
    if any(pattern.search(content) for pattern in secret_patterns):
        problems.append(name + ': posible secreto')

if problems:
    print('\n'.join(problems))
    sys.exit(1)
print('Higiene de Git: OK')
