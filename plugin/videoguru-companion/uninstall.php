<?php
/**
 * Runs when the plugin is deleted from the Plugins screen: remove everything it stored.
 * (Imported posts, saved featured images and the _videoguru_duration lengths are the site's content, so they stay.)
 *
 * @package VideoGuru_Companion
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'vgcompanion_settings' );
delete_option( 'vgcompanion_yt_key' );
delete_option( 'vgcompanion_vimeo_token' );
delete_option( 'vgcompanion_durations' );

global $wpdb;
// Leftover review lists (one transient per user) and the "tried" markers on posts.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_vgcompanion_' ) . '%', $wpdb->esc_like( '_transient_timeout_vgcompanion_' ) . '%' ) );
delete_post_meta_by_key( '_vgcompanion_tried' );

wp_clear_scheduled_hook( 'vgcompanion_poster_job' );
