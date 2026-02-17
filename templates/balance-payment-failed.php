<?php
/**
 * Balance Payment Failed Email Template
 *
 * Sent to members when a balance payment attempt fails.
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
	<title><?php echo esc_html__( 'Balance Payment Failed', 'smoketree-plugin' ); ?></title>
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
		.muted { color:#50575e; font-size:13px; }
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
							<?php echo esc_html__( 'Balance Payment Could Not Be Processed', 'smoketree-plugin' ); ?>
						</td>
					</tr>
					<tr>
						<td class="email-body">
							<h1><?php echo esc_html__( 'Your payment attempt was not completed', 'smoketree-plugin' ); ?></h1>
							<p>
								<?php
								printf(
									/* translators: %s: first name */
									esc_html__( 'Hi %s, we were unable to process your recent balance payment.', 'smoketree-plugin' ),
									esc_html( $first_name ?? '' )
								);
								?>
							</p>

							<div class="alert">
								<strong><?php echo esc_html__( 'Reason:', 'smoketree-plugin' ); ?></strong>
								<?php echo esc_html( $failure_reason ?? esc_html__( 'Payment failed', 'smoketree-plugin' ) ); ?>
							</div>

							<table role="presentation" class="data-table">
								<tbody>
									<tr>
										<th><?php echo esc_html__( 'Attempted Amount', 'smoketree-plugin' ); ?></th>
										<td><?php echo esc_html( $attempted_amount ?? '' ); ?></td>
									</tr>
									<tr>
										<th><?php echo esc_html__( 'Current Balance Owed', 'smoketree-plugin' ); ?></th>
										<td><?php echo esc_html( $current_balance ?? '' ); ?></td>
									</tr>
								</tbody>
							</table>

							<p style="text-align:center; margin-top:20px;">
								<a href="<?php echo esc_url( $portal_url ?? home_url( '/member-portal' ) ); ?>" class="btn">
									<?php echo esc_html__( 'Retry Payment', 'smoketree-plugin' ); ?>
								</a>
							</p>

							<p class="muted">
								<?php echo esc_html__( 'If you prefer, you can pay by Zelle or check. Contact us for manual payment instructions and confirmation.', 'smoketree-plugin' ); ?>
								<?php if ( ! empty( $secretary_email ) ) : ?>
									<?php
									printf(
										/* translators: %s: secretary email address */
										esc_html__( ' Reach us at %s.', 'smoketree-plugin' ),
										esc_html( $secretary_email )
									);
									?>
								<?php endif; ?>
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
