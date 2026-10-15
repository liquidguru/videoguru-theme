"""Build dist/videoguru.zip (forward-slash paths, one top-level 'videoguru' folder) ready for Appearance > Themes > Upload."""
import os
import zipfile

HERE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(HERE, 'videoguru')
os.makedirs(os.path.join(HERE, 'dist'), exist_ok=True)
out = os.path.join(HERE, 'dist', 'videoguru.zip')
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as zf:
    for d, _, files in os.walk(SRC):
        for f in files:
            p = os.path.join(d, f)
            zf.write(p, 'videoguru/' + os.path.relpath(p, SRC).replace(os.sep, '/'))
with zipfile.ZipFile(out) as zf:
    print(out, os.path.getsize(out) // 1024, 'KB,', len(zf.namelist()), 'files')
