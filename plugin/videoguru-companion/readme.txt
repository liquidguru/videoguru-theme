=== VideoGuru Companion ===
Contributors: liquidguru
Tags: video, youtube, vimeo, import, featured image
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import a YouTube or Vimeo channel as posts, and save each video's poster as the featured image and its length. Made for the VideoGuru theme.

== Description ==

VideoGuru Companion does the two chores that come with a site full of video posts:

1. **Import videos.** Paste a list of links, or point it at a YouTube channel or playlist, or a Vimeo channel, group, showcase, folder or account. Tick the videos you want and each becomes one post with its link (as an Embed block), description and original publish date. Videos you already have are skipped, so you can run it again whenever the channel has new uploads. Import as drafts first if you want to review them.
2. **Posters and lengths.** For every post with a Vimeo or YouTube link it can save the video's poster into your Media Library as the post's **featured image**, and remember the video's length. The [VideoGuru theme](https://videoguru.liquid.guru/) shows both on its thumbnail wall, and the featured images work in any theme.

It does not add SEO tags or analytics (use a plugin such as Yoast SEO, Rank Math or Site Kit for those). If the full edition of the VideoGuru theme is active, this plugin switches itself off because the theme already includes the same features.

= What you need =

* Pasted links: nothing.
* YouTube channel: nothing for the newest 15 videos. For the whole channel (and YouTube video lengths) a free YouTube Data API key from Google Cloud Console.
* Vimeo channel, group, showcase, folder or account: a free Vimeo personal access token (developer.vimeo.com).

Keys and tokens are stored in your site's settings and are only used when you list videos or fetch lengths.

= Privacy and external services =

By default the plugin contacts no one. Importing and "Fetch posters and lengths now" are things you start by clicking a button. **Automatic fetching** (when a post is saved or imported) stays off until you tick the box under Tools → VideoGuru Companion → Posters and lengths.

When it does run, your website's server (not your visitors' browsers) contacts these services. It sends the video's address and, if you saved one, your key or token. Nothing is sent about your visitors, and the plugin sets no cookies and adds nothing to your front-end pages.

* **Vimeo** (https://vimeo.com): the public oEmbed endpoint `https://vimeo.com/api/oembed.json` (title, description, poster and length of a video), and the Vimeo API `https://api.vimeo.com` when you list a channel with your access token. Vimeo Terms of Service: https://vimeo.com/terms · Privacy Policy: https://vimeo.com/privacy
* **YouTube / Google** (https://www.youtube.com): the public feed `https://www.youtube.com/feeds/videos.xml`, the oEmbed endpoint `https://www.youtube.com/oembed`, the poster image on `https://i.ytimg.com`, and the YouTube Data API `https://www.googleapis.com/youtube/v3` when you saved an API key. YouTube Terms of Service: https://www.youtube.com/t/terms · Google Privacy Policy: https://policies.google.com/privacy

== Installation ==

1. Plugins → Add New → Upload Plugin, choose the zip, Activate. (Or search for VideoGuru Companion.)
2. Go to Tools → VideoGuru Companion.
3. Paste links or enter a channel address on the Paste links, YouTube or Vimeo tab, tick the videos you want, and click Import.
4. On the Posters and lengths tab, click "Fetch posters and lengths now" to catch up on existing posts, and tick the automatic fetching box if you want new posts handled for you.

== Frequently Asked Questions ==

= Does it work without the VideoGuru theme? =
Yes. Importing and featured images work with any theme. Only the video lengths are specific to the VideoGuru theme, which reads them.

= Why is only the newest 15 YouTube videos listed? =
That's all YouTube's public feed shares. Add a free YouTube Data API key on the YouTube tab to list the whole channel.

= A video was skipped or had no poster =
Private, removed or non-embeddable videos can't be read by Vimeo or YouTube. Set a featured image on that post yourself.

= Can I delete the plugin without losing my posts? =
Yes. Imported posts, the featured images and the saved lengths are your site's content and stay. Deleting the plugin removes its settings, keys and tokens.

== Changelog ==

= 0.1.0 =
* First version: importer for pasted links, YouTube channels and playlists, and Vimeo channels, groups, showcases, folders and accounts; poster-as-featured-image and video length fetching, with automatic fetching off until the site owner agrees.
