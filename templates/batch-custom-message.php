<?php
/**
 * Batch Custom Message Email Template
 *
 * Generic wrapper for custom messages composed in the batch email composer.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/templates
 */

// Template variables available:
// - $first_name
// - $last_name
// - $email
// - $message  (HTML body composed in the WYSIWYG editor)
?>
<?php
$club_name     = 'Smoketree Swim and Recreation Club';
$contact_email = function_exists( 'get_field' ) ? get_field( 'stsrc_contact_email', 'option' ) : get_option( 'stsrc_contact_email', '' );
$body          = $message ?? '';
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $club_name ); ?></title>
	<style>
		body { margin:0; padding:0; background-color:#f4f6f9; color:#1d2327; font-family:"Segoe UI", Arial, sans-serif; }
		.email-shell { width:100%; padding:24px 0; }
		.email-container { width:100%; max-width:600px; margin:0 auto; background-color:#ffffff; border-radius:8px; box-shadow:0 3px 12px rgba(0,0,0,0.08); overflow:hidden; }
		.email-header { background-color:#2271b1; padding:28px 24px; text-align:center; color:#ffffff; font-size:22px; font-weight:600; }
		.email-body { padding:32px 28px; }
		.email-body p { margin:0 0 16px; line-height:1.6; }
		.email-body h1, .email-body h2, .email-body h3 { color:#1d2327; }
		.email-body a { color:#2271b1; }
		.email-footer { padding:24px 28px; background-color:#f1f3f6; font-size:12px; color:#555d66; text-align:center; }
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
							<?php echo wp_kses_post( $body ); ?>
						</td>
					</tr>
					<tr>
						<td class="email-footer">
							<?php echo esc_html( $club_name ); ?>
							<?php if ( ! empty( $contact_email ) ) : ?>
								· <a href="mailto:<?php echo esc_attr( $contact_email ); ?>" style="color:#555d66;"><?php echo esc_html( $contact_email ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</div>
</body>
</html>
