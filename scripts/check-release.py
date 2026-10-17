#!/usr/bin/env python3
"""Check that the plugin header, RTM_VERSION, release notes and tag agree.

Usage: check-release.py [TAG]   (TAG such as v1.4.0; omitted on branches)
"""
from pathlib import Path
import re
import sys

root = Path(__file__).resolve().parents[1]
source = (root / 'research-team-manager.php').read_text()
header = re.search(r'^ \* Version: (\S+)$', source, re.M)
constant = re.search(r"define\('RTM_VERSION', '([^']+)'\);", source)
errors = []
if not header or not re.fullmatch(r'[0-9]+\.[0-9]+\.[0-9]+', header.group(1)):
    errors.append('Plugin header Version must be MAJOR.MINOR.PATCH')
version = header.group(1) if header else ''
if not constant or constant.group(1) != version:
    errors.append(f'RTM_VERSION does not match header Version {version}')
if 'Update URI: https://github.com/ahzs645/research-team-plugin' not in source:
    errors.append('Update URI header missing')
if not (root / 'releases' / f'{version}.md').is_file():
    errors.append(f'releases/{version}.md missing')
if len(sys.argv) > 1:
    tag = sys.argv[1]
    if tag.split('-', 1)[0] != f'v{version}':
        errors.append(f'Tag {tag} does not match plugin version {version}')
if errors:
    sys.exit('\n'.join(errors))
print(f'Release metadata OK for {version}')
