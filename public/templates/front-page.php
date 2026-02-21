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
	: home_url( '/register' . ( $most_expensive ? '?membership_type_id=' . $most_expensive['membership_type_id'] : '' ) );
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

$partials_dir = plugin_dir_path( __FILE__ ) . '../partials/front-page/';

// Load plugin header.
require_once plugin_dir_path( __FILE__ ) . 'header.php';
?>

<main>
	<?php require $partials_dir . 'hero-section.php'; ?>

	<?php if ( is_user_logged_in() ) : ?>
		<?php if ( $events->have_posts() ) { require $partials_dir . 'upcoming-events.php'; } ?>
		<?php require $partials_dir . 'about-us.php'; ?>
	<?php else : ?>
		<?php require $partials_dir . 'membership-plans.php'; ?>
		<?php require $partials_dir . 'about-us.php'; ?>
		<?php require $partials_dir . 'membership-benefits.php'; ?>
		<?php if ( $events->have_posts() ) { require $partials_dir . 'upcoming-events.php'; } ?>
	<?php endif; ?>

	<?php require $partials_dir . 'signal-newsletter.php'; ?>
	<?php if ( $sponsors->have_posts() ) { require $partials_dir . 'sponsors.php'; } ?>
</main>

<?php
// Load plugin footer.
require_once plugin_dir_path( __FILE__ ) . 'footer.php';
