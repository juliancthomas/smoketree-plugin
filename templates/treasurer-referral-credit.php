<?php
/**
 * Treasurer referral credit notification template.
 *
 * Notifies treasurer when a referral credit is due.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/templates
 */

// Template variables:
// - $referrer_name
// - $referrer_email
// - $new_member_name
// - $new_member_email
// - $credit_amount
// - $registration_date

$club_name         = 'Smoketree Swim and Recreation Club';
$referrals_admin   = admin_url( 'admin.php?page=stsrc-promo-codes&tab=referrals' );
$formatted_amount  = '$' . number_format( (float) ( $credit_amount ?? 0 ), 2 );
$formatted_date    = ! empty( $registration_date )
	? date_i18n( get_option( 'date_format' ), strtotime( (string) $registration_date ) )
	: date_i18n( get_option( 'date_format' ) );
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html__( 'Referral credit due', 'smoketree-plugin' ); ?></title>
	<style>
		body { margin:0; padding:0; background-color:#f5f7fb; color:#1d2327; font-family:"Segoe UI", Arial, sans-serif; }
		.email-shell { width:100%; padding:24px 0; }
		.email-container { width:100%; max-width:620px; margin:0 auto; background-color:#ffffff; border-radius:8px; box-shadow:0 3px 12px rgba(0,0,0,0.08); overflow:hidden; }
		.email-header { background-color:#14532d; padding:26px 24px; text-align:center; color:#ffffff; font-size:20px; font-weight:600; }
		.email-body { padding:32px 28px; }
		.email-footer { padding:20px 24px; background-color:#f3f4f6; font-size:12px; color:#555d66; text-align:center; }
		h1 { margin:0 0 18px; font-size:22px; color:#1d2327; }
		p { margin:0 0 16px; line-height:1.6; }
		table.data-table { width:100%; border-collapse:collapse; margin:18px 0; }
		table.data-table th, table.data-table td { padding:10px 12px; text-align:left; border-bottom:1px solid #e5e7eb; font-size:14px; }
		table.data-table th { width:38%; font-weight:600; color:#1d2327; }
		.notice { background:#fef9c3; border:1px solid #fcd34d; border-radius:6px; padding:14px 16px; margin:18px 0; color:#713f12; }
		.btn { display:inline-block; padding:12px 24px; background-color:#2271b1; color:#ffffff !important; text-decoration:none; border-radius:4px; font-weight:600; margin-top:12px; }
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
							<?php echo esc_html__( 'Referral credit due', 'smoketree-plugin' ); ?>
						</td>
					</tr>
					<tr>
						<td class="email-body">
							<h1><?php echo esc_html__( 'A referral payout needs review', 'smoketree-plugin' ); ?></h1>
							<p><?php echo esc_html__( 'A new member used a referral code and created a credit owed to the referrer. Details are below for manual payout processing.', 'smoketree-plugin' ); ?></p>

							<table role="presentation" class="data-table">
								<tbody>
									<tr>
										<th><?php echo esc_html__( 'Referrer', 'smoketree-plugin' ); ?></th>
										<td><?php echo esc_html( (string) ( $referrer_name ?? '' ) ); ?></td>
									</tr>
									<tr>
										<th><?php echo esc_html__( 'Referrer email', 'smoketree-plugin' ); ?></th>
										<td><?php echo esc_html( (string) ( $referrer_email ?? '' ) ); ?></td>
									</tr>
									<tr>
										<th><?php echo esc_html__( 'New member', 'smoketree-plugin' ); ?></th>
										<td><?php echo esc_html( (string) ( $new_member_name ?? '' ) ); ?></td>
									</tr>
									<tr>
										<th><?php echo esc_html__( 'New member email', 'smoketree-plugin' ); ?></th>
										<td><?php echo esc_html( (string) ( $new_member_email ?? '' ) ); ?></td>
									</tr>
									<tr>
										<th><?php echo esc_html__( 'Credit amount owed', 'smoketree-plugin' ); ?></th>
										<td><?php echo esc_html( $formatted_amount ); ?></td>
									</tr>
									<tr>
										<th><?php echo esc_html__( 'Registration date', 'smoketree-plugin' ); ?></th>
										<td><?php echo esc_html( $formatted_date ); ?></td>
									</tr>
								</tbody>
							</table>

							<div class="notice">
								<?php echo esc_html__( 'No automatic balance adjustment is made by the system. Please issue this credit manually and mark payout status in the Referral Report when completed.', 'smoketree-plugin' ); ?>
							</div>

							<p style="text-align:center;">
								<a href="<?php echo esc_url( $referrals_admin ); ?>" class="btn"><?php echo esc_html__( 'Open Referral Report', 'smoketree-plugin' ); ?></a>
							</p>
						</td>
					</tr>
					<tr>
						<td class="email-footer">
							<?php echo esc_html( $club_name ); ?> · <?php echo esc_html( $referrals_admin ); ?><br>
							<?php echo esc_html__( 'Automated treasurer notification for referral credits.', 'smoketree-plugin' ); ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</div>
</body>
</html>
