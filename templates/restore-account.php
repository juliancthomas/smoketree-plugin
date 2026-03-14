<?php
/**
 * Restore account email template.
 *
 * @package Smoketree_Plugin
 */
?>
<h2><?php echo esc_html__( 'Restore Your Smoketree Account', 'smoketree-plugin' ); ?></h2>

<p>
	<?php
	echo esc_html(
		sprintf(
			/* translators: %s: first name */
			__( 'Hi %s,', 'smoketree-plugin' ),
			$first_name ?? __( 'there', 'smoketree-plugin' )
		)
	);
	?>
</p>

<p><?php echo esc_html__( 'We found a previously deleted Smoketree member account associated with this email address.', 'smoketree-plugin' ); ?></p>

<p><?php echo esc_html__( 'If you want to restore that account, click the secure link below:', 'smoketree-plugin' ); ?></p>

<p>
	<a href="<?php echo esc_url( $restore_url ?? '' ); ?>">
		<?php echo esc_html__( 'Restore My Account', 'smoketree-plugin' ); ?>
	</a>
</p>

<p>
	<?php
	echo esc_html(
		sprintf(
			/* translators: %s: expiration window */
			__( 'This link expires in %s.', 'smoketree-plugin' ),
			$expires_in ?? __( '24 hours', 'smoketree-plugin' )
		)
	);
	?>
</p>

<p><?php echo esc_html__( 'If you did not request this, you can safely ignore this email.', 'smoketree-plugin' ); ?></p>

