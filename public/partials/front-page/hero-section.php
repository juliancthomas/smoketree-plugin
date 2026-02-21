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

<section id="hero_section" class="relative bg-center bg-cover h-[60vh] sm:h-[500px] flex flex-col items-center justify-center text-center pb-[40px] sm:pb-[60px]" style="background-image: url('<?php echo esc_url( get_field( 'hero_background_image' ) ); ?>');">
	<div class="absolute inset-0 bg-black/50"></div>
	<div class="relative z-10 px-4">
		<h1 class="text-white text-4xl sm:text-5xl font-bold max-w-md sm:max-w-none drop-shadow-lg">
			<?php echo esc_html( get_field( 'hero_title' ) ); ?>
		</h1>
		<p class="text-lg text-white font-semibold mt-2 drop-shadow">
			<?php echo esc_html( get_field( 'hero_subtitle' ) ); ?>
		</p>
		<a href="<?php echo esc_url( $hero_url ); ?>" class="inline-block mt-6 px-8 py-3 bg-white text-gray-900 font-bold text-lg rounded-full shadow-lg hover:bg-gray-100 transition-colors">
			<?php echo esc_html( $hero_cta_label ); ?>
		</a>
	</div>
	<svg class="absolute bottom-0 left-0 w-full h-[40px] sm:h-[60px] z-10" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true">
		<path fill="<?php echo esc_attr( $hero_next_color ); ?>" d="M0,40 C360,80 1080,0 1440,40 L1440,80 L0,80 Z"/>
	</svg>
</section>
