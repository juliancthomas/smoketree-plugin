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

<section id="sponsors" class="stsrc-sponsors">
	<div class="stsrc-sponsors__inner">
		<h2 class="stsrc-sponsors__title">Sponsors</h2>
		<div class="stsrc-sponsors__grid">
			<?php while ( $sponsors->have_posts() ) : $sponsors->the_post(); ?>
				<?php
					$logo            = get_field( 'logo' );
					$url             = get_field( 'url' );
					$expiration_date = DateTime::createFromFormat( 'd/m/Y', get_field( 'expiration_date' ) );
					if ( ! $logo || ! $expiration_date || $expiration_date < new DateTime() ) {
						continue;
					}
				?>
				<div class="stsrc-sponsors__item">
					<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
						<img
							loading="lazy"
							src="<?php echo esc_url( $logo ); ?>"
							alt="<?php the_title(); ?>"
							class="stsrc-sponsors__logo"
						>
					</a>
				</div>
			<?php endwhile; wp_reset_postdata(); ?>
		</div>
	</div>
</section>
