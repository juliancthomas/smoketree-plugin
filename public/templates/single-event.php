<?php
/**
 * Template: Single Event Detail
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Bootstrap the post.
if ( have_posts() ) {
	the_post();
}

$start_date  = get_field( 'start_date' );
$end_date    = get_field( 'end_date' );
$location    = get_field( 'location' );
$event_cost  = get_field( 'event_cost' );
$sign_up_url = get_field( 'sign_up_url' );

$start_ts = $start_date ? strtotime( $start_date ) : false;

$date_display = '';
if ( $start_ts ) {
	$date_display = wp_date( 'F j, Y g:i a', $start_ts );
	if ( $end_date ) {
		$end_ts = strtotime( $end_date );
		// Same day → show only time range on same line.
		if ( wp_date( 'Y-m-d', $start_ts ) === wp_date( 'Y-m-d', $end_ts ) ) {
			$date_display .= ' &ndash; ' . wp_date( 'g:i a', $end_ts );
		} else {
			$date_display .= ' &ndash; ' . wp_date( 'F j, Y g:i a', $end_ts );
		}
	}
}

require_once plugin_dir_path( __FILE__ ) . 'header.php';
?>

<main class="stsrc-event-single">

	<!-- Hero -->
	<div class="stsrc-event-single__hero">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'full', array( 'class' => 'stsrc-event-single__hero-img', 'alt' => get_the_title() ) ); ?>
		<?php else : ?>
			<div class="stsrc-event-single__hero-placeholder"></div>
		<?php endif; ?>

		<?php if ( $start_ts ) : ?>
		<span class="stsrc-event-single__badge">
			<span class="stsrc-event-single__badge-month"><?php echo esc_html( wp_date( 'M', $start_ts ) ); ?></span>
			<span class="stsrc-event-single__badge-day"><?php echo esc_html( wp_date( 'j', $start_ts ) ); ?></span>
		</span>
		<?php endif; ?>
	</div>

	<!-- Body -->
	<div class="stsrc-event-single__body">

		<h1 class="stsrc-event-single__title"><?php the_title(); ?></h1>

		<ul class="stsrc-event-single__meta">
			<?php if ( $date_display ) : ?>
			<li class="stsrc-event-single__meta-item">
				<svg class="stsrc-event-single__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
				<?php echo $date_display; ?>
			</li>
			<?php endif; ?>
			<?php if ( $location ) : ?>
			<li class="stsrc-event-single__meta-item">
				<svg class="stsrc-event-single__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 1 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
				<?php echo esc_html( $location ); ?>
			</li>
			<?php endif; ?>
			<?php if ( $event_cost ) : ?>
			<li class="stsrc-event-single__meta-item">
				<svg class="stsrc-event-single__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
				<?php echo esc_html( $event_cost ); ?>
			</li>
			<?php endif; ?>
		</ul>

		<?php if ( get_the_content() ) : ?>
			<div class="stsrc-event-single__content">
				<?php the_content(); ?>
			</div>
		<?php endif; ?>

		<div class="stsrc-event-single__actions">
			<?php if ( $sign_up_url ) : ?>
				<a href="<?php echo esc_url( $sign_up_url ); ?>" class="stsrc-event-single__cta" target="_blank" rel="noopener noreferrer">
					Sign Up &rarr;
				</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( home_url( '/events/' ) ); ?>" class="stsrc-event-single__back">
				&larr; Back to Events
			</a>
		</div>

	</div>

</main>

<style>
	.stsrc-event-single {
		max-width: 52rem;
		margin: 0 auto;
		padding-bottom: 4rem;
	}

	/* Hero */
	.stsrc-event-single__hero {
		position: relative;
		width: 100%;
		aspect-ratio: 16 / 7;
		background: #f3f4f6;
		overflow: hidden;
	}
	.stsrc-event-single__hero-img {
		width: 100%;
		height: 100%;
		object-fit: cover;
		display: block;
	}
	.stsrc-event-single__hero-placeholder {
		width: 100%;
		height: 100%;
		background: linear-gradient(135deg, #e0f2fe 0%, #c3e1e1 100%);
	}

	/* Date badge */
	.stsrc-event-single__badge {
		position: absolute;
		top: 1rem;
		left: 1rem;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		width: 3.75rem;
		height: 4rem;
		background: #ffffff;
		border-radius: 0.625rem;
		box-shadow: 0 2px 8px rgba(0,0,0,0.15);
		line-height: 1;
	}
	.stsrc-event-single__badge-month {
		font-size: 0.7rem;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.05em;
		color: #059669;
	}
	.stsrc-event-single__badge-day {
		font-size: 1.4rem;
		font-weight: 700;
		color: #1f2937;
	}

	/* Body */
	.stsrc-event-single__body {
		padding: 2rem 1rem 0;
	}
	@media (min-width: 640px) {
		.stsrc-event-single__body { padding: 2.5rem 1.5rem 0; }
	}

	.stsrc-event-single__title {
		font-size: 1.75rem;
		font-weight: 700;
		color: #1f2937;
		margin: 0 0 1.25rem;
		line-height: 1.25;
	}
	@media (min-width: 640px) {
		.stsrc-event-single__title { font-size: 2.25rem; }
	}

	/* Meta */
	.stsrc-event-single__meta {
		list-style: none;
		margin: 0 0 1.75rem;
		padding: 0;
		display: flex;
		flex-direction: column;
		gap: 0.6rem;
		border-left: 3px solid #059669;
		padding-left: 1rem;
	}
	.stsrc-event-single__meta-item {
		display: flex;
		align-items: flex-start;
		gap: 0.5rem;
		font-size: 0.95rem;
		color: #374151;
		line-height: 1.45;
	}
	.stsrc-event-single__icon {
		width: 1.1rem;
		height: 1.1rem;
		flex-shrink: 0;
		margin-top: 0.15rem;
		color: #059669;
	}

	/* Content */
	.stsrc-event-single__content {
		color: #374151;
		font-size: 1rem;
		line-height: 1.75;
		margin-bottom: 2rem;
	}
	.stsrc-event-single__content p { margin: 0 0 1em; }
	.stsrc-event-single__content p:last-child { margin-bottom: 0; }

	/* Actions */
	.stsrc-event-single__actions {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 1rem;
		margin-top: 2rem;
	}
	.stsrc-event-single__cta {
		display: inline-block;
		padding: 0.625rem 1.5rem;
		background-color: #059669;
		color: #ffffff;
		font-weight: 600;
		font-size: 0.95rem;
		border-radius: 0.5rem;
		text-decoration: none;
		transition: background-color 0.2s ease;
	}
	.stsrc-event-single__cta:hover { background-color: #047857; }
	.stsrc-event-single__back {
		font-size: 0.875rem;
		color: #6b7280;
		text-decoration: none;
		transition: color 0.2s ease;
	}
	.stsrc-event-single__back:hover { color: #374151; }
</style>

<?php require_once plugin_dir_path( __FILE__ ) . 'footer.php'; ?>
