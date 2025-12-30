<?php
/**
 * Subscription Update Email Template
 *
 * Alerts members whenever their subscription status changes (renewed, cancelled, etc.).
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/templates
 */

// Template variables available:
// - $first_name
// - $last_name
// - $email
// - $status
// - $member (STSRC_Member object, if available)
?>
<?php
$club_name   = 'Smoketree Swim and Recreation Club';
$portal_url  = home_url( '/member-portal/' );
$support_url = home_url( '/contact/' );
$status_label = $status ?? '';
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html__( 'Membership Status Update', 'smoketree-plugin' ); ?></title>
	<style>
		body { margin:0; padding:0; background-color:#f4f6f9; color:#1d2327; font-family:"Segoe UI", Arial, sans-serif; }
		.email-shell { width:100%; padding:24px 0; }
		.email-container { width:100%; max-width:600px; margin:0 auto; background-color:#ffffff; border-radius:8px; box-shadow:0 3px 12px rgba(0,0,0,0.08); overflow:hidden; }
		.email-header { background-color:#2271b1; padding:26px 24px; text-align:center; color:#ffffff; font-size:20px; font-weight:600; }
		.email-body { padding:32px 28px; }
		.email-footer { padding:24px 28px; background-color:#f1f3f6; font-size:12px; color:#555d66; text-align:center; }
		h1 { margin:0 0 18px; font-size:24px; color:#1d2327; }
		p { margin:0 0 16px; line-height:1.6; }
		.status-pill { display:inline-block; padding:6px 12px; border-radius:999px; background-color:#e0eefb; color:#135e96; font-weight:600; font-size:13px; letter-spacing:0.3px; text-transform:uppercase; }
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
							<h1><?php echo esc_html__( 'Your membership has been updated', 'smoketree-plugin' ); ?></h1>
							<p>
								<?php
								printf(
									/* translators: %s: First name */
									esc_html__( 'Hi %s,', 'smoketree-plugin' ),
									esc_html( $first_name ?? '' )
								);
								?>
							</p>
							<p><?php echo esc_html__( 'We wanted to let you know that the status of your Smoketree membership has recently changed.', 'smoketree-plugin' ); ?></p>

							<?php if ( ! empty( $status_label ) ) : ?>
								<p>
									<span class="status-pill"><?php echo esc_html( ucfirst( $status_label ) ); ?></span>
								</p>
							<?php endif; ?>

							<p><?php echo esc_html__( 'You can review the details of your membership, update your profile, and manage add-ons any time inside the member portal.', 'smoketree-plugin' ); ?></p>

							<p style="text-align:center;">
								<a href="<?php echo esc_url( $portal_url ); ?>" class="btn"><?php echo esc_html__( 'Open Member Portal', 'smoketree-plugin' ); ?></a>
							</p>

							<p><?php echo esc_html__( 'If this change looks unexpected, please contact us right away so we can double-check things for you.', 'smoketree-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<td class="email-footer">
							<?php echo esc_html( $club_name ); ?> · <?php echo esc_html( $support_url ); ?><br>
							<?php echo esc_html__( 'We look forward to seeing you soon at the club!', 'smoketree-plugin' ); ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</div>
</body>
</html>

