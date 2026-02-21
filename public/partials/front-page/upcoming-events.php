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

<section id="upcoming_events" class="px-6 my-20">
	<div class="container mx-auto">
		<h2 class="text-3xl text-center text-black dark:text-[#ececec] mb-10">Upcoming Events</h2>
		<div class="flex flex-wrap gap-6 justify-center">
			<?php while ( $events->have_posts() ) : $events->the_post(); ?>
				<div class="w-[350px] bg-white dark:bg-[#0d0d0d] shadow-lg rounded-lg overflow-hidden text-center flex flex-col justify-between">
					<a href="<?php the_permalink(); ?>">
						<?php if ( has_post_thumbnail() ) : ?>
							<img
								loading="lazy"
								src="<?php the_post_thumbnail_url( 'medium' ); ?>"
								class="object-cover w-full h-48"
								alt="<?php echo esc_attr( get_the_title() ); ?>"
							>
						<?php endif; ?>
						<div class="p-4">
							<h3 class="text-xl mb-4 text-black dark:text-[#d9e7fb]"><?php the_title(); ?></h3>
							<table class="w-full text-left">
								<tr>
									<td class="font-bold text-sm text-gray-700 dark:text-[#d9e7fb]">Date &amp; Time</td>
									<td class="text-red-700 dark:text-red-300 text-sm">
									<?php
										$start_raw = get_field( 'start_date', false, false );
										$end_raw   = get_field( 'end_date', false, false );
										$start     = new DateTime( $start_raw );
										$end       = new DateTime( $end_raw );

										echo esc_html(
											$start->format( 'Y-m-d' ) === $end->format( 'Y-m-d' )
												? $start->format( 'F j, Y \u{2022} g:i A' ) . ' - ' . $end->format( 'g:i A' )
												: $start->format( 'F j, Y g:i A' ) . ' - ' . $end->format( 'F j, Y g:i A' )
										);
									?>
									</td>
								</tr>
								<tr>
									<td class="font-bold text-sm text-gray-700 dark:text-[#d9e7fb]">Location</td>
									<td class="text-sm text-gray-700 dark:text-[#d9e7fb]"><?php the_field( 'location' ); ?></td>
								</tr>
								<tr>
									<td class="font-bold text-sm text-gray-700 dark:text-[#d9e7fb]">Cost</td>
									<td class="text-sm text-gray-700 dark:text-[#d9e7fb]">
										<span class="font-bold">
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

		<div class="mt-10 text-center">
			<a href="<?php echo esc_url( home_url( '/events' ) ); ?>" class="bg-blue-500 hover:bg-blue-700 text-white py-2 px-4 rounded-full text-sm">
				See All Events
			</a>
		</div>
	</div>
</section>
