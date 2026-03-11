<?php
/**
 * Payment Reminder Email Template
 *
 * Nudges members whose membership invoice is still outstanding.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/templates
 */

// Template variables available:
// - $first_name
// - $last_name
// - $email
// - $amount_due
// - $due_date
// - $payment_link
// Renewal-specific (optional):
// - $is_renewal           (bool)
// - $membership_name      (string)
// - $base_amount          (string, e.g. "$200.00")
// - $extra_member_count   (int)
// - $extra_member_fee     (string, e.g. "$50.00")
// - $extra_member_total   (string, e.g. "$150.00")
// - $flat_fee             (string, e.g. "$10.00")
?>
<?php
$club_name     = 'Smoketree Swim and Recreation Club';
$portal_url    = home_url( '/member-portal/' );
$contact_email = function_exists( 'get_field' ) ? get_field( 'stsrc_contact_email', 'option' ) : get_option( 'stsrc_contact_email', '' );
$amount_label  = $amount_due ?? '';
$due_label     = $due_date ?? '';
$payment_url   = $payment_link ?? '';
$is_renewal    = ! empty( $is_renewal );
$extra_count   = (int) ( $extra_member_count ?? 0 );
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html__( 'Membership Payment Reminder', 'smoketree-plugin' ); ?></title>
	<style>
		body { margin:0; padding:0; background-color:#f4f6f9; color:#1d2327; font-family:"Segoe UI", Arial, sans-serif; }
		.email-shell { width:100%; padding:24px 0; }
		.email-container { width:100%; max-width:600px; margin:0 auto; background-color:#ffffff; border-radius:8px; box-shadow:0 3px 12px rgba(0,0,0,0.08); overflow:hidden; }
		.email-header { background-color:#d97706; padding:26px 24px; text-align:center; color:#ffffff; font-size:20px; font-weight:600; }
		.email-body { padding:32px 28px; }
		.email-footer { padding:24px 28px; background-color:#f1f3f6; font-size:12px; color:#555d66; text-align:center; }
		h1 { margin:0 0 18px; font-size:24px; color:#1d2327; }
		p { margin:0 0 16px; line-height:1.6; }
		.summary-card { background:#fff7ed; border:1px solid #f9c675; border-radius:6px; padding:16px 18px; margin:18px 0; }
		.summary-card p { margin:0 0 8px; font-weight:600; color:#7c2d12; }
		.summary-card span { display:block; font-weight:400; color:#1d2327; }
		.breakdown-table { width:100%; border-collapse:collapse; margin:10px 0 4px; }
		.breakdown-table td { padding:4px 0; font-weight:400; color:#1d2327; }
		.breakdown-table td:last-child { text-align:right; }
		.breakdown-table .total-row td { border-top:1px solid #f9c675; padding-top:8px; font-weight:700; }
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
							<?php if ( $is_renewal ) : ?>
								<?php echo esc_html__( 'Membership Renewal Notice', 'smoketree-plugin' ); ?>
							<?php else : ?>
								<?php echo esc_html__( 'Friendly payment reminder', 'smoketree-plugin' ); ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td class="email-body">
							<?php if ( $is_renewal ) : ?>
								<h1><?php echo esc_html__( 'Your membership renewal is coming up', 'smoketree-plugin' ); ?></h1>
								<p>
									<?php
									printf(
										esc_html__( 'Hi %s,', 'smoketree-plugin' ),
										esc_html( $first_name ?? '' )
									);
									?>
								</p>
								<p>
									<?php
									printf(
										esc_html__( 'Your %s membership will automatically renew on %s. Here is a breakdown of what will be charged to your saved payment method:', 'smoketree-plugin' ),
										esc_html( $membership_name ?? '' ),
										esc_html( $due_label )
									);
									?>
								</p>

								<div class="summary-card">
									<table class="breakdown-table">
										<tr>
											<td><?php echo esc_html( $membership_name ?? '' ); ?> <?php echo esc_html__( 'membership', 'smoketree-plugin' ); ?></td>
											<td><?php echo esc_html( $base_amount ?? '' ); ?></td>
										</tr>
										<?php if ( $extra_count > 0 ) : ?>
											<tr>
												<td>
													<?php
													printf(
														esc_html__( 'Extra members (%1$d × %2$s)', 'smoketree-plugin' ),
														$extra_count,
														esc_html( $extra_member_fee ?? '' )
													);
													?>
												</td>
												<td><?php echo esc_html( $extra_member_total ?? '' ); ?></td>
											</tr>
										<?php endif; ?>
										<tr>
											<td><?php echo esc_html__( 'Processing fee', 'smoketree-plugin' ); ?></td>
											<td><?php echo esc_html( $flat_fee ?? '' ); ?></td>
										</tr>
										<tr class="total-row">
											<td><?php echo esc_html__( 'Total', 'smoketree-plugin' ); ?></td>
											<td><?php echo esc_html( $amount_label ); ?></td>
										</tr>
									</table>
								</div>

								<?php if ( $extra_count > 0 ) : ?>
									<p><?php echo esc_html__( 'Want to add or remove extra members before renewal? You can manage your extra members (up to 3) from your Member Portal. Any changes you make before the renewal date will be reflected in the final charge.', 'smoketree-plugin' ); ?></p>
								<?php else : ?>
									<p><?php echo esc_html__( 'Need to add extra members to your membership? You can add up to 3 extra members from your Member Portal before the renewal date and they will be included in the charge.', 'smoketree-plugin' ); ?></p>
								<?php endif; ?>

								<?php if ( ! empty( $payment_url ) ) : ?>
									<p style="text-align:center;">
										<a href="<?php echo esc_url( $payment_url ); ?>" class="btn"><?php echo esc_html__( 'Manage My Membership', 'smoketree-plugin' ); ?></a>
									</p>
								<?php endif; ?>

								<p><?php echo esc_html__( 'If you would like to opt out of auto-renewal, you can do so from the Member Portal before the renewal date. If you do not opt out, your account will become inactive at renewal.', 'smoketree-plugin' ); ?></p>
								<p><?php echo esc_html__( 'Questions? Reply to this email and we will be glad to help.', 'smoketree-plugin' ); ?></p>

							<?php else : ?>
								<h1><?php echo esc_html__( 'A quick note about your membership dues', 'smoketree-plugin' ); ?></h1>
								<p>
									<?php
									printf(
										esc_html__( 'Hi %s,', 'smoketree-plugin' ),
										esc_html( $first_name ?? '' )
									);
									?>
								</p>
								<p><?php echo esc_html__( 'We noticed your membership payment is still outstanding. When you have a moment, please take a second to wrap it up so we can keep your access uninterrupted.', 'smoketree-plugin' ); ?></p>

								<div class="summary-card">
									<?php if ( ! empty( $amount_label ) ) : ?>
										<p>
											<?php echo esc_html__( 'Amount due', 'smoketree-plugin' ); ?>
											<span><?php echo esc_html( $amount_label ); ?></span>
										</p>
									<?php endif; ?>
									<?php if ( ! empty( $due_label ) ) : ?>
										<p>
											<?php echo esc_html__( 'Due date', 'smoketree-plugin' ); ?>
											<span><?php echo esc_html( $due_label ); ?></span>
										</p>
									<?php endif; ?>
								</div>

								<?php if ( ! empty( $payment_url ) ) : ?>
									<p style="text-align:center;">
										<a href="<?php echo esc_url( $payment_url ); ?>" class="btn"><?php echo esc_html__( 'Pay Securely Online', 'smoketree-plugin' ); ?></a>
									</p>
								<?php else : ?>
									<p><?php echo esc_html__( 'You can complete your payment from the member portal or by contacting us directly.', 'smoketree-plugin' ); ?></p>
								<?php endif; ?>

								<p><?php echo esc_html__( 'If you have already taken care of this, thank you and please disregard this message.', 'smoketree-plugin' ); ?></p>
								<p><?php echo esc_html__( 'Need a hand or want to confirm your balance? Reply to this email and we will be glad to assist.', 'smoketree-plugin' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td class="email-footer">
							<?php echo esc_html( $club_name ); ?>
							<?php if ( ! empty( $contact_email ) ) : ?>
								· <a href="mailto:<?php echo esc_attr( $contact_email ); ?>" style="color:#555d66;"><?php echo esc_html( $contact_email ); ?></a>
							<?php endif; ?><br>
							<?php echo esc_html__( 'We appreciate your continued membership.', 'smoketree-plugin' ); ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</div>
</body>
</html>

