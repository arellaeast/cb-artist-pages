<?php
/**
 * CB Artist Pages — cb_artist archive (the hub at /radio/artists/).
 * A directory of every Pirate Radio artist: masthead in deck style,
 * then a responsive card grid (circular photo, name, hood / home
 * station, short blurb), sorted A–Z by the archive query in the
 * plugin. Replaces the theme's blog listing, which carried the news
 * category filter and no photos.
 *
 * Search mode (?artist_search=<term>, v1.3.0): the same grid narrowed
 * to artists whose name or neighborhood matches, all on one view,
 * with a match count and a clear-search link back to the A–Z browse.
 */

defined( 'ABSPATH' ) || exit;

get_header();

global $wp_query;
$total       = (int) ( wp_count_posts( 'cb_artist' )->publish ?? 0 );
$palette     = [ '#FF2E88', '#7B2FFF', '#00C2E8', '#7ED321', '#FFD500' ];
$idx         = 0;
$search_term = CB_Artist_Pages::search_term();
$is_search   = '' !== $search_term;
$found       = (int) $wp_query->found_posts;
$archive_url = get_post_type_archive_link( 'cb_artist' );
?>

<div id="primary" class="content-area">
	<div id="content" class="site-content" role="main">

		<div class="cbah">
			<div class="cbah-mast">
				<span class="cbah-beacon"></span>
				<span class="cbah-callsign">CAPT&middot;BLTMR &mdash; PIRATE RADIO</span>
				<span class="cbah-mast-r">Artist Directory</span>
			</div>

			<h1 class="cbah-title">Pirate Radio &mdash; The Artists</h1>
			<p class="cbah-sub">Every artist spinning on the Captain&rsquo;s stations. Tap a card for their story, top tracks, and the dial where you can hear them.</p>

			<form class="cbah-search" method="get" action="<?php echo esc_url( $archive_url ); ?>" role="search">
				<label class="cbah-search-label" for="cbah-q">Find an artist</label>
				<div class="cbah-search-row">
					<input class="cbah-search-input" id="cbah-q" type="search" name="artist_search" value="<?php echo esc_attr( $search_term ); ?>" placeholder="Name or neighborhood &mdash; try &ldquo;Nelson&rdquo; or &ldquo;Hampden&rdquo;" autocomplete="off">
					<button class="cbah-search-btn" type="submit">Search</button>
				</div>
			</form>

			<?php if ( $is_search ) : ?>
				<div class="cbah-countrow">
					<span class="cbah-count"><?php echo esc_html( sprintf( _n( '%1$s artist matches', '%1$s artists match', $found, 'cbap' ), number_format_i18n( $found ) ) ); ?> &ldquo;<?php echo esc_html( $search_term ); ?>&rdquo;</span>
					<a class="cbah-clear" href="<?php echo esc_url( $archive_url ); ?>">&times; Clear search &mdash; browse all A&ndash;Z</a>
				</div>
			<?php elseif ( $total > 0 ) : ?>
				<div class="cbah-countrow"><span class="cbah-count"><?php echo esc_html( number_format_i18n( $total ) ); ?> artists &middot; A&ndash;Z</span></div>
			<?php endif; ?>

			<?php if ( have_posts() ) : ?>
				<div class="cbah-grid">
					<?php
					while ( have_posts() ) :
						the_post();
						$post_id  = get_the_ID();
						$hood     = trim( (string) get_post_meta( $post_id, 'cb_hood', true ) );
						$station  = CB_Artist_Pages::station_for( $post_id );
						$blurb    = CB_Artist_Pages::card_blurb( get_post() );
						$photo    = get_the_post_thumbnail_url( $post_id, 'medium' );
						$accent   = $station ? $station['accent'] : $palette[ $idx % count( $palette ) ];
						$idx++;
						?>
						<a class="cbah-card" href="<?php echo esc_url( get_permalink() ); ?>" style="--acc: <?php echo esc_attr( $accent ); ?>">
							<span class="cbah-avatarwrap">
								<?php if ( $photo ) : ?>
									<img class="cbah-avatar" src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" loading="lazy">
								<?php else : ?>
									<span class="cbah-avatar cbah-avatar-fallback" aria-hidden="true">&#127988;&#8205;&#9760;&#65039;</span>
								<?php endif; ?>
							</span>
							<span class="cbah-name"><?php the_title(); ?></span>
							<?php if ( $hood || $station ) : ?>
								<span class="cbah-chips">
									<?php if ( $hood ) : ?>
										<span class="cbah-chip">&#128205; <?php echo esc_html( $hood ); ?></span>
									<?php endif; ?>
									<?php if ( $station ) : ?>
										<span class="cbah-chip cbah-chip-station"><?php echo esc_html( $station['label'] ); ?><?php echo $station['freq'] ? ' &middot; ' . esc_html( $station['freq'] ) . ' FM' : ''; ?></span>
									<?php endif; ?>
								</span>
							<?php endif; ?>
							<?php if ( $blurb ) : ?>
								<span class="cbah-blurb"><?php echo esc_html( $blurb ); ?></span>
							<?php endif; ?>
						</a>
					<?php endwhile; ?>
				</div>

				<?php
				$pager = paginate_links( [
					'type'      => 'array',
					'mid_size'  => 2,
					'prev_text' => '&larr; Prev',
					'next_text' => 'Next &rarr;',
				] );
				if ( $pager ) :
					?>
					<nav class="cbah-pager" aria-label="Artists pages">
						<?php foreach ( $pager as $link ) : ?>
							<?php echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links output ?>
						<?php endforeach; ?>
					</nav>
				<?php endif; ?>
			<?php else : ?>
				<?php if ( $is_search ) : ?>
					<div class="cbah-soon">
						<p class="cbah-noresults-line">Nobody on the roster matches &ldquo;<?php echo esc_html( $search_term ); ?>&rdquo; &mdash; try a different name or neighborhood.</p>
						<a class="cbah-clearbtn" href="<?php echo esc_url( $archive_url ); ?>">&times; Clear search &mdash; browse all A&ndash;Z</a>
					</div>
				<?php else : ?>
					<div class="cbah-soon">The crew is still boarding &mdash; artist profiles are coming soon.</div>
				<?php endif; ?>
			<?php endif; ?>

			<div class="cbah-listenrow">
				<a class="cbah-listen" href="/radio/">&#9654; Listen on Pirate Radio</a>
			</div>
		</div>

	</div>
</div>

<?php
get_footer();
