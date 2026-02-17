<?php
/**
 * Balance Payment Success Email Template
 *
 * Sent to members after a successful balance payment.
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
	<title><?php echo esc_html__( 'Balance Payment Received', 'smoketree-plugin' ); ?></title>
	<style>
		body { margin:0; padding:0; background-color:#f5f7fb; color:#1d2327; font-family:"Segoe UI", Arial, sans-serif; }
		.email-shell { width:100%; padding:24px 0; }
		.email-container { width:100%; max-width:620px; margin:0 auto; background-color:#ffffff; border-radius:8px; box-shadow:0 3px 12px rgba(0,0,0,0.08); overflow:hidden; }
		.email-header { background-color:#2271b1; padding:24px; text-align:center; color:#ffffff; font-size:20px; font-weight:600; }
		.email-body { padding:28px 24px; }
		.email-footer { padding:18px 24px; background-color:#f3f4f6; font-size:12px; color:#555d66; text-align:center; }
		h1 { margin:0 0 16px; font-size:22px; color:#1d2327; }
		p { margin:0 0 14px; line-height:1.6; }
		table.data-table { width:100%; border-collapse:collapse; margin:18px 0; }
		table.data-table th, table.data-table td { padding:10px 12px; text-align:left; border-bottom:1px solid #e5e7eb; font-size:14px; }
		table.data-table th { width:40%; font-weight:600; color:#1d2327; }
		.badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; background:#e7f3ff; color:#1565c0; }
		.badge-success { background:#e8f5e9; color:#1b5e20; }
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
							<?php echo esc_html__( 'Balance Payment Confirmation', 'smoketree-plugin' ); ?>
						</td>
					</tr>
					<tr>
						<td class="email-body">
							<h1><?php echo esc_html__( 'Thank you for your payment!', 'smoketree-plugin' ); ?></h1>
							<p>
								<?php
								printf(
									/* translators: %s: first name */
									esc_html__( 'Hi %s, we received your balance payment.', 'smoketree-plugin' ),
									esc_html( $first_name ?? '' )
								);
								?>
							</p>

							<table role="presentation" class="data-table">
								<tbody>
									<tr>
										<th><?php echo esc_html__( 'Amount Paid', 'smoketree-plugin' ); ?></th>
										<td><?php echo esc_html( $amount_paid ?? '' ); ?></td>
									</tr>
									<tr>
										<th><?php echo esc_html__( 'Payment Method', 'smoketree-plugin' ); ?></th>
										<td><span class="badge"><?php echo esc_html( $payment_method ?? '' ); ?></span></td>
									</tr>
									<tr>
										<th><?php echo esc_html__( 'Transaction Date', 'smoketree-plugin' ); ?></th>
										<td><?php echo esc_html( $transaction_date ?? '' ); ?></td>
									</tr>
									<tr>
										<th><?php echo esc_html__( 'Current Balance', 'smoketree-plugin' ); ?></th>
										<td>
											<?php if ( ! empty( $is_paid_in_full ) ) : ?>
												<span class="badge badge-success"><?php echo esc_html__( 'Paid in Full', 'smoketree-plugin' ); ?></span>
											<?php else : ?>
												<?php echo esc_html( $new_balance_text ?? '' ); ?>
											<?php endif; ?>
										</td>
									</tr>
								</tbody>
							</table>

							<?php if ( ! empty( $is_paid_in_full ) ) : ?>
								<p><?php echo esc_html__( 'Great news - your account balance is now fully paid. Your membership can now be activated if it was pending.', 'smoketree-plugin' ); ?></p>
							<?php endif; ?>

							<p style="text-align:center;">
								<a href="<?php echo esc_url( $portal_url ?? home_url( '/member-portal' ) ); ?>" class="btn">
									<?php echo esc_html__( 'View Transaction History', 'smoketree-plugin' ); ?>
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
