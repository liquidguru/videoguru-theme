# VideoGuru

A fast, simple **video wall** theme for WordPress, for people with lots of videos. Every post is one Vimeo or YouTube video, shown as a big thumbnail on a grid you can filter, search and shuffle, with muted hover previews. No plugins needed.

Born from [liquidguru.com](https://www.liquidguru.com/), a site of 138 short macro videos.

**Status: 0.1.0, first working version, not released yet.**

## How it works

- **One post = one video.** Paste a Vimeo or YouTube link (or add the Embed block) anywhere in a post. That's it: no custom fields, no setup step.
- **Posters** come from your featured image if you set one, otherwise from the video itself (Vimeo's public oEmbed, fetched once and cached; YouTube's image server).
- **Filter chips** are your top-level categories, with counts. **Search** matches titles, categories and tags, instantly. **Random video** picks one from what's showing.
- **Hover previews** play a muted, looping clip when the mouse rests on a thumbnail (mouse only; off for touch, reduced motion and Data Saver).
- **Customizer** (Appearance → Customize): colour theme (six presets or your own two colours), logo and tagline (Site Identity), thumbnail size, order (newest, oldest or random on every visit), intro text, footer text, social links, and switches for search, Random, hover previews and durations.
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
| `videoguru/inc/` | `video.php` (finding the video, posters, durations), `wall.php` (queries, cards, related), `customizer.php` (options and colour maths), `seo.php` |
| `tools/package.py` | builds `dist/videoguru.zip` |
| `tools/wpt.sh` | WP-CLI against the local test site |
| `_test/` | git-ignored local test site (WordPress copy on `php -S` at http://localhost:7430, seeded by `_test/seed.php`) |

## Notes

- Posts that don't contain a Vimeo/YouTube link are left off the wall (switch off in Customizer → Video wall).
- Up to 200 videos per page by default (Customizer). Beyond that the wall paginates and search goes to the server.
- Vimeo posters for a brand-new site are fetched in the background (WP-Cron) the first time a page is viewed, and straight away when a post is saved.
- Open Sans (SIL Open Font License) is bundled for fast, private loading.

## Licence

GPL v2 or later. See `LICENSE`.
