<?php
/**
 * Thank You Cash Email Template
 *
 * Confirms cash registrations and informs member a board rep will reach out.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/templates
 */

// Template variables available:
// - $first_name
// - $last_name
// - $email
// - $amount_due
?>
<?php
$club_name     = 'Smoketree Swim and Recreation Club';
$portal_url    = home_url( '/member-portal/' );
$contact_email = function_exists( 'get_field' ) ? get_field( 'stsrc_contact_email', 'option' ) : get_option( 'stsrc_contact_email', '' );
$amount_label  = $amount_due ?? '';
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html__( 'Thank you for registering', 'smoketree-plugin' ); ?></title>
	<style>
		body { margin:0; padding:0; background-color:#f4f6f9; color:#1d2327; font-family:"Segoe UI", Arial, sans-serif; }
		.email-shell { width:100%; padding:24px 0; }
		.email-container { width:100%; max-width:600px; margin:0 auto; background-color:#ffffff; border-radius:8px; box-shadow:0 3px 12px rgba(0,0,0,0.08); overflow:hidden; }
		.email-header { background-color:#2271b1; padding:26px 24px; text-align:center; color:#ffffff; font-size:20px; font-weight:600; }
		.email-body { padding:32px 28px; }
		.email-footer { padding:24px 28px; background-color:#f1f3f6; font-size:12px; color:#555d66; text-align:center; }
		h1 { margin:0 0 18px; font-size:24px; color:#1d2327; }
		p { margin:0 0 16px; line-height:1.6; }
		.summary-card { background:#eef6ff; border:1px solid #c5dafa; border-radius:6px; padding:16px 18px; margin:18px 0; }
		.summary-card span { display:block; font-weight:600; color:#0f4a7b; }
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
							<h1><?php echo esc_html__( 'Welcome aboard!', 'smoketree-plugin' ); ?></h1>
							<p>
								<?php
								printf(
									/* translators: %s: First name */
									esc_html__( 'Hi %s,', 'smoketree-plugin' ),
									esc_html( $first_name ?? '' )
								);
								?>
							</p>
							<p><?php echo esc_html__( 'Thank you for registering! We have saved your registration. A member of our board will reach out to you shortly to arrange your cash payment.', 'smoketree-plugin' ); ?></p>

							<?php if ( ! empty( $amount_label ) ) : ?>
								<div class="summary-card">
									<p><?php echo esc_html__( 'Balance due', 'smoketree-plugin' ); ?></p>
									<span><?php echo esc_html( $amount_label ); ?></span>
								</div>
							<?php endif; ?>

							<p><?php echo esc_html__( 'Once payment is recorded we will activate your membership and send you a confirmation email. You can visit the member portal to check your status at any time.', 'smoketree-plugin' ); ?></p>

							<p style="text-align:center;">
								<a href="<?php echo esc_url( $portal_url ); ?>" class="btn"><?php echo esc_html__( 'Visit Member Portal', 'smoketree-plugin' ); ?></a>
							</p>

							<p><?php echo esc_html__( 'Questions? Reply to this email and our team will be happy to help.', 'smoketree-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<td class="email-footer">
							<?php echo esc_html( $club_name ); ?>
							<?php if ( ! empty( $contact_email ) ) : ?>
								· <a href="mailto:<?php echo esc_attr( $contact_email ); ?>" style="color:#555d66;"><?php echo esc_html( $contact_email ); ?></a>
							<?php endif; ?><br>
							<?php echo esc_html__( 'We can\'t wait to see you at the pool!', 'smoketree-plugin' ); ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</div>
</body>
</html>
