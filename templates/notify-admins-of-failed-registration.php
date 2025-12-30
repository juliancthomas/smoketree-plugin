<?php
$club_name        = 'Smoketree Swim and Recreation Club';
$admin_members    = admin_url( 'admin.php?page=stsrc-members' );
$first_last       = trim( ( $first_name ?? '' ) . ' ' . ( $last_name ?? '' ) );
$payment_label    = $payment_type ?? '';
$error_details    = $error_message ?? '';
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html__( 'Registration failed', 'smoketree-plugin' ); ?></title>
	<style>
		body { margin:0; padding:0; background-color:#fdf2f8; color:#1d2327; font-family:"Segoe UI", Arial, sans-serif; }
		.email-shell { width:100%; padding:24px 0; }
		.email-container { width:100%; max-width:620px; margin:0 auto; background-color:#ffffff; border-radius:8px; box-shadow:0 3px 12px rgba(0,0,0,0.1); overflow:hidden; }
		.email-header { background-color:#9d174d; padding:26px 24px; text-align:center; color:#ffffff; font-size:20px; font-weight:600; }
		.email-body { padding:32px 28px; }
		.email-footer { padding:20px 24px; background-color:#f3f4f6; font-size:12px; color:#555d66; text-align:center; }
		h1 { margin:0 0 18px; font-size:22px; color:#1d2327; }
		p { margin:0 0 16px; line-height:1.6; }
		table.data-table { width:100%; border-collapse:collapse; margin:18px 0; }
		table.data-table th, table.data-table td { padding:10px 12px; text-align:left; border-bottom:1px solid #f1d5e7; font-size:14px; }
		table.data-table th { width:36%; font-weight:600; color:#1d2327; }
		.error-box { background:#fef2f2; border:1px solid #fca5a5; border-radius:6px; padding:14px 16px; margin:18px 0; color:#991b1b; }
		.btn { display:inline-block; padding:12px 24px; background-color:#1f2937; color:#ffffff !important; text-decoration:none; border-radius:4px; font-weight:600; margin-top:12px; }
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
							<?php echo esc_html__( 'Registration attempt failed', 'smoketree-plugin' ); ?>
						</td>
					</tr>
					<tr>
						<td class="email-body">
							<h1><?php echo esc_html__( 'A prospective member needs attention', 'smoketree-plugin' ); ?></h1>
							<p><?php echo esc_html__( 'Someone attempted to register but did not complete the process successfully. Please follow up so we can help them finish their membership.', 'smoketree-plugin' ); ?></p>

							<table role="presentation" class="data-table">
								<tbody>
									<tr>
										<th><?php echo esc_html__( 'Name on form', 'smoketree-plugin' ); ?></th>
										<td><?php echo esc_html( $first_last ); ?></td>
									</tr>
									<tr>
										<th><?php echo esc_html__( 'Email', 'smoketree-plugin' ); ?></th>
										<td><?php echo esc_html( $email ?? '' ); ?></td>
									</tr>
									<?php if ( ! empty( $payment_label ) ) : ?>
										<tr>
											<th><?php echo esc_html__( 'Chosen payment type', 'smoketree-plugin' ); ?></th>
											<td><?php echo esc_html( ucfirst( $payment_label ) ); ?></td>
										</tr>
									<?php endif; ?>
								</tbody>
							</table>

							<?php if ( ! empty( $error_details ) ) : ?>
								<div class="error-box">
									<strong><?php echo esc_html__( 'System message:', 'smoketree-plugin' ); ?></strong>
									<p style="margin:8px 0 0; line-height:1.6;"><?php echo esc_html( $error_details ); ?></p>
								</div>
							<?php endif; ?>

							<p><?php echo esc_html__( 'Consider contacting the member to help them retry or to collect payment manually if the attempt timed out.', 'smoketree-plugin' ); ?></p>

							<p style="text-align:center;">
								<a href="<?php echo esc_url( $admin_members ); ?>" class="btn"><?php echo esc_html__( 'Open registrations dashboard', 'smoketree-plugin' ); ?></a>
							</p>
						</td>
					</tr>
					<tr>
						<td class="email-footer">
							<?php echo esc_html( $club_name ); ?> · <?php echo esc_html( $admin_members ); ?><br>
							<?php echo esc_html__( 'Automated alert for Smoketree administrators.', 'smoketree-plugin' ); ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</div>
</body>
</html>
<?php
/**
 * Notify Admins of Failed Registration Email Template
 *
 * Flags unsuccessful registrations so admins can follow up.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/templates
 */

// Template variables available:
// - $first_name
// - $last_name
// - $email
// - $error_message
// - $payment_type
?>

