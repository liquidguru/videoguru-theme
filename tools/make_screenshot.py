"""Make videoguru/screenshot.jpg (1200x900, shown in Appearance > Themes) from a folder of video posters.

Usage: python tools/make_screenshot.py [posters-dir] [titles.json]
Defaults use the author's own footage (liquidguru-mockup/thumbs + videos.json); any 16:9 jpgs work.
"""
import json
import os
import sys
from PIL import Image, ImageDraw, ImageFont

HERE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
POSTERS = sys.argv[1] if len(sys.argv) > 1 else r'C:\Users\liqui\dev\liquidguru-mockup\thumbs'
TITLES = sys.argv[2] if len(sys.argv) > 2 else r'C:\Users\liqui\dev\liquidguru-mockup\videos.json'
W, H, BG, LINE, ACC, MUTED = 1200, 900, (4, 3, 22), (28, 27, 74), (92, 200, 224), (140, 144, 184)
FONTS = r'C:\Windows\Fonts'

img = Image.new('RGB', (W, H), BG)
d = ImageDraw.Draw(img)
bold = ImageFont.truetype(os.path.join(FONTS, 'segoeuib.ttf'), 40)
reg = ImageFont.truetype(os.path.join(FONTS, 'segoeui.ttf'), 22)
small = ImageFont.truetype(os.path.join(FONTS, 'segoeui.ttf'), 18)
semi = ImageFont.truetype(os.path.join(FONTS, 'seguisb.ttf'), 18)

d.text((48, 30), 'VideoGuru', font=bold, fill=(238, 240, 255))
d.text((300, 46), 'A video wall for people with lots of videos', font=reg, fill=MUTED)
x = 48
for i, t in enumerate(['All', 'Reef', 'Wrecks', 'Night dives', 'Travel', 'Random video']):
    w = int(d.textlength(t, font=reg)) + 36
    d.rounded_rectangle([x, 112, x + w, 154], 21, fill=ACC if i == 0 else None, outline=ACC if i == 0 else LINE, width=2)
    d.text((x + 18, 117), t, font=reg, fill=(6, 16, 26) if i == 0 else MUTED)
    x += w + 12
d.rounded_rectangle([W - 48 - 260, 112, W - 48, 154], 21, fill=(12, 11, 43), outline=LINE, width=2)
d.text((W - 48 - 240, 118), 'Search videos…', font=small, fill=MUTED)
d.line([0, 174, W, 174], fill=LINE, width=1)

titles = {str(v['vimeo']): v['name'] for v in json.load(open(TITLES, encoding='utf-8')) if v.get('vimeo')}
durs = {str(v['vimeo']): v.get('dur', 0) for v in json.load(open(TITLES, encoding='utf-8')) if v.get('vimeo')}
names = sorted(f for f in os.listdir(POSTERS) if f.endswith('.jpg') and f[:-4] in titles and len(titles[f[:-4]]) <= 22)
pick = [names[i * 11 % len(names)] for i in range(16)]
cw, ch, gap = 270, 152, 16
for n, f in enumerate(pick):
    r, c = divmod(n, 4)
    p = Image.open(os.path.join(POSTERS, f)).convert('RGB')
    s = max(cw / p.width, ch / p.height)
    p = p.resize((round(p.width * s), round(p.height * s)))
    l, t = (p.width - cw) // 2, (p.height - ch) // 2
    p = p.crop((l, t, l + cw, t + ch))
    shade = Image.linear_gradient('L').resize(p.size).point(lambda v: int(v * 0.8))
    p = Image.composite(Image.new('RGB', p.size, (5, 6, 16)), p, shade)
    pd = ImageDraw.Draw(p)
    key = f[:-4]
    pd.text((12, ch - 30), titles[key], font=semi, fill=(255, 255, 255))
    secs = int(durs.get(key) or 0)
    if secs:
        label = f'{secs // 60}:{secs % 60:02d}'
        tw = int(pd.textlength(label, font=small))
        pd.rounded_rectangle([cw - tw - 18, 8, cw - 8, 32], 4, fill=(5, 6, 16))
        pd.text((cw - tw - 13, 9), label, font=small, fill=(255, 255, 255))
    img.paste(p, (48 + c * (cw + gap), 196 + r * (ch + gap)))
# the tall poster rows end at 196 + 3*168 + 152 = 852
out = os.path.join(HERE, 'videoguru', 'screenshot.jpg')
img.save(out, quality=88, optimize=True)
print(out, os.path.getsize(out) // 1024, 'KB')
