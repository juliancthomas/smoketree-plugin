<?php
/**
 * Template Name: Smoketree Guest Pass Portal
 * 
 * Guest pass portal page template (accessed via QR code).
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/templates
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if user is logged in
if ( ! is_user_logged_in() ) {
	wp_safe_redirect( home_url( '/login?redirect_to=' . urlencode( home_url( '/guest-pass-portal' ) ) ) );
	exit;
}

// Get current user
$current_user = wp_get_current_user();

// Get member data
require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-member-db.php';
$member = STSRC_Member_DB::get_member_by_email( $current_user->user_email );

if ( ! $member ) {
	wp_die( esc_html__( 'Member account not found. Please contact support.', 'smoketree-plugin' ) );
}

// Get guest pass balance
require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-guest-pass-db.php';
$guest_pass_balance = STSRC_Guest_Pass_DB::get_guest_pass_balance( (int) $member['member_id'] );

// Get recent usage log (last 10 entries)
$usage_log = STSRC_Guest_Pass_DB::get_guest_pass_log(
	(int) $member['member_id'],
	array(
		'payment_status' => 'paid',
	)
);
$usage_log = array_slice( $usage_log, 0, 10 );

get_header();
?>

<div class="stsrc-guest-pass-portal">
	<div class="stsrc-container">
		<div class="stsrc-portal-header">
			<h1><?php echo esc_html__( 'Guest Pass Portal', 'smoketree-plugin' ); ?></h1>
			<a href="<?php echo esc_url( home_url( '/member-portal' ) ); ?>" class="stsrc-button stsrc-button-secondary">
				<?php echo esc_html__( 'Back to Portal', 'smoketree-plugin' ); ?>
			</a>
		</div>

		<div id="stsrc-portal-messages"></div>

		<!-- Balance Display -->
		<div class="stsrc-portal-section stsrc-balance-section">
			<div class="stsrc-balance-display-large">
				<div class="stsrc-balance-label"><?php echo esc_html__( 'Current Balance', 'smoketree-plugin' ); ?></div>
				<div class="stsrc-balance-amount-large">
					<?php echo esc_html( number_format( $guest_pass_balance, 0 ) ); ?>
					<span class="stsrc-balance-unit"><?php echo esc_html( 1 === $guest_pass_balance ? 'pass' : 'passes' ); ?></span>
				</div>
			</div>

			<?php if ( 0 === $guest_pass_balance ) : ?>
				<div class="stsrc-no-balance-message">
					<p><?php echo esc_html__( 'You have no guest passes available. Purchase passes to allow guests to use the facility.', 'smoketree-plugin' ); ?></p>
					<button type="button" class="stsrc-button stsrc-button-primary stsrc-button-large" id="stsrc-purchase-guest-passes-btn">
						<?php echo esc_html__( 'Purchase Guest Passes', 'smoketree-plugin' ); ?>
					</button>
				</div>
			<?php else : ?>
				<div class="stsrc-use-pass-section">
					<p class="stsrc-description"><?php echo esc_html__( 'Scan this page when a guest arrives to use a guest pass.', 'smoketree-plugin' ); ?></p>
					<button type="button" class="stsrc-button stsrc-button-primary stsrc-button-large" id="stsrc-use-guest-pass-btn">
						<?php echo esc_html__( 'Use Guest Pass', 'smoketree-plugin' ); ?>
					</button>
				</div>
			<?php endif; ?>
		</div>

		<!-- Usage Log -->
		<?php if ( ! empty( $usage_log ) ) : ?>
			<div class="stsrc-portal-section">
				<h2><?php echo esc_html__( 'Recent Usage', 'smoketree-plugin' ); ?></h2>
				<div class="stsrc-usage-log">
					<table class="stsrc-table">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Date', 'smoketree-plugin' ); ?></th>
								<th><?php echo esc_html__( 'Type', 'smoketree-plugin' ); ?></th>
								<th><?php echo esc_html__( 'Quantity', 'smoketree-plugin' ); ?></th>
								<th><?php echo esc_html__( 'Amount', 'smoketree-plugin' ); ?></th>
								<th><?php echo esc_html__( 'Status', 'smoketree-plugin' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $usage_log as $log ) : ?>
								<tr>
									<td>
										<?php
										if ( ! empty( $log['used_at'] ) ) {
											echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log['used_at'] ) ) );
										} else {
											echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log['created_at'] ) ) );
										}
										?>
									</td>
									<td>
										<?php if ( ! empty( $log['admin_adjusted'] ) ) : ?>
											<span class="stsrc-badge stsrc-badge-admin"><?php echo esc_html__( 'Admin Adjustment', 'smoketree-plugin' ); ?></span>
										<?php elseif ( ! empty( $log['used_at'] ) ) : ?>
											<span class="stsrc-badge stsrc-badge-used"><?php echo esc_html__( 'Usage', 'smoketree-plugin' ); ?></span>
										<?php else : ?>
											<span class="stsrc-badge stsrc-badge-purchase"><?php echo esc_html__( 'Purchase', 'smoketree-plugin' ); ?></span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $log['quantity'] ); ?></td>
									<td>
										<?php if ( ! empty( $log['amount'] ) && $log['amount'] > 0 ) : ?>
											$<?php echo esc_html( number_format( floatval( $log['amount'] ), 2 ) ); ?>
										<?php else : ?>
											<span class="description">—</span>
										<?php endif; ?>
									</td>
									<td>
										<span class="stsrc-status-badge <?php echo esc_attr( $log['payment_status'] ); ?>">
											<?php echo esc_html( ucfirst( $log['payment_status'] ) ); ?>
										</span>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<!-- Purchase Guest Passes Modal -->
<div class="stsrc-modal-overlay" id="stsrc-purchase-guest-passes-modal">
	<div class="stsrc-modal">
		<div class="stsrc-modal-header">
			<h2><?php echo esc_html__( 'Purchase Guest Passes', 'smoketree-plugin' ); ?></h2>
			<button class="stsrc-modal-close">&times;</button>
		</div>
		<div class="stsrc-modal-body">
			<form id="stsrc-purchase-guest-passes-form">
				<input type="hidden" name="action" value="stsrc_purchase_guest_passes">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'stsrc_guest_pass_nonce' ) ); ?>">
				
				<div class="stsrc-form-group">
					<label for="guest_pass_quantity"><?php echo esc_html__( 'Number of Passes', 'smoketree-plugin' ); ?></label>
					<input type="number" name="quantity" id="guest_pass_quantity" min="1" value="1" required>
					<small><?php echo esc_html__( '$5 per pass', 'smoketree-plugin' ); ?></small>
				</div>
				
				<div class="stsrc-form-group">
					<strong><?php echo esc_html__( 'Total:', 'smoketree-plugin' ); ?> $<span id="stsrc-guest-pass-total">5.00</span></strong>
				</div>
				
				<div class="stsrc-modal-footer">
					<button type="button" class="stsrc-button stsrc-button-secondary stsrc-modal-close"><?php echo esc_html__( 'Cancel', 'smoketree-plugin' ); ?></button>
					<button type="submit" class="stsrc-button stsrc-button-primary"><?php echo esc_html__( 'Purchase', 'smoketree-plugin' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Calculate total when quantity changes
	$('#guest_pass_quantity').on('change', function() {
		const quantity = parseInt($(this).val()) || 1;
		const total = (quantity * 5).toFixed(2);
		$('#stsrc-guest-pass-total').text(total);
	});

	// Purchase guest passes
	$('#stsrc-purchase-guest-passes-btn, #stsrc-purchase-guest-passes-form').on('click', function(e) {
		if ($(this).is('form') || $(this).is('button[type="submit"]')) {
			if ($(this).is('button[type="submit"]')) {
				e.preventDefault();
			}
		} else {
			e.preventDefault();
			$('#stsrc-purchase-guest-passes-modal').addClass('active');
		}
	});

	$('#stsrc-purchase-guest-passes-form').on('submit', function(e) {
		e.preventDefault();
		
		const $form = $(this);
		const $submitBtn = $form.find('button[type="submit"]');
		const $messages = $('#stsrc-portal-messages');
		
		$submitBtn.prop('disabled', true).text('<?php echo esc_js( __( 'Processing...', 'smoketree-plugin' ) ); ?>');
		$messages.html('');
		
		$.ajax({
			url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
			type: 'POST',
			data: $form.serialize(),
			success: function(response) {
				if (response.success) {
					if (response.data.checkout_url) {
						// Redirect to Stripe checkout
						window.location.href = response.data.checkout_url;
					} else {
						$messages.html('<div class="stsrc-notice success"><p>' + response.data.message + '</p></div>');
						$('#stsrc-purchase-guest-passes-modal').removeClass('active');
						setTimeout(function() {
							location.reload();
						}, 1000);
					}
				} else {
					$messages.html('<div class="stsrc-notice error"><p>' + response.data.message + '</p></div>');
					$submitBtn.prop('disabled', false).text('<?php echo esc_js( __( 'Purchase', 'smoketree-plugin' ) ); ?>');
				}
			},
			error: function() {
				$messages.html('<div class="stsrc-notice error"><p><?php echo esc_js( __( 'An error occurred. Please try again.', 'smoketree-plugin' ) ); ?></p></div>');
				$submitBtn.prop('disabled', false).text('<?php echo esc_js( __( 'Purchase', 'smoketree-plugin' ) ); ?>');
			}
		});
	});

	// Use guest pass
	$('#stsrc-use-guest-pass-btn').on('click', function(e) {
		e.preventDefault();
		
		if (!confirm('<?php echo esc_js( __( 'Are you sure you want to use a guest pass?', 'smoketree-plugin' ) ); ?>')) {
			return;
		}
		
		const $button = $(this);
		const $messages = $('#stsrc-portal-messages');
		
		$button.prop('disabled', true).text('<?php echo esc_js( __( 'Processing...', 'smoketree-plugin' ) ); ?>');
		$messages.html('');
		
		$.ajax({
			url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
			type: 'POST',
			data: {
				action: 'stsrc_use_guest_pass',
				nonce: '<?php echo esc_js( wp_create_nonce( 'stsrc_guest_pass_nonce' ) ); ?>'
			},
			success: function(response) {
				if (response.success) {
					$messages.html('<div class="stsrc-notice success"><p>' + response.data.message + '</p></div>');
					setTimeout(function() {
						location.reload();
					}, 1000);
				} else {
					$messages.html('<div class="stsrc-notice error"><p>' + response.data.message + '</p></div>');
					$button.prop('disabled', false).text('<?php echo esc_js( __( 'Use Guest Pass', 'smoketree-plugin' ) ); ?>');
				}
			},
			error: function() {
				$messages.html('<div class="stsrc-notice error"><p><?php echo esc_js( __( 'An error occurred. Please try again.', 'smoketree-plugin' ) ); ?></p></div>');
				$button.prop('disabled', false).text('<?php echo esc_js( __( 'Use Guest Pass', 'smoketree-plugin' ) ); ?>');
			}
		});
	});

	// Modal close handlers
	$('.stsrc-modal-close, .stsrc-modal-overlay').on('click', function(e) {
		if ($(e.target).hasClass('stsrc-modal-overlay') || $(e.target).hasClass('stsrc-modal-close')) {
			$('.stsrc-modal-overlay').removeClass('active');
		}
	});
});
</script>

<?php
get_footer();

