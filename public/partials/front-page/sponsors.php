<?php
/**
 * Sponsors Section Partial
 *
 * Expects: $sponsors (WP_Query of 'sponsor' CPT)
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/partials/front-page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! $sponsors->have_posts() ) {
	return;
}
?>

<section id="sponsors" class="bg-white px-6 py-24">
	<div class="container max-w-6xl mx-auto">
		<h2 class="text-3xl text-center text-black dark:text-[#ececec] mb-10">Sponsors</h2>
		<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-10">
			<?php while ( $sponsors->have_posts() ) : $sponsors->the_post(); ?>
				<?php
					$logo            = get_field( 'logo' );
					$url             = get_field( 'url' );
					$expiration_date = DateTime::createFromFormat( 'd/m/Y', get_field( 'expiration_date' ) );
					if ( ! $logo || ! $expiration_date || $expiration_date < new DateTime() ) {
						continue;
					}
				?>
				<div class="flex justify-center items-center">
					<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
						<img
							loading="lazy"
							src="<?php echo esc_url( $logo ); ?>"
							alt="<?php the_title(); ?>"
							class="max-w-[150px] max-h-[100px] w-full object-contain"
						>
					</a>
				</div>
			<?php endwhile; wp_reset_postdata(); ?>
		</div>
	</div>
</section>
