<?php
/**
 * Settings and video detection shared by the importer, the poster fetching and the Tools page.
 *
 * @package VideoGuru_Companion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin's settings.
 *
 * allow_remote: the site owner's consent to contact Vimeo and YouTube automatically (when a post is saved or imported).
 * Off until they tick the box on Tools → VideoGuru Companion → Posters and lengths.
 */
function vgcompanion_settings() {
	$saved = get_option( 'vgcompanion_settings', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), array( 'allow_remote' => 0 ) );
}

/** True when the owner has agreed to automatic requests to Vimeo and YouTube. */
function vgcompanion_consent() {
	$settings = vgcompanion_settings();
	return ! empty( $settings['allow_remote'] );
}

/**
 * The main video in a post: array( 'provider' => 'vimeo'|'youtube'|'', 'id' => '', 'hash' => '' ).
 * The earliest Vimeo or YouTube link or embed in the content wins.
 *
 * @param WP_Post|int|null $post Post.
 */
function vgcompanion_video_info( $post = null ) {
	$none = array(
		'provider' => '',
		'id'       => '',
		'hash'     => '',
	);
	$post = get_post( $post );
	if ( ! $post ) {
		return $none;
	}
	$content = (string) $post->post_content;
	$found   = array();

	$vimeo = '~(?:player\.)?vimeo\.com/(?:video/|channels/[^/\s"\'<]+/|groups/[^/\s"\'<]+/videos/|album/\d+/video/)?(\d{5,})(?:/([0-9a-f]{8,}))?(?:\?(?:[^"\s<]*?&(?:amp;)?)?h=([0-9a-f]{8,}))?~i';
	if ( preg_match( $vimeo, $content, $m, PREG_OFFSET_CAPTURE ) ) {
		$found[ $m[0][1] ] = array(
			'provider' => 'vimeo',
			'id'       => $m[1][0],
			'hash'     => ( $m[2][0] ?? '' ) ? $m[2][0] : ( $m[3][0] ?? '' ),
		);
	}
	if ( preg_match( '~\[vimeo[^\]]*?(\d{5,})~i', $content, $m, PREG_OFFSET_CAPTURE ) ) {
		$found[ $m[0][1] ] = array(
			'provider' => 'vimeo',
			'id'       => $m[1][0],
			'hash'     => '',
		);
	}
	$youtube = '~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^"\s<]*?&(?:amp;)?)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~i';
	if ( preg_match( $youtube, $content, $m, PREG_OFFSET_CAPTURE ) ) {
		$found[ $m[0][1] ] = array(
			'provider' => 'youtube',
			'id'       => $m[1][0],
			'hash'     => '',
		);
	}
	if ( ! $found ) {
		return $none;
	}
	ksort( $found );
	return reset( $found );
}

/** Convert an ISO 8601 duration such as PT1H4M13S (YouTube's format) to seconds. */
function vgcompanion_iso_seconds( $iso ) {
	if ( ! preg_match( '~^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$~', (string) $iso, $m ) ) {
		return 0;
	}
	return ( (int) ( $m[1] ?? 0 ) * 86400 ) + ( (int) ( $m[2] ?? 0 ) * 3600 ) + ( (int) ( $m[3] ?? 0 ) * 60 ) + (int) ( $m[4] ?? 0 );
}
