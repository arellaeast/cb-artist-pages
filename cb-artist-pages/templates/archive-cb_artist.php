<?php
/**
 * CB Artist Pages — cb_artist archive (the hub at /radio/artists/).
 * A directory of every Pirate Radio artist: masthead in deck style,
 * then a responsive card grid (circular photo, name, hood / home
 * station, short blurb), sorted A–Z by the archive query in the
 * plugin. Replaces the theme's blog listing, which carried the news
 * category filter and no photos.
 */

defined( 'ABSPATH' ) || exit;

get_header();

$total   = (int) ( wp_count_posts( 'cb_artist' )->publish ?? 0 );
$palette = [ '#FF2E88', '#7B2FFF', '#00C2E8', '#7ED321', '#FFD500' ];
$idx     = 0;
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
			<?php if ( $total > 0 ) : ?>
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
				<div class="cbah-soon">The crew is still boarding &mdash; artist profiles are coming soon.</div>
			<?php endif; ?>

			<div class="cbah-listenrow">
				<a class="cbah-listen" href="/radio/">&#9654; Listen on Pirate Radio</a>
			</div>
		</div>

	</div>
</div>

<?php
get_footer();
