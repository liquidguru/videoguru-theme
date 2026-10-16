=== VideoGuru ===
Contributors: liquidguru
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: blog, entertainment, grid-layout, custom-colors, custom-logo, custom-menu, featured-images, threaded-comments, translation-ready

A fast, simple video wall for people with lots of videos: a filterable, searchable grid of large thumbnails, one post per video.

== Description ==

VideoGuru turns your posts into a contact sheet of large video thumbnails. Visitors can filter by category, search as they type, sort, press Random video, and (if you switch it on) rest the mouse on a thumbnail for a muted preview. It was made for a site of 138 short macro videos and suits any collection: travel clips, a film portfolio, a channel archive, tutorials.

* One post is one video. Paste a Vimeo or YouTube link (or add the Embed block) anywhere in the post. No custom fields and no plugins.
* Thumbnails come from each post's featured image.
* Filter chips come from your categories, with counts. Choose which category level they use and hide the ones you don't want.
* Instant search of titles, categories and tags.
* A Sort menu for visitors (newest, oldest, A-Z, Z-A, random), or fix the home page order yourself.
* Six colour themes (Midnight, Deep ocean, Charcoal, Forest, Plum, Paper) or your own colours. Logo and tagline from Site Identity.
* Eight thumbnail shapes: rounded, very rounded, square corners, cut corners, square tile, circle, oval, wavy edges.
* Choose thumbnail size, intro text, footer text and social links in the Customizer.
* Light and quick: no jQuery, one small script, self-hosted font.

A fuller edition with a channel importer, Vimeo posters and video lengths, SEO tags and an analytics box is available from https://videoguru.liquid.guru/

= Privacy =

By default this theme loads nothing from other sites. Two Customizer options are off until you switch them on, and each makes visitors' browsers contact Vimeo or YouTube:

* "Play a muted preview when the mouse rests on a thumbnail" loads the video from Vimeo or YouTube when a visitor rests the mouse on a thumbnail.
* "Use YouTube thumbnails for posts without a featured image" loads the thumbnail from YouTube's image server.

Playing a video on its own page loads the Vimeo or YouTube player (YouTube through its privacy-enhanced youtube-nocookie.com address). The theme itself collects and stores no visitor data.

== Installation ==

1. Appearance → Themes → Add New → Upload Theme, choose the zip, Activate. (Or search for VideoGuru in Add New.)
2. Settings → Reading: set "Your homepage displays" to "Your latest posts".
3. Add a post with a Vimeo or YouTube link in it, and set a featured image for its thumbnail. Repeat.
4. Appearance → Customize to set colours, logo, tagline and the wall options. Appearance → Menus to add header links.

== Frequently Asked Questions ==

= How do I add a video? =
Add a post and paste the Vimeo or YouTube link on its own line, or use the Embed block. The post title is the video's title on the wall. Set a featured image for the thumbnail.

= A thumbnail is blank =
Thumbnails come from the featured image. Set one on the post, or switch on "Use YouTube thumbnails for posts without a featured image" in Customize → Video wall (YouTube videos only).

= Posts without a video are missing from the wall =
By design. Customize → Video wall → "Only list posts that contain a Vimeo or YouTube link" turns that off.

= Why is search not instant on my site? =
Up to 200 videos load on one page, with instant filtering. With more, the wall paginates and search goes to the server. Change the number in Customize → Video wall.

== Copyright ==

VideoGuru, (C) 2026 Kaj Maney, is distributed under the terms of the GNU GPL v2 or later.

== Resources ==

* Open Sans (fonts/open-sans-normal.woff2 and open-sans-italic.woff2), (C) Steve Matteson, licensed under the SIL Open Font License 1.1 (https://openfontlicense.org), bundled for fast, private loading.
* screenshot.jpg shows video thumbnails from videos filmed and owned by the theme author, (C) Kaj Maney, shown with permission.
* All other code, CSS and JavaScript was written for this theme and is licensed GPLv2 or later.

== Changelog ==

= 0.5.0 =
* New: choose the shape of the video thumbnails in the Customizer: rounded corners, very rounded, square corners, cut corners, square tile, circle, oval or wavy edges.

= 0.4.0 =
* First version for the WordPress.org directory: a video wall with filter chips, instant search, Sort menu, six colour themes, logo and tagline, optional hover previews.
