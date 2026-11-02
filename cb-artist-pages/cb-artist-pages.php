<?php
/**
 * Plugin Name:       CB Artist Pages
 * Description:       Radio-deck-styled templates for Pirate Radio artist profiles (cb_artist): a single profile (title, circular photo, bio, Baltimore connection, top tracks, discography) plus an A–Z card-grid hub for the archive with name/neighborhood search. Enriched via post meta by Data/Lisa.
 * Version:           1.3.0
 * Author:            Lisa (Captain Baltimore)
 */

defined( 'ABSPATH' ) || exit;

define( 'CBAP_VERSION', '1.3.0' );
define( 'CBAP_DIR', plugin_dir_path( __FILE__ ) );
define( 'CBAP_URL', plugin_dir_url( __FILE__ ) );

class CB_Artist_Pages {

	/** Channel slug => deck accent color. */
	const ACCENTS = [
		'drill'                     => '#FF2E88',
		'hip-hop-and-r-and-b'       => '#7B2FFF',
		'pop-rock-and-alternative'  => '#00C2E8',
		'bmore-club2'               => '#00C2E8',
		'dock-talk'                 => '#FFD500',
		'247-rock-off'              => '#7ED321',
		'jazz'                      => '#7B2FFF',
		'country'                   => '#FFD500',
		'gospel'                    => '#FF2E88',
	];

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_meta' ] );
		add_action( 'init', [ __CLASS__, 'rest_support' ], 20 );
		add_action( 'rest_after_insert_cb_artist', [ __CLASS__, 'rest_sideload' ] );
		add_filter( 'template_include', [ __CLASS__, 'template' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'assets' ] );
		add_action( 'pre_get_posts', [ __CLASS__, 'archive_query' ] );
		add_filter( 'query_vars', [ __CLASS__, 'query_vars' ] );
		add_action( 'add_meta_boxes', [ __CLASS__, 'meta_boxes' ] );
		add_action( 'save_post_cb_artist', [ __CLASS__, 'save_details' ], 10, 2 );
	}

	/**
	 * Enrichment fields. Existing fields written by cb-pirate-radio / Lisa:
	 *   cb_hood  (string)  Baltimore neighborhood
	 *   cb_links (string)  JSON array of URLs
	 * New fields owned by this template:
	 *   cb_connection  (string) how the artist connects to Baltimore
	 *   cb_discography (string) one release per line: Title | Year | Note or URL
	 * Bio is the post content (~250 words); photo is the featured image.
	 */
	public static function register_meta() {
		$fields = [
			'cb_connection'  => 'sanitize_textarea_field',
			'cb_discography' => 'sanitize_textarea_field',
			'cb_hood'        => 'sanitize_text_field',
			'cb_links'       => 'sanitize_text_field',
			'cb_photo_url'   => 'esc_url_raw',
		];
		foreach ( $fields as $key => $sanitize ) {
			register_post_meta( 'cb_artist', $key, [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => $sanitize,
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			] );
		}
	}

	/**
	 * Editor-facing meta box (v1.1.0, Jacob's ask): every enrichment field
	 * fillable from the artist edit screen by anyone who can edit the post
	 * (Data's Editor account included). Bio stays in the main editor and
	 * the photo in the Featured Image box; this box adds the rest, plus a
	 * photo-URL field that sideloads into the featured image on save.
	 */
	public static function meta_boxes() {
		add_meta_box(
			'cbap_details',
			__( 'Artist Profile Details', 'cbap' ),
			[ __CLASS__, 'render_meta_box' ],
			'cb_artist',
			'normal',
			'high'
		);
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'cbap_details', 'cbap_nonce' );
		$hood       = (string) get_post_meta( $post->ID, 'cb_hood', true );
		$connection = (string) get_post_meta( $post->ID, 'cb_connection', true );
		$disco      = (string) get_post_meta( $post->ID, 'cb_discography', true );
		$photo_url  = (string) get_post_meta( $post->ID, 'cb_photo_url', true );
		$applied    = (string) get_post_meta( $post->ID, 'cb_photo_url_applied', true );
		$photo_err  = (string) get_post_meta( $post->ID, 'cb_photo_url_error', true );
		$links      = json_decode( (string) get_post_meta( $post->ID, 'cb_links', true ) ?: '[]', true );
		$links_text = is_array( $links ) ? implode( "\n", $links ) : '';
		?>
		<p>
			<label for="cbap_hood"><strong><?php esc_html_e( 'Baltimore neighborhood', 'cbap' ); ?></strong></label><br>
			<input type="text" id="cbap_hood" name="cbap_hood" class="widefat" value="<?php echo esc_attr( $hood ); ?>" placeholder="Hampden, Pigtown, Highlandtown…">
			<span class="description"><?php esc_html_e( 'Shows as the location chip under the photo.', 'cbap' ); ?></span>
		</p>
		<p>
			<label for="cbap_photo_url"><strong><?php esc_html_e( 'Artist photo URL', 'cbap' ); ?></strong></label><br>
			<input type="url" id="cbap_photo_url" name="cbap_photo_url" class="widefat" value="<?php echo esc_attr( $photo_url ); ?>" placeholder="https://…">
			<span class="description">
				<?php
				if ( $photo_err ) {
					echo '<span style="color:#b32d2e">' . esc_html( sprintf( __( 'Last pull failed: %s', 'cbap' ), $photo_err ) ) . '</span> ';
				}
				if ( $photo_url && $photo_url === $applied && has_post_thumbnail( $post ) ) {
					esc_html_e( 'Pulled in as the featured image. (Or just use the Featured Image box to upload instead.)', 'cbap' );
				} else {
					esc_html_e( 'On Update, the image is pulled in and set as the round profile photo.', 'cbap' );
				}
				?>
			</span>
		</p>
		<p>
			<label for="cbap_connection"><strong><?php esc_html_e( 'Baltimore connection', 'cbap' ); ?></strong></label><br>
			<textarea id="cbap_connection" name="cbap_connection" class="widefat" rows="4"><?php echo esc_textarea( $connection ); ?></textarea>
			<span class="description"><?php esc_html_e( 'How this artist connects to Baltimore — their story with the city.', 'cbap' ); ?></span>
		</p>
		<p>
			<label for="cbap_links"><strong><?php esc_html_e( 'Links', 'cbap' ); ?></strong></label><br>
			<textarea id="cbap_links" name="cbap_links" class="widefat" rows="4"><?php echo esc_textarea( $links_text ); ?></textarea>
			<span class="description"><?php esc_html_e( 'One URL per line (Bandcamp, Instagram, Spotify…). They render as labeled chips on the page.', 'cbap' ); ?></span>
		</p>
		<p>
			<label for="cbap_discography"><strong><?php esc_html_e( 'Discography', 'cbap' ); ?></strong></label><br>
			<textarea id="cbap_discography" name="cbap_discography" class="widefat" rows="5"><?php echo esc_textarea( $disco ); ?></textarea>
			<span class="description"><?php esc_html_e( 'One release per line: Title | Year | Note or URL', 'cbap' ); ?></span>
		</p>
		<?php
	}

	public static function save_details( $post_id, $post ) {
		if ( ! isset( $_POST['cbap_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['cbap_nonce'] ), 'cbap_details' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, 'cb_hood', sanitize_text_field( wp_unslash( $_POST['cbap_hood'] ?? '' ) ) );
		update_post_meta( $post_id, 'cb_connection', sanitize_textarea_field( wp_unslash( $_POST['cbap_connection'] ?? '' ) ) );
		update_post_meta( $post_id, 'cb_discography', sanitize_textarea_field( wp_unslash( $_POST['cbap_discography'] ?? '' ) ) );

		$lines = preg_split( '/\r\n|\r|\n/', (string) wp_unslash( $_POST['cbap_links'] ?? '' ) );
		$urls  = [];
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$url = esc_url_raw( $line );
				if ( $url ) {
					$urls[] = $url;
				}
			}
		}
		update_post_meta( $post_id, 'cb_links', wp_json_encode( $urls ) );

		update_post_meta( $post_id, 'cb_photo_url', esc_url_raw( wp_unslash( $_POST['cbap_photo_url'] ?? '' ) ) );
		self::maybe_sideload_photo( $post_id );
	}

	/**
	 * REST exposure (v1.1.2): cb_artist was registered without the
	 * 'custom-fields' support, so the REST controller omitted the meta
	 * object entirely even though the keys above are show_in_rest.
	 * Adding the support exposes them for editors pushing enrichment
	 * over REST (Data's working path). Photo URL set over REST also
	 * triggers the same sideload the meta box performs on save.
	 */
	public static function rest_support() {
		add_post_type_support( 'cb_artist', 'custom-fields' );
	}

	public static function rest_sideload( $post ) {
		if ( $post && 'cb_artist' === $post->post_type ) {
			self::maybe_sideload_photo( $post->ID );
		}
	}

	/** Pull cb_photo_url into the featured image when it changes. */
	public static function maybe_sideload_photo( $post_id ) {
		$url     = (string) get_post_meta( $post_id, 'cb_photo_url', true );
		$applied = (string) get_post_meta( $post_id, 'cb_photo_url_applied', true );
		if ( '' === $url || $url === $applied ) {
			return;
		}
		try {
			if ( ! function_exists( 'media_sideload_image' ) ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
			// Verified 2026-10-31: on this install, if nothing has initialized
			// the WP filesystem yet, the sideload path lazily resolves it to
			// ftpext with no credentials and dies with a TypeError (which would
			// 500 the whole post save). Initializing it explicitly (direct
			// method) first makes the sideload work.
			if ( function_exists( 'WP_Filesystem' ) ) {
				WP_Filesystem();
			}
			$att_id = media_sideload_image( $url, $post_id, null, 'id' );
		} catch ( \Throwable $e ) {
			// A photo problem must never break the save itself.
			update_post_meta( $post_id, 'cb_photo_url_error', $e->getMessage() );
			return;
		}
		if ( is_wp_error( $att_id ) ) {
			update_post_meta( $post_id, 'cb_photo_url_error', $att_id->get_error_message() );
			return;
		}
		set_post_thumbnail( $post_id, (int) $att_id );
		update_post_meta( $post_id, 'cb_photo_url_applied', $url );
		delete_post_meta( $post_id, 'cb_photo_url_error' );
	}

	public static function template( $template ) {
		if ( is_singular( 'cb_artist' ) ) {
			$ours = CBAP_DIR . 'templates/single-cb_artist.php';
			if ( file_exists( $ours ) ) {
				return $ours;
			}
		}
		if ( is_post_type_archive( 'cb_artist' ) ) {
			$ours = CBAP_DIR . 'templates/archive-cb_artist.php';
			if ( file_exists( $ours ) ) {
				return $ours;
			}
		}
		return $template;
	}

	public static function assets() {
		if ( is_singular( 'cb_artist' ) ) {
			wp_enqueue_style(
				'cbap-artist',
				CBAP_URL . 'assets/artist.css',
				[],
				CBAP_VERSION
			);
		}
		if ( is_post_type_archive( 'cb_artist' ) ) {
			wp_enqueue_style(
				'cbap-archive',
				CBAP_URL . 'assets/archive.css',
				[],
				CBAP_VERSION
			);
		}
	}

	/**
	 * The hub is a directory: alphabetical by artist name, paginated,
	 * instead of the theme's newest-first blog order. Archive query only.
	 *
	 * With ?artist_search=<term> (v1.3.0) the hub switches to search
	 * mode: artists whose name (post title) or neighborhood (cb_hood)
	 * contains the term, still A–Z, all matches on one view (cap 60).
	 */
	public static function archive_query( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( ! $query->is_post_type_archive( 'cb_artist' ) ) {
			return;
		}
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );

		$term = self::search_term( $query );
		if ( '' !== $term ) {
			$ids = self::search_match_ids( $term );
			$query->set( 'posts_per_page', 60 );
			$query->set( 'post__in', $ids ?: [ 0 ] );
		} else {
			$query->set( 'posts_per_page', 24 );
		}
	}

	/** The directory search box rides in on its own query var. */
	public static function query_vars( $vars ) {
		$vars[] = 'artist_search';
		return $vars;
	}

	/** The visitor's search term, sanitized ('' when just browsing). */
	public static function search_term( $query = null ) {
		$raw = $query ? $query->get( 'artist_search' ) : get_query_var( 'artist_search' );
		if ( ! is_string( $raw ) ) {
			return '';
		}
		return trim( sanitize_text_field( $raw ) );
	}

	/**
	 * IDs of published artists matching a search term: the term appears
	 * in the artist's name (post title) or in their cb_hood neighborhood.
	 */
	public static function search_match_ids( $term ) {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( $term ) . '%';
		$ids  = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT p.ID
			 FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm
			   ON pm.post_id = p.ID AND pm.meta_key = 'cb_hood'
			 WHERE p.post_type = 'cb_artist'
			   AND p.post_status = 'publish'
			   AND ( p.post_title LIKE %s OR pm.meta_value LIKE %s )",
			$like,
			$like
		) );
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * The artist's home station on Pirate Radio: the channel of their
	 * most-voted live track, resolved to [slug, label, freq, accent].
	 * Null when the artist has no live tracks yet.
	 */
	public static function station_for( $artist_post_id ) {
		$tracks = self::top_tracks( $artist_post_id, 1 );
		if ( ! $tracks ) {
			return null;
		}
		$slug     = (string) $tracks[0]->channel_slug;
		$channels = self::channels_map();
		if ( isset( $channels[ $slug ] ) ) {
			return [ 'slug' => $slug ] + $channels[ $slug ];
		}
		return [
			'slug'   => $slug,
			'label'  => ucwords( str_replace( '-', ' ', $slug ) ),
			'freq'   => '',
			'accent' => self::ACCENTS[ $slug ] ?? '#00C2E8',
		];
	}

	/** Short plain-text blurb for a hub card: bio first, connection as fallback. */
	public static function card_blurb( $post ) {
		$text = trim( wp_strip_all_tags( (string) $post->post_content ) );
		if ( '' === $text ) {
			$text = trim( wp_strip_all_tags( (string) get_post_meta( $post->ID, 'cb_connection', true ) ) );
		}
		if ( '' === $text ) {
			return '';
		}
		return wp_trim_words( $text, 24, '…' );
	}

	/** slug => [label, freq, accent] from the radio plugin's channel map. */
	public static function channels_map() {
		$map = [];
		if ( class_exists( 'CB_Radio_Helpers' ) ) {
			foreach ( CB_Radio_Helpers::channels_display() as $c ) {
				if ( empty( $c['slug'] ) ) {
					continue;
				}
				$map[ $c['slug'] ] = [
					'label'  => $c['label'] ?? ucwords( str_replace( '-', ' ', $c['slug'] ) ),
					'freq'   => $c['freq'] ?? '',
					'accent' => self::ACCENTS[ $c['slug'] ] ?? '#00C2E8',
				];
			}
		}
		return $map;
	}

	/**
	 * The artist's top live tracks on Pirate Radio, most-voted first.
	 * Returns rows: track_title, channel_slug, art_url, votes.
	 */
	public static function top_tracks( $artist_post_id, $limit = 5 ) {
		global $wpdb;
		$submissions = $wpdb->prefix . 'cb_radio_submissions';
		$votes       = $wpdb->prefix . 'cb_radio_votes';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $submissions ) ) !== $submissions ) {
			return [];
		}
		$has_votes = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $votes ) ) === $votes;
		if ( $has_votes ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT s.track_title, s.channel_slug, s.art_attachment_id, COUNT(v.id) AS votes
				 FROM {$submissions} s
				 LEFT JOIN {$votes} v ON v.submission_id = s.id
				 WHERE s.artist_post_id = %d AND s.status = 'live' AND s.submission_type = 'track'
				 GROUP BY s.id
				 ORDER BY votes DESC, s.id ASC
				 LIMIT %d",
				$artist_post_id, $limit
			) );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT s.track_title, s.channel_slug, s.art_attachment_id, 0 AS votes
				 FROM {$submissions} s
				 WHERE s.artist_post_id = %d AND s.status = 'live' AND s.submission_type = 'track'
				 ORDER BY s.id ASC
				 LIMIT %d",
				$artist_post_id, $limit
			) );
		}
		foreach ( (array) $rows as $row ) {
			$row->art_url = $row->art_attachment_id
				? wp_get_attachment_image_url( (int) $row->art_attachment_id, 'thumbnail' )
				: '';
		}
		return (array) $rows;
	}

	/** Discography lines parsed into [title, year, note, url]. */
	public static function discography( $post_id ) {
		$raw = (string) get_post_meta( $post_id, 'cb_discography', true );
		$out = [];
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line ) );
			$item  = [ 'title' => $parts[0], 'year' => '', 'note' => '', 'url' => '' ];
			foreach ( array_slice( $parts, 1 ) as $part ) {
				if ( preg_match( '#^https?://#i', $part ) ) {
					$item['url'] = $part;
				} elseif ( preg_match( '/^\d{4}$/', $part ) ) {
					$item['year'] = $part;
				} else {
					$item['note'] = '' === $item['note'] ? $part : $item['note'] . ' — ' . $part;
				}
			}
			$out[] = $item;
		}
		return $out;
	}

	/** Friendly label for a profile/social URL. */
	public static function link_label( $url ) {
		$host = (string) parse_url( $url, PHP_URL_HOST );
		$host = preg_replace( '/^www\./', '', $host );
		$known = [
			'instagram.com' => 'Instagram',
			'facebook.com'  => 'Facebook',
			'spotify.com'   => 'Spotify',
			'soundcloud.com' => 'SoundCloud',
			'bandcamp.com'  => 'Bandcamp',
			'youtube.com'   => 'YouTube',
			'youtu.be'      => 'YouTube',
			'x.com'         => 'X',
			'twitter.com'   => 'X',
			'tiktok.com'    => 'TikTok',
			'threads.net'   => 'Threads',
			'bsky.app'      => 'Bluesky',
		];
		foreach ( $known as $domain => $label ) {
			if ( $host === $domain || str_ends_with( $host, '.' . $domain ) ) {
				return $label;
			}
		}
		return 'Website';
	}

	/** Previous / next published artist (by date), or null. */
	public static function neighbor( $post, $direction ) {
		$posts = get_posts( [
			'post_type'      => 'cb_artist',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'fields'         => 'ids',
		] );
		$idx = array_search( $post->ID, $posts, true );
		if ( false === $idx ) {
			return null;
		}
		$target = 'prev' === $direction ? $idx - 1 : $idx + 1;
		if ( ! isset( $posts[ $target ] ) ) {
			return null;
		}
		return get_post( $posts[ $target ] );
	}
}

CB_Artist_Pages::init();
