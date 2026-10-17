#!/usr/bin/env python3
"""Deterministic WordPress ZIP and update.json for a GitHub release.

The ZIP unpacks to research-team-manager/ and holds only runtime files: no
docs, block sources, Node metadata or the standalone import utilities.
"""
from pathlib import Path
from zipfile import ZipFile, ZipInfo, ZIP_DEFLATED
import hashlib
import json
import re

root = Path(__file__).resolve().parents[1]
SLUG = 'research-team-manager'
version = re.search(r'^ \* Version: ([0-9]+\.[0-9]+\.[0-9]+)$', (root / f'{SLUG}.php').read_text(), re.M).group(1)

files = [root / f'{SLUG}.php', root / 'uninstall.php', root / 'README.md']
for folder in ['includes', 'assets', 'templates', 'patterns', 'blocks/team-member-field/build']:
    files += sorted(p for p in (root / folder).rglob('*') if p.is_file() or p.is_symlink())
block = root / 'blocks/team-member-field'
files += [block / name for name in ['block.json', 'render.php', 'index.css', 'style-index.css']]

out = root / 'dist'
out.mkdir(exist_ok=True)
archive = out / f'{SLUG}-{version}.zip'
with ZipFile(archive, 'w', compression=ZIP_DEFLATED) as z:
    for file in files:
        if file.is_symlink():
            raise RuntimeError(f'Symlink in package: {file}')
        if file.name.startswith('.'):
            continue
        info = ZipInfo(f'{SLUG}/' + file.relative_to(root).as_posix(), (2026, 1, 1, 0, 0, 0))
        info.compress_type = ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        z.writestr(info, file.read_bytes())

digest = hashlib.sha256(archive.read_bytes()).hexdigest()
archive.with_suffix('.zip.sha256').write_text(f'{digest}  {archive.name}\n')
(out / 'update.json').write_text(json.dumps({'version': version, 'sha256': digest}, indent=2) + '\n')
print(archive)
print(digest)
