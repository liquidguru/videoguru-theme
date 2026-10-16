<?php
/**
 * Plugin Name: VideoGuru Companion
 * Plugin URI: https://videoguru.liquid.guru/
 * Description: Import a YouTube or Vimeo channel as posts, and save each video's poster as the featured image and its length. Made to go with the VideoGuru theme; works with any theme.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Kaj Maney
 * Author URI: https://www.liquidguru.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: videoguru-companion
 *
 * @package VideoGuru_Companion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VGCOMPANION_VERSION', '0.1.0' );
define( 'VGCOMPANION_DIR', plugin_dir_path( __FILE__ ) );
define( 'VGCOMPANION_URL', plugin_dir_url( __FILE__ ) );

/**
 * Start up once the theme has loaded, so we can tell which edition of the VideoGuru theme (if any) is active.
 * The full edition of the theme already contains everything this plugin does, and running both would double up.
 */
function vgcompanion_boot() {
	if ( function_exists( 'videoguru_is_full' ) && videoguru_is_full() ) {
		add_action( 'admin_notices', 'vgcompanion_full_theme_notice' );
		return;
	}
	require_once VGCOMPANION_DIR . 'includes/helpers.php';
	require_once VGCOMPANION_DIR . 'includes/importer.php';
	require_once VGCOMPANION_DIR . 'includes/posters.php';
	if ( is_admin() ) {
		require_once VGCOMPANION_DIR . 'includes/admin.php';
	}
}
add_action( 'after_setup_theme', 'vgcompanion_boot', 20 );

/** Tell the owner why the plugin is idle. */
function vgcompanion_full_theme_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'VideoGuru Companion is switched off because the full edition of the VideoGuru theme is active and already includes the importer and the poster fetching. You can deactivate this plugin.', 'videoguru-companion' ) . '</p></div>';
}
