<?php
/**
 * Password Reset Email Template
 *
 * Delivers the secure reset link when a member requests a new password.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/templates
 */

// Template variables available:
// - $first_name
// - $last_name
// - $email
// - $reset_link
// - $expiration_time
?>
<?php
$club_name   = 'Smoketree Swim and Recreation Club';
$support_url = home_url( '/contact/' );
$reset_url   = $reset_link ?? '';
$expiration_label = $expiration_time ?? esc_html__( '1 hour', 'smoketree-plugin' );
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html__( 'Password Reset Request', 'smoketree-plugin' ); ?></title>
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
		.notice { background:#fef3c7; color:#92400e; padding:12px 16px; border-radius:4px; margin:16px 0; font-size:14px; }
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
							<h1><?php echo esc_html__( 'Reset your password', 'smoketree-plugin' ); ?></h1>
							<p>
								<?php
								printf(
									/* translators: %s: First name */
									esc_html__( 'Hi %s,', 'smoketree-plugin' ),
									esc_html( $first_name ?? '' )
								);
								?>
							</p>
							<p><?php echo esc_html__( 'We received a request to reset the password for your Smoketree member account.', 'smoketree-plugin' ); ?></p>

							<?php if ( ! empty( $reset_url ) ) : ?>
								<p style="text-align:center;">
									<a href="<?php echo esc_url( $reset_url ); ?>" class="btn"><?php echo esc_html__( 'Create a New Password', 'smoketree-plugin' ); ?></a>
								</p>
								<p><?php echo esc_html__( 'For security, this reset link will expire in the time frame noted below. If it expires, you can request a fresh link from the login screen.', 'smoketree-plugin' ); ?></p>
								<p class="notice">
									<?php
									printf(
										/* translators: %s: Expiration time description */
										esc_html__( 'This link expires in %s.', 'smoketree-plugin' ),
										esc_html( $expiration_label )
									);
									?>
								</p>
							<?php else : ?>
								<p><?php echo esc_html__( 'The secure reset link is unavailable. Please try requesting another reset from the login screen.', 'smoketree-plugin' ); ?></p>
							<?php endif; ?>

							<p><?php echo esc_html__( 'If you did not request a password reset, you can safely ignore this email—your existing password will continue to work.', 'smoketree-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<td class="email-footer">
							<?php echo esc_html( $club_name ); ?> · <?php echo esc_html( $support_url ); ?><br>
							<?php echo esc_html__( 'Need help? Let us know and we will be happy to assist.', 'smoketree-plugin' ); ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</div>
</body>
</html>

