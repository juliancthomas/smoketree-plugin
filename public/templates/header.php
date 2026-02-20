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

	/* Body Padding for Fixed Header */
	body {
		padding-top: 74px;
	}
	
	@media (min-width: 768px) {
		body {
			padding-top: 50px;
		}
	}
</style>

<body <?php body_class( 'bg-white dark:bg-[#212121]' ); ?>>
<?php wp_body_open(); ?>

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
				<a href="<?php echo esc_url( home_url( '/my-account/' ) ); ?>" class="stsrc-nav-link"><?php esc_html_e( 'Account', 'smoketree-plugin' ); ?></a>
				<a href="<?php echo esc_url( wp_logout_url( home_url( '/login?loggedout=true' ) ) ); ?>" class="stsrc-nav-link"><?php esc_html_e( 'Logout', 'smoketree-plugin' ); ?></a>
			<?php else : ?>
				<a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="stsrc-nav-link"><?php esc_html_e( 'Login', 'smoketree-plugin' ); ?></a>
			<?php endif; ?>
		</nav>
	</div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const menuToggle = document.getElementById('stsrc-menu-toggle');
	const menu = document.getElementById('stsrc-menu');
	
	if (menuToggle && menu) {
		menuToggle.addEventListener('click', function() {
			menu.classList.toggle('active');
			const isExpanded = menu.classList.contains('active');
			menuToggle.setAttribute('aria-expanded', isExpanded);
		});
	}
});
</script>
