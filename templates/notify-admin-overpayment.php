<?php
/**
 * Admin Overpayment Alert Email Template
 *
 * Sent to admins when a member overpays their balance.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/templates
 */
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html__( 'Member Overpayment Alert', 'smoketree-plugin' ); ?></title>
	<style>
		body { margin:0; padding:0; background-color:#f5f7fb; color:#1d2327; font-family:"Segoe UI", Arial, sans-serif; }
		.email-shell { width:100%; padding:24px 0; }
		.email-container { width:100%; max-width:620px; margin:0 auto; background-color:#ffffff; border-radius:8px; box-shadow:0 3px 12px rgba(0,0,0,0.08); overflow:hidden; }
		.email-header { background-color:#b32d2e; padding:24px; text-align:center; color:#ffffff; font-size:20px; font-weight:600; }
		.email-body { padding:28px 24px; }
		.email-footer { padding:18px 24px; background-color:#f3f4f6; font-size:12px; color:#555d66; text-align:center; }
		h1 { margin:0 0 16px; font-size:22px; color:#1d2327; }
		p { margin:0 0 14px; line-height:1.6; }
		table.data-table { width:100%; border-collapse:collapse; margin:18px 0; }
		table.data-table th, table.data-table td { padding:10px 12px; text-align:left; border-bottom:1px solid #e5e7eb; font-size:14px; vertical-align:top; }
		table.data-table th { width:40%; font-weight:600; color:#1d2327; }
		.alert { padding:12px 14px; border-radius:6px; background:#fde8e8; color:#842029; border:1px solid #f5c2c7; margin:14px 0 18px; }
		.btn { display:inline-block; padding:12px 22px; background-color:#2271b1; color:#ffffff !important; text-decoration:none; border-radius:4px; font-weight:600; }
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
							<?php echo esc_html__( 'Overpayment Alert', 'smoketree-plugin' ); ?>
						</td>
					</tr>
					<tr>
						<td class="email-body">
							<h1><?php echo esc_html__( 'Member account has a negative balance', 'smoketree-plugin' ); ?></h1>
							<p><?php echo esc_html__( 'A member appears to have overpaid. Please review and follow up regarding refund or credit handling.', 'smoketree-plugin' ); ?></p>

							<div class="alert">
								<strong><?php echo esc_html__( 'Overpayment Amount:', 'smoketree-plugin' ); ?></strong>
								<?php echo esc_html( $overpayment_amount ?? '' ); ?>
							</div>

							<table role="presentation" class="data-table">
								<tbody>
									<tr>
										<th><?php echo esc_html__( 'Member', 'smoketree-plugin' ); ?></th>
										<td><?php echo esc_html( $member_name ?? '' ); ?></td>
									</tr>
									<tr>
										<th><?php echo esc_html__( 'Email', 'smoketree-plugin' ); ?></th>
										<td><?php echo esc_html( $member_email ?? '' ); ?></td>
									</tr>
									<tr>
										<th><?php echo esc_html__( 'Payment Amount', 'smoketree-plugin' ); ?></th>
										<td><?php echo esc_html( $payment_amount ?? '' ); ?></td>
									</tr>
									<tr>
										<th><?php echo esc_html__( 'Payment Method', 'smoketree-plugin' ); ?></th>
										<td><?php echo esc_html( $payment_method ?? '' ); ?></td>
									</tr>
									<tr>
										<th><?php echo esc_html__( 'Transaction Date', 'smoketree-plugin' ); ?></th>
										<td><?php echo esc_html( $transaction_date ?? '' ); ?></td>
									</tr>
									<tr>
										<th><?php echo esc_html__( 'Current Balance', 'smoketree-plugin' ); ?></th>
										<td><?php echo esc_html( $new_balance ?? '' ); ?></td>
									</tr>
								</tbody>
							</table>

							<p><?php echo esc_html__( 'Suggested action: contact the member to arrange a refund or apply an agreed credit.', 'smoketree-plugin' ); ?></p>

							<p style="text-align:center;">
								<a href="<?php echo esc_url( $member_admin_url ?? admin_url( 'admin.php?page=stsrc-members' ) ); ?>" class="btn">
									<?php echo esc_html__( 'Review Member in Admin', 'smoketree-plugin' ); ?>
								</a>
							</p>
						</td>
					</tr>
					<tr>
						<td class="email-footer">
							<?php echo esc_html__( 'Smoketree Swim and Recreation Club', 'smoketree-plugin' ); ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</div>
</body>
</html>
