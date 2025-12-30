<?php
/**
 * Payment Success Email Template
 *
 * Thanks members immediately after Stripe confirms their payment.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/templates
 */

// Template variables available:
// - $first_name
// - $last_name
// - $email
// - $amount
// - $payment_type
// - $member (STSRC_Member object, if available)
?>
<?php
$club_name        = 'Smoketree Swim and Recreation Club';
$portal_url       = home_url( '/member-portal/' );
$support_url      = home_url( '/contact/' );
$display_amount   = $amount ?? '';
$display_payment_type = $payment_type ?? '';
$membership_label = '';

if ( isset( $member ) && is_object( $member ) ) {
	if ( ! empty( $member->membership_type['name'] ) ) {
		$membership_label = $member->membership_type['name'];
	} elseif ( method_exists( $member, 'get_membership_type_name' ) ) {
		$membership_label = $member->get_membership_type_name();
	}
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html__( 'Payment Successful', 'smoketree-plugin' ); ?></title>
	<style>
		body { margin:0; padding:0; background-color:#f4f6f9; color:#1d2327; font-family:"Segoe UI", Arial, sans-serif; }
		.email-shell { width:100%; padding:24px 0; }
		.email-container { width:100%; max-width:600px; margin:0 auto; background-color:#ffffff; border-radius:8px; box-shadow:0 3px 12px rgba(0,0,0,0.08); overflow:hidden; }
		.email-header { background-color:#2271b1; padding:26px 24px; text-align:center; color:#ffffff; font-size:20px; font-weight:600; }
		.email-body { padding:32px 28px; }
		.email-footer { padding:24px 28px; background-color:#f1f3f6; font-size:12px; color:#555d66; text-align:center; }
		h1 { margin:0 0 18px; font-size:24px; color:#1d2327; }
		p { margin:0 0 16px; line-height:1.6; }
		.btn { display:inline-block; padding:12px 24px; background-color:#2271b1; color:#ffffff !important; text-decoration:none; border-radius:4px; font-weight:600; }
		table.data-table { width:100%; border-collapse:collapse; margin:20px 0; }
		table.data-table th, table.data-table td { padding:10px 12px; text-align:left; border-bottom:1px solid #e5e9ef; font-size:14px; }
		table.data-table th { width:38%; font-weight:600; color:#1d2327; }
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
							<h1><?php echo esc_html__( 'Thank you – your payment was successful!', 'smoketree-plugin' ); ?></h1>
							<p>
								<?php
								printf(
									/* translators: %s: First name */
									esc_html__( 'Hi %s,', 'smoketree-plugin' ),
									esc_html( $first_name ?? '' )
								);
								?>
							</p>
							<p><?php echo esc_html__( 'We have received your membership payment and your account is now in good standing.', 'smoketree-plugin' ); ?></p>

							<table role="presentation" class="data-table">
								<tbody>
									<?php if ( ! empty( $display_amount ) ) : ?>
										<tr>
											<th><?php echo esc_html__( 'Amount Paid', 'smoketree-plugin' ); ?></th>
											<td><?php echo esc_html( $display_amount ); ?></td>
										</tr>
									<?php endif; ?>
									<?php if ( ! empty( $display_payment_type ) ) : ?>
										<tr>
											<th><?php echo esc_html__( 'Payment Method', 'smoketree-plugin' ); ?></th>
											<td><?php echo esc_html( ucfirst( $display_payment_type ) ); ?></td>
										</tr>
									<?php endif; ?>
									<?php if ( ! empty( $membership_label ) ) : ?>
										<tr>
											<th><?php echo esc_html__( 'Membership Type', 'smoketree-plugin' ); ?></th>
											<td><?php echo esc_html( $membership_label ); ?></td>
										</tr>
									<?php endif; ?>
								</tbody>
							</table>

							<p><?php echo esc_html__( 'You can visit your member portal any time to review your details, update family members, or purchase guest passes.', 'smoketree-plugin' ); ?></p>

							<p style="text-align:center;">
								<a href="<?php echo esc_url( $portal_url ); ?>" class="btn"><?php echo esc_html__( 'Go to Member Portal', 'smoketree-plugin' ); ?></a>
							</p>

							<p><?php echo esc_html__( 'If you have any questions, simply reply to this email or reach out to our team—we\'re happy to help.', 'smoketree-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<td class="email-footer">
							<?php echo esc_html( $club_name ); ?> · <?php echo esc_html( $support_url ); ?><br>
							<?php echo esc_html__( 'Thank you for being part of our community.', 'smoketree-plugin' ); ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</div>
</body>
</html>

