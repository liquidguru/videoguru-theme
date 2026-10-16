<?php
/**
 * Posters and lengths: save each video's poster into the Media Library as the post's featured image, and remember its length.
 *
 * Nothing here contacts Vimeo or YouTube on its own unless the owner has ticked the consent box (see vgcompanion_consent()).
 * The "Fetch now" button on the Tools page is an explicit action and needs no tick box.
 *
 * @package VideoGuru_Companion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Download an image into a temporary .jpg file.
 *
 * @param string $url Image address (a fixed Vimeo or YouTube image host).
 * @return string|WP_Error Path to the file.
 */
function vgcompanion_download_image( $url ) {
	$tmp = trailingslashit( get_temp_dir() ) . 'vgcompanion-' . wp_generate_password( 10, false ) . '.jpg';
	$res = wp_safe_remote_get(
		$url,
		array(
			'timeout'    => 25,
			'stream'     => true,
			'filename'   => $tmp,
			'user-agent' => 'VideoGuru Companion WordPress plugin; ' . home_url( '/' ),
		)
	);
	$ok = ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) && file_exists( $tmp );
	$size = $ok ? wp_getimagesize( $tmp ) : false;
	if ( ! $ok || ! $size || empty( $size[0] ) ) {
		if ( file_exists( $tmp ) ) {
			wp_delete_file( $tmp );
		}
		return new WP_Error( 'vgcompanion_download', __( 'Could not download the poster.', 'videoguru-companion' ) );
	}
	return $tmp;
}

/** YouTube's 480x360 fallback thumbnail has black bars above and below the 16:9 picture; cut them off. */
function vgcompanion_crop_letterbox( $file ) {
	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) ) {
		return $file;
	}
	$size = $editor->get_size();
	if ( 480 !== (int) $size['width'] || 360 !== (int) $size['height'] ) {
		return $file;
	}
	$editor->crop( 0, 45, 480, 270 );
	$saved = $editor->save( $file, 'image/jpeg' );
	return is_wp_error( $saved ) ? $file : $saved['path'];
}

/**
 * Poster address and length of a Vimeo video, from Vimeo's public oEmbed endpoint (no key needed).
 *
 * @return array|WP_Error array( 'thumb' => url, 'duration' => seconds ).
 */
function vgcompanion_vimeo_details( $id, $hash = '' ) {
	$video = 'https://vimeo.com/' . $id . ( $hash ? '/' . $hash : '' );
	$data  = vgcompanion_import_json( 'https://vimeo.com/api/oembed.json?width=1280&url=' . rawurlencode( $video ), array(), 'Vimeo' );
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	if ( empty( $data['thumbnail_url'] ) ) {
		return new WP_Error( 'vgcompanion_vimeo', __( 'Vimeo did not give a poster for that video (private or removed?).', 'videoguru-companion' ) );
	}
	return array(
		'thumb'    => esc_url_raw( $data['thumbnail_url'] ),
		'duration' => (int) ( $data['duration'] ?? 0 ),
	);
}

/** A YouTube video's length in seconds. Needs the YouTube API key saved on the Tools page; returns 0 without one. */
function vgcompanion_youtube_length( $id, $key ) {
	if ( '' === $key ) {
		return 0;
	}
	$data = vgcompanion_import_json( 'https://www.googleapis.com/youtube/v3/videos?part=contentDetails&id=' . rawurlencode( $id ) . '&key=' . rawurlencode( $key ), array(), 'YouTube' );
	if ( is_wp_error( $data ) ) {
		return 0;
	}
	return vgcompanion_iso_seconds( $data['items'][0]['contentDetails']['duration'] ?? '' );
}

/** Remember a video's length where the VideoGuru theme can find it, whichever post it is on. */
function vgcompanion_store_duration( $provider, $id, $seconds ) {
	$map = get_option( 'vgcompanion_durations', array() );
	$map = is_array( $map ) ? $map : array();
	$map[ $provider . ':' . $id ] = (int) $seconds;
	update_option( 'vgcompanion_durations', $map, false );
}

// The VideoGuru theme asks this filter for a video's length (its second argument is the video: provider, id, hash).
add_filter(
	'videoguru_duration',
	function ( $seconds, $video ) {
		if ( $seconds ) {
			return $seconds;
		}
		static $map = null;
		if ( null === $map ) {
			$map = (array) get_option( 'vgcompanion_durations', array() );
		}
		return (int) ( $map[ ( $video['provider'] ?? '' ) . ':' . ( $video['id'] ?? '' ) ] ?? 0 );
	},
	20,
	2
);

/**
 * Save a post's poster (as the featured image) and length, whichever it is still missing.
 *
 * @param int  $post_id     Post.
 * @param bool $want_poster Save the poster if the post has no featured image.
 * @param bool $want_length Save the length if it isn't known yet.
 * @return array array( 'poster' => bool, 'length' => bool, 'error' => string ).
 */
function vgcompanion_process_post( $post_id, $want_poster = true, $want_length = true ) {
	$out  = array(
		'poster' => false,
		'length' => false,
		'error'  => '',
	);
	$post = get_post( $post_id );
	$v    = $post ? vgcompanion_video_info( $post ) : array( 'provider' => '' );
	if ( ! $post || '' === $v['provider'] ) {
		$out['error'] = __( 'No Vimeo or YouTube link in that post.', 'videoguru-companion' );
		return $out;
	}

	$want_poster = $want_poster && ! has_post_thumbnail( $post_id );
	$want_length = $want_length && ! get_post_meta( $post_id, '_videoguru_duration', true );
	$key         = (string) get_option( 'vgcompanion_yt_key', '' );
	$candidates  = array();
	$seconds     = 0;

	if ( 'vimeo' === $v['provider'] ) {
		if ( $want_poster || $want_length ) {
			$details = vgcompanion_vimeo_details( $v['id'], $v['hash'] );
			if ( is_wp_error( $details ) ) {
				$out['error'] = $details->get_error_message();
				return $out;
			}
			$candidates = array( $details['thumb'] );
			$seconds    = $details['duration'];
		}
	} else {
		$candidates = array(
			'https://i.ytimg.com/vi/' . $v['id'] . '/maxresdefault.jpg',
			'https://i.ytimg.com/vi/' . $v['id'] . '/hqdefault.jpg',
		);
		if ( $want_length ) {
			$seconds = vgcompanion_youtube_length( $v['id'], $key );
		}
	}

	if ( $want_poster ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		foreach ( $candidates as $url ) {
			$tmp = vgcompanion_download_image( $url );
			if ( is_wp_error( $tmp ) ) {
				continue;
			}
			if ( false !== strpos( $url, 'hqdefault' ) ) {
				$tmp = vgcompanion_crop_letterbox( $tmp );
			}
			$title = wp_strip_all_tags( get_the_title( $post_id ) );
			$aid   = media_handle_sideload(
				array(
					'name'     => sanitize_file_name( $v['provider'] . '-' . $v['id'] . '.jpg' ),
					'tmp_name' => $tmp,
				),
				$post_id,
				$title
			);
			if ( is_wp_error( $aid ) ) {
				if ( file_exists( $tmp ) ) {
					wp_delete_file( $tmp );
				}
				$out['error'] = $aid->get_error_message();
				continue;
			}
			set_post_thumbnail( $post_id, $aid );
			update_post_meta( $aid, '_wp_attachment_image_alt', $title );
			$out['poster'] = true;
			$out['error']  = '';
			break;
		}
		if ( ! $out['poster'] && '' === $out['error'] ) {
			$out['error'] = __( 'Could not get a poster for that video.', 'videoguru-companion' );
		}
	}

	if ( $want_length && $seconds > 0 ) {
		update_post_meta( $post_id, '_videoguru_duration', $seconds );
		vgcompanion_store_duration( $v['provider'], $v['id'], $seconds );
		$out['length'] = true;
	}
	return $out;
}

// When a post with a video link is saved, queue its poster and length (only if the owner agreed to automatic requests).
add_action(
	'save_post_post',
	function ( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'publish' !== $post->post_status || ! vgcompanion_consent() ) {
			return;
		}
		$v = vgcompanion_video_info( $post );
		if ( '' === $v['provider'] ) {
			return;
		}
		if ( has_post_thumbnail( $post_id ) && get_post_meta( $post_id, '_videoguru_duration', true ) ) {
			return;
		}
		if ( (int) get_post_meta( $post_id, '_vgcompanion_tried', true ) > time() - DAY_IN_SECONDS ) {
			return;   // tried in the last day (it may have failed), so don't hammer the video site
		}
		if ( ! wp_next_scheduled( 'vgcompanion_poster_job', array( $post_id ) ) ) {
			wp_schedule_single_event( time() + 10, 'vgcompanion_poster_job', array( $post_id ) );
		}
	},
	20,
	2
);

add_action(
	'vgcompanion_poster_job',
	function ( $post_id ) {
		update_post_meta( $post_id, '_vgcompanion_tried', time() );
		vgcompanion_process_post( (int) $post_id );
	}
);

/**
 * Posts that have a video link but no featured image, or whose length isn't known and could be.
 *
 * @param int $limit Stop after this many.
 * @return int[] Post IDs, newest first.
 */
function vgcompanion_posts_needing_work( $limit = 1000 ) {
	$key  = (string) get_option( 'vgcompanion_yt_key', '' );
	$ids  = array();
	$page = 1;
	do {
		$query = new WP_Query(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => 100,
				'paged'               => $page,
				'fields'              => 'ids',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'orderby'             => 'date',
				'order'               => 'DESC',
			)
		);
		foreach ( $query->posts as $post_id ) {
			$v = vgcompanion_video_info( $post_id );
			if ( '' === $v['provider'] ) {
				continue;
			}
			$needs_poster = ! has_post_thumbnail( $post_id );
			$can_length   = 'vimeo' === $v['provider'] || '' !== $key;
			$needs_length = $can_length && ! get_post_meta( $post_id, '_videoguru_duration', true );
			if ( $needs_poster || $needs_length ) {
				$ids[] = $post_id;
			}
		}
		++$page;
	} while ( count( $query->posts ) === 100 && $page <= 20 && count( $ids ) < $limit );
	return array_slice( $ids, 0, $limit );
}

/**
 * Process posts from the list for up to $seconds.
 *
 * @return array array( 'posters' => n, 'lengths' => n, 'failed' => string[], 'left' => n ).
 */
function vgcompanion_bulk_run( $seconds = 20 ) {
	$result = array(
		'posters' => 0,
		'lengths' => 0,
		'failed'  => array(),
		'left'    => 0,
	);
	$ids    = vgcompanion_posts_needing_work();
	$start  = microtime( true );
	foreach ( $ids as $i => $post_id ) {
		if ( microtime( true ) - $start > $seconds ) {
			$result['left'] = count( $ids ) - $i;
			break;
		}
		update_post_meta( $post_id, '_vgcompanion_tried', time() );
		$r = vgcompanion_process_post( $post_id );
		$result['posters'] += $r['poster'] ? 1 : 0;
		$result['lengths'] += $r['length'] ? 1 : 0;
		if ( '' !== $r['error'] ) {
			$result['failed'][] = get_the_title( $post_id ) . ': ' . $r['error'];
		}
	}
	return $result;
}
