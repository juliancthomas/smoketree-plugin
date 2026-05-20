<?php
/**
 * Registration Account Created Email Template
 *
 * Sent immediately when a Stripe-payment registration creates the member account,
 * before the user completes checkout. Gives them a permanent login link in case
 * they abandon Stripe and need to return to pay their balance.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/templates
 *
 * Template variables:
 * - $first_name
 * - $last_name
 * - $email
 */

$club_name   = 'Smoketree Swim and Recreation Club';
$portal_url  = home_url( '/member-portal/' );
$member_name = trim( ( $first_name ?? '' ) . ' ' . ( $last_name ?? '' ) );
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html__( 'Your Smoketree account has been created', 'smoketree-plugin' ); ?></title>
	<style>
		body { margin:0; padding:0; background-color:#f4f6f9; color:#1d2327; font-family:"Segoe UI", Arial, sans-serif; }
		.email-shell { width:100%; padding:24px 0; }
		.email-container { width:100%; max-width:600px; margin:0 auto; background-color:#ffffff; border-radius:8px; box-shadow:0 3px 12px rgba(0,0,0,0.08); overflow:hidden; }
		.email-header { background-color:#1f4d3a; padding:28px 24px; text-align:center; color:#ffffff; font-size:22px; font-weight:600; }
		.email-body { padding:32px 28px; }
		.email-footer { padding:24px 28px; background-color:#f1f3f6; font-size:12px; color:#555d66; text-align:center; }
		h1 { margin:0 0 18px; font-size:22px; color:#1d2327; }
		p { margin:0 0 16px; line-height:1.6; }
		.btn { display:inline-block; padding:12px 24px; background-color:#2271b1; color:#ffffff !important; text-decoration:none; border-radius:4px; font-weight:600; margin:12px 0; }
		.info-box { margin:20px 0; padding:16px; border-left:4px solid #1f4d3a; background:#f0f6f3; border-radius:0 4px 4px 0; }
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
							<?php echo esc_html__( 'Your account has been created!', 'smoketree-plugin' ); ?>
						</td>
					</tr>
					<tr>
						<td class="email-body">
							<h1>
								<?php
								printf(
									/* translators: %s: First name */
									esc_html__( 'Hi %s,', 'smoketree-plugin' ),
									esc_html( $first_name ?? '' )
								);
								?>
							</h1>
							<p><?php echo esc_html__( 'Your Smoketree Swim and Recreation Club account has been created. You\'re being redirected to complete your payment — once that\'s done, your membership will be activated.', 'smoketree-plugin' ); ?></p>

							<div class="info-box">
								<p style="margin:0;"><strong><?php echo esc_html__( 'Account email:', 'smoketree-plugin' ); ?></strong> <?php echo esc_html( $email ?? '' ); ?></p>
							</div>

							<p><?php echo esc_html__( 'If you got interrupted before finishing payment, you can log in to the member portal at any time to complete it.', 'smoketree-plugin' ); ?></p>

							<p style="text-align:center;">
								<a href="<?php echo esc_url( $portal_url ); ?>" class="btn">
									<?php echo esc_html__( 'Go to Member Portal', 'smoketree-plugin' ); ?>
								</a>
							</p>

							<p style="font-size:13px; color:#646970;">
								<?php echo esc_html__( 'If you have any questions, please contact us and we\'ll be happy to help.', 'smoketree-plugin' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<td class="email-footer">
							<?php echo esc_html( $club_name ); ?><br>
							<?php echo esc_html__( 'See you at the club!', 'smoketree-plugin' ); ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</div>
</body>
</html>
