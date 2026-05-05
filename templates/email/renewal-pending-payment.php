<?php
/**
 * Member renewal pending payment email template.
 *
 * Sent to the member immediately after submitting a non-Stripe renewal
 * so they have a permanent reference for payment instructions.
 *
 * @package Smoketree_Plugin
 */

$member_name         = trim( (string) ( $first_name ?? '' ) . ' ' . (string) ( $last_name ?? '' ) );
$membership_label    = (string) ( $membership_type_name ?? '' );
$season_key_value    = (string) ( $season_key ?? '' );
$payment_method_key  = (string) ( $payment_method ?? '' );
$payment_method_text = (string) ( $payment_method_label ?? '' );
$total_text          = '$' . number_format( (float) ( $total_amount ?? 0 ), 2 );
$portal_url          = home_url( '/member-portal/' );
$acf_instructions    = (string) ( $payment_instructions ?? '' );
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html__( 'Renewal Submitted - Payment Instructions', 'smoketree-plugin' ); ?></title>
</head>
<body style="font-family: Arial, sans-serif; color: #1d2327; background: #f6f7f7; padding: 20px;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 620px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden;">
		<tr><td style="padding: 20px 24px; background: #1f4d3a; color: #ffffff;"><strong><?php echo esc_html__( 'Renewal Submitted', 'smoketree-plugin' ); ?></strong></td></tr>
		<tr>
			<td style="padding: 24px;">
				<p><?php echo esc_html( sprintf( __( 'Hi %s,', 'smoketree-plugin' ), $member_name ) ); ?></p>
				<p><?php echo esc_html__( 'Thank you for submitting your Smoketree membership renewal! Your membership will be activated once payment is received and confirmed.', 'smoketree-plugin' ); ?></p>

				<p><strong><?php echo esc_html__( 'Season:', 'smoketree-plugin' ); ?></strong> <?php echo esc_html( $season_key_value ); ?></p>
				<p><strong><?php echo esc_html__( 'Membership Type:', 'smoketree-plugin' ); ?></strong> <?php echo esc_html( $membership_label ); ?></p>
				<p><strong><?php echo esc_html__( 'Payment Method:', 'smoketree-plugin' ); ?></strong> <?php echo esc_html( $payment_method_text ); ?></p>
				<p><strong><?php echo esc_html__( 'Amount Due:', 'smoketree-plugin' ); ?></strong> <?php echo esc_html( $total_text ); ?></p>

				<div style="margin: 20px 0; padding: 16px; border-left: 4px solid #2271b1; background: #f0f6fc; border-radius: 0 4px 4px 0;">
					<strong style="display: block; margin-bottom: 10px; font-size: 15px;"><?php echo esc_html__( 'Payment Instructions', 'smoketree-plugin' ); ?></strong>

					<?php if ( ! empty( $acf_instructions ) ) : ?>
						<?php echo wp_kses_post( $acf_instructions ); ?>
					<?php endif; ?>
				</div>

				<p style="margin-top: 18px;">
					<a href="<?php echo esc_url( $portal_url ); ?>" style="display:inline-block;padding:10px 16px;background:#2271b1;color:#fff;text-decoration:none;border-radius:4px;">
						<?php echo esc_html__( 'Open Member Portal', 'smoketree-plugin' ); ?>
					</a>
				</p>

				<p style="margin-top: 16px; font-size: 13px; color: #646970;">
					<?php echo esc_html__( 'If you have any questions, please contact us and we will be happy to help.', 'smoketree-plugin' ); ?>
				</p>
			</td>
		</tr>
	</table>
</body>
</html>
