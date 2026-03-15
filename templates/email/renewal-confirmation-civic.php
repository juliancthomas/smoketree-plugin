<?php
/**
 * Civic renewal confirmation email template.
 *
 * @package Smoketree_Plugin
 */

$member_name         = trim( (string) ( $first_name ?? '' ) . ' ' . (string) ( $last_name ?? '' ) );
$season_key_value    = (string) ( $season_key ?? '' );
$payment_method_text = (string) ( $payment_method_label ?? '' );
$total_text          = '$' . number_format( (float) ( $total_amount ?? 0 ), 2 );
$portal_url          = home_url( '/member-portal/' );
$payment_instructions = (string) ( $payment_instructions ?? '' );
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html__( 'Civic Renewal Confirmation', 'smoketree-plugin' ); ?></title>
</head>
<body style="font-family: Arial, sans-serif; color: #1d2327; background: #f6f7f7; padding: 20px;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 620px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden;">
		<tr><td style="padding: 20px 24px; background: #2e7d5b; color: #ffffff;"><strong><?php echo esc_html__( 'Civic Membership Renewal Confirmed', 'smoketree-plugin' ); ?></strong></td></tr>
		<tr>
			<td style="padding: 24px;">
				<p><?php echo esc_html( sprintf( __( 'Hi %s,', 'smoketree-plugin' ), $member_name ) ); ?></p>
				<p><?php echo esc_html__( 'Your civic membership renewal has been recorded.', 'smoketree-plugin' ); ?></p>
				<p><strong><?php echo esc_html__( 'Season:', 'smoketree-plugin' ); ?></strong> <?php echo esc_html( $season_key_value ); ?></p>
				<p><strong><?php echo esc_html__( 'Payment Method:', 'smoketree-plugin' ); ?></strong> <?php echo esc_html( $payment_method_text ); ?></p>
				<p><strong><?php echo esc_html__( 'Total:', 'smoketree-plugin' ); ?></strong> <?php echo esc_html( $total_text ); ?></p>
				<?php if ( ! empty( $payment_instructions ) ) : ?>
					<div style="margin-top: 12px; padding: 12px; border-left: 4px solid #2271b1; background: #f0f6fc;">
						<?php echo wp_kses_post( $payment_instructions ); ?>
					</div>
				<?php endif; ?>
				<p style="margin-top: 18px;">
					<a href="<?php echo esc_url( $portal_url ); ?>" style="display:inline-block;padding:10px 16px;background:#2271b1;color:#fff;text-decoration:none;border-radius:4px;">
						<?php echo esc_html__( 'Open Member Portal', 'smoketree-plugin' ); ?>
					</a>
				</p>
			</td>
		</tr>
	</table>
</body>
</html>

