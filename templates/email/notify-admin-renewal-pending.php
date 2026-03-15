<?php
/**
 * Admin renewal pending payment notice template.
 *
 * @package Smoketree_Plugin
 */
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html__( 'Renewal Awaiting Payment', 'smoketree-plugin' ); ?></title>
</head>
<body style="font-family: Arial, sans-serif; color: #1d2327; background: #f6f7f7; padding: 20px;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 620px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden;">
		<tr><td style="padding: 20px 24px; background: #92400e; color: #ffffff;"><strong><?php echo esc_html__( 'Renewal Awaiting Payment', 'smoketree-plugin' ); ?></strong></td></tr>
		<tr>
			<td style="padding: 24px;">
				<p><?php echo esc_html__( 'A member has submitted a renewal and selected an offline payment method. Please follow up to collect payment.', 'smoketree-plugin' ); ?></p>
				<p><strong><?php echo esc_html__( 'Member:', 'smoketree-plugin' ); ?></strong> <?php echo esc_html( (string) ( $member_name ?? '' ) ); ?></p>
				<p><strong><?php echo esc_html__( 'Email:', 'smoketree-plugin' ); ?></strong> <?php echo esc_html( (string) ( $member_email ?? '' ) ); ?></p>
				<p><strong><?php echo esc_html__( 'Season:', 'smoketree-plugin' ); ?></strong> <?php echo esc_html( (string) ( $season_key ?? '' ) ); ?></p>
				<p><strong><?php echo esc_html__( 'Membership:', 'smoketree-plugin' ); ?></strong> <?php echo esc_html( (string) ( $membership_type_name ?? '' ) ); ?></p>
				<p><strong><?php echo esc_html__( 'Payment Method:', 'smoketree-plugin' ); ?></strong> <?php echo esc_html( (string) ( $payment_method_label ?? '' ) ); ?></p>
				<p><strong><?php echo esc_html__( 'Amount Due:', 'smoketree-plugin' ); ?></strong> <?php echo esc_html( '$' . number_format( (float) ( $total_amount ?? 0 ), 2 ) ); ?></p>
				<p style="margin-top: 18px;">
					<a href="<?php echo esc_url( (string) ( $member_admin_url ?? admin_url( 'admin.php?page=stsrc-members' ) ) ); ?>" style="display:inline-block;padding:10px 16px;background:#2271b1;color:#fff;text-decoration:none;border-radius:4px;">
						<?php echo esc_html__( 'View Member Record', 'smoketree-plugin' ); ?>
					</a>
				</p>
			</td>
		</tr>
	</table>
</body>
</html>
