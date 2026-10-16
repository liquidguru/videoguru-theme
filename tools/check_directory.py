"""Check the WordPress.org edition the way the theme review does: unpack dist/videoguru-directory.zip and run
PHP_CodeSniffer with the WordPress coding standards, the theme text domain / prefix rules and PHP 7.4+ compatibility.

One-off setup (Local's bundled Composer; installs into _test/phpcs, which is git-ignored):
  see _test/phpcs/composer.json (wp-coding-standards/wpcs, phpcompatibility-wp, dealerdirect installer)

Usage: python tools/check_directory.py [--full | --plugin] [--details]   (--full: the full theme edition; --plugin: the companion plugin; --details: list each finding)
"""
import os
import shutil
import subprocess
import sys
import zipfile

HERE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PHP = r'C:\Users\liqui\AppData\Roaming\Local\lightning-services\php-8.2.29+0\bin\win64\php.exe'
PHPCS = os.path.join(HERE, '_test', 'phpcs', 'vendor', 'squizlabs', 'php_codesniffer', 'bin', 'phpcs')
full = '--full' in sys.argv
plugin = '--plugin' in sys.argv
zip_name = 'videoguru-companion.zip' if plugin else ('videoguru.zip' if full else 'videoguru-directory.zip')
zip_path = os.path.join(HERE, 'dist', zip_name)
out = os.path.join(HERE, '_test', 'check_plugin' if plugin else ('check_full' if full else 'check_directory'))

shutil.rmtree(out, ignore_errors=True)
with zipfile.ZipFile(zip_path) as z:
    z.extractall(out)
theme = os.path.join(out, 'videoguru-companion' if plugin else 'videoguru')

rc = 0
print('PHP lint (7.4 syntax only checked by the compatibility sniff below)...')
for d, _, files in os.walk(theme):
    for f in files:
        if f.endswith('.php'):
            r = subprocess.run([PHP, '-l', os.path.join(d, f)], capture_output=True, text=True)
            if r.returncode:
                print(r.stdout + r.stderr)
                rc = 1

args = [PHP, '-d', 'memory_limit=1G', PHPCS, '--standard=WordPress,PHPCompatibilityWP', '--extensions=php', '--runtime-set', 'testVersion', '7.4-',
        '--runtime-set', 'minimum_wp_version', '6.3', '--runtime-set', 'text_domain', 'videoguru-companion' if plugin else 'videoguru', '--runtime-set', 'prefixes', 'vgcompanion,VGCOMPANION' if plugin else 'videoguru,Videoguru,VIDEOGURU',
        '--report=json', theme]
r = subprocess.run(args, capture_output=True, text=True)

import json
# The theme review looks at security, escaping, prefixes, deprecated/discouraged functions, i18n and compatibility,
# not at code layout, so report only those (the full WordPress style run is `phpcs --standard=WordPress`).
KEEP = ('WordPress.Security', 'WordPress.WP', 'WordPress.DB', 'WordPress.PHP', 'WordPress.NamingConventions.PrefixAllGlobals', 'PHPCompatibility')
SKIP = ('WordPress.PHP.YodaConditions', 'WordPress.PHP.DisallowShortTernary', 'WordPress.PHP.NoSilencedErrors')
try:
    data = json.loads(r.stdout[r.stdout.index('{'):])
except ValueError:
    print(r.stdout[-3000:], r.stderr[-2000:])
    sys.exit(2)
count = {}
details = []
for path, info in data['files'].items():
    for m in info['messages']:
        src = m['source']
        if not src.startswith(KEEP) or src.startswith(SKIP):
            continue
        count[(m['type'], src)] = count.get((m['type'], src), 0) + 1
        details.append((os.path.relpath(path, theme), m['line'], m['type'], src, m['message']))
total_err = sum(n for (t, _), n in count.items() if t == 'ERROR')
total_warn = sum(n for (t, _), n in count.items() if t == 'WARNING')
print(f'\nReview-relevant findings: {total_err} errors, {total_warn} warnings')
for (t, src), n in sorted(count.items(), key=lambda kv: (-kv[1], kv[0])):
    print(f'  {n:4d}  {t:7s} {src}')
if '--details' in sys.argv:
    print()
    for f, line, t, src, msg in sorted(details):
        print(f'{f}:{line} [{t}] {src}\n    {msg[:230]}')
sys.exit(rc or (1 if total_err else 0))
