<?php
/**
 * Tools → VideoGuru Companion: import videos, and fetch posters and lengths.
 *
 * @package VideoGuru_Companion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_management_page(
			__( 'VideoGuru Companion', 'videoguru-companion' ),
			__( 'VideoGuru Companion', 'videoguru-companion' ),
			'manage_options',
			'videoguru-companion',
			'vgcompanion_page'
		);
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'tools_page_videoguru-companion' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'vgcompanion-admin', VGCOMPANION_URL . 'assets/admin.js', array(), VGCOMPANION_VERSION, true );
	}
);

/** Remember the review list for an hour, per user, so a second click can import more of it. */
function vgcompanion_list_key() {
	return 'vgcompanion_import_' . get_current_user_id();
}

/** Handle a submitted form. Returns array( notice, review list|null, import result|null, fetch result|null ). */
function vgcompanion_handle_post() {
	$notice = '';
	$review = null;
	$result = null;
	$fetch  = null;
	check_admin_referer( 'vgcompanion_import' ); // Also checked by vgcompanion_page() before this runs; checking again here is harmless.
	$act = isset( $_POST['vgcompanion_action'] ) ? sanitize_key( wp_unslash( $_POST['vgcompanion_action'] ) ) : '';

	$opts   = array(
		'status' => ( isset( $_POST['status'] ) && 'draft' === $_POST['status'] ) ? 'draft' : 'publish',
		'cat'    => 0,
	);
	$new_cat = isset( $_POST['new_cat'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['new_cat'] ) ) ) : '';
	if ( '' !== $new_cat ) {
		require_once ABSPATH . 'wp-admin/includes/taxonomy.php';
		$opts['cat'] = (int) wp_create_category( $new_cat );
	} elseif ( ! empty( $_POST['cat'] ) ) {
		$opts['cat'] = absint( $_POST['cat'] );
	}
	$source = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';

	switch ( $act ) {
		case 'save_yt_key':
			update_option( 'vgcompanion_yt_key', isset( $_POST['yt_key'] ) ? sanitize_text_field( wp_unslash( $_POST['yt_key'] ) ) : '', false );
			$notice = __( 'YouTube key saved.', 'videoguru-companion' );
			break;
		case 'save_vimeo_token':
			update_option( 'vgcompanion_vimeo_token', isset( $_POST['vimeo_token'] ) ? sanitize_text_field( wp_unslash( $_POST['vimeo_token'] ) ) : '', false );
			$notice = __( 'Vimeo token saved.', 'videoguru-companion' );
			break;
		case 'save_settings':
			update_option( 'vgcompanion_settings', array( 'allow_remote' => empty( $_POST['allow_remote'] ) ? 0 : 1 ) );
			$notice = __( 'Settings saved.', 'videoguru-companion' );
			break;
		case 'fetch_posters':
			$fetch = vgcompanion_bulk_run();
			break;
		case 'paste':
			$bad    = array();
			$links  = isset( $_POST['links'] ) ? sanitize_textarea_field( wp_unslash( $_POST['links'] ) ) : '';
			$items  = vgcompanion_import_from_links( $links, 40, $bad );
			$result = $items ? vgcompanion_import_run( $items, $opts ) : array(
				'made'    => 0,
				'skipped' => 0,
				'failed'  => array(),
				'left'    => 0,
			);
			$result['failed'] = array_merge( $bad, $result['failed'] );
			if ( ! $items && ! $bad ) {
				$result['failed'][] = __( 'No YouTube or Vimeo links found in that text.', 'videoguru-companion' );
			}
			break;
		case 'list_youtube':
		case 'list_vimeo':
			$items = 'list_youtube' === $act
				? vgcompanion_yt_list( $source, (string) get_option( 'vgcompanion_yt_key', '' ) )
				: vgcompanion_vimeo_list( $source, (string) get_option( 'vgcompanion_vimeo_token', '' ) );
			if ( is_wp_error( $items ) ) {
				$notice = $items->get_error_message();
			} elseif ( ! $items ) {
				$notice = __( 'No videos were found at that address.', 'videoguru-companion' );
			} else {
				set_transient( vgcompanion_list_key(), $items, HOUR_IN_SECONDS );
				$review = $items;
			}
			break;
		case 'import_selected':
			$all  = get_transient( vgcompanion_list_key() );
			$pick = isset( $_POST['pick'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['pick'] ) ) : array();
			$sel  = array_values(
				array_filter(
					(array) $all,
					function ( $it ) use ( $pick ) {
						return in_array( $it['provider'] . ':' . $it['id'], $pick, true );
					}
				)
			);
			$result = $sel ? vgcompanion_import_run( $sel, $opts ) : array(
				'made'    => 0,
				'skipped' => 0,
				'failed'  => array( __( 'Nothing was selected.', 'videoguru-companion' ) ),
				'left'    => 0,
			);
			if ( $result['left'] ) {
				$review = $all; // keep the list on screen so the rest can be imported with another click.
			}
			break;
	}
	return array( $notice, $review, $result, $fetch );
}

/** The Tools page. */
function vgcompanion_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$tabs = array(
		'paste'   => __( 'Paste links', 'videoguru-companion' ),
		'youtube' => 'YouTube',
		'vimeo'   => 'Vimeo',
		'posters' => __( 'Posters and lengths', 'videoguru-companion' ),
	);
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'paste'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks which tab to show.
	$tab  = isset( $tabs[ $tab ] ) ? $tab : 'paste';
	$base = admin_url( 'tools.php?page=videoguru-companion' );

	$notice = '';
	$review = null;
	$result = null;
	$fetch  = null;
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && check_admin_referer( 'vgcompanion_import' ) ) {
		list( $notice, $review, $result, $fetch ) = vgcompanion_handle_post();
	}
	$yt_key = (string) get_option( 'vgcompanion_yt_key', '' );
	$vm_tok = (string) get_option( 'vgcompanion_vimeo_token', '' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'VideoGuru Companion', 'videoguru-companion' ); ?></h1>
		<p><?php esc_html_e( 'Bring in videos from YouTube or Vimeo as posts, and save each video\'s poster as the featured image. Each video becomes one post, with its link, description and original date. Videos you already have are skipped, so running this again is safe.', 'videoguru-companion' ); ?></p>
		<h2 class="nav-tab-wrapper">
			<?php foreach ( $tabs as $slug => $label ) : ?>
				<a class="nav-tab<?php echo $tab === $slug ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</h2>

		<?php if ( $notice ) : ?>
			<div class="notice notice-info"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>
		<?php if ( $result ) : ?>
			<div class="notice <?php echo $result['failed'] ? 'notice-warning' : 'notice-success'; ?>"><p>
				<?php
				/* translators: 1: number of videos imported, 2: number skipped */
				echo esc_html( sprintf( __( 'Imported %1$d new videos, skipped %2$d already on the site.', 'videoguru-companion' ), $result['made'], $result['skipped'] ) );
				if ( $result['left'] ) {
					/* translators: %d: number of videos still waiting */
					echo ' ' . esc_html( sprintf( __( '%d more are still waiting: click Import again.', 'videoguru-companion' ), $result['left'] ) );
				}
				?>
			</p>
			<?php foreach ( $result['failed'] as $failure ) : ?>
				<p><?php echo esc_html( $failure ); ?></p>
			<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php if ( $fetch ) : ?>
			<div class="notice <?php echo $fetch['failed'] ? 'notice-warning' : 'notice-success'; ?>"><p>
				<?php
				/* translators: 1: number of posters saved, 2: number of video lengths saved */
				echo esc_html( sprintf( __( 'Saved %1$d posters and %2$d video lengths.', 'videoguru-companion' ), $fetch['posters'], $fetch['lengths'] ) );
				if ( $fetch['left'] ) {
					/* translators: %d: number of posts still waiting */
					echo ' ' . esc_html( sprintf( __( '%d posts are still waiting: click Fetch again.', 'videoguru-companion' ), $fetch['left'] ) );
				}
				?>
			</p>
			<?php foreach ( array_slice( $fetch['failed'], 0, 10 ) as $failure ) : ?>
				<p><?php echo esc_html( $failure ); ?></p>
			<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php
		if ( 'posters' === $tab ) {
			vgcompanion_posters_tab( $base );
		} elseif ( null !== $review ) {
			vgcompanion_review( $review, $tab );
		} else {
			?>
			<form method="post" action="<?php echo esc_url( add_query_arg( 'tab', $tab, $base ) ); ?>">
				<?php wp_nonce_field( 'vgcompanion_import' ); ?>
				<?php if ( 'paste' === $tab ) : ?>
					<input type="hidden" name="vgcompanion_action" value="paste">
					<p><label for="links"><strong><?php esc_html_e( 'Video links, one per line (YouTube or Vimeo)', 'videoguru-companion' ); ?></strong></label></p>
					<textarea id="links" name="links" rows="10" class="large-text code" placeholder="https://vimeo.com/123456789&#10;https://www.youtube.com/watch?v=XXXXXXXXXXX"></textarea>
					<p class="description"><?php esc_html_e( 'Up to 40 at a time. Titles and descriptions are fetched from the video sites when you click Import. No key needed.', 'videoguru-companion' ); ?></p>
					<?php vgcompanion_import_options(); ?>
					<?php submit_button( __( 'Import these videos', 'videoguru-companion' ) ); ?>
				<?php elseif ( 'youtube' === $tab ) : ?>
					<input type="hidden" name="vgcompanion_action" value="list_youtube">
					<p><label for="source"><strong><?php esc_html_e( 'YouTube channel or playlist address', 'videoguru-companion' ); ?></strong></label></p>
					<input id="source" name="source" type="text" class="large-text" placeholder="https://www.youtube.com/@yourchannel   or   https://www.youtube.com/playlist?list=PL…">
					<p class="description"><?php echo $yt_key ? esc_html__( 'An API key is saved, so every video can be listed.', 'videoguru-companion' ) : esc_html__( 'Without an API key YouTube only shares the newest 15 videos. Add a key below to list them all.', 'videoguru-companion' ); ?></p>
					<?php submit_button( __( 'Find videos', 'videoguru-companion' ), 'primary', 'submit', false ); ?>
				<?php else : ?>
					<input type="hidden" name="vgcompanion_action" value="list_vimeo">
					<p><label for="source"><strong><?php esc_html_e( 'Vimeo address', 'videoguru-companion' ); ?></strong></label></p>
					<input id="source" name="source" type="text" class="large-text" placeholder="https://vimeo.com/channels/yourchannel   |   /groups/…   |   /showcase/123456   |   /user12345   |   or leave empty for your own videos">
					<p class="description"><?php echo $vm_tok ? esc_html__( 'An access token is saved.', 'videoguru-companion' ) : esc_html__( 'Vimeo only lists videos for people with an access token. Add one below.', 'videoguru-companion' ); ?></p>
					<?php submit_button( __( 'Find videos', 'videoguru-companion' ), 'primary', 'submit', false ); ?>
				<?php endif; ?>
			</form>

			<?php if ( 'youtube' === $tab ) : ?>
				<hr>
				<h2><?php esc_html_e( 'YouTube API key (optional)', 'videoguru-companion' ); ?></h2>
				<form method="post" action="<?php echo esc_url( add_query_arg( 'tab', 'youtube', $base ) ); ?>">
					<?php wp_nonce_field( 'vgcompanion_import' ); ?>
					<input type="hidden" name="vgcompanion_action" value="save_yt_key">
					<input name="yt_key" type="password" class="regular-text" autocomplete="off" value="<?php echo esc_attr( $yt_key ); ?>" placeholder="AIza…">
					<?php submit_button( __( 'Save key', 'videoguru-companion' ), 'secondary', 'submit', false ); ?>
					<p class="description"><?php esc_html_e( 'Free: Google Cloud Console → create a project → enable "YouTube Data API v3" → Credentials → Create API key. Stored in this site\'s settings; only administrators can see this page. The key is sent to Google only when you list a channel or fetch video lengths.', 'videoguru-companion' ); ?></p>
				</form>
			<?php elseif ( 'vimeo' === $tab ) : ?>
				<hr>
				<h2><?php esc_html_e( 'Vimeo access token', 'videoguru-companion' ); ?></h2>
				<form method="post" action="<?php echo esc_url( add_query_arg( 'tab', 'vimeo', $base ) ); ?>">
					<?php wp_nonce_field( 'vgcompanion_import' ); ?>
					<input type="hidden" name="vgcompanion_action" value="save_vimeo_token">
					<input name="vimeo_token" type="password" class="regular-text" autocomplete="off" value="<?php echo esc_attr( $vm_tok ); ?>">
					<?php submit_button( __( 'Save token', 'videoguru-companion' ), 'secondary', 'submit', false ); ?>
					<p class="description"><?php esc_html_e( 'Free: developer.vimeo.com/apps → create an app → Generate an access token with the "public" and "private" scopes. Private videos in your own account are listed too, but only public ones play for visitors. Stored in this site\'s settings; only administrators can see this page. The token is sent to Vimeo only when you list videos.', 'videoguru-companion' ); ?></p>
				</form>
			<?php endif; ?>
		<?php } ?>
	</div>
	<?php
}

/** The "Posters and lengths" tab: consent for automatic requests, and a button to catch up on existing posts. */
function vgcompanion_posters_tab( $base ) {
	$settings = vgcompanion_settings();
	$waiting  = count( vgcompanion_posts_needing_work() );
	?>
	<h2><?php esc_html_e( 'Automatic fetching', 'videoguru-companion' ); ?></h2>
	<form method="post" action="<?php echo esc_url( add_query_arg( 'tab', 'posters', $base ) ); ?>">
		<?php wp_nonce_field( 'vgcompanion_import' ); ?>
		<input type="hidden" name="vgcompanion_action" value="save_settings">
		<p>
			<label>
				<input type="checkbox" name="allow_remote" value="1" <?php checked( ! empty( $settings['allow_remote'] ) ); ?>>
				<?php esc_html_e( 'When a post with a Vimeo or YouTube link is saved or imported, fetch its poster and length automatically.', 'videoguru-companion' ); ?>
			</label>
		</p>
		<p class="description">
			<?php esc_html_e( 'Ticking this lets your website\'s server contact Vimeo (vimeo.com) and YouTube (i.ytimg.com, and googleapis.com if you saved a YouTube key) in the background, using WP-Cron. It sends the video\'s address, and your YouTube key if you saved one. Nothing is sent about your visitors. It stays off until you tick it.', 'videoguru-companion' ); ?>
		</p>
		<?php submit_button( __( 'Save', 'videoguru-companion' ), 'secondary', 'submit', false ); ?>
	</form>

	<hr>
	<h2><?php esc_html_e( 'Catch up on existing posts', 'videoguru-companion' ); ?></h2>
	<p>
		<?php
		/* translators: %d: number of posts */
		echo esc_html( sprintf( _n( '%d post has a video link but no poster or length yet.', '%d posts have a video link but no poster or length yet.', $waiting, 'videoguru-companion' ), $waiting ) );
		?>
	</p>
	<form method="post" action="<?php echo esc_url( add_query_arg( 'tab', 'posters', $base ) ); ?>">
		<?php wp_nonce_field( 'vgcompanion_import' ); ?>
		<input type="hidden" name="vgcompanion_action" value="fetch_posters">
		<?php submit_button( __( 'Fetch posters and lengths now', 'videoguru-companion' ), 'primary', 'submit', false, $waiting ? array() : array( 'disabled' => 'disabled' ) ); ?>
		<p class="description"><?php esc_html_e( 'Clicking this contacts Vimeo and YouTube straight away. It works for about twenty seconds at a time: click again if some are left. Posts that already have a featured image keep it; only the length is added.', 'videoguru-companion' ); ?></p>
	</form>
	<?php
}

/** Category and status choices shared by the import forms. */
function vgcompanion_import_options() {
	?>
	<p>
		<label><?php esc_html_e( 'Put them in category:', 'videoguru-companion' ); ?>
			<?php
			wp_dropdown_categories(
				array(
					'show_option_none'  => __( '(none)', 'videoguru-companion' ),
					'option_none_value' => 0,
					'hide_empty'        => 0,
					'name'              => 'cat',
					'taxonomy'          => 'category',
					'hierarchical'      => 1,
				)
			);
			?>
		</label>
		<label><?php esc_html_e( 'or a new one:', 'videoguru-companion' ); ?> <input type="text" name="new_cat" class="regular-text" style="width:12em"></label>
	</p>
	<p><label><?php esc_html_e( 'Publish as:', 'videoguru-companion' ); ?>
		<select name="status">
			<option value="publish"><?php esc_html_e( 'Published', 'videoguru-companion' ); ?></option>
			<option value="draft"><?php esc_html_e( 'Drafts (I will review first)', 'videoguru-companion' ); ?></option>
		</select></label></p>
	<?php
}

/** The "found N videos" list with tick boxes. */
function vgcompanion_review( $items, $tab ) {
	$new = 0;
	foreach ( $items as $i => $it ) {
		$items[ $i ]['dup'] = vgcompanion_import_existing( $it['provider'], $it['id'] );
		if ( ! $items[ $i ]['dup'] ) {
			++$new;
		}
	}
	?>
	<form method="post" action="<?php echo esc_url( add_query_arg( 'tab', $tab, admin_url( 'tools.php?page=videoguru-companion' ) ) ); ?>">
		<?php wp_nonce_field( 'vgcompanion_import' ); ?>
		<input type="hidden" name="vgcompanion_action" value="import_selected">
		<p><strong>
			<?php
			/* translators: 1: number of videos found, 2: number not on the site yet */
			echo esc_html( sprintf( __( 'Found %1$d videos, %2$d not on your site yet.', 'videoguru-companion' ), count( $items ), $new ) );
			?>
		</strong></p>
		<?php vgcompanion_import_options(); ?>
		<table class="widefat striped" style="max-width:900px">
			<thead><tr>
				<td class="check-column"><input type="checkbox" id="vgcompanion-all" checked></td>
				<th><?php esc_html_e( 'Title', 'videoguru-companion' ); ?></th>
				<th><?php esc_html_e( 'Date', 'videoguru-companion' ); ?></th>
				<th></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $items as $it ) : ?>
				<tr>
					<th class="check-column"><input class="vgcompanion-pick" type="checkbox" name="pick[]" value="<?php echo esc_attr( $it['provider'] . ':' . $it['id'] ); ?>" <?php echo $it['dup'] ? 'disabled' : 'checked'; ?>></th>
					<td><?php echo esc_html( $it['title'] ); ?></td>
					<td><?php echo esc_html( substr( $it['date'], 0, 10 ) ); ?></td>
					<td><?php echo $it['dup'] ? esc_html__( 'already on site', 'videoguru-companion' ) : ''; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php submit_button( __( 'Import selected videos', 'videoguru-companion' ) ); ?>
	</form>
	<?php
}
