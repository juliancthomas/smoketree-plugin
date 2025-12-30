<?php
/**
 * Welcome Email Template
 *
 * Full welcome package for standard memberships, including quick links and upcoming events.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/templates
 */

// Template variables available:
// - $first_name
// - $last_name
// - $email
// - $membership_type
// - $member (STSRC_Member object, if available)
?>
<?php
$club_name         = 'Smoketree Swim and Recreation Club';
$portal_url        = home_url( '/member-portal/' );
$guest_pass_url    = home_url( '/guest-pass-portal/' );
$support_url       = home_url( '/contact/' );
$membership_label  = $membership_type ?? '';
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html__( 'Welcome to Smoketree', 'smoketree-plugin' ); ?></title>
	<style>
		body { margin:0; padding:0; background-color:#f4f6f9; color:#1d2327; font-family:"Segoe UI", Arial, sans-serif; }
		.email-shell { width:100%; padding:24px 0; }
		.email-container { width:100%; max-width:600px; margin:0 auto; background-color:#ffffff; border-radius:8px; box-shadow:0 3px 12px rgba(0,0,0,0.08); overflow:hidden; }
		.email-header { background-color:#2271b1; padding:28px 24px; text-align:center; color:#ffffff; font-size:22px; font-weight:600; }
		.email-body { padding:32px 28px; }
		.email-footer { padding:24px 28px; background-color:#f1f3f6; font-size:12px; color:#555d66; text-align:center; }
		h1 { margin:0 0 18px; font-size:24px; color:#1d2327; }
		h2 { margin:24px 0 12px; font-size:18px; color:#1d2327; }
		p { margin:0 0 16px; line-height:1.6; }
		.btn { display:inline-block; padding:12px 24px; background-color:#2271b1; color:#ffffff !important; text-decoration:none; border-radius:4px; font-weight:600; margin:12px 0; }
		ul { margin:0 0 16px 18px; padding:0; }
		ul li { margin:0 0 10px; line-height:1.6; }
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
							<?php echo esc_html__( 'Welcome to the Smoketree family!', 'smoketree-plugin' ); ?>
						</td>
					</tr>
					<tr>
						<td class="email-body">
							<h1><?php echo esc_html__( 'We\'re so glad you\'re here', 'smoketree-plugin' ); ?></h1>
							<p>
								<?php
								printf(
									/* translators: %s: First name */
									esc_html__( 'Hi %s,', 'smoketree-plugin' ),
									esc_html( $first_name ?? '' )
								);
								?>
							</p>
							<p><?php echo esc_html__( 'Welcome to Smoketree Swim and Recreation Club! Your membership is active and your summer of pool days, socials, and community fun starts now.', 'smoketree-plugin' ); ?></p>

							<?php if ( ! empty( $membership_label ) ) : ?>
								<p>
									<strong><?php echo esc_html__( 'Membership Type:', 'smoketree-plugin' ); ?></strong>
									<?php echo esc_html( $membership_label ); ?>
								</p>
							<?php endif; ?>

							<h2><?php echo esc_html__( 'Here’s what to do next', 'smoketree-plugin' ); ?></h2>
							<ul>
								<li><?php echo esc_html__( 'Log into the member portal to update your profile, add family members, and manage guest passes.', 'smoketree-plugin' ); ?></li>
								<li><?php echo esc_html__( 'Review the calendar of events and important club dates posted on the portal dashboard.', 'smoketree-plugin' ); ?></li>
								<li><?php echo esc_html__( 'Invite friends! Guest passes can be purchased anytime and are instantly added to your account.', 'smoketree-plugin' ); ?></li>
							</ul>

							<p style="text-align:center;">
								<a href="<?php echo esc_url( $portal_url ); ?>" class="btn"><?php echo esc_html__( 'Open Member Portal', 'smoketree-plugin' ); ?></a>
							</p>

							<h2><?php echo esc_html__( 'Membership perks at a glance', 'smoketree-plugin' ); ?></h2>
							<ul>
								<li><?php echo esc_html__( 'Unlimited access to the pool, lakefront, and playground', 'smoketree-plugin' ); ?></li>
								<li><?php echo esc_html__( 'Club socials, swim events, and family nights all season long', 'smoketree-plugin' ); ?></li>
								<li><?php echo esc_html__( 'Easy guest pass sharing with friends and family', 'smoketree-plugin' ); ?></li>
							</ul>

							<p><?php echo esc_html__( 'If you need anything, just reply to this email or reach out through the portal. Our team is here to help you make the most of your membership.', 'smoketree-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<td class="email-footer">
							<?php echo esc_html( $club_name ); ?> · <?php echo esc_html( $support_url ); ?><br>
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

