<?php
$club_name         = 'Smoketree Swim and Recreation Club';
$guest_pass_portal = home_url( '/guest-pass-portal/' );
$portal_url        = home_url( '/member-portal/' );
$support_url       = home_url( '/contact/' );
$quantity_label    = $quantity ?? '';
$amount_label      = $amount ?? '';
$balance_label     = $total_balance ?? '';
$instructions_text = $usage_instructions ?? '';
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html__( 'Guest Pass Purchase Confirmation', 'smoketree-plugin' ); ?></title>
	<style>
		body { margin:0; padding:0; background-color:#f4f6f9; color:#1d2327; font-family:"Segoe UI", Arial, sans-serif; }
		.email-shell { width:100%; padding:24px 0; }
		.email-container { width:100%; max-width:600px; margin:0 auto; background-color:#ffffff; border-radius:8px; box-shadow:0 3px 12px rgba(0,0,0,0.08); overflow:hidden; }
		.email-header { background-color:#2563eb; padding:28px 24px; text-align:center; color:#ffffff; font-size:22px; font-weight:600; }
		.email-body { padding:32px 28px; }
		.email-footer { padding:24px 28px; background-color:#f1f3f6; font-size:12px; color:#555d66; text-align:center; }
		h1 { margin:0 0 18px; font-size:24px; color:#1d2327; }
		h2 { margin:22px 0 12px; font-size:18px; color:#1d2327; }
		p { margin:0 0 16px; line-height:1.6; }
		table.data-table { width:100%; border-collapse:collapse; margin:18px 0; }
		table.data-table th, table.data-table td { padding:10px 12px; text-align:left; border-bottom:1px solid #e5e7eb; font-size:14px; }
		table.data-table th { width:40%; font-weight:600; color:#1d2327; }
		.instructions { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:15px 18px; margin:18px 0; color:#166534; }
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
							<?php echo esc_html__( 'Your guest passes are ready!', 'smoketree-plugin' ); ?>
						</td>
					</tr>
					<tr>
						<td class="email-body">
							<h1><?php echo esc_html__( 'Thanks for grabbing extra passes', 'smoketree-plugin' ); ?></h1>
							<p>
								<?php
								printf(
									/* translators: %s: First name */
									esc_html__( 'Hi %s,', 'smoketree-plugin' ),
									esc_html( $first_name ?? '' )
								);
								?>
							</p>
							<p><?php echo esc_html__( 'Your guest passes have been added to your account and are ready to be used at the gate.', 'smoketree-plugin' ); ?></p>

							<table role="presentation" class="data-table">
								<tbody>
									<?php if ( ! empty( $quantity_label ) ) : ?>
										<tr>
											<th><?php echo esc_html__( 'Quantity purchased', 'smoketree-plugin' ); ?></th>
											<td><?php echo esc_html( $quantity_label ); ?></td>
										</tr>
									<?php endif; ?>
									<?php if ( ! empty( $amount_label ) ) : ?>
										<tr>
											<th><?php echo esc_html__( 'Total charged', 'smoketree-plugin' ); ?></th>
											<td><?php echo esc_html( $amount_label ); ?></td>
										</tr>
									<?php endif; ?>
									<?php if ( ! empty( $balance_label ) ) : ?>
										<tr>
											<th><?php echo esc_html__( 'Guest pass balance', 'smoketree-plugin' ); ?></th>
											<td><?php echo esc_html( $balance_label ); ?></td>
										</tr>
									<?php endif; ?>
								</tbody>
							</table>

							<?php if ( ! empty( $instructions_text ) ) : ?>
								<div class="instructions">
									<strong><?php echo esc_html__( 'How to use your passes', 'smoketree-plugin' ); ?></strong>
									<p style="margin:12px 0 0; line-height:1.6;"><?php echo esc_html( $instructions_text ); ?></p>
								</div>
							<?php endif; ?>

							<p><?php echo esc_html__( 'You can keep an eye on your remaining balance and share your guest link right from the portal.', 'smoketree-plugin' ); ?></p>

							<p style="text-align:center;">
								<a href="<?php echo esc_url( $guest_pass_portal ); ?>" class="btn"><?php echo esc_html__( 'View Guest Pass Balance', 'smoketree-plugin' ); ?></a>
							</p>

							<p><?php echo esc_html__( 'Need to purchase more or have questions about how passes work? Our team is just a reply away.', 'smoketree-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<td class="email-footer">
							<?php echo esc_html( $club_name ); ?> · <?php echo esc_html( $support_url ); ?><br>
							<?php echo esc_html__( 'We can’t wait to welcome your guests!', 'smoketree-plugin' ); ?>
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
 * Guest Pass Purchase Email Template
 *
 * Sends members a receipt and usage instructions after buying guest passes.
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
// - $total_balance
// - $usage_instructions
?>

