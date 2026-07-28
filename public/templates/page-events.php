<?php
/**
 * Template: Events List
 *
 * Displays upcoming events in a card grid with a FullCalendar month view.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Query upcoming events.
$events_query = new WP_Query( array(
	'post_type'      => 'event',
	'posts_per_page' => 8,
	'meta_key'       => 'start_date',
	'orderby'        => 'meta_value',
	'order'          => 'ASC',
	'meta_query'     => array( array(
		'key'     => 'start_date',
		'value'   => wp_date( 'Y-m-d', strtotime( '-1 day' ) ),
		'compare' => '>=',
		'type'    => 'DATE',
	) ),
) );

require_once plugin_dir_path( __FILE__ ) . 'header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">

<main class="stsrc-events">

	<?php while ( have_posts() ) : the_post(); ?>
	<section class="stsrc-events__intro">
		<h1 class="stsrc-events__title"><?php the_title(); ?></h1>
		<?php if ( get_the_content() ) : ?>
			<div class="stsrc-events__description"><?php the_content(); ?></div>
		<?php endif; ?>
	</section>
	<?php endwhile; ?>

	<!-- Event Cards -->
	<section class="stsrc-events__grid-wrap">
		<?php if ( $events_query->have_posts() ) : ?>
			<div class="stsrc-events__grid">
				<?php while ( $events_query->have_posts() ) : $events_query->the_post();

					$start_date  = get_field( 'start_date' );
					$end_date    = get_field( 'end_date' );
					$location    = get_field( 'location' );
					$event_cost  = get_field( 'event_cost' );
					$sign_up_url = get_field( 'sign_up_url' );
					$post_url    = get_permalink();

					$start_ts   = strtotime( $start_date );
					$month_abbr = wp_date( 'M', $start_ts );
					$day_num    = wp_date( 'j', $start_ts );

					$date_display = wp_date( 'M j, Y', $start_ts );
					if ( $end_date ) {
						$date_display .= ' &ndash; ' . wp_date( 'M j, Y', strtotime( $end_date ) );
					}
				?>
				<a href="<?php echo esc_url( $post_url ); ?>" class="stsrc-event-card">
					<div class="stsrc-event-card__image-wrap">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'medium_large', array( 'class' => 'stsrc-event-card__image' ) ); ?>
						<?php else : ?>
							<div class="stsrc-event-card__image stsrc-event-card__placeholder"></div>
						<?php endif; ?>
						<span class="stsrc-event-card__badge">
							<span class="stsrc-event-card__badge-month"><?php echo esc_html( $month_abbr ); ?></span>
							<span class="stsrc-event-card__badge-day"><?php echo esc_html( $day_num ); ?></span>
						</span>
					</div>
					<div class="stsrc-event-card__body">
						<h2 class="stsrc-event-card__title"><?php the_title(); ?></h2>
						<ul class="stsrc-event-card__meta">
							<li class="stsrc-event-card__meta-item">
								<svg class="stsrc-event-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
								<?php echo wp_kses( $date_display, array() ); ?>
							</li>
							<?php if ( $location ) : ?>
							<li class="stsrc-event-card__meta-item">
								<svg class="stsrc-event-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 1 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
								<?php echo esc_html( $location ); ?>
							</li>
							<?php endif; ?>
							<?php if ( $event_cost ) : ?>
							<li class="stsrc-event-card__meta-item">
								<svg class="stsrc-event-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
								<?php echo esc_html( $event_cost ); ?>
							</li>
							<?php endif; ?>
						</ul>
						<?php if ( $sign_up_url ) : ?>
							<span class="stsrc-event-card__cta">Sign Up &rarr;</span>
						<?php endif; ?>
					</div>
				</a>
				<?php endwhile; ?>
			</div>
		<?php else : ?>
			<p class="stsrc-events__empty">No upcoming events right now &mdash; check back soon!</p>
		<?php endif; ?>
		<?php wp_reset_postdata(); ?>
	</section>

	<!-- FullCalendar -->
	<section class="stsrc-events__calendar-wrap">
		<h2 class="stsrc-events__calendar-heading">Event Calendar</h2>
		<div id="stsrc-events-calendar"></div>
	</section>

</main>

<style>
	.stsrc-events {
		max-width: 72rem;
		margin: 0 auto;
		padding: 2rem 1rem 4rem;
	}
	.stsrc-events__intro {
		text-align: center;
		margin-bottom: 2.5rem;
	}
	.stsrc-events__title {
		font-size: 2rem;
		font-weight: 700;
		color: #1f2937;
		margin: 0 0 0.75rem;
		line-height: 1.25;
	}
	@media (min-width: 768px) {
		.stsrc-events__title { font-size: 2.5rem; }
	}
	.stsrc-events__description {
		color: #4b5563;
		font-size: 1.05rem;
		line-height: 1.7;
		max-width: 40rem;
		margin: 0 auto;
	}
	.stsrc-events__description p:last-child { margin-bottom: 0; }
	.stsrc-events__grid {
		display: grid;
		gap: 1.5rem;
		grid-template-columns: 1fr;
	}
	@media (min-width: 600px)  { .stsrc-events__grid { grid-template-columns: repeat(2, 1fr); } }
	@media (min-width: 960px)  { .stsrc-events__grid { grid-template-columns: repeat(3, 1fr); } }
	@media (min-width: 1200px) { .stsrc-events__grid { grid-template-columns: repeat(4, 1fr); } }
	.stsrc-events__empty {
		text-align: center;
		color: #6b7280;
		font-size: 1.05rem;
		padding: 3rem 1rem;
	}
	.stsrc-event-card {
		display: flex;
		flex-direction: column;
		background: #ffffff;
		border: 1px solid #e5e7eb;
		border-radius: 0.75rem;
		overflow: hidden;
		text-decoration: none;
		color: inherit;
		transition: box-shadow 0.2s ease, transform 0.2s ease;
	}
	.stsrc-event-card:hover {
		box-shadow: 0 8px 24px rgba(0,0,0,0.1);
		transform: translateY(-2px);
	}
	.stsrc-event-card__image-wrap {
		position: relative;
		overflow: hidden;
		aspect-ratio: 16 / 10;
		background: #f3f4f6;
	}
	.stsrc-event-card__image {
		width: 100%;
		height: 100%;
		object-fit: cover;
		display: block;
	}
	.stsrc-event-card__placeholder {
		width: 100%;
		height: 100%;
		background: linear-gradient(135deg, #e0f2fe 0%, #c3e1e1 100%);
	}
	.stsrc-event-card__badge {
		position: absolute;
		top: 0.75rem;
		left: 0.75rem;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		width: 3rem;
		height: 3.25rem;
		background: #ffffff;
		border-radius: 0.5rem;
		box-shadow: 0 2px 6px rgba(0,0,0,0.12);
		line-height: 1;
	}
	.stsrc-event-card__badge-month {
		font-size: 0.65rem;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.05em;
		color: #059669;
	}
	.stsrc-event-card__badge-day {
		font-size: 1.15rem;
		font-weight: 700;
		color: #1f2937;
	}
	.stsrc-event-card__body {
		display: flex;
		flex-direction: column;
		flex: 1;
		padding: 1rem 1rem 1.25rem;
	}
	.stsrc-event-card__title {
		font-size: 1.05rem;
		font-weight: 600;
		color: #1f2937;
		margin: 0 0 0.625rem;
		line-height: 1.35;
	}
	.stsrc-event-card__meta {
		list-style: none;
		margin: 0;
		padding: 0;
		display: flex;
		flex-direction: column;
		gap: 0.35rem;
		flex: 1;
	}
	.stsrc-event-card__meta-item {
		display: flex;
		align-items: flex-start;
		gap: 0.4rem;
		font-size: 0.825rem;
		color: #4b5563;
		line-height: 1.4;
	}
	.stsrc-event-card__icon {
		width: 0.95rem;
		height: 0.95rem;
		flex-shrink: 0;
		margin-top: 0.1rem;
		color: #9ca3af;
	}
	.stsrc-event-card__cta {
		display: inline-block;
		margin-top: 0.75rem;
		font-size: 0.825rem;
		font-weight: 600;
		color: #059669;
		transition: color 0.15s ease;
	}
	.stsrc-event-card:hover .stsrc-event-card__cta { color: #047857; }
	.stsrc-events__calendar-wrap {
		margin-top: 4rem;
		max-width: 56rem;
		margin-left: auto;
		margin-right: auto;
	}
	.stsrc-events__calendar-heading {
		font-size: 1.5rem;
		font-weight: 700;
		color: #1f2937;
		text-align: center;
		margin: 0 0 1.5rem;
	}
	#stsrc-events-calendar {
		background: #ffffff;
		border: 1px solid #e5e7eb;
		border-radius: 0.75rem;
		padding: 1rem;
	}
	#stsrc-events-calendar .fc-toolbar-title { font-size: 1.15rem; font-weight: 600; }
	#stsrc-events-calendar .fc-button-primary { background-color: #059669; border-color: #059669; }
	#stsrc-events-calendar .fc-button-primary:hover { background-color: #047857; border-color: #047857; }
	#stsrc-events-calendar .fc-button-primary:disabled { background-color: #d1d5db; border-color: #d1d5db; }
	#stsrc-events-calendar .fc-daygrid-event { border-radius: 0.25rem; padding: 1px 4px; font-size: 0.8rem; }
	#stsrc-events-calendar .fc-event { background-color: #059669; border-color: #059669; cursor: pointer; }
	#stsrc-events-calendar .fc-event:hover { background-color: #047857; border-color: #047857; }
</style>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
	var calendarEl = document.getElementById('stsrc-events-calendar');
	if (!calendarEl) return;
	var calendar = new FullCalendar.Calendar(calendarEl, {
		initialView: 'dayGridMonth',
		headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
		height: 'auto',
		events: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>?action=fetch_events',
		eventClick: function (info) {
			info.jsEvent.preventDefault();
			if (info.event.url) {
				window.location.href = info.event.url;
			}
		}
	});
	calendar.render();
});
</script>

<?php require_once plugin_dir_path( __FILE__ ) . 'footer.php'; ?>
