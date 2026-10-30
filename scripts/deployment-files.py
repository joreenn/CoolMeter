"""Emit the explicit deployment file allowlist for Vercel's authenticated file API."""
import base64
import hashlib
import json
from pathlib import Path

root = Path(__file__).resolve().parent.parent
files = []
for folder in ('app', 'config', 'routes', 'resources/views', 'database/migrations'):
    files += list((root / folder).rglob('*.php'))
for filename in ('artisan', 'composer.json', 'composer.lock', 'bootstrap/app.php', 'bootstrap/providers.php', 'bootstrap/cache/.gitignore', 'storage/framework/cache/data/.gitignore', 'storage/framework/views/.gitignore', 'storage/framework/sessions/.gitignore', 'storage/logs/.gitignore', 'api/index.php', 'vercel.json', 'scripts/vercel-build.php'):
    files.append(root / filename)
for filename in ('app.css', 'app.js', 'theme.js', 'icon.svg', 'icon-192.png', 'icon-512.png', 'manifest.webmanifest', 'sw.js', 'offline.html'):
    files.append(root / 'public' / filename)
result = []
for file in sorted(set(files)):
    data = file.read_bytes()
    result.append({'file': file.relative_to(root).as_posix(), 'size': len(data), 'sha': hashlib.sha1(data).hexdigest(), 'data': base64.b64encode(data).decode('ascii')})
print(json.dumps(result))
