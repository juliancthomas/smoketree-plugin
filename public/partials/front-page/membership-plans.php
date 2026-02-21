<?php
/**
 * Membership Plans Section Partial
 *
 * Expects: $membership_types (array from STSRC_Membership_DB)
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/partials/front-page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<section id="membership_plans" class="stsrc-plans">
	<div class="stsrc-plans__inner">
		<h2 class="stsrc-plans__title">Membership Plans</h2>

		<div class="stsrc-plans__subtitle">
			<?php echo get_field( 'membership_plans_subtitle' ); ?>
		</div>

		<?php if ( ! empty( $membership_types ) ) : ?>
			<div class="stsrc-plans__grid">
				<?php foreach ( $membership_types as $type ) :
					$card_classes = 'stsrc-plans__card';
					if ( ! empty( $type['is_best_seller'] ) ) {
						$card_classes .= ' stsrc-plans__card--best-seller';
					}
					$register_url = add_query_arg( 'membership_type_id', $type['membership_type_id'], site_url( '/register' ) );
				?>
					<a href="<?php echo esc_url( $register_url ); ?>" class="<?php echo esc_attr( $card_classes ); ?>">
						<?php if ( ! empty( $type['is_best_seller'] ) ) : ?>
							<div class="stsrc-plans__badge">Best Seller</div>
						<?php endif; ?>

						<div>
							<h3 class="stsrc-plans__card-name"><?php echo esc_html( $type['name'] ); ?></h3>
							<p class="stsrc-plans__card-price">$<?php echo esc_html( number_format( $type['price'], 2 ) ); ?></p>
							<?php if ( ! empty( $type['description'] ) ) : ?>
								<div class="stsrc-plans__card-desc">
									<?php echo wp_kses_post( $type['description'] ); ?>
								</div>
							<?php endif; ?>
						</div>
						<span class="stsrc-plans__card-btn">Select Plan</span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="stsrc-plans__footer">
			<?php echo get_field( 'after_membership_plans_text' ); ?>
		</div>
	</div>
</section>
