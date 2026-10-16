"""Build the upload-ready zips into dist/ (forward-slash paths, one top-level 'videoguru' folder each).

  dist/videoguru.zip            full edition: everything (GitHub, videoguru.liquid.guru)
  dist/videoguru-directory.zip  WordPress.org edition: no importer, no Vimeo/SEO/analytics extras, no remote requests

Usage: python tools/package.py [full|directory]    (no argument builds both)
"""
import os
import sys
import zipfile

HERE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(HERE, 'videoguru')
DIST = os.path.join(HERE, 'dist')

# Files the WordPress.org edition leaves out (a theme there may not create content, add SEO or analytics, or fetch remote data)
DIRECTORY_LEAVES_OUT = {'inc/importer.php', 'inc/seo.php', 'inc/analytics.php', 'inc/posters.php', 'readme.txt'}
# Files that replace the full edition's version in the directory build: {path in zip: source file}
DIRECTORY_REPLACES = {'readme.txt': os.path.join(HERE, 'editions', 'directory', 'readme.txt')}


def build(edition):
    os.makedirs(DIST, exist_ok=True)
    out = os.path.join(DIST, 'videoguru.zip' if edition == 'full' else 'videoguru-directory.zip')
    with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as zf:
        for d, _, files in os.walk(SRC):
            for f in sorted(files):
                p = os.path.join(d, f)
                rel = os.path.relpath(p, SRC).replace(os.sep, '/')
                if edition == 'directory' and rel in DIRECTORY_LEAVES_OUT:
                    continue
                zf.write(p, 'videoguru/' + rel)
        if edition == 'directory':
            for rel, src in DIRECTORY_REPLACES.items():
                zf.write(src, 'videoguru/' + rel)
    with zipfile.ZipFile(out) as zf:
        print(out, os.path.getsize(out) // 1024, 'KB,', len(zf.namelist()), 'files')


if __name__ == '__main__':
    which = sys.argv[1:] or ['full', 'directory']
    for e in which:
        build(e)
