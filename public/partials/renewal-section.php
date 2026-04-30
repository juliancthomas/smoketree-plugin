<?php
/**
 * Renewal section partial.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-membership-db.php';

$member          = $data['member'] ?? array();
$membership_type = $data['membership_type'] ?? array();
$family_members  = $data['family_members'] ?? array();
$extra_members   = $data['extra_members'] ?? array();
$renewal_context = $data['renewal_context'] ?? array();
$season_key      = (string) ( $renewal_context['season_key'] ?? gmdate( 'Y' ) );
$types           = STSRC_Membership_DB::get_all_membership_types( true );
$current_type_id = (int) ( $member['membership_type_id'] ?? 0 );
$current_price   = (float) ( $membership_type['price'] ?? 0.00 );
$balance_owed    = (float) ( $member['balance_owed'] ?? 0.00 );
$renewal_nonce   = wp_create_nonce( 'stsrc_renewal_nonce' );

$initiated_renewal = $data['initiated_renewal'] ?? null;

$use_acf           = function_exists( 'get_field' );
$auto_renewal_text = $use_acf ? get_field( 'stsrc_auto_renewal_text', 'option' ) : get_option( 'stsrc_auto_renewal_text', '' );

$payment_instructions = array();
$instruction_fields   = array(
	'zelle'        => 'zelle_instructions',
	'check'        => 'check_instructions',
	'cash'         => 'cash_instructions',
	'payment_plan' => 'payment_plan_instructions',
);
if ( function_exists( 'get_field' ) ) {
	foreach ( $instruction_fields as $method => $field_name ) {
		$value = get_field( $field_name, 'option' );
		if ( ! empty( $value ) ) {
			$payment_instructions[ $method ] = $value;
		}
	}
}
?>

<section class="stsrc-portal-section stsrc-renewal-section" id="stsrc-renewal-section">

	<?php if ( ! empty( $initiated_renewal ) ) : ?>
	<div class="stsrc-renewal-notice stsrc-renewal-notice--warning" id="stsrc-renewal-cancel-notice"
		data-renewal-id="<?php echo esc_attr( (string) (int) $initiated_renewal['renewal_id'] ); ?>">
		<p>
			<?php echo esc_html__( 'You started a renewal checkout but did not complete it. Cancel the pending checkout below to start fresh.', 'smoketree-plugin' ); ?>
		</p>
		<button type="button" class="stsrc-button stsrc-button-secondary" id="stsrc-renewal-cancel-btn">
			<?php echo esc_html__( 'Cancel Checkout &amp; Start Over', 'smoketree-plugin' ); ?>
		</button>
		<span class="stsrc-renewal-cancel-spinner" style="display:none;" aria-hidden="true"></span>
	</div>
	<?php endif; ?>

	<div id="stsrc-renewal-form-wrap"<?php echo ! empty( $initiated_renewal ) ? ' style="display:none;"' : ''; ?>>
	<div class="stsrc-renewal-header">
		<h2><?php echo esc_html__( 'Renew Membership', 'smoketree-plugin' ); ?></h2>
		<p class="stsrc-description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s season key */
					__( 'Complete the steps below to renew your %s membership.', 'smoketree-plugin' ),
					$season_key
				)
			);
			?>
		</p>
	</div>

	<form id="stsrc-renewal-form">
		<input type="hidden" name="member_id" value="<?php echo esc_attr( (string) (int) ( $member['member_id'] ?? 0 ) ); ?>">
		<input type="hidden" name="season_key" value="<?php echo esc_attr( $season_key ); ?>">
		<input type="hidden" name="nonce" value="<?php echo esc_attr( $renewal_nonce ); ?>">

		<div id="stsrc-renewal-wizard"
			data-extra-price="50.00"
			data-max-extras="<?php echo esc_attr( (string) STSRC_Extra_Member_DB::MAX_HOUSEHOLD_EXTRAS ); ?>"
			data-max-family="4"
			data-balance="<?php echo esc_attr( number_format( $balance_owed, 2, '.', '' ) ); ?>">

			<ul class="nav">
				<li class="nav-item">
					<a class="nav-link" href="#stsrc-step-plan">
						<strong><?php echo esc_html__( 'Choose Plan', 'smoketree-plugin' ); ?></strong><br>
						<small><?php echo esc_html__( 'Select membership', 'smoketree-plugin' ); ?></small>
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" href="#stsrc-step-members">
						<strong><?php echo esc_html__( 'Members', 'smoketree-plugin' ); ?></strong><br>
						<small><?php echo esc_html__( 'Manage your group', 'smoketree-plugin' ); ?></small>
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" href="#stsrc-step-payment">
						<strong><?php echo esc_html__( 'Payment', 'smoketree-plugin' ); ?></strong><br>
						<small><?php echo esc_html__( 'Choose how to pay', 'smoketree-plugin' ); ?></small>
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" href="#stsrc-step-review">
						<strong><?php echo esc_html__( 'Review', 'smoketree-plugin' ); ?></strong><br>
						<small><?php echo esc_html__( 'Confirm & submit', 'smoketree-plugin' ); ?></small>
					</a>
				</li>
			</ul>

			<div class="tab-content">
				<!-- Step 1: Choose Plan -->
				<div id="stsrc-step-plan" class="tab-pane" role="tabpanel">
					<h3><?php echo esc_html__( 'Select Your Membership Plan', 'smoketree-plugin' ); ?></h3>
					<p class="stsrc-description"><?php echo esc_html__( 'Choose the plan that best fits your needs.', 'smoketree-plugin' ); ?></p>

					<div class="stsrc-renewal-cards">
						<?php foreach ( $types as $type ) : ?>
							<?php
							$type_id       = (int) ( $type['membership_type_id'] ?? 0 );
							$type_name     = (string) ( $type['name'] ?? '' );
							$type_price    = (float) ( $type['price'] ?? 0.00 );
							$type_benefits = $type['benefits'] ?? array();
							$is_current    = $type_id === $current_type_id;
							$is_downgrade  = $type_price < $current_price;
							?>
							<label class="stsrc-renewal-card<?php echo $is_current ? ' is-current' : ''; ?>">
								<input
									type="radio"
									name="target_membership_type_id"
									value="<?php echo esc_attr( (string) $type_id ); ?>"
									data-type-name="<?php echo esc_attr( strtolower( $type_name ) ); ?>"
									data-type-label="<?php echo esc_attr( $type_name ); ?>"
									data-type-price="<?php echo esc_attr( number_format( $type_price, 2, '.', '' ) ); ?>"
									<?php checked( $is_current ); ?>
								>
								<div class="stsrc-renewal-card__header">
									<h3><?php echo esc_html( $type_name ); ?></h3>
									<span class="stsrc-renewal-card__price"><?php echo esc_html( '$' . number_format( $type_price, 2 ) ); ?></span>
								</div>
								<?php if ( $is_current ) : ?>
									<p class="stsrc-renewal-badge"><?php echo esc_html__( 'Your current plan', 'smoketree-plugin' ); ?></p>
								<?php endif; ?>
								<?php if ( $is_downgrade ) : ?>
									<p class="stsrc-renewal-warning">
										<?php echo esc_html__( 'Downgrade may remove some member benefits.', 'smoketree-plugin' ); ?>
									</p>
								<?php endif; ?>
								<?php if ( is_array( $type_benefits ) && ! empty( $type_benefits ) ) : ?>
									<ul class="stsrc-renewal-benefits">
										<?php foreach ( $type_benefits as $benefit ) : ?>
											<li><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $benefit ) ) ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- Step 2: Manage Members (auto-skipped for Individual) -->
				<div id="stsrc-step-members" class="tab-pane" role="tabpanel">
					<h3><?php echo esc_html__( 'Manage Your Members', 'smoketree-plugin' ); ?></h3>
					<p class="stsrc-description"><?php echo esc_html__( 'Manage the members included in your renewal.', 'smoketree-plugin' ); ?></p>

					<div class="stsrc-renewal-members__group" id="stsrc-renewal-family-group" style="display:none;">
						<h4><?php echo esc_html__( 'Family Members', 'smoketree-plugin' ); ?></h4>
						<?php if ( ! empty( $family_members ) ) : ?>
						<p class="stsrc-description"><?php echo esc_html__( 'Select which family members to include in your renewal.', 'smoketree-plugin' ); ?></p>
						<div class="stsrc-renewal-members__list" id="stsrc-existing-family-list">
							<?php foreach ( $family_members as $fm ) : ?>
							<label class="stsrc-renewal-member-check">
								<input type="checkbox"
									name="retain_family_member_ids[]"
									value="<?php echo esc_attr( (string) (int) $fm['family_member_id'] ); ?>"
									checked>
								<span class="stsrc-renewal-member-check__name">
									<?php echo esc_html( $fm['first_name'] . ' ' . $fm['last_name'] ); ?>
								</span>
								<?php if ( ! empty( $fm['email'] ) ) : ?>
									<span class="stsrc-renewal-member-check__email"><?php echo esc_html( $fm['email'] ); ?></span>
								<?php endif; ?>
							</label>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>

						<div class="stsrc-renewal-new-members" id="stsrc-new-family-members"></div>
						<button type="button" class="stsrc-add-member-btn" id="stsrc-add-family-btn">
							+ <?php echo esc_html__( 'Add Family Member', 'smoketree-plugin' ); ?>
						</button>
						<p class="stsrc-renewal-members__hint" id="stsrc-family-hint" style="display:none;"></p>
					</div>

					<div class="stsrc-renewal-members__group" id="stsrc-renewal-extras-group" style="display:none;">
						<h4>
							<?php echo esc_html__( 'Extra Members', 'smoketree-plugin' ); ?>
							<span class="stsrc-renewal-extra-price"><?php echo esc_html__( '$50.00 each', 'smoketree-plugin' ); ?></span>
						</h4>

						<?php if ( ! empty( $extra_members ) ) : ?>
						<p class="stsrc-description"><?php echo esc_html__( 'Select which extra members to keep.', 'smoketree-plugin' ); ?></p>
						<div class="stsrc-renewal-members__list" id="stsrc-existing-extras-list">
							<?php foreach ( $extra_members as $em ) : ?>
							<label class="stsrc-renewal-member-check">
								<input type="checkbox"
									name="retain_extra_member_ids[]"
									value="<?php echo esc_attr( (string) (int) $em['extra_member_id'] ); ?>"
									checked>
								<span class="stsrc-renewal-member-check__name">
									<?php echo esc_html( $em['first_name'] . ' ' . $em['last_name'] ); ?>
								</span>
								<?php if ( ! empty( $em['email'] ) ) : ?>
									<span class="stsrc-renewal-member-check__email"><?php echo esc_html( $em['email'] ); ?></span>
								<?php endif; ?>
							</label>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>

						<div class="stsrc-renewal-new-members" id="stsrc-new-extra-members"></div>
						<button type="button" class="stsrc-add-member-btn" id="stsrc-add-extra-btn">
							+ <?php echo esc_html__( 'Add Extra Member', 'smoketree-plugin' ); ?>
						</button>
					</div>
				</div>

				<!-- Step 3: Payment Method -->
				<div id="stsrc-step-payment" class="tab-pane" role="tabpanel">
					<h3><?php echo esc_html__( 'Choose Your Payment Method', 'smoketree-plugin' ); ?></h3>
					<p class="stsrc-description"><?php echo esc_html__( 'Select how you would like to pay for your renewal.', 'smoketree-plugin' ); ?></p>

					<div class="stsrc-renewal-payment-methods">
						<label><input type="radio" name="payment_method" value="card" checked> <?php echo esc_html__( 'Credit/Debit Card', 'smoketree-plugin' ); ?></label>
						<label><input type="radio" name="payment_method" value="ach"> <?php echo esc_html__( 'Bank Account (ACH)', 'smoketree-plugin' ); ?></label>
						<label><input type="radio" name="payment_method" value="zelle"> <?php echo esc_html__( 'Zelle', 'smoketree-plugin' ); ?></label>
						<label><input type="radio" name="payment_method" value="check"> <?php echo esc_html__( 'Check', 'smoketree-plugin' ); ?></label>
						<label><input type="radio" name="payment_method" value="cash"> <?php echo esc_html__( 'Cash', 'smoketree-plugin' ); ?></label>
						<label><input type="radio" name="payment_method" value="payment_plan"> <?php echo esc_html__( 'Payment Plan', 'smoketree-plugin' ); ?></label>
					</div>

					<?php if ( ! empty( $payment_instructions ) ) : ?>
						<div class="stsrc-renewal-payment-instructions" id="stsrc-renewal-payment-instructions" style="display:none;">
							<?php foreach ( $payment_instructions as $method => $instructions ) : ?>
								<div class="stsrc-renewal-instruction" data-method="<?php echo esc_attr( $method ); ?>" style="display:none;">
									<h4><?php echo esc_html__( 'Payment Instructions', 'smoketree-plugin' ); ?></h4>
									<div class="stsrc-renewal-instruction__body"><?php echo wp_kses_post( $instructions ); ?></div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<div class="stsrc-renewal-auto-renewal" id="stsrc-renewal-auto-renewal">
						<h4><?php echo esc_html__( 'Auto-Renewal for Next Season', 'smoketree-plugin' ); ?></h4>

						<?php if ( ! empty( $auto_renewal_text ) ) : ?>
							<div class="stsrc-legal-text" tabindex="0"><?php echo wp_kses_post( $auto_renewal_text ); ?></div>
						<?php endif; ?>

						<label class="stsrc-checkbox-label">
							<input type="checkbox" name="auto_renewal_optin" id="stsrc-renewal-auto-renewal-optin" value="1">
							<span><?php echo esc_html__( 'Yes, automatically renew my membership next season using my saved payment method.', 'smoketree-plugin' ); ?></span>
						</label>
						<p class="stsrc-description">
							<?php echo esc_html__( 'You can change this at any time from your Member Portal.', 'smoketree-plugin' ); ?>
						</p>
					</div>
				</div>

				<!-- Step 4: Review & Submit -->
				<div id="stsrc-step-review" class="tab-pane" role="tabpanel">
					<h3><?php echo esc_html__( 'Review Your Renewal', 'smoketree-plugin' ); ?></h3>
					<p class="stsrc-description"><?php echo esc_html__( 'Please confirm your selections before submitting.', 'smoketree-plugin' ); ?></p>

					<div class="stsrc-review-selections">
						<div class="stsrc-review-item">
							<span class="stsrc-review-item__label"><?php echo esc_html__( 'Membership Plan', 'smoketree-plugin' ); ?></span>
							<span class="stsrc-review-item__value" id="stsrc-review-plan">—</span>
						</div>
						<div class="stsrc-review-item" id="stsrc-review-members-row" style="display:none;">
							<span class="stsrc-review-item__label"><?php echo esc_html__( 'Members', 'smoketree-plugin' ); ?></span>
							<span class="stsrc-review-item__value" id="stsrc-review-members">—</span>
						</div>
						<div class="stsrc-review-item">
							<span class="stsrc-review-item__label"><?php echo esc_html__( 'Payment Method', 'smoketree-plugin' ); ?></span>
							<span class="stsrc-review-item__value" id="stsrc-review-payment">—</span>
						</div>
						<div class="stsrc-review-item" id="stsrc-review-auto-renewal-row" style="display:none;">
							<span class="stsrc-review-item__label"><?php echo esc_html__( 'Auto-Renewal', 'smoketree-plugin' ); ?></span>
							<span class="stsrc-review-item__value" id="stsrc-review-auto-renewal">—</span>
						</div>
					</div>

					<div class="stsrc-renewal-summary" id="stsrc-renewal-summary">
						<h4><?php echo esc_html__( 'Order Summary', 'smoketree-plugin' ); ?></h4>
						<div class="stsrc-renewal-summary__row">
							<span><?php echo esc_html__( 'Membership', 'smoketree-plugin' ); ?></span>
							<strong id="stsrc-renewal-membership-amount">$0.00</strong>
						</div>
						<div class="stsrc-renewal-summary__row" id="stsrc-renewal-extras-row" style="display:none;">
							<span><?php echo esc_html__( 'Extra Members', 'smoketree-plugin' ); ?></span>
							<strong id="stsrc-renewal-extras-amount">$0.00</strong>
						</div>
						<div class="stsrc-renewal-summary__row">
							<span><?php echo esc_html__( 'Current Balance', 'smoketree-plugin' ); ?></span>
							<strong id="stsrc-renewal-balance-amount"><?php echo esc_html( '$' . number_format( $balance_owed, 2 ) ); ?></strong>
						</div>
						<div class="stsrc-renewal-summary__row">
							<span><?php echo esc_html__( 'Processing Fee', 'smoketree-plugin' ); ?></span>
							<strong id="stsrc-renewal-fee-amount">$0.00</strong>
						</div>
						<div class="stsrc-renewal-summary__row stsrc-renewal-summary__row--total">
							<span><?php echo esc_html__( 'Total', 'smoketree-plugin' ); ?></span>
							<strong id="stsrc-renewal-total-amount">$0.00</strong>
						</div>
					</div>

					<button type="button" class="stsrc-button stsrc-button-primary stsrc-renewal-submit-btn" id="stsrc-renewal-continue-btn">
						<?php echo esc_html__( 'Continue to Renewal Payment', 'smoketree-plugin' ); ?>
					</button>
				</div>
			</div>
		</div>
	</form>
	</div><!-- /#stsrc-renewal-form-wrap -->
</section>

