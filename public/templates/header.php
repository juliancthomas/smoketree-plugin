<?php
/**
 * Custom Header Template for Smoketree Plugin
 * 
 * This header replaces the theme header site-wide for public and member pages.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/templates
 * @since      1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Start session if not already started
if ( session_status() === PHP_SESSION_NONE ) {
	session_start();
}

// Announcement banner
$stsrc_banner_active = false;
$stsrc_banner        = array();
$stsrc_banner_key    = '';

if ( '1' === get_option( 'stsrc_banner_enabled', '0' ) ) {
	$stsrc_banner_message  = get_option( 'stsrc_banner_message', '' );
	$stsrc_banner_expiry   = get_option( 'stsrc_banner_expiry_date', '' );
	$stsrc_banner_audience = get_option( 'stsrc_banner_audience', 'all' );

	$stsrc_banner_expired = ! empty( $stsrc_banner_expiry ) && strtotime( $stsrc_banner_expiry . ' 23:59:59' ) < time();
	$stsrc_audience_match = (
		'all' === $stsrc_banner_audience ||
		( 'members' === $stsrc_banner_audience && is_user_logged_in() ) ||
		( 'public' === $stsrc_banner_audience && ! is_user_logged_in() )
	);

	if ( ! empty( $stsrc_banner_message ) && ! $stsrc_banner_expired && $stsrc_audience_match ) {
		$stsrc_banner_active = true;
		$stsrc_banner        = array(
			'message'         => $stsrc_banner_message,
			'size'            => get_option( 'stsrc_banner_size', 'small' ),
			'type'            => get_option( 'stsrc_banner_type', 'info' ),
			'dismissible'     => '1' === get_option( 'stsrc_banner_dismissible', '1' ),
			'link_label'      => get_option( 'stsrc_banner_link_label', '' ),
			'link_url'        => get_option( 'stsrc_banner_link_url', '' ),
			'star_text'       => get_option( 'stsrc_banner_star_text', '' ),
			'star_bg_color'   => get_option( 'stsrc_banner_star_bg_color', '#facc15' ),
			'star_text_color' => get_option( 'stsrc_banner_star_text_color', '#1a1a1a' ),
		);
		$stsrc_banner_key = substr( md5( $stsrc_banner_message ), 0, 8 );
	}
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta http-equiv="Content-Language" content="en">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	
	<title><?php wp_title( '|', true, 'right' ); ?><?php bloginfo( 'name' ); ?></title>
	
	<!-- SEO Meta Tags -->
	<meta name="title" content="Smoketree Swim & Recreation Club - Community and Amenities">
	<meta name="description" content="Smoketree Swim & Recreation Club maintains neighborhood amenities like a junior Olympic-sized pool, tennis courts, and the cabana. Join today to enjoy year-round access and social events.">
	<meta name="keywords" content="Smoketree, swim club, recreation, neighborhood pool, tennis courts, pickleball, community events">
	<meta name="robots" content="index, follow">
	<meta name="author" content="Julian Thomas">
	<meta name="theme-color" content="#c3e1e1">
	
	<!-- Open Graph Meta Tags -->
	<meta property="og:title" content="Smoketree Swim & Recreation Club">
	<meta property="og:description" content="Smoketree Swim & Recreation Club maintains neighborhood amenities like a junior Olympic-sized pool, tennis courts, and the cabana. Join today to enjoy year-round access and social events.">
	<meta property="og:image" content="<?php echo esc_url( get_template_directory_uri() . '/ssrc-1200.jpg' ); ?>">
	<meta property="og:url" content="https://smoketree.us">
	<meta property="og:type" content="website">
	<meta property="og:locale" content="en_US">
	<meta property="og:site_name" content="Smoketree Swim & Recreation Club">
	
	<!-- Twitter Card Meta Tags -->
	<meta name="twitter:card" content="summary_large_image">
	<meta name="twitter:title" content="Smoketree Swim & Recreation Club">
	<meta name="twitter:description" content="Smoketree Swim & Recreation Club maintains neighborhood amenities like a junior Olympic-sized pool, tennis courts, and the cabana. Join today to enjoy year-round access and social events.">
	<meta name="twitter:image" content="<?php echo esc_url( get_template_directory_uri() . '/ssrc-1200.jpg' ); ?>">
	<meta property="twitter:domain" content="smoketree.us">
	
	<!-- Favicons -->
	<link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url( get_template_directory_uri() . '/apple-touch-icon.png' ); ?>">
	<link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url( get_template_directory_uri() . '/favicon-32x32.png' ); ?>">
	<link rel="icon" type="image/png" sizes="16x16" href="<?php echo esc_url( get_template_directory_uri() . '/favicon-16x16.png' ); ?>">
	<link rel="manifest" href="<?php echo esc_url( get_template_directory_uri() . '/manifest.json' ); ?>">
	
	<!-- Preload Fonts -->
	<link rel="preload" as="style" href="<?php echo esc_url( get_template_directory_uri() . '/assets/css/fonts.css' ); ?>" onload="this.onload=null;this.rel='stylesheet';">
	<noscript><link rel="stylesheet" href="<?php echo esc_url( get_template_directory_uri() . '/assets/css/fonts.css' ); ?>"></noscript>
	
	<?php wp_head(); ?>
</head>

<style>
	/* WordPress Admin Bar Compatibility */
	html {
		margin-top: 0 !important;
	}
	
	body.admin-bar {
		padding-top: 32px;
	}
	
	body.admin-bar header.stsrc-header {
		top: 32px;
	}
	
	@media screen and (max-width: 782px) {
		body.admin-bar {
			padding-top: 46px;
		}
		
		body.admin-bar header.stsrc-header {
			top: 46px;
		}
	}
	
	/* Header Styles */
	.stsrc-header {
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		z-index: 999;
		background-color: #f3f4f6;
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
	}
	
	.stsrc-header-container {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 0.75rem 1rem;
		flex-wrap: wrap;
		gap: 0.5rem;
	}
	
	@media (min-width: 768px) {
		.stsrc-header-container {
			padding: 0.5rem 1rem;
		}
	}
	
	.stsrc-logo-link {
		display: flex;
		align-items: center;
		flex-shrink: 0;
	}
	
	.stsrc-logo {
		height: 2.5rem;
		width: auto;
	}
	
	.stsrc-menu-toggle {
		display: block;
		padding: 0.5rem;
		margin-left: auto;
		color: #374151;
		background: none;
		border: none;
		cursor: pointer;
	}
	
	@media (min-width: 768px) {
		.stsrc-menu-toggle {
			display: none;
		}
	}
	
	.stsrc-nav {
		display: none;
		width: 100%;
		flex-direction: column;
	}
	
	.stsrc-nav.active {
		display: flex;
	}
	
	@media (min-width: 768px) {
		.stsrc-nav {
			display: flex;
			width: auto;
			flex-direction: row;
			flex-wrap: wrap;
			align-items: center;
			gap: 1rem;
		}
	}
	
	.stsrc-nav-link {
		display: block;
		padding: 0.5rem 1rem;
		color: #374151;
		text-decoration: none;
		transition: color 0.2s ease;
	}
	
	.stsrc-nav-link:hover {
		color: #059669;
	}

	/* Body Padding for Fixed Header — overridden dynamically by JS to account for banner */
	body {
		padding-top: 74px;
	}

	.stsrc-site-body {
		background-color: #ffffff;
	}

	@media (min-width: 768px) {
		body {
			padding-top: 50px;
		}
	}

	/* Announcement Banner */
	.stsrc-banner {
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		z-index: 9999;
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 0.5rem;
		padding: 0.5rem 1rem;
		font-size: 0.875rem;
		line-height: 1.4;
		text-align: center;
		overflow: visible;
	}

	.stsrc-banner--info    { background-color: #dbeafe; color: #1e40af; }
	.stsrc-banner--warning { background-color: #ffedd5; color: #9a3412; }
	.stsrc-banner--alert   { background-color: #fee2e2; color: #991b1b; }
	.stsrc-banner--success { background-color: #dcfce7; color: #166534; }

	/* size modifiers — small is the base style above */
	.stsrc-banner--medium     { font-size: 1.5rem; padding: 1.25rem 1.5rem; font-weight: 700; }
	.stsrc-banner--large      { font-size: 2.5rem; padding: 2.5rem 2rem;  font-weight: 800; }
	.stsrc-banner--xl         { font-size: 4rem;   min-height: 33vh;      font-weight: 900; padding: 2rem; }
	.stsrc-banner--fullscreen {
		top: 5px;
		left: 5px;
		width: calc(100% - 10px);
		min-height: calc(100vh - 10px);
		font-size: 4rem;
		font-weight: 900;
		border-radius: 6px;
	}

	/* Dismiss button — absolutely positioned top-right, scales with banner size */
	.stsrc-banner-dismiss {
		position: absolute;
		top: 10px;
		right: 10px;
		display: flex;
		align-items: center;
		background: none;
		border: none;
		cursor: pointer;
		color: inherit;
		opacity: 0.6;
		padding: 0.25rem;
	}
	.stsrc-banner-dismiss:hover { opacity: 1; }
	.stsrc-banner-dismiss svg   { width: 14px; height: 14px; }

	.stsrc-banner--medium .stsrc-banner-dismiss            { top: 12px; right: 12px; }
	.stsrc-banner--medium .stsrc-banner-dismiss svg        { width: 16px; height: 16px; }
	.stsrc-banner--large .stsrc-banner-dismiss             { top: 20px; right: 20px; }
	.stsrc-banner--large .stsrc-banner-dismiss svg         { width: 28px; height: 28px; }
	.stsrc-banner--xl .stsrc-banner-dismiss                { top: 24px; right: 24px; }
	.stsrc-banner--xl .stsrc-banner-dismiss svg            { width: 36px; height: 36px; }
	.stsrc-banner--fullscreen .stsrc-banner-dismiss        { top: 28px; right: 28px; }
	.stsrc-banner--fullscreen .stsrc-banner-dismiss svg    { width: 44px; height: 44px; }

	/* Star sticker */
	@media (max-width: 767px) { .stsrc-banner-star { display: none !important; } }

	.stsrc-banner-star {
		position: absolute;
		top: 50%;
		transform: translateY(-50%);
		left: 16px;
		width: 68px;
		height: 68px;
		clip-path: polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%);
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 0.6rem;
		font-weight: 800;
		text-align: center;
		line-height: 1.1;
		padding: 18px 10px 10px;
		z-index: 1;
	}

	.stsrc-banner-content {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		flex-wrap: wrap;
		justify-content: center;
	}

	.stsrc-banner-link {
		font-weight: 600;
		text-decoration: underline;
		color: inherit;
		white-space: nowrap;
	}

	.stsrc-banner-link:hover { opacity: 0.8; }
</style>

<body <?php body_class( 'stsrc-site-body' ); ?>>
<?php wp_body_open(); ?>

<?php if ( $stsrc_banner_active ) : ?>
<div id="stsrc-banner"
	class="stsrc-banner stsrc-banner--<?php echo esc_attr( $stsrc_banner['type'] ); ?> stsrc-banner--<?php echo esc_attr( $stsrc_banner['size'] ); ?>"
	role="alert"
	<?php if ( $stsrc_banner['dismissible'] ) : ?>data-banner-key="<?php echo esc_attr( $stsrc_banner_key ); ?>"<?php endif; ?>>
	<div class="stsrc-banner-content">
		<span><?php echo esc_html( $stsrc_banner['message'] ); ?></span>
		<?php if ( ! empty( $stsrc_banner['link_url'] ) && ! empty( $stsrc_banner['link_label'] ) ) : ?>
			<a href="<?php echo esc_url( $stsrc_banner['link_url'] ); ?>" class="stsrc-banner-link"><?php echo esc_html( $stsrc_banner['link_label'] ); ?></a>
		<?php endif; ?>
	</div>
	<?php if ( $stsrc_banner['dismissible'] ) : ?>
		<button type="button" class="stsrc-banner-dismiss" aria-label="<?php echo esc_attr__( 'Dismiss announcement', 'smoketree-plugin' ); ?>">
			<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
		</button>
	<?php endif; ?>
	<?php if ( ! empty( $stsrc_banner['star_text'] ) ) : ?>
		<div class="stsrc-banner-star" aria-hidden="true" style="background-color:<?php echo esc_attr( $stsrc_banner['star_bg_color'] ); ?>;color:<?php echo esc_attr( $stsrc_banner['star_text_color'] ); ?>;">
			<?php echo esc_html( $stsrc_banner['star_text'] ); ?>
		</div>
	<?php endif; ?>
</div>
<?php endif; ?>

<header class="stsrc-header">

	<div class="stsrc-header-container">
		<!-- Logo -->
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="stsrc-logo-link">
			<img src="<?php echo esc_url( get_template_directory_uri() . '/logo.webp' ); ?>" alt="<?php bloginfo( 'name' ); ?>" class="stsrc-logo">
		</a>
		
		<!-- Mobile Menu Toggle -->
		<button id="stsrc-menu-toggle" class="stsrc-menu-toggle" aria-label="Toggle navigation menu" aria-expanded="false">
			<svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/>
			</svg>
		</button>
		
		<!-- Navigation -->
		<nav id="stsrc-menu" class="stsrc-nav">
			<?php if ( ! is_front_page() ) : ?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="stsrc-nav-link"><?php esc_html_e( 'Home', 'smoketree-plugin' ); ?></a>
			<?php endif; ?>
			
			<?php if ( ! is_user_logged_in() ) : ?>
				<a href="<?php echo esc_url( home_url( '/register/' ) ); ?>" class="stsrc-nav-link"><?php esc_html_e( 'Join Us!', 'smoketree-plugin' ); ?></a>
			<?php endif; ?>
			
			<a href="<?php echo esc_url( home_url( '/pavilion-rental' ) ); ?>" class="stsrc-nav-link"><?php esc_html_e( 'Rent the Pavilion', 'smoketree-plugin' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/events' ) ); ?>" class="stsrc-nav-link"><?php esc_html_e( 'Events', 'smoketree-plugin' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/board' ) ); ?>" class="stsrc-nav-link"><?php esc_html_e( 'Board', 'smoketree-plugin' ); ?></a>
			
			<?php if ( is_user_logged_in() ) : ?>
				<a href="<?php echo esc_url( home_url( '/member-portal/' ) ); ?>" class="stsrc-nav-link"><?php esc_html_e( 'Account', 'smoketree-plugin' ); ?></a>
				<a href="<?php echo esc_url( wp_logout_url( home_url( '/login?loggedout=true' ) ) ); ?>" class="stsrc-nav-link"><?php esc_html_e( 'Logout', 'smoketree-plugin' ); ?></a>
			<?php else : ?>
				<a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="stsrc-nav-link"><?php esc_html_e( 'Login', 'smoketree-plugin' ); ?></a>
			<?php endif; ?>
		</nav>
	</div>
</header>

<script>
// Dynamic body padding — accounts for header height including any banner
function stsrcSyncPadding() {
	var h = document.querySelector('.stsrc-header');
	if (h) document.body.style.paddingTop = h.offsetHeight + 'px';
}
stsrcSyncPadding();
window.addEventListener('resize', stsrcSyncPadding);

// Banner dismiss
(function() {
	var banner = document.getElementById('stsrc-banner');
	if (!banner) return;

	var key = banner.getAttribute('data-banner-key');

	// Hide immediately if previously dismissed
	if (key && localStorage.getItem('stsrc_banner_' + key) === '1') {
		banner.style.display = 'none';
		stsrcSyncPadding();
		return;
	}

	var btn = banner.querySelector('.stsrc-banner-dismiss');
	if (btn && key) {
		btn.addEventListener('click', function() {
			localStorage.setItem('stsrc_banner_' + key, '1');
			banner.style.display = 'none';
			stsrcSyncPadding();
		});
	}
})();

document.addEventListener('DOMContentLoaded', function() {
	var menuToggle = document.getElementById('stsrc-menu-toggle');
	var menu = document.getElementById('stsrc-menu');

	if (menuToggle && menu) {
		menuToggle.addEventListener('click', function() {
			menu.classList.toggle('active');
			var isExpanded = menu.classList.contains('active');
			menuToggle.setAttribute('aria-expanded', isExpanded);
			stsrcSyncPadding();
		});
	}
});
</script>
