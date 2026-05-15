<?php
/**
 * Access codes partial
 *
 * Displays active access codes as styled credential cards.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$access_codes = $data['access_codes'] ?? array();

if ( empty( $access_codes ) ) {
	return;
}
?>

<div class="stsrc-portal-section">
	<h2><?php echo esc_html__( 'Access Codes', 'smoketree-plugin' ); ?></h2>
	<div class="stsrc-access-code-grid">
		<?php foreach ( $access_codes as $code ) : ?>
			<div class="stsrc-access-code-card">
				<div class="stsrc-access-code-card__value"><?php echo esc_html( $code['code'] ); ?></div>
				<?php if ( ! empty( $code['description'] ) ) : ?>
					<div class="stsrc-access-code-card__description"><?php echo esc_html( $code['description'] ); ?></div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</div>
