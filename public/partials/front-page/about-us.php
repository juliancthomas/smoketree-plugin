<?php
/**
 * About Us Section Partial
 *
 * Uses ACF repeater field 'about_us' on the current page.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/partials/front-page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<style>
	#about_us a {
		color: #ececec;
		text-decoration: underline;
	}
</style>

<?php if ( have_rows( 'about_us' ) ) : ?>
<section id="about_us" class="p-6 bg-[#48758f]">
	<div class="container mx-auto max-w-5xl">
		<h2 class="text-3xl text-center text-[#ececec] mb-10">About Us</h2>

		<?php $row_index = 0; ?>
		<?php while ( have_rows( 'about_us' ) ) : the_row(); ?>
			<?php
				$text  = get_sub_field( 'text' );
				$image = get_sub_field( 'image' );
				$is_reversed = $row_index % 2 !== 0 ? 'sm:flex-row-reverse' : '';
			?>
			<div class="flex flex-col sm:flex-row items-center justify-between gap-6 mb-16 <?php echo esc_attr( $is_reversed ); ?>">
				<div class="w-full sm:w-1/2 text-lg text-[#ececec]">
					<?php echo wp_kses_post( $text ); ?>
				</div>
				<div class="w-full sm:w-1/2">
					<?php
					$webp_url = str_replace(
						'/wp-content/uploads/',
						'/wp-content/uploads-webpc/uploads/',
						$image
					) . '.webp';
					?>
					<picture>
						<source srcset="<?php echo esc_url( $webp_url ); ?>" type="image/webp">
						<img
							loading="lazy"
							src="<?php echo esc_url( $image ); ?>"
							alt="About Us"
							class="rounded-lg w-full h-auto object-cover"
						>
					</picture>
				</div>
			</div>
			<?php $row_index++; ?>
		<?php endwhile; ?>
	</div>
</section>
<?php endif; ?>
