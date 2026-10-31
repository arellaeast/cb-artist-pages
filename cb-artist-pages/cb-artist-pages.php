<?php
/**
 * Plugin Name:       CB Artist Pages
 * Description:       Radio-deck-styled single template for Pirate Radio artist profiles (cb_artist). Sections: title, circular photo, bio, Baltimore connection, top tracks, discography — enriched via post meta by Data/Lisa.
 * Version:           1.0.0
 * Author:            Lisa (Captain Baltimore)
 */

defined( 'ABSPATH' ) || exit;

define( 'CBAP_VERSION', '1.0.0' );
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
		add_filter( 'template_include', [ __CLASS__, 'template' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'assets' ] );
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

	public static function template( $template ) {
		if ( is_singular( 'cb_artist' ) ) {
			$ours = CBAP_DIR . 'templates/single-cb_artist.php';
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
