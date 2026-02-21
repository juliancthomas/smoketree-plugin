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

<section id="membership_plans" class="bg-[#5d99bb] px-6 py-12 md:py-16">
	<div class="container mx-auto text-center">
		<h2 class="text-4xl md:text-5xl text-white">Membership Plans</h2>

		<div class="mt-6 text-white max-w-2xl mx-auto prose prose-lg">
			<?php echo get_field( 'membership_plans_subtitle' ); ?>
		</div>

		<?php if ( ! empty( $membership_types ) ) : ?>
			<div class="max-w-[1500px] mx-auto mt-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 auto-rows-fr">
				<?php foreach ( $membership_types as $type ) :
					$card_classes = 'relative bg-white rounded-lg shadow-xl w-full h-full px-4 py-6 flex flex-col justify-between text-center transition-shadow duration-300 hover:shadow-2xl';
					if ( ! empty( $type['is_best_seller'] ) ) {
						$card_classes .= ' border-4 border-yellow-700 pt-5';
					}
					$register_url = add_query_arg( 'membership_type_id', $type['membership_type_id'], site_url( '/register' ) );
				?>
					<a href="<?php echo esc_url( $register_url ); ?>" class="<?php echo esc_attr( $card_classes ); ?>">
						<?php if ( ! empty( $type['is_best_seller'] ) ) : ?>
							<div class="absolute top-0 right-0 bg-yellow-700 text-white text-sm px-2 py-1 rounded-bl">
								Best Seller
							</div>
						<?php endif; ?>

						<div>
							<h3 class="text-lg font-bold text-blue-700"><?php echo esc_html( $type['name'] ); ?></h3>
							<p class="text-xl my-3">$<?php echo esc_html( number_format( $type['price'], 2 ) ); ?></p>
							<?php if ( ! empty( $type['description'] ) ) : ?>
								<div class="text-gray-700 prose prose-lg">
									<?php echo wp_kses_post( $type['description'] ); ?>
								</div>
							<?php endif; ?>
						</div>
						<span class="mt-4 inline-block w-full py-2 bg-blue-700 text-white font-semibold rounded hover:bg-blue-800 transition-colors">
							Select Plan
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="mt-16 text-white prose prose-lg">
			<?php echo get_field( 'after_membership_plans_text' ); ?>
		</div>
	</div>
</section>
