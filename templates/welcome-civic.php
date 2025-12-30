<?php
/**
 * Welcome Civic Email Template
 *
 * Tailored welcome message for civic-only members with voting information.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/templates
 */

// Template variables available:
// - $first_name
// - $last_name
// - $email
// - $voting_information
?>
<?php
$club_name           = 'Smoketree Swim and Recreation Club';
$portal_url          = home_url( '/member-portal/' );
$support_url         = home_url( '/contact/' );
$voting_details      = $voting_information ?? '';
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html__( 'Welcome Civic Member', 'smoketree-plugin' ); ?></title>
	<style>
		body { margin:0; padding:0; background-color:#f4f6f9; color:#1d2327; font-family:"Segoe UI", Arial, sans-serif; }
		.email-shell { width:100%; padding:24px 0; }
		.email-container { width:100%; max-width:600px; margin:0 auto; background-color:#ffffff; border-radius:8px; box-shadow:0 3px 12px rgba(0,0,0,0.08); overflow:hidden; }
		.email-header { background-color:#1f2937; padding:28px 24px; text-align:center; color:#ffffff; font-size:22px; font-weight:600; }
		.email-body { padding:32px 28px; }
		.email-footer { padding:24px 28px; background-color:#f1f3f6; font-size:12px; color:#555d66; text-align:center; }
		h1 { margin:0 0 18px; font-size:24px; color:#1d2327; }
		h2 { margin:24px 0 12px; font-size:18px; color:#1d2327; }
		p { margin:0 0 16px; line-height:1.6; }
		.info-box { background:#eef2ff; border:1px solid #c7d2fe; border-radius:6px; padding:16px 18px; margin:18px 0; color:#312e81; }
		.btn { display:inline-block; padding:12px 24px; background-color:#2271b1; color:#ffffff !important; text-decoration:none; border-radius:4px; font-weight:600; margin:12px 0; }
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
							<?php echo esc_html__( 'Thank you for representing our community', 'smoketree-plugin' ); ?>
						</td>
					</tr>
					<tr>
						<td class="email-body">
							<h1><?php echo esc_html__( 'Welcome, Civic Member!', 'smoketree-plugin' ); ?></h1>
							<p>
								<?php
								printf(
									/* translators: %s: First name */
									esc_html__( 'Hi %s,', 'smoketree-plugin' ),
									esc_html( $first_name ?? '' )
								);
								?>
							</p>
							<p><?php echo esc_html__( 'Thank you for joining Smoketree as a civic member. Your voice matters—civic members play a key role in shaping the direction of our club.', 'smoketree-plugin' ); ?></p>

							<div class="info-box">
								<strong><?php echo esc_html__( 'Voting privileges include:', 'smoketree-plugin' ); ?></strong>
								<ul style="margin:12px 0 0 18px; padding:0;">
									<li style="margin:0 0 8px;"><?php echo esc_html__( 'Participation in annual meetings and club elections', 'smoketree-plugin' ); ?></li>
									<li style="margin:0 0 8px;"><?php echo esc_html__( 'Ability to vote on major improvements and bylaw changes', 'smoketree-plugin' ); ?></li>
									<li style="margin:0;"><?php echo esc_html__( 'Opportunities to serve on committees and help guide the club’s future', 'smoketree-plugin' ); ?></li>
								</ul>
							</div>

							<?php if ( ! empty( $voting_details ) ) : ?>
								<p><?php echo esc_html( $voting_details ); ?></p>
							<?php endif; ?>

							<p><?php echo esc_html__( 'Meeting notices, ballots, and club updates will be delivered to your member portal and email. Please keep your contact information up to date so you never miss a vote.', 'smoketree-plugin' ); ?></p>

							<p style="text-align:center;">
								<a href="<?php echo esc_url( $portal_url ); ?>" class="btn"><?php echo esc_html__( 'Review Civic Resources', 'smoketree-plugin' ); ?></a>
							</p>

							<p><?php echo esc_html__( 'We appreciate your commitment to the Smoketree community and look forward to collaborating with you on future initiatives.', 'smoketree-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<td class="email-footer">
							<?php echo esc_html( $club_name ); ?> · <?php echo esc_html( $support_url ); ?><br>
							<?php echo esc_html__( 'Reach out any time if you have questions or ideas to share.', 'smoketree-plugin' ); ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</div>
</body>
</html>

