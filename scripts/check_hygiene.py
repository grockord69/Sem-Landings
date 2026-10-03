"""Revisa solo ficheros versionados; nunca imprime contenido potencialmente sensible."""
from pathlib import Path, PurePosixPath
import re, subprocess, sys

files = subprocess.check_output(['git', 'ls-files', '-z']).decode().split('\0')
problems = []
patterns = [
    re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'),
    re.compile(r'\b(?:gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{30,}|sk_live_[A-Za-z0-9]{20,}|AIza[A-Za-z0-9_-]{30,})\b'),
    re.compile(r"['\"](?:password|username|from_email|user)['\"]\s*=>\s*['\"][^'\"]+['\"]")
]
for name in filter(None, files):
    path = PurePosixPath(name)
    if (path.name == 'config.php' or path.name.startswith('.env') or path.suffix in {'.log','.pem','.key','.p12','.pfx','.pyc'}
        or any(part in {'vendor','node_modules','__pycache__','.local','.codex-remote-attachments','var'} for part in path.parts)
        or (path.suffix == '.sql' and path.name != 'schema.sql')):
        problems.append(name + ': fichero privado, generado o dump')
    if path.suffix not in {'.php','.js','.cjs','.py','.md','.yml','.json','.txt'}:
        continue
    content = Path(name).read_text(encoding='utf-8')
    if any(pattern.search(content) for pattern in patterns[:2]):
        problems.append(name + ': posible secreto')
    if name.endswith('config/config.example.php'):
        # BD/SMTP vacíos en el ejemplo; usuarios admin solo hashes introducidos fuera de Git.
        config_sections = content.split("'db' =>",1)[-1].split("'admin' =>",1)[0]
        if patterns[2].search(config_sections):
            problems.append(name + ': configuración de credenciales no vacía')
if problems:
    print('\n'.join(problems))
    sys.exit(1)
print('Higiene de Git: OK (fixtures de tests usan únicamente credenciales sintéticas).')
