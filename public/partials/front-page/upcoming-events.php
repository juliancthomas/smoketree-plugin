<?php
/**
 * Upcoming Events Section Partial
 *
 * Expects: $events (WP_Query of 'event' CPT)
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/partials/front-page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! $events->have_posts() ) {
	return;
}
?>

<section id="upcoming_events" class="stsrc-events">
	<div class="stsrc-events__inner">
		<h2 class="stsrc-events__title">Upcoming Events</h2>
		<div class="stsrc-events__grid">
			<?php while ( $events->have_posts() ) : $events->the_post(); ?>
				<div class="stsrc-events__card">
					<a href="<?php the_permalink(); ?>">
						<?php if ( has_post_thumbnail() ) : ?>
							<img
								loading="lazy"
								src="<?php the_post_thumbnail_url( 'medium' ); ?>"
								class="stsrc-events__card-img"
								alt="<?php echo esc_attr( get_the_title() ); ?>"
							>
						<?php endif; ?>
						<div class="stsrc-events__card-body">
							<h3 class="stsrc-events__card-name"><?php the_title(); ?></h3>
							<table class="stsrc-events__card-table">
								<tr>
									<td class="stsrc-events__card-label">Date &amp; Time</td>
									<td class="stsrc-events__card-date">
									<?php
										$start_raw = get_field( 'start_date', false, false );
										$end_raw   = get_field( 'end_date', false, false );
										$start     = new DateTime( $start_raw );
										$end       = new DateTime( $end_raw );

										echo esc_html(
											$start->format( 'Y-m-d' ) === $end->format( 'Y-m-d' )
												? $start->format( 'F j, Y' ) . ' • ' . $start->format( 'g:i A' ) . ' - ' . $end->format( 'g:i A' )
												: $start->format( 'F j, Y g:i A' ) . ' - ' . $end->format( 'F j, Y g:i A' )
										);
									?>
									</td>
								</tr>
								<tr>
									<td class="stsrc-events__card-label">Location</td>
									<td class="stsrc-events__card-value"><?php the_field( 'location' ); ?></td>
								</tr>
								<tr>
									<td class="stsrc-events__card-label">Cost</td>
									<td class="stsrc-events__card-value">
										<span class="stsrc-events__card-cost">
											<?php echo get_field( 'event_cost' ) ? esc_html( get_field( 'event_cost' ) ) : 'Free!'; ?>
										</span>
									</td>
								</tr>
							</table>
						</div>
					</a>
				</div>
			<?php endwhile; wp_reset_postdata(); ?>
		</div>

		<div class="stsrc-events__footer">
			<a href="<?php echo esc_url( home_url( '/events' ) ); ?>" class="stsrc-events__cta">
				See All Events
			</a>
		</div>
	</div>
</section>
