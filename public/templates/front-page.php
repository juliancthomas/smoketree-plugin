<?php
/**
 * Front Page Template
 *
 * The main landing page for the Smoketree website.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/templates
 * @since      1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load membership types from the plugin's custom table.
require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-membership-db.php';
$membership_types = STSRC_Membership_DB::get_all_membership_types( true );

$most_expensive = null;
foreach ( $membership_types as $type ) {
	if ( null === $most_expensive || $type['price'] > $most_expensive['price'] ) {
		$most_expensive = $type;
	}
}

$hero_url = is_user_logged_in()
	? home_url( '/member-portal' )
	: home_url( '/register' );
$hero_cta_label = is_user_logged_in() ? 'My Account' : 'Join Now';

$events = new WP_Query( [
	'post_type'      => 'event',
	'meta_key'       => 'end_date',
	'orderby'        => 'meta_value',
	'order'          => 'ASC',
	'posts_per_page' => 6,
	'meta_query'     => [ [
		'key'     => 'end_date',
		'value'   => current_time( 'Y-m-d H:i:s' ),
		'compare' => '>=',
		'type'    => 'DATETIME',
	] ],
] );

$sponsors = new WP_Query( [
	'post_type'      => 'sponsor',
	'posts_per_page' => -1,
	'meta_query'     => [ [
		'key'     => 'expiration_date',
		'value'   => date( 'd/m/Y' ),
		'compare' => '>=',
		'type'    => 'DATE',
	] ],
] );

if ( is_user_logged_in() ) {
	$hero_next_color = $events->have_posts() ? '#ffffff' : '#48758f';
} else {
	$hero_next_color = '#5d99bb';
}

function stsrc_wave_divider( $bg_color, $fill_color, $variant = 1 ) {
	$paths = [
		1 => 'M0,40 C360,80 1080,0 1440,40 L1440,80 L0,80 Z',
		2 => 'M0,56 C480,0 960,80 1440,24 L1440,80 L0,80 Z',
		3 => 'M0,24 C320,72 640,8 960,56 C1280,88 1400,48 1440,48 L1440,80 L0,80 Z',
	];
	$path = $paths[ $variant ] ?? $paths[1];
	?>
	<div class="leading-[0]" style="background-color: <?php echo esc_attr( $bg_color ); ?>;" aria-hidden="true">
		<svg viewBox="0 0 1440 80" preserveAspectRatio="none" class="block w-full h-[40px] sm:h-[60px]">
			<path fill="<?php echo esc_attr( $fill_color ); ?>" d="<?php echo $path; ?>"/>
		</svg>
	</div>
	<?php
}

$partials_dir = plugin_dir_path( __FILE__ ) . '../partials/front-page/';

// Load plugin header.
require_once plugin_dir_path( __FILE__ ) . 'header.php';
?>

<main>
	<?php require $partials_dir . 'hero-section.php'; ?>

	<?php if ( is_user_logged_in() ) : ?>

		<?php if ( $events->have_posts() ) : ?>
			<?php require $partials_dir . 'upcoming-events.php'; ?>
			<?php stsrc_wave_divider( '#ffffff', '#48758f', 2 ); ?>
		<?php endif; ?>

		<?php require $partials_dir . 'about-us.php'; ?>
		<?php stsrc_wave_divider( '#48758f', '#f3f4f6', 3 ); ?>

	<?php else : ?>

		<?php require $partials_dir . 'membership-plans.php'; ?>
		<?php stsrc_wave_divider( '#5d99bb', '#48758f', 2 ); ?>

		<?php require $partials_dir . 'about-us.php'; ?>
		<?php stsrc_wave_divider( '#48758f', '#345365', 3 ); ?>

		<?php require $partials_dir . 'membership-benefits.php'; ?>

		<?php if ( $events->have_posts() ) : ?>
			<?php stsrc_wave_divider( '#345365', '#ffffff', 1 ); ?>
			<?php require $partials_dir . 'upcoming-events.php'; ?>
			<?php stsrc_wave_divider( '#ffffff', '#f3f4f6', 2 ); ?>
		<?php else : ?>
			<?php stsrc_wave_divider( '#345365', '#f3f4f6', 1 ); ?>
		<?php endif; ?>

	<?php endif; ?>

	<?php require $partials_dir . 'signal-newsletter.php'; ?>

	<?php if ( $sponsors->have_posts() ) : ?>
		<?php stsrc_wave_divider( '#f3f4f6', '#ffffff', 3 ); ?>
		<?php require $partials_dir . 'sponsors.php'; ?>
	<?php endif; ?>
</main>

<?php
// Load plugin footer.
require_once plugin_dir_path( __FILE__ ) . 'footer.php';
