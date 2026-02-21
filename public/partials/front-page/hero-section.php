<?php
/**
 * Hero Section Partial
 *
 * Expects: $hero_url, $hero_cta_label, $hero_next_color
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/partials/front-page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<section id="hero_section" class="stsrc-hero" style="background-image: url('<?php echo esc_url( get_field( 'hero_background_image' ) ); ?>');">
	<div class="stsrc-hero__overlay"></div>
	<div class="stsrc-hero__content">
		<h1 class="stsrc-hero__title">
			<?php echo esc_html( get_field( 'hero_title' ) ); ?>
		</h1>
		<p class="stsrc-hero__subtitle">
			<?php echo esc_html( get_field( 'hero_subtitle' ) ); ?>
		</p>
		<a href="<?php echo esc_url( $hero_url ); ?>" class="stsrc-hero__cta">
			<?php echo esc_html( $hero_cta_label ); ?>
		</a>
	</div>
	<svg class="stsrc-hero__wave" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true">
		<path fill="<?php echo esc_attr( $hero_next_color ); ?>" d="M0,40 C360,80 1080,0 1440,40 L1440,80 L0,80 Z"/>
	</svg>
</section>
