<?php
/**
 * Thank You Pay Later Email Template
 *
 * Confirms "pay later" registrations and includes the amount still due.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/templates
 */

// Template variables available:
// - $first_name
// - $last_name
// - $email
// - $amount_due
// - $payment_instructions
?>
<?php
$club_name            = 'Smoketree Swim and Recreation Club';
$portal_url           = home_url( '/member-portal/' );
$support_url          = home_url( '/contact/' );
$amount_label         = $amount_due ?? '';
$instructions_content = $payment_instructions ?? '';
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html__( 'Thank you for registering', 'smoketree-plugin' ); ?></title>
	<style>
		body { margin:0; padding:0; background-color:#f4f6f9; color:#1d2327; font-family:"Segoe UI", Arial, sans-serif; }
		.email-shell { width:100%; padding:24px 0; }
		.email-container { width:100%; max-width:600px; margin:0 auto; background-color:#ffffff; border-radius:8px; box-shadow:0 3px 12px rgba(0,0,0,0.08); overflow:hidden; }
		.email-header { background-color:#2271b1; padding:26px 24px; text-align:center; color:#ffffff; font-size:20px; font-weight:600; }
		.email-body { padding:32px 28px; }
		.email-footer { padding:24px 28px; background-color:#f1f3f6; font-size:12px; color:#555d66; text-align:center; }
		h1 { margin:0 0 18px; font-size:24px; color:#1d2327; }
		p { margin:0 0 16px; line-height:1.6; }
		.summary-card { background:#eef6ff; border:1px solid #c5dafa; border-radius:6px; padding:16px 18px; margin:18px 0; }
		.summary-card span { display:block; font-weight:600; color:#0f4a7b; }
		.instructions { background:#fffbea; border:1px solid #fde68a; border-radius:6px; padding:16px 18px; margin:18px 0; color:#854d0e; }
		.btn { display:inline-block; padding:12px 24px; background-color:#2271b1; color:#ffffff !important; text-decoration:none; border-radius:4px; font-weight:600; }
		@media only screen and (max-width:620px) {
			.email-body { padding:26px 20px; }
			.email-header { padding:22px 18px; font-size:18px; }
		}
	</style>
</head>
<body>
<div class="email-shell">
	<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
		<tr>
			<td align="center">
				<table role="presentation" class="email-container" width="100%" cellspacing="0" cellpadding="0" border="0">
					<tr>
						<td class="email-header">
							<?php echo esc_html( $club_name ); ?>
						</td>
					</tr>
					<tr>
						<td class="email-body">
							<h1><?php echo esc_html__( 'Welcome aboard!', 'smoketree-plugin' ); ?></h1>
							<p>
								<?php
								printf(
									/* translators: %s: First name */
									esc_html__( 'Hi %s,', 'smoketree-plugin' ),
									esc_html( $first_name ?? '' )
								);
								?>
							</p>
							<p><?php echo esc_html__( 'Thank you for choosing the pay-later option. We have saved your registration and will activate your membership as soon as the remaining balance is received.', 'smoketree-plugin' ); ?></p>

							<div class="summary-card">
								<?php if ( ! empty( $amount_label ) ) : ?>
									<p><?php echo esc_html__( 'Balance due', 'smoketree-plugin' ); ?></p>
									<span><?php echo esc_html( $amount_label ); ?></span>
								<?php endif; ?>
							</div>

							<?php if ( ! empty( $instructions_content ) ) : ?>
								<div class="instructions">
									<strong><?php echo esc_html__( 'How to submit payment:', 'smoketree-plugin' ); ?></strong>
									<p style="margin:12px 0 0; line-height:1.6;"><?php echo esc_html( $instructions_content ); ?></p>
								</div>
							<?php endif; ?>

							<p><?php echo esc_html__( 'Once payment is recorded we will send you a confirmation email. You can always visit the member portal to update your details or check your status.', 'smoketree-plugin' ); ?></p>

							<p style="text-align:center;">
								<a href="<?php echo esc_url( $portal_url ); ?>" class="btn"><?php echo esc_html__( 'Visit Member Portal', 'smoketree-plugin' ); ?></a>
							</p>

							<p><?php echo esc_html__( 'Questions or need a hand with payment arrangements? Reply to this email and our team will be happy to help.', 'smoketree-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<td class="email-footer">
							<?php echo esc_html( $club_name ); ?> · <?php echo esc_html( $support_url ); ?><br>
							<?php echo esc_html__( 'We can’t wait to see you at the pool!', 'smoketree-plugin' ); ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</div>
</body>
</html>

