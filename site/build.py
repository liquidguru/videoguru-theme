"""Build the website into site/dist: copies site/src, fills in the version and zip size, adds the download zip and the social image.

Run `python tools/package.py` first so dist/videoguru.zip is current. Then deploy with `npx.cmd wrangler deploy` from this folder.
"""
import os
import re
import shutil
from PIL import Image

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
SRC = os.path.join(HERE, 'src')
DIST = os.path.join(HERE, 'dist')
ZIP = os.path.join(ROOT, 'dist', 'videoguru.zip')

match = re.search(r'^Version:\s*(\S+)', open(os.path.join(ROOT, 'videoguru', 'style.css'), encoding='utf-8').read(), re.M)
if not match:
    raise SystemExit('No "Version:" line in videoguru/style.css')
version = match.group(1)
size = os.path.getsize(ZIP)
size_label = f'{size / 1024:.0f} KB'

# Empty dist rather than delete it, so a preview server sitting in that folder doesn't block the build
os.makedirs(DIST, exist_ok=True)
for entry in os.listdir(DIST):
    path = os.path.join(DIST, entry)
    shutil.rmtree(path) if os.path.isdir(path) else os.remove(path)
shutil.copytree(SRC, DIST, dirs_exist_ok=True)

# Social-share image (1200x630) from the hero screenshot
wall = Image.open(os.path.join(SRC, 'img', 'wall-midnight.webp')).convert('RGB')
og = wall.crop((0, 0, 1440, 756)).resize((1200, 630), Image.Resampling.LANCZOS)
og.save(os.path.join(DIST, 'img', 'og-image.jpg'), quality=86, optimize=True)

# Colour-theme gallery: the header, chips and first row of each wall, cropped so the colours read at a glance
for preset in ['midnight', 'ocean', 'charcoal', 'forest', 'plum', 'paper']:
    w = Image.open(os.path.join(SRC, 'img', f'wall-{preset}.webp')).convert('RGB')
    w.crop((0, 0, 1440, 440)).save(os.path.join(DIST, 'img', f'theme-{preset}.webp'), quality=84, method=6)

# Downloads: a stable name plus the versioned one
os.makedirs(os.path.join(DIST, 'download'))
shutil.copy(ZIP, os.path.join(DIST, 'download', 'videoguru.zip'))
shutil.copy(ZIP, os.path.join(DIST, 'download', f'videoguru-{version}.zip'))

html_path = os.path.join(DIST, 'index.html')
html = open(html_path, encoding='utf-8').read().replace('{{VERSION}}', version).replace('{{SIZE}}', size_label)
open(html_path, 'w', encoding='utf-8').write(html)

import datetime

today = datetime.date.today().isoformat()
with open(os.path.join(DIST, 'robots.txt'), 'w') as f:
    f.write('User-agent: *\nAllow: /\n\nSitemap: https://videoguru.liquid.guru/sitemap.xml\n')
with open(os.path.join(DIST, 'sitemap.xml'), 'w') as f:
    f.write('<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
            f'  <url><loc>https://videoguru.liquid.guru/</loc><lastmod>{today}</lastmod></url>\n</urlset>\n')

# Home-screen icon for iPhones/iPads (180x180): the accent tile with the play triangle, same as the favicon
from PIL import ImageDraw

icon = Image.new('RGB', (180, 180), (92, 200, 224))
ImageDraw.Draw(icon).polygon([(67, 53), (67, 127), (130, 90)], fill=(6, 16, 26))
icon.save(os.path.join(DIST, 'apple-touch-icon.png'), optimize=True)

left = re.findall(r'\{\{[A-Z]+\}\}', html)
print('built site/dist: version', version, size_label, '| unfilled placeholders:', left or 'none')
