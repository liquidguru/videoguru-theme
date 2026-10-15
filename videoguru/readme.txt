=== VideoGuru ===
Contributors: liquidguru
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: blog, entertainment, grid-layout, custom-colors, custom-logo, custom-menu, featured-images, threaded-comments, translation-ready, accessibility-ready

A fast, simple video wall for people with lots of videos. Every post is one Vimeo or YouTube video, shown as a big thumbnail you can filter, search and shuffle.

== Description ==

VideoGuru turns your posts into a contact sheet of large video thumbnails: filter by category, search instantly, press Random video, and rest the mouse on a thumbnail for a muted preview. It was made for a site of 138 short macro videos and works for any collection: travel clips, a film portfolio, a channel archive, tutorials.

* One post is one video. Paste a Vimeo or YouTube link (or add the Embed block) anywhere in the post. No custom fields, no plugins.
* Posters come from your featured image, or from the video itself.
* Filter chips come from your top-level categories, with counts. Search matches titles, categories and tags.
* Muted, looping hover previews for Vimeo and YouTube (mouse only; off for touch, reduced motion and Data Saver).
* Six colour themes (Midnight, Deep ocean, Charcoal, Forest, Plum, Paper) or your own two colours. Logo and tagline from Site Identity.
* Choose thumbnail size, newest/oldest/random order on the home page, intro text, footer text and social links.
* Light and quick: no jQuery, one small script, self-hosted font.
* Basic SEO built in (description, canonical, social cards, VideoObject data); it steps aside when Yoast, Rank Math, SEOPress or AIOSEO is active.

== Installation ==

1. Appearance → Themes → Add New → Upload Theme, choose videoguru.zip, Activate.
2. Settings → Reading: set "Your homepage displays" to "Your latest posts".
3. Add a post with a Vimeo or YouTube link in it. Repeat.
4. Appearance → Customize to set colours, logo, tagline and the wall options. Appearance → Menus to add header links.

== Frequently Asked Questions ==

= How do I add a video? =
Add a post and paste the Vimeo or YouTube link on its own line, or use the Embed block. The title is the video's title on the wall.

= Posts without a video are missing from the wall =
By design. Customize → Video wall → "Only list posts that contain a Vimeo or YouTube link" turns that off.

= A Vimeo thumbnail is blank =
New Vimeo posters are fetched when you save a post, or in the background the first time the wall is viewed. Private or unlisted Vimeo videos may not return a poster; set a featured image for those.

= Why is search not instant on my site? =
Up to 200 videos load on one page, with instant filtering. With more, the wall paginates and search goes to the server. Change the number in Customize → Video wall.

== Copyright ==

VideoGuru, (C) 2026 Kaj Maney, is distributed under the terms of the GNU GPL v2 or later.

Open Sans, (C) Steve Matteson / Google, is licensed under the SIL Open Font License 1.1 (https://openfontlicense.org).

== Changelog ==

= 0.1.0 =
* First version.
