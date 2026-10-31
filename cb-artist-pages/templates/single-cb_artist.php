<?php
/**
 * CB Artist Pages — single cb_artist template.
 * Layout (Jacob, 2026-10-31): Title → circular photo → Bio → Baltimore
 * connection → Top tracks → Discography. Styled after the Radio Deck.
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$post_id = get_the_ID();

	$hood       = trim( (string) get_post_meta( $post_id, 'cb_hood', true ) );
	$connection = trim( (string) get_post_meta( $post_id, 'cb_connection', true ) );
	$links      = json_decode( (string) get_post_meta( $post_id, 'cb_links', true ) ?: '[]', true );
	$links      = is_array( $links ) ? array_filter( $links ) : [];
	$bio_html   = apply_filters( 'the_content', get_the_content() );
	$has_bio    = '' !== trim( wp_strip_all_tags( $bio_html ) );
	$tracks     = CB_Artist_Pages::top_tracks( $post_id, 5 );
	$channels   = CB_Artist_Pages::channels_map();
	$disco      = CB_Artist_Pages::discography( $post_id );
	$photo_url  = get_the_post_thumbnail_url( $post_id, 'medium' );
	$prev       = CB_Artist_Pages::neighbor( get_post(), 'prev' );
	$next       = CB_Artist_Pages::neighbor( get_post(), 'next' );
	$is_empty   = ! $has_bio && '' === $connection && '' === $hood && ! $tracks && ! $disco;
	?>

	<div id="primary" class="content-area">
		<div id="content" class="site-content" role="main">

			<article class="cbap">
				<div class="cbap-mast">
					<span class="cbap-beacon"></span>
					<span class="cbap-callsign">CAPT&middot;BLTMR &mdash; PIRATE RADIO</span>
					<span class="cbap-mast-r">Artist Profile</span>
				</div>

				<h1 class="cbap-title"><?php the_title(); ?></h1>

				<div class="cbap-avatarwrap">
					<?php if ( $photo_url ) : ?>
						<img class="cbap-avatar" src="<?php echo esc_url( $photo_url ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>">
					<?php else : ?>
						<div class="cbap-avatar cbap-avatar-fallback" aria-hidden="true">&#127988;&#8205;&#9760;&#65039;</div>
					<?php endif; ?>
				</div>

				<?php if ( $hood ) : ?>
					<div class="cbap-hoodrow"><span class="cbap-hood">&#128205; <?php echo esc_html( $hood ); ?> &middot; Baltimore</span></div>
				<?php endif; ?>

				<?php if ( $links ) : ?>
					<div class="cbap-links">
						<?php foreach ( $links as $url ) : ?>
							<a class="cbap-linkchip" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( CB_Artist_Pages::link_label( $url ) ); ?></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( $has_bio ) : ?>
					<section class="cbap-section">
						<h2 class="cbap-h cbap-h-pink">Bio</h2>
						<div class="cbap-body"><?php echo $bio_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- filtered post content ?></div>
					</section>
				<?php endif; ?>

				<?php if ( $connection || $hood ) : ?>
					<section class="cbap-section">
						<h2 class="cbap-h cbap-h-purple">Baltimore Connection</h2>
						<?php if ( $connection ) : ?>
							<div class="cbap-body"><?php echo wpautop( wp_kses_post( $connection ) ); ?></div>
						<?php else : ?>
							<div class="cbap-body"><p><?php echo esc_html( get_the_title() ); ?> is based in <?php echo esc_html( $hood ); ?>, Baltimore.</p></div>
						<?php endif; ?>
					</section>
				<?php endif; ?>

				<?php if ( $tracks ) : ?>
					<section class="cbap-section">
						<h2 class="cbap-h cbap-h-cyan">Top Tracks</h2>
						<div class="cbap-tracks">
							<?php foreach ( $tracks as $track ) :
								$ch = $channels[ $track->channel_slug ] ?? [
									'label'  => ucwords( str_replace( '-', ' ', $track->channel_slug ) ),
									'freq'   => '',
									'accent' => '#00C2E8',
								];
								?>
								<div class="cbap-track" style="--acc: <?php echo esc_attr( $ch['accent'] ); ?>">
									<?php if ( $track->art_url ) : ?>
										<img class="cbap-trackart" src="<?php echo esc_url( $track->art_url ); ?>" alt="">
									<?php endif; ?>
									<div class="cbap-trackmain">
										<span class="cbap-tracktitle"><?php echo esc_html( $track->track_title ); ?></span>
										<span class="cbap-trackstation"><?php echo esc_html( $ch['label'] ); ?></span>
									</div>
									<?php if ( (int) $track->votes > 0 ) : ?>
										<span class="cbap-votes">&#9650; <?php echo (int) $track->votes; ?></span>
									<?php endif; ?>
									<?php if ( $ch['freq'] ) : ?>
										<span class="cbap-dial"><?php echo esc_html( $ch['freq'] ); ?> FM</span>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( $disco ) : ?>
					<section class="cbap-section">
						<h2 class="cbap-h cbap-h-yellow">Discography</h2>
						<div class="cbap-tracks">
							<?php foreach ( $disco as $item ) : ?>
								<div class="cbap-track cbap-release">
									<div class="cbap-trackmain">
										<?php if ( $item['url'] ) : ?>
											<a class="cbap-tracktitle" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
										<?php else : ?>
											<span class="cbap-tracktitle"><?php echo esc_html( $item['title'] ); ?></span>
										<?php endif; ?>
										<?php if ( $item['note'] ) : ?>
											<span class="cbap-trackstation"><?php echo esc_html( $item['note'] ); ?></span>
										<?php endif; ?>
									</div>
									<?php if ( $item['year'] ) : ?>
										<span class="cbap-dial"><?php echo esc_html( $item['year'] ); ?></span>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( $is_empty ) : ?>
					<div class="cbap-soon">Full profile coming soon &mdash; hear <?php echo esc_html( get_the_title() ); ?> on Pirate Radio.</div>
				<?php endif; ?>

				<div class="cbap-listenrow">
					<a class="cbap-listen" href="/radio/">&#9654; Listen on Pirate Radio</a>
				</div>

				<nav class="cbap-nav">
					<?php if ( $prev ) : ?>
						<a class="cbap-navlink" href="<?php echo esc_url( get_permalink( $prev ) ); ?>">&larr; <?php echo esc_html( $prev->post_title ); ?></a>
					<?php else : ?>
						<span></span>
					<?php endif; ?>
					<a class="cbap-navlink cbap-navall" href="<?php echo esc_url( get_post_type_archive_link( 'cb_artist' ) ); ?>">All Artists</a>
					<?php if ( $next ) : ?>
						<a class="cbap-navlink" href="<?php echo esc_url( get_permalink( $next ) ); ?>"><?php echo esc_html( $next->post_title ); ?> &rarr;</a>
					<?php else : ?>
						<span></span>
					<?php endif; ?>
				</nav>
			</article>

		</div>
	</div>

	<?php
endwhile;

get_footer();
