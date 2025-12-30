<?php
$club_name        = 'Smoketree Swim and Recreation Club';
$admin_members    = admin_url( 'admin.php?page=stsrc-members' );
$member_admin_url = $admin_members;
$used_timestamp   = $used_at ?? '';
$notes_text       = $notes ?? '';
$is_adjustment    = ! empty( $admin_adjusted );

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
	<title><?php echo esc_html__( 'Guest pass usage recorded', 'smoketree-plugin' ); ?></title>
	<style>
		body { margin:0; padding:0; background-color:#f5f7fb; color:#1d2327; font-family:"Segoe UI", Arial, sans-serif; }
		.email-shell { width:100%; padding:24px 0; }
		.email-container { width:100%; max-width:620px; margin:0 auto; background-color:#ffffff; border-radius:8px; box-shadow:0 3px 12px rgba(0,0,0,0.08); overflow:hidden; }
		.email-header { background-color:#047857; padding:26px 24px; text-align:center; color:#ffffff; font-size:20px; font-weight:600; }
		.email-body { padding:32px 28px; }
		.email-footer { padding:20px 24px; background-color:#f3f4f6; font-size:12px; color:#555d66; text-align:center; }
		h1 { margin:0 0 18px; font-size:22px; color:#1d2327; }
		p { margin:0 0 16px; line-height:1.6; }
		table.data-table { width:100%; border-collapse:collapse; margin:18px 0; }
		table.data-table th, table.data-table td { padding:10px 12px; text-align:left; border-bottom:1px solid #e5e7eb; font-size:14px; }
		table.data-table th { width:36%; font-weight:600; color:#1d2327; }
		.adjustment-tag { display:inline-block; padding:6px 10px; border-radius:999px; background:#fef3c7; color:#92400e; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.4px; }
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
							<?php echo esc_html__( 'Guest pass usage logged', 'smoketree-plugin' ); ?>
						</td>
					</tr>
					<tr>
						<td class="email-body">
							<h1><?php echo esc_html__( 'A guest pass was redeemed', 'smoketree-plugin' ); ?></h1>
							<p><?php echo esc_html__( 'Here is a quick summary so staff can keep track of the membership balance and visit log.', 'smoketree-plugin' ); ?></p>

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
									<?php if ( ! empty( $used_timestamp ) ) : ?>
										<tr>
											<th><?php echo esc_html__( 'Used at', 'smoketree-plugin' ); ?></th>
											<td><?php echo esc_html( $used_timestamp ); ?></td>
										</tr>
									<?php endif; ?>
									<?php if ( ! empty( $notes_text ) ) : ?>
										<tr>
											<th><?php echo esc_html__( 'Notes', 'smoketree-plugin' ); ?></th>
											<td><?php echo esc_html( $notes_text ); ?></td>
										</tr>
									<?php endif; ?>
								</tbody>
							</table>

							<?php if ( $is_adjustment ) : ?>
								<p><span class="adjustment-tag"><?php echo esc_html__( 'Admin adjustment', 'smoketree-plugin' ); ?></span></p>
							<?php endif; ?>

							<p style="text-align:center;">
								<a href="<?php echo esc_url( $member_admin_url ); ?>" class="btn"><?php echo esc_html__( 'Review member history', 'smoketree-plugin' ); ?></a>
							</p>
						</td>
					</tr>
					<tr>
						<td class="email-footer">
							<?php echo esc_html( $club_name ); ?> · <?php echo esc_html( $admin_members ); ?><br>
							<?php echo esc_html__( 'Sent automatically to guest pass administrators.', 'smoketree-plugin' ); ?>
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
 * Notify Admin Guest Pass Was Used Email Template
 *
 * Logs each guest-pass redemption or admin adjustment for staff awareness.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/templates
 */

// Template variables available:
// - $first_name
// - $last_name
// - $email
// - $used_at
// - $notes
// - $admin_adjusted
?>

