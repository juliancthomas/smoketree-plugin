<?php
$club_name        = 'Smoketree Swim and Recreation Club';
$admin_members    = admin_url( 'admin.php?page=stsrc-members' );
$member_admin_url = $admin_members;
$quantity_label   = $quantity ?? '';
$amount_label     = $amount ?? '';

if ( isset( $member ) && is_object( $member ) && ! empty( $member->member_id ) ) {
	$member_admin_url = add_query_arg(
		array(
			'action'    => 'view',
			'member_id' => (int) $member->member_id,
		),
		$admin_members
	);
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html__( 'Guest pass purchase alert', 'smoketree-plugin' ); ?></title>
	<style>
		body { margin:0; padding:0; background-color:#f5f7fb; color:#1d2327; font-family:"Segoe UI", Arial, sans-serif; }
		.email-shell { width:100%; padding:24px 0; }
		.email-container { width:100%; max-width:620px; margin:0 auto; background-color:#ffffff; border-radius:8px; box-shadow:0 3px 12px rgba(0,0,0,0.08); overflow:hidden; }
		.email-header { background-color:#0f766e; padding:26px 24px; text-align:center; color:#ffffff; font-size:20px; font-weight:600; }
		.email-body { padding:32px 28px; }
		.email-footer { padding:20px 24px; background-color:#f3f4f6; font-size:12px; color:#555d66; text-align:center; }
		h1 { margin:0 0 18px; font-size:22px; color:#1d2327; }
		p { margin:0 0 16px; line-height:1.6; }
		table.data-table { width:100%; border-collapse:collapse; margin:18px 0; }
		table.data-table th, table.data-table td { padding:10px 12px; text-align:left; border-bottom:1px solid #e5e7eb; font-size:14px; }
		table.data-table th { width:36%; font-weight:600; color:#1d2327; }
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
							<?php echo esc_html__( 'Guest pass purchase recorded', 'smoketree-plugin' ); ?>
						</td>
					</tr>
					<tr>
						<td class="email-body">
							<h1><?php echo esc_html__( 'A member just purchased additional guest passes', 'smoketree-plugin' ); ?></h1>
							<p><?php echo esc_html__( 'Here are the details so you can keep inventory and billing aligned.', 'smoketree-plugin' ); ?></p>

							<table role="presentation" class="data-table">
								<tbody>
									<tr>
										<th><?php echo esc_html__( 'Member', 'smoketree-plugin' ); ?></th>
										<td><?php echo esc_html( trim( ( $first_name ?? '' ) . ' ' . ( $last_name ?? '' ) ) ); ?></td>
									</tr>
									<tr>
										<th><?php echo esc_html__( 'Email', 'smoketree-plugin' ); ?></th>
										<td><?php echo esc_html( $email ?? '' ); ?></td>
									</tr>
									<?php if ( ! empty( $quantity_label ) ) : ?>
										<tr>
											<th><?php echo esc_html__( 'Quantity purchased', 'smoketree-plugin' ); ?></th>
											<td><?php echo esc_html( $quantity_label ); ?></td>
										</tr>
									<?php endif; ?>
									<?php if ( ! empty( $amount_label ) ) : ?>
										<tr>
											<th><?php echo esc_html__( 'Total amount', 'smoketree-plugin' ); ?></th>
											<td><?php echo esc_html( $amount_label ); ?></td>
										</tr>
									<?php endif; ?>
								</tbody>
							</table>

							<p><?php echo esc_html__( 'The balance has been added to their account automatically. If you need to make manual adjustments, open the member record below.', 'smoketree-plugin' ); ?></p>

							<p style="text-align:center;">
								<a href="<?php echo esc_url( $member_admin_url ); ?>" class="btn"><?php echo esc_html__( 'View member in admin', 'smoketree-plugin' ); ?></a>
							</p>
						</td>
					</tr>
					<tr>
						<td class="email-footer">
							<?php echo esc_html( $club_name ); ?> · <?php echo esc_html( $admin_members ); ?><br>
							<?php echo esc_html__( 'Automated notification for guest pass purchases.', 'smoketree-plugin' ); ?>
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
 * Notify Admin of Guest Pass Email Template
 *
 * Lets staff know when guest passes are purchased.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/templates
 */

// Template variables available:
// - $first_name
// - $last_name
// - $email
// - $quantity
// - $amount
// - $member (STSRC_Member object, if available)
?>

