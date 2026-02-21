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

<?php if ( have_rows( 'about_us' ) ) : ?>
<section id="about_us" class="stsrc-about">
	<div class="stsrc-about__inner">
		<h2 class="stsrc-about__title">About Us</h2>

		<?php $row_index = 0; ?>
		<?php while ( have_rows( 'about_us' ) ) : the_row(); ?>
			<?php
				$text  = get_sub_field( 'text' );
				$image = get_sub_field( 'image' );
				$row_classes = 'stsrc-about__row';
				if ( $row_index % 2 !== 0 ) {
					$row_classes .= ' stsrc-about__row--reversed';
				}
			?>
			<div class="<?php echo esc_attr( $row_classes ); ?>">
				<div class="stsrc-about__text">
					<?php echo wp_kses_post( $text ); ?>
				</div>
				<div class="stsrc-about__image-col">
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
							class="stsrc-about__image"
						>
					</picture>
				</div>
			</div>
			<?php $row_index++; ?>
		<?php endwhile; ?>
	</div>
</section>
<?php endif; ?>
