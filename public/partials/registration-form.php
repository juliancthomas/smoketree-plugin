<?php
/**
 * Registration form partial
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<form id="stsrc-registration-form" class="stsrc-registration-form" method="post">
	<input type="hidden" name="action" value="stsrc_register_member">
	<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'stsrc_registration_nonce' ) ); ?>">
	
	<div id="stsrc-form-messages"></div>

	<!-- Personal Information -->
	<div class="stsrc-form-section">
		<h2><?php echo esc_html__( 'Personal Information', 'smoketree-plugin' ); ?></h2>
		
		<div class="stsrc-form-row">
			<div class="stsrc-form-group">
				<label for="first_name"><?php echo esc_html__( 'First Name', 'smoketree-plugin' ); ?> <span class="required">*</span></label>
				<input type="text" name="first_name" id="first_name" required>
			</div>
			
			<div class="stsrc-form-group">
				<label for="last_name"><?php echo esc_html__( 'Last Name', 'smoketree-plugin' ); ?> <span class="required">*</span></label>
				<input type="text" name="last_name" id="last_name" required>
			</div>
		</div>

		<div class="stsrc-form-row">
			<div class="stsrc-form-group">
				<label for="email"><?php echo esc_html__( 'Email Address', 'smoketree-plugin' ); ?> <span class="required">*</span></label>
				<input type="email" name="email" id="email" required>
			</div>
			
			<div class="stsrc-form-group">
				<label for="phone"><?php echo esc_html__( 'Phone Number', 'smoketree-plugin' ); ?> <span class="required">*</span></label>
				<input type="tel" name="phone" id="phone" required>
			</div>
		</div>
	</div>

	<!-- Address Information -->
	<div class="stsrc-form-section">
		<h2><?php echo esc_html__( 'Address', 'smoketree-plugin' ); ?></h2>
		
		<div class="stsrc-form-group">
			<label for="street_1"><?php echo esc_html__( 'Street Address', 'smoketree-plugin' ); ?> <span class="required">*</span></label>
			<input type="text" name="street_1" id="street_1" required>
		</div>

		<div class="stsrc-form-group">
			<label for="street_2"><?php echo esc_html__( 'Apartment, Suite, etc. (optional)', 'smoketree-plugin' ); ?></label>
			<input type="text" name="street_2" id="street_2">
		</div>

		<div class="stsrc-form-row">
			<div class="stsrc-form-group">
				<label for="city"><?php echo esc_html__( 'City', 'smoketree-plugin' ); ?></label>
				<input type="text" name="city" id="city" value="Tucker">
			</div>
			
			<div class="stsrc-form-group">
				<label for="state"><?php echo esc_html__( 'State', 'smoketree-plugin' ); ?></label>
				<input type="text" name="state" id="state" value="GA" maxlength="2">
			</div>
			
			<div class="stsrc-form-group">
				<label for="zip"><?php echo esc_html__( 'ZIP Code', 'smoketree-plugin' ); ?></label>
				<input type="text" name="zip" id="zip" value="30084">
			</div>
		</div>

		<div class="stsrc-form-group">
			<label for="country"><?php echo esc_html__( 'Country', 'smoketree-plugin' ); ?></label>
			<input type="text" name="country" id="country" value="US">
		</div>
	</div>

	<!-- Membership Selection -->
	<div class="stsrc-form-section">
		<h2><?php echo esc_html__( 'Membership Selection', 'smoketree-plugin' ); ?></h2>
		
		<div class="stsrc-form-group">
			<label for="membership_type_id"><?php echo esc_html__( 'Membership Type', 'smoketree-plugin' ); ?> <span class="required">*</span></label>
			<select name="membership_type_id" id="membership_type_id" required>
				<option value=""><?php echo esc_html__( 'Select a membership type...', 'smoketree-plugin' ); ?></option>
				<?php foreach ( $membership_types as $type ) : ?>
					<option value="<?php echo esc_attr( $type['membership_type_id'] ); ?>" 
							data-name="<?php echo esc_attr( strtolower( $type['name'] ) ); ?>"
							data-price="<?php echo esc_attr( $type['price'] ); ?>"
							data-allows-family="<?php echo esc_attr( in_array( strtolower( $type['name'] ), array( 'household', 'duo' ), true ) ? '1' : '0' ); ?>"
							data-allows-extra="<?php echo esc_attr( 'household' === strtolower( $type['name'] ) ? '1' : '0' ); ?>"
							data-family-limit="<?php echo esc_attr( 'household' === strtolower( $type['name'] ) ? '4' : ( 'duo' === strtolower( $type['name'] ) ? '1' : '0' ) ); ?>">
						<?php echo esc_html( $type['name'] ); ?> - $<?php echo esc_html( number_format( $type['price'], 2 ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php if ( ! empty( $membership_types ) ) : ?>
				<?php foreach ( $membership_types as $type ) : ?>
					<?php if ( ! empty( $type['description'] ) ) : ?>
						<div class="stsrc-membership-description" data-membership-id="<?php echo esc_attr( $type['membership_type_id'] ); ?>" style="display: none;">
							<p><?php echo esc_html( $type['description'] ); ?></p>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>

	<!-- Family Members (Dynamic) -->
	<div class="stsrc-form-section" id="stsrc-family-members-section" style="display: none;">
		<h2><?php echo esc_html__( 'Family Members', 'smoketree-plugin' ); ?></h2>
		<p class="stsrc-description"><?php echo esc_html__( 'Add family members included with your membership.', 'smoketree-plugin' ); ?></p>
		
		<div id="stsrc-family-members-container"></div>
		<button type="button" class="stsrc-button stsrc-button-secondary" id="stsrc-add-family-member"><?php echo esc_html__( '+ Add Family Member', 'smoketree-plugin' ); ?></button>
	</div>

	<!-- Extra Members (Dynamic, Household only) -->
	<div class="stsrc-form-section" id="stsrc-extra-members-section" style="display: none;">
		<h2><?php echo esc_html__( 'Extra Members', 'smoketree-plugin' ); ?></h2>
		<p class="stsrc-description"><?php echo esc_html__( 'Add extra members for $50 each (maximum 3). Payment will be required after registration.', 'smoketree-plugin' ); ?></p>
		
		<div id="stsrc-extra-members-container"></div>
		<button type="button" class="stsrc-button stsrc-button-secondary" id="stsrc-add-extra-member"><?php echo esc_html__( '+ Add Extra Member', 'smoketree-plugin' ); ?></button>
	</div>

	<!-- Account Information -->
	<div class="stsrc-form-section">
		<h2><?php echo esc_html__( 'Account Information', 'smoketree-plugin' ); ?></h2>
		
		<div class="stsrc-form-row">
			<div class="stsrc-form-group">
				<label for="password"><?php echo esc_html__( 'Password', 'smoketree-plugin' ); ?> <span class="required">*</span></label>
				<input type="password" name="password" id="password" required minlength="8">
				<small><?php echo esc_html__( 'Must be at least 8 characters long.', 'smoketree-plugin' ); ?></small>
			</div>
			
			<div class="stsrc-form-group">
				<label for="password_confirm"><?php echo esc_html__( 'Confirm Password', 'smoketree-plugin' ); ?> <span class="required">*</span></label>
				<input type="password" name="password_confirm" id="password_confirm" required>
			</div>
		</div>
	</div>

	<!-- Referral Source -->
	<div class="stsrc-form-section">
		<h2><?php echo esc_html__( 'How did you hear about us?', 'smoketree-plugin' ); ?></h2>
		
		<div class="stsrc-form-group">
			<label for="referral_source"><?php echo esc_html__( 'Referral Source', 'smoketree-plugin' ); ?></label>
			<select name="referral_source" id="referral_source">
				<option value=""><?php echo esc_html__( 'Select...', 'smoketree-plugin' ); ?></option>
				<option value="A current or previous member"><?php echo esc_html__( 'A current or previous member', 'smoketree-plugin' ); ?></option>
				<option value="social media"><?php echo esc_html__( 'Social media', 'smoketree-plugin' ); ?></option>
				<option value="friend or family"><?php echo esc_html__( 'Friend or family', 'smoketree-plugin' ); ?></option>
				<option value="search engine"><?php echo esc_html__( 'Search engine', 'smoketree-plugin' ); ?></option>
				<option value="news article"><?php echo esc_html__( 'News article', 'smoketree-plugin' ); ?></option>
				<option value="advertisement"><?php echo esc_html__( 'Advertisement', 'smoketree-plugin' ); ?></option>
				<option value="event"><?php echo esc_html__( 'Event', 'smoketree-plugin' ); ?></option>
				<option value="other"><?php echo esc_html__( 'Other', 'smoketree-plugin' ); ?></option>
			</select>
		</div>
	</div>

	<!-- Waiver -->
	<div class="stsrc-form-section">
		<h2><?php echo esc_html__( 'Waiver Agreement', 'smoketree-plugin' ); ?></h2>
		
		<?php if ( ! empty( $waiver_text ) ) : ?>
			<div class="stsrc-form-group">
				<label for="waiver_text_display"><?php echo esc_html__( 'Please read the waiver agreement below:', 'smoketree-plugin' ); ?></label>
				<textarea id="waiver_text_display" rows="10" readonly style="width: 100%; resize: vertical; background-color: #f5f5f5; border: 1px solid #ddd; padding: 10px;"><?php echo esc_textarea( $waiver_text ); ?></textarea>
			</div>
		<?php endif; ?>
		
		<div class="stsrc-form-row">
			<div class="stsrc-form-group">
				<label for="waiver_full_name"><?php echo esc_html__( 'Full Name (as signature)', 'smoketree-plugin' ); ?> <span class="required">*</span></label>
				<input type="text" name="waiver_full_name" id="waiver_full_name" required>
			</div>
			
			<div class="stsrc-form-group">
				<label for="waiver_signed_date"><?php echo esc_html__( 'Date Signed', 'smoketree-plugin' ); ?> <span class="required">*</span></label>
				<input type="date" name="waiver_signed_date" id="waiver_signed_date" required value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
			</div>
		</div>
	</div>

	<!-- Payment Type -->
	<div class="stsrc-form-section">
		<h2><?php echo esc_html__( 'Payment Method', 'smoketree-plugin' ); ?></h2>

		<div class="stsrc-form-group">
			<label><?php echo esc_html__( 'How would you like to pay?', 'smoketree-plugin' ); ?> <span class="required">*</span></label>
			<div class="stsrc-pay-balance-methods">
				<label class="stsrc-pay-balance-method">
					<input type="radio" name="payment_type" value="card" required>
					<span class="stsrc-pay-balance-method__label">
						<?php echo esc_html__( 'Credit / Debit Card', 'smoketree-plugin' ); ?>
						<span class="stsrc-pay-balance-method__fee"><?php echo esc_html__( '2.9% + $0.30 processing fee', 'smoketree-plugin' ); ?></span>
					</span>
				</label>
				<label class="stsrc-pay-balance-method">
					<input type="radio" name="payment_type" value="bank_account" required>
					<span class="stsrc-pay-balance-method__label">
						<?php echo esc_html__( 'Bank Account (ACH)', 'smoketree-plugin' ); ?>
						<span class="stsrc-pay-balance-method__fee"><?php echo esc_html__( '0.8% processing fee ($5.00 max)', 'smoketree-plugin' ); ?></span>
					</span>
				</label>
				<label class="stsrc-pay-balance-method">
					<input type="radio" name="payment_type" value="zelle" required>
					<span class="stsrc-pay-balance-method__label">
						<?php echo esc_html__( 'Zelle', 'smoketree-plugin' ); ?>
						<span class="stsrc-pay-balance-method__fee"><?php echo esc_html__( 'No processing fee', 'smoketree-plugin' ); ?></span>
					</span>
				</label>
				<label class="stsrc-pay-balance-method">
					<input type="radio" name="payment_type" value="check" required>
					<span class="stsrc-pay-balance-method__label">
						<?php echo esc_html__( 'Check', 'smoketree-plugin' ); ?>
						<span class="stsrc-pay-balance-method__fee"><?php echo esc_html__( 'No processing fee', 'smoketree-plugin' ); ?></span>
					</span>
				</label>
				<label class="stsrc-pay-balance-method">
					<input type="radio" name="payment_type" value="pay_later" required>
					<span class="stsrc-pay-balance-method__label">
						<?php echo esc_html__( 'Pay Later (Special Cases Only)', 'smoketree-plugin' ); ?>
						<span class="stsrc-pay-balance-method__fee"><?php echo esc_html__( 'No processing fee', 'smoketree-plugin' ); ?></span>
					</span>
				</label>
			</div>
		</div>
	</div>

	<!-- Auto-Renewal Agreement (visible only for Stripe-compatible payment types) -->
	<div class="stsrc-form-section" id="stsrc-auto-renewal-section" style="display: none;">
		<h2><?php echo esc_html__( 'Auto-Renewal Agreement', 'smoketree-plugin' ); ?></h2>
		
		<?php if ( ! empty( $auto_renewal_text ) ) : ?>
			<div class="stsrc-form-group">
				<label for="auto_renewal_text_display"><?php echo esc_html__( 'Please read the auto-renewal agreement below:', 'smoketree-plugin' ); ?></label>
				<textarea id="auto_renewal_text_display" rows="8" readonly style="width: 100%; resize: vertical; background-color: #f5f5f5; border: 1px solid #ddd; padding: 10px;"><?php echo esc_textarea( $auto_renewal_text ); ?></textarea>
			</div>
		<?php endif; ?>
		
		<div class="stsrc-form-group">
			<label class="stsrc-checkbox-label">
				<input type="checkbox" name="auto_renewal_acknowledged" id="auto_renewal_acknowledged" value="1" required>
				<span><?php echo esc_html__( 'I have read and agree to the auto-renewal terms above. I understand that my membership will automatically renew and my saved payment method will be charged.', 'smoketree-plugin' ); ?></span>
			</label>
			<p class="stsrc-description" style="margin-top: 6px; font-size: 0.875em; color: #666;">
				<?php echo esc_html__( 'You may opt out of auto-renewal at any time from your Member Portal after registration. If you do not opt out, your membership status will become inactive when the next renewal period begins.', 'smoketree-plugin' ); ?>
			</p>
		</div>
	</div>

	<!-- Order Summary -->
	<div class="stsrc-form-section">
		<h2><?php echo esc_html__( 'Order Summary', 'smoketree-plugin' ); ?></h2>

		<div class="stsrc-pay-balance-summary" id="stsrc-registration-summary" aria-live="polite">
			<div class="stsrc-pay-balance-summary__row">
				<span class="stsrc-pay-balance-summary__label"><?php echo esc_html__( 'Membership', 'smoketree-plugin' ); ?></span>
				<span class="stsrc-pay-balance-summary__value" id="stsrc-membership-fee">$0.00</span>
			</div>
			<div class="stsrc-pay-balance-summary__row" id="stsrc-family-fee-row" style="display: none;">
				<span class="stsrc-pay-balance-summary__label"><?php echo esc_html__( 'Family Members', 'smoketree-plugin' ); ?></span>
				<span class="stsrc-pay-balance-summary__value"><?php echo esc_html__( 'Included', 'smoketree-plugin' ); ?></span>
			</div>
			<div class="stsrc-pay-balance-summary__row" id="stsrc-extra-fee-row" style="display: none;">
				<span class="stsrc-pay-balance-summary__label"><?php echo esc_html__( 'Extra Members', 'smoketree-plugin' ); ?></span>
				<span class="stsrc-pay-balance-summary__value" id="stsrc-extra-fee">$0.00</span>
			</div>
			<div class="stsrc-pay-balance-summary__row stsrc-pay-balance-summary__row--fee" id="stsrc-transaction-fee-row" style="display: none;">
				<span class="stsrc-pay-balance-summary__label"><?php echo esc_html__( 'Processing Fee', 'smoketree-plugin' ); ?></span>
				<span class="stsrc-pay-balance-summary__value" id="stsrc-transaction-fee">$0.00</span>
			</div>
			<div class="stsrc-pay-balance-summary__row stsrc-pay-balance-summary__row--total">
				<strong class="stsrc-pay-balance-summary__label"><?php echo esc_html__( 'Total', 'smoketree-plugin' ); ?></strong>
				<strong class="stsrc-pay-balance-summary__value" id="stsrc-total">$0.00</strong>
			</div>
		</div>
	</div>
	
	<!-- CAPTCHA -->
	<?php if ( $captcha_enabled && ! empty( $captcha_site_key ) ) : ?>
		<div class="stsrc-form-section">
			<?php if ( 'recaptcha' === $captcha_provider ) : ?>
				<script src="https://www.google.com/recaptcha/api.js?render=<?php echo esc_attr( $captcha_site_key ); ?>"></script>
			<?php elseif ( 'hcaptcha' === $captcha_provider ) : ?>
				<script src="https://js.hcaptcha.com/1/api.js" async defer></script>
				<div class="h-captcha" data-sitekey="<?php echo esc_attr( $captcha_site_key ); ?>"></div>
			<?php endif; ?>
			<input type="hidden" name="captcha_token" id="captcha_token">
		</div>
	<?php endif; ?>

	<!-- Submit -->
	<div class="stsrc-form-section">
		<button type="submit" class="stsrc-button stsrc-button-primary" id="stsrc-submit-registration">
			<?php echo esc_html__( 'Submit Registration', 'smoketree-plugin' ); ?>
		</button>
	</div>
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Faker/3.1.0/faker.min.js"></script>

<script>
jQuery(document).ready(function($) {
	let familyMemberCount = 0;
	let extraMemberCount = 0;
	let familyLimit = 0;
	const extraMemberFee = <?php echo floatval( $extra_member_fee ?? 50.00 ); ?>;

	const FEE_RATES = {
		card:         { percent: 0.029, flat: 0.30, cap: null },
		bank_account: { percent: 0.008, flat: 0,    cap: 5.00 }
	};

	function calculateFee(amount, method) {
		var rate = FEE_RATES[method];
		if (!rate || amount <= 0) return 0;
		var fee = amount * rate.percent + rate.flat;
		if (rate.cap !== null && fee > rate.cap) fee = rate.cap;
		return Math.round(fee * 100) / 100;
	}

	function formatCurrency(val) {
		return '$' + Number(val).toFixed(2);
	}

	function updateOrderSummary() {
		const $option = $('#membership_type_id').find('option:selected');
		const membershipPrice = parseFloat($option.data('price')) || 0;
		const allowsFamily = $option.data('allows-family') === '1';

		$('#stsrc-membership-fee').text(formatCurrency(membershipPrice));

		if (allowsFamily && familyMemberCount > 0) {
			$('#stsrc-family-fee-row').show();
		} else {
			$('#stsrc-family-fee-row').hide();
		}

		const extraFee = extraMemberCount * extraMemberFee;
		if (extraMemberCount > 0) {
			$('#stsrc-extra-fee-row').show();
			$('#stsrc-extra-fee').text(formatCurrency(extraFee));
		} else {
			$('#stsrc-extra-fee-row').hide();
		}

		const subtotal = membershipPrice + extraFee;
		const selectedPayment = $('input[name="payment_type"]:checked').val() || '';
		const fee = calculateFee(subtotal, selectedPayment);

		if (fee > 0) {
			$('#stsrc-transaction-fee-row').show();
			$('#stsrc-transaction-fee').text(formatCurrency(fee));
			$('#stsrc-fee-note').show();
		} else {
			$('#stsrc-transaction-fee-row').hide();
			$('#stsrc-fee-note').hide();
		}

		$('#stsrc-total').text(formatCurrency(subtotal + fee));
	}

	// Auto-select membership type from URL query string
	var urlParams = new URLSearchParams(window.location.search);
	var preselectedId = urlParams.get('membership_type_id');
	if (preselectedId && $('#membership_type_id option[value="' + preselectedId + '"]').length) {
		$('#membership_type_id').val(preselectedId).trigger('change');
	}

	$('#membership_type_id').on('change', function() {
		const $option = $(this).find('option:selected');
		const allowsFamily = $option.data('allows-family') === 1;
		const allowsExtra = $option.data('allows-extra') === 1;
		familyLimit = parseInt($option.data('family-limit')) || 0;

		if (allowsFamily) {
			$('#stsrc-family-members-section').show();
		} else {
			$('#stsrc-family-members-section').hide();
			$('#stsrc-family-members-container').empty();
			familyMemberCount = 0;
		}

		if (allowsExtra) {
			$('#stsrc-extra-members-section').show();
		} else {
			$('#stsrc-extra-members-section').hide();
			$('#stsrc-extra-members-container').empty();
			extraMemberCount = 0;
		}

		$('.stsrc-membership-description').hide();
		$('.stsrc-membership-description[data-membership-id="' + $(this).val() + '"]').show();

		updateOrderSummary();
	});

	$('input[name="payment_type"]').on('change', function() {
		$('.stsrc-pay-balance-method').removeClass('stsrc-pay-balance-method--selected');
		$(this).closest('.stsrc-pay-balance-method').addClass('stsrc-pay-balance-method--selected');

		var stripeTypes = ['card', 'bank_account'];
		var selected = $(this).val();
		if (stripeTypes.indexOf(selected) !== -1) {
			$('#stsrc-auto-renewal-section').show();
			$('#auto_renewal_acknowledged').prop('required', true);
		} else {
			$('#stsrc-auto-renewal-section').hide();
			$('#auto_renewal_acknowledged').prop('required', false).prop('checked', false);
		}

		updateOrderSummary();
	});
	
	// Add family member
	$('#stsrc-add-family-member').on('click', function() {
		if (familyMemberCount >= familyLimit) {
			alert('Maximum of ' + familyLimit + ' family members allowed for this membership type.');
			return;
		}
		
		familyMemberCount++;
		const html = `
			<div class="stsrc-family-member-item" data-index="${familyMemberCount}">
				<h3>Family Member ${familyMemberCount}</h3>
				<div class="stsrc-form-row">
					<div class="stsrc-form-group">
						<label>First Name</label>
						<input type="text" name="family_members[${familyMemberCount}][first_name]" required>
					</div>
					<div class="stsrc-form-group">
						<label>Last Name</label>
						<input type="text" name="family_members[${familyMemberCount}][last_name]" required>
					</div>
				</div>
				<div class="stsrc-form-group">
					<label>Email (optional)</label>
					<input type="email" name="family_members[${familyMemberCount}][email]">
				</div>
				<button type="button" class="stsrc-button stsrc-button-danger stsrc-remove-family-member">Remove</button>
			</div>
		`;
		$('#stsrc-family-members-container').append(html);
		updateOrderSummary();
	});
	
	// Remove family member
	$(document).on('click', '.stsrc-remove-family-member', function() {
		$(this).closest('.stsrc-family-member-item').remove();
		familyMemberCount--;
		updateOrderSummary();
	});
	
	// Add extra member
	$('#stsrc-add-extra-member').on('click', function() {
		if (extraMemberCount >= 3) {
			alert('Maximum of 3 extra members allowed.');
			return;
		}
		
		extraMemberCount++;
		const html = `
			<div class="stsrc-extra-member-item" data-index="${extraMemberCount}">
				<h3>Extra Member ${extraMemberCount} ($${extraMemberFee.toFixed(2)})</h3>
				<div class="stsrc-form-row">
					<div class="stsrc-form-group">
						<label>First Name</label>
						<input type="text" name="extra_members[${extraMemberCount}][first_name]" required>
					</div>
					<div class="stsrc-form-group">
						<label>Last Name</label>
						<input type="text" name="extra_members[${extraMemberCount}][last_name]" required>
					</div>
				</div>
				<div class="stsrc-form-group">
					<label>Email (optional)</label>
					<input type="email" name="extra_members[${extraMemberCount}][email]">
				</div>
				<button type="button" class="stsrc-button stsrc-button-danger stsrc-remove-extra-member">Remove</button>
			</div>
		`;
		$('#stsrc-extra-members-container').append(html);
		updateOrderSummary();
	});
	
	// Remove extra member
	$(document).on('click', '.stsrc-remove-extra-member', function() {
		$(this).closest('.stsrc-extra-member-item').remove();
		extraMemberCount--;
		updateOrderSummary();
	});
	
	// Form submission
	$('#stsrc-registration-form').on('submit', function(e) {
		e.preventDefault();
		
		const $form = $(this);
		const $submitBtn = $('#stsrc-submit-registration');
		const $messages = $('#stsrc-form-messages');
		
		// Validate password match
		if ($('#password').val() !== $('#password_confirm').val()) {
			$messages.html('<div class="stsrc-notice error"><p>Passwords do not match.</p></div>');
			return;
		}
		
		// Get CAPTCHA token if enabled
		<?php if ( $captcha_enabled && ! empty( $captcha_site_key ) ) : ?>
			<?php if ( 'recaptcha' === $captcha_provider ) : ?>
				grecaptcha.ready(function() {
					grecaptcha.execute('<?php echo esc_js( $captcha_site_key ); ?>', {action: 'register'}).then(function(token) {
						$('#captcha_token').val(token);
						submitForm();
					});
				});
				return;
			<?php elseif ( 'hcaptcha' === $captcha_provider ) : ?>
				const hcaptchaToken = $('textarea[name="h-captcha-response"]').val();
				if (!hcaptchaToken) {
					$messages.html('<div class="stsrc-notice error"><p>Please complete the CAPTCHA.</p></div>');
					return;
				}
				$('#captcha_token').val(hcaptchaToken);
			<?php endif; ?>
		<?php endif; ?>
		
		submitForm();
		
		function submitForm() {
			$submitBtn.prop('disabled', true).text('Submitting...');
			$messages.html('');
			
			$.ajax({
				url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
				type: 'POST',
				data: $form.serialize(),
				success: function(response) {
					if (response.success) {
						if (response.data.checkout_url) {
							window.location.href = response.data.checkout_url;
						} else if (response.data.redirect_url) {
							window.location.href = response.data.redirect_url;
						} else {
							$messages.html('<div class="stsrc-notice success"><p>' + response.data.message + '</p></div>');
							$form[0].reset();
						}
					} else {
						$messages.html('<div class="stsrc-notice error"><p>' + response.data.message + '</p></div>');
						$submitBtn.prop('disabled', false).text('Submit Registration');
					}
				},
				error: function() {
					$messages.html('<div class="stsrc-notice error"><p>An error occurred. Please try again.</p></div>');
					$submitBtn.prop('disabled', false).text('Submit Registration');
				}
			});
		}
	});
	const firstName = faker.name.firstName();
	const lastName = faker.name.lastName();
	document.querySelector('#first_name').value = firstName;
	document.querySelector('#last_name').value = lastName;
	document.querySelector('#email').value = `${firstName}.${lastName}@example.com`;
	document.querySelector('#phone').value = faker.phone.phoneNumber();
	document.querySelector('#street_1').value = faker.address.streetAddress();
	document.querySelector('#street_2').value = faker.address.secondaryAddress();
	document.querySelector('#password').value = 'abc123123';
	document.querySelector('#password_confirm').value = 'abc123123';
	document.querySelector('#referral_source').value = 'other';
	document.querySelector('#waiver_full_name').value = `${firstName} ${lastName}`;
});
</script>

