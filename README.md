# VideoGuru

A fast, simple **video wall** theme for WordPress, for people with lots of videos. Every post is one Vimeo or YouTube video, shown as a big thumbnail on a grid you can filter, search and shuffle, with muted hover previews. No plugins needed.

Born from [liquidguru.com](https://www.liquidguru.com/), a site of 138 short macro videos.

**Status: 0.3.0 (private repo; not publicly released yet).** All import routes (pasted links, YouTube with and without an API key, Vimeo with an access token) have been tried against real accounts.

## How it works

- **One post = one video.** Paste a Vimeo or YouTube link (or add the Embed block) anywhere in a post. That's it: no custom fields, no setup step.
- **Posters** come from your featured image if you set one, otherwise from the video itself (Vimeo's public oEmbed, fetched once and cached; YouTube's image server).
- **Filter chips** come from your categories, with counts: top-level by default, or pick the second or third level in the Customizer when your real groups sit under a parent (e.g. Archive → Fish). Hide housekeeping categories by slug. A title with a line break (`Common name<br>Latin name`) shows its second part as a subtitle. **Search** matches titles, categories and tags, instantly. **Random video** picks one from what's showing.
- **Order:** the owner picks the home page order in the Customizer (newest, oldest, title A–Z, title Z–A, or random on every visit), and an optional **Sort menu** lets visitors re-sort the wall themselves (Newest, Oldest, A–Z, Z–A, Random). Sorting happens in the browser, so page caches can't freeze a random order; crawlers get the server order.
- **Hover previews** play a muted, looping clip when the mouse rests on a thumbnail (mouse only; off for touch, reduced motion and Data Saver).
- **Customizer** (Appearance → Customize): colour theme (six presets or your own two colours), logo and tagline (Site Identity), thumbnail size, order (newest, oldest or random on every visit), intro text, footer text, social links, and switches for search, Random, hover previews and durations.
- **Import** (Appearance → Import videos): paste a list of links, or point it at a YouTube channel/@handle/playlist or a Vimeo channel/group/showcase/folder/account, tick the videos you want and it makes one post each (link, description, original date, optional category, published or draft). Existing videos are skipped, so it is safe to run again. YouTube works without a key for the newest 15 videos (a free API key lists all); Vimeo needs a free personal access token. Keys are stored in the site's settings.
- **Analytics** (Customize → Analytics): paste a Google Analytics, Plausible, Cloudflare or Matomo snippet; skipped for logged-in administrators by default. Consent is the site owner's responsibility.
- **SEO basics built in**: meta description, canonical, social cards and `VideoObject` structured data (skipped automatically if Yoast, Rank Math, SEOPress or AIOSEO is active).

## Install

1. Download `videoguru.zip` (or run `python tools/package.py` to build `dist/videoguru.zip`).
2. WordPress admin → Appearance → Themes → Add New → Upload Theme → Activate.
3. Settings → Reading: "Your homepage displays" → **Your latest posts**.
4. Add posts with a video link in each. Add a menu under Appearance → Menus if you want header links.

Requires WordPress 6.0+ and PHP 7.4+.

## Layout of this repo

| Path | What |
|---|---|
| `videoguru/` | the theme (this folder is what gets zipped) |
| `videoguru/inc/` | **core (both editions):** `video.php` (finding the video, titles, player), `wall.php` (queries, cards, related), `template-tags.php` (the markup behind the wall, video page, bars and footer), `customizer.php` (options and colour maths). **full edition only:** `posters.php` (Vimeo posters/lengths via oEmbed, cached, WP-Cron), `seo.php`, `analytics.php`, `importer.php` (Appearance → Import videos) |
| `site/` | the download website, live at **https://videoguru.liquid.guru** (single static page): `src/` (page, CSS, screenshots, fonts), `build.py` (fills in version and zip size, adds the download zip and social image, writes `site/dist`), `shoot.py` (regenerates the screenshots from the test site with headless Chrome), `wrangler.toml` (Cloudflare Worker with static assets; deploy from `site/` with `npx.cmd wrangler deploy` after `python tools/package.py` and `python site/build.py`) |
| `tools/package.py` | builds `dist/videoguru.zip` (full) and `dist/videoguru-directory.zip` (WordPress.org edition); `python tools/package.py full` or `directory` for one |
| `tools/check_directory.py` | unpacks the directory zip and runs PHP_CodeSniffer (WordPress standards, theme text domain/prefix rules, PHP 7.4+ compatibility), reporting what the theme review cares about; needs the one-off Composer install in `_test/phpcs` |
| `editions/directory/readme.txt` | the WordPress.org edition's `readme.txt` (privacy section, resources, its own changelog); `package.py` swaps it in |
| `tools/make_screenshot.py` | regenerates `videoguru/screenshot.jpg` from a folder of posters |
| `tools/wpt.sh` | WP-CLI against the local test site |
| `_test/` | git-ignored local test site (WordPress copy on `php -S` at http://localhost:7430, seeded by `_test/seed.php`) |

## Two editions

| | Full edition | WordPress.org edition |
|---|---|---|
| Where | GitHub, https://videoguru.liquid.guru | the WordPress.org theme directory (submission pending) |
| Built by | `python tools/package.py full` → `dist/videoguru.zip` | `python tools/package.py directory` → `dist/videoguru-directory.zip` |
| Import videos page | yes | no (a theme there may not create content) |
| Vimeo posters and lengths | yes (oEmbed, cached) | no: posters come from featured images |
| SEO tags and structured data | yes | no (plugin territory) |
| Analytics snippet box | yes | no (plugin territory) |
| Remote requests | Vimeo oEmbed; YouTube posters; hover previews (on by default) | **none by itself**. Hover previews and YouTube thumbnails are Customizer options that are **off until the owner switches them on** |

Both are built from the same source: the full edition's extra files (`inc/posters.php`, `inc/seo.php`, `inc/analytics.php`, `inc/importer.php`) are simply left out of the directory zip, and the core falls back gracefully (`videoguru_is_full()` in `functions.php`; the extras plug in through filters such as `videoguru_poster_url` and `videoguru_duration`).

**Submitting to WordPress.org** (needs a WordPress.org account; the `Contributors:` line in `editions/directory/readme.txt` must be that username): run `python tools/package.py directory` and `python tools/check_directory.py` (expect 0 findings), then upload `dist/videoguru-directory.zip` at https://wordpress.org/themes/upload/. The review queue can take weeks. The Theme Check plugin (https://wordpress.org/plugins/theme-check/) reports no required items or warnings for this build.

## Notes

- Posts that don't contain a Vimeo/YouTube link are left off the wall (switch off in Customizer → Video wall).
- Up to 200 videos per page by default (Customizer). Beyond that the wall paginates and search goes to the server.
- Vimeo posters for a brand-new site are fetched in the background (WP-Cron) the first time a page is viewed, and straight away when a post is saved.
- Open Sans (SIL Open Font License) is bundled for fast, private loading.

## Licence

GPL v2 or later. See `LICENSE`.
