<?php
/**
 * Hero Section Partial
 *
 * Expects: $hero_url
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/partials/front-page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<a href="<?php echo esc_url( $hero_url ); ?>" class="block text-white">
	<section id="hero_section" class="bg-center bg-cover h-[100vh] sm:max-h-[500px] flex flex-col items-center justify-center text-center" style="background-image: url('<?php echo esc_url( get_field( 'hero_background_image' ) ); ?>');">
		<h1 class="text-white text-shadow text-4xl sm:text-5xl font-bold max-w-md sm:max-w-none">
			<?php echo esc_html( get_field( 'hero_title' ) ); ?>
		</h1>
		<p class="text-lg text-white font-semibold mt-2">
			<?php echo esc_html( get_field( 'hero_subtitle' ) ); ?>
		</p>
	</section>
</a>
