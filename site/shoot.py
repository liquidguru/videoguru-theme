"""Regenerate the website screenshots from the local test site (http://localhost:7430, start it first).

Uses headless Chrome Beta and WP-CLI (tools/wpt.sh) to switch colour themes. Output: site/src/img/*.webp
"""
import os
import subprocess
from PIL import Image

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
OUT = os.path.join(HERE, 'src', 'img')
CHROME = r'C:\Program Files\Google\Chrome Beta\Application\chrome.exe'
BASE = 'http://localhost:7430'
SH = os.path.join(ROOT, 'tools', 'wpt.sh')
GIT_SH = r'C:\Program Files\Git\bin\sh.exe'


def wp(*args):
    subprocess.run([GIT_SH, SH, *args], check=True, capture_output=True, env={**os.environ, 'MSYS_NO_PATHCONV': '1'})


def shoot(url, name, w, h, scale=1):
    png = os.path.join(OUT, '_' + name + '.png')
    subprocess.run([CHROME, '--headless=new', '--disable-gpu', '--hide-scrollbars', f'--window-size={w},{h}', f'--force-device-scale-factor={scale}',
                    '--virtual-time-budget=14000', f'--screenshot={png}', url], check=True, capture_output=True)
    im = Image.open(png).convert('RGB')
    im.save(os.path.join(OUT, name + '.webp'), quality=82, method=6)
    os.remove(png)
    print(name, im.size, os.path.getsize(os.path.join(OUT, name + '.webp')) // 1024, 'KB')


os.makedirs(OUT, exist_ok=True)
wp('option', 'update', 'blogname', 'VideoGuru Demo')
for preset in ['midnight', 'ocean', 'charcoal', 'forest', 'plum', 'paper']:
    wp('theme', 'mod', 'set', 'preset', preset)
    shoot(BASE + '/', 'wall-' + preset, 1440, 900)
wp('theme', 'mod', 'set', 'preset', 'midnight')
shoot(BASE + '/peanut-worm/', 'video-page', 1440, 900)


def poster_over_player(name, post_slug):
    """Headless Chrome can't run Vimeo's player (it shows a spinner), so paint the video's poster and a play button over it,
    which is what the player looks like before you press play."""
    import io
    import re
    import urllib.request
    from PIL import ImageDraw
    html = urllib.request.urlopen(BASE + '/').read().decode('utf-8', 'ignore')
    m = re.search(r'href="[^"]*/' + re.escape(post_slug) + r'/"[^>]*>\s*<img[^>]*src="([^"]+)"', html)
    if not m:
        print('no poster found for', post_slug)
        return
    src = re.sub(r'-d_\d+', '-d_1280', m.group(1).replace('&amp;', '&'))
    img = Image.open(io.BytesIO(urllib.request.urlopen(urllib.request.Request(src, headers={'User-Agent': 'Mozilla/5.0'})).read())).convert('RGB')
    path = os.path.join(OUT, name + '.webp')
    base = Image.open(path).convert('RGB')
    box = (144, 144, 1296, 792)
    poster = img.resize((box[2] - box[0], box[3] - box[1]))
    mask = Image.new('L', poster.size, 0)
    ImageDraw.Draw(mask).rounded_rectangle([0, 0, poster.size[0], poster.size[1]], 10, fill=255)
    base.paste(poster, box[:2], mask)
    d = ImageDraw.Draw(base, 'RGBA')
    cx, cy = (box[0] + box[2]) // 2, (box[1] + box[3]) // 2
    d.ellipse([cx - 46, cy - 46, cx + 46, cy + 46], fill=(0, 0, 0, 150), outline=(255, 255, 255, 230), width=3)
    d.polygon([(cx - 14, cy - 22), (cx - 14, cy + 22), (cx + 24, cy)], fill=(255, 255, 255, 240))
    base.save(path, quality=82, method=6)
    print(name, 'poster painted over the player')


poster_over_player('video-page', 'peanut-worm')
shoot(BASE + '/', 'wall-mobile', 390, 844, 2)
wp('theme', 'mod', 'set', 'preset', 'midnight')
