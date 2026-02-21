<?php
/**
 * The Signal Newsletter Section Partial
 *
 * Uses ACF repeater field 'news_and_announcements' on page ID 1032.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/partials/front-page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! have_rows( 'news_and_announcements', 1032 ) ) {
	return;
}
?>

<section id="signal_newsletter" class="stsrc-newsletter">
	<div class="stsrc-newsletter__header">
		<h2 class="stsrc-newsletter__title">The Signal Newsletter</h2>
		<p class="stsrc-newsletter__desc">
			Stay updated with our monthly newsletter. Read the latest issue or browse past editions.
		</p>

		<?php the_row(); $pdf = get_sub_field( 'pdf_upload' ); ?>
		<?php if ( $pdf ) : ?>
			<a href="<?php echo esc_url( $pdf['url'] ); ?>" target="_blank" class="stsrc-newsletter__latest-cta">
				View Latest Issue (PDF)
			</a>
		<?php endif; ?>
	</div>

	<?php
		$max_previous   = 6;
		$previous_count = 0;
		$has_more       = false;
	?>
	<div class="stsrc-newsletter__previous">
		<h3 class="stsrc-newsletter__previous-title">Previous Issues</h3>
		<ul class="stsrc-newsletter__list">
			<?php while ( have_rows( 'news_and_announcements', 1032 ) ) : the_row(); $pdf = get_sub_field( 'pdf_upload' ); ?>
				<?php if ( $pdf ) : ?>
					<?php if ( $previous_count >= $max_previous ) : ?>
						<?php $has_more = true; break; ?>
					<?php endif; ?>
					<li>
						<a href="<?php echo esc_url( $pdf['url'] ); ?>" target="_blank">
							<?php the_sub_field( 'news_title' ); ?>
						</a>
					</li>
					<?php $previous_count++; ?>
				<?php endif; ?>
			<?php endwhile; ?>
		</ul>
		<?php if ( $has_more ) : ?>
			<a href="<?php echo esc_url( get_permalink( 1032 ) ); ?>" class="stsrc-newsletter__see-all">
				See all issues &rarr;
			</a>
		<?php endif; ?>
	</div>
</section>
