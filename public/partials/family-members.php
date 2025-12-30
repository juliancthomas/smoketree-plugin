<?php
/**
 * Family members list partial
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$member = $data['member'] ?? array();
$membership_type = $data['membership_type'] ?? null;
$family_members = $data['family_members'] ?? array();

// Determine family member limit
$family_limit = 0;
if ( ! empty( $membership_type ) ) {
	$type_name = strtolower( $membership_type['name'] );
	if ( 'household' === $type_name ) {
		$family_limit = 4;
	} elseif ( 'duo' === $type_name ) {
		$family_limit = 1;
	}
}

$can_add_family = ! empty( $membership_type ) && in_array( strtolower( $membership_type['name'] ), array( 'household', 'duo' ), true );
$can_add_more = $can_add_family && count( $family_members ) < $family_limit;
?>

<div class="stsrc-portal-section">
	<h2><?php echo esc_html__( 'Family Members', 'smoketree-plugin' ); ?></h2>
	
	<?php if ( $can_add_family ) : ?>
		<p class="stsrc-description">
			<?php
			if ( $family_limit > 0 ) {
				printf(
					esc_html__( 'You can add up to %d family member(s) with your membership.', 'smoketree-plugin' ),
					$family_limit
				);
			}
			?>
		</p>
	<?php else : ?>
		<p class="stsrc-description">
			<?php echo esc_html__( 'Your membership type does not include family members.', 'smoketree-plugin' ); ?>
		</p>
	<?php endif; ?>

	<?php if ( ! empty( $family_members ) ) : ?>
		<div class="stsrc-family-members-list">
			<?php foreach ( $family_members as $family_member ) : ?>
				<div class="stsrc-family-member-item" data-id="<?php echo esc_attr( $family_member['family_member_id'] ); ?>">
					<div class="stsrc-member-details">
						<strong><?php echo esc_html( $family_member['first_name'] . ' ' . $family_member['last_name'] ); ?></strong>
						<?php if ( ! empty( $family_member['email'] ) ) : ?>
							<span class="stsrc-member-email"><?php echo esc_html( $family_member['email'] ); ?></span>
						<?php endif; ?>
					</div>
					<div class="stsrc-member-actions">
						<button type="button" class="stsrc-button stsrc-button-secondary stsrc-edit-family-member" data-id="<?php echo esc_attr( $family_member['family_member_id'] ); ?>">
							<?php echo esc_html__( 'Edit', 'smoketree-plugin' ); ?>
						</button>
						<button type="button" class="stsrc-button stsrc-button-danger stsrc-delete-family-member" data-id="<?php echo esc_attr( $family_member['family_member_id'] ); ?>">
							<?php echo esc_html__( 'Delete', 'smoketree-plugin' ); ?>
						</button>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p class="stsrc-empty-state"><?php echo esc_html__( 'No family members added yet.', 'smoketree-plugin' ); ?></p>
	<?php endif; ?>

	<?php if ( $can_add_more ) : ?>
		<button type="button" class="stsrc-button stsrc-button-primary" id="stsrc-add-family-member-btn">
			<?php echo esc_html__( '+ Add Family Member', 'smoketree-plugin' ); ?>
		</button>
	<?php endif; ?>
</div>

<!-- Add/Edit Family Member Modal -->
<div class="stsrc-modal-overlay" id="stsrc-family-member-modal">
	<div class="stsrc-modal">
		<div class="stsrc-modal-header">
			<h2 id="stsrc-family-member-modal-title"><?php echo esc_html__( 'Add Family Member', 'smoketree-plugin' ); ?></h2>
			<button class="stsrc-modal-close">&times;</button>
		</div>
		<div class="stsrc-modal-body">
			<form id="stsrc-family-member-form">
				<input type="hidden" name="action" id="stsrc-family-member-action" value="stsrc_add_family_member">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'stsrc_family_member_nonce' ) ); ?>">
				<input type="hidden" name="family_member_id" id="stsrc-family-member-id" value="">
				
				<div class="stsrc-form-group">
					<label for="family_first_name"><?php echo esc_html__( 'First Name', 'smoketree-plugin' ); ?></label>
					<input type="text" name="first_name" id="family_first_name" required>
				</div>
				
				<div class="stsrc-form-group">
					<label for="family_last_name"><?php echo esc_html__( 'Last Name', 'smoketree-plugin' ); ?></label>
					<input type="text" name="last_name" id="family_last_name" required>
				</div>
				
				<div class="stsrc-form-group">
					<label for="family_email"><?php echo esc_html__( 'Email (optional)', 'smoketree-plugin' ); ?></label>
					<input type="email" name="email" id="family_email">
				</div>
				
				<div class="stsrc-modal-footer">
					<button type="button" class="stsrc-button stsrc-button-secondary stsrc-modal-close"><?php echo esc_html__( 'Cancel', 'smoketree-plugin' ); ?></button>
					<button type="submit" class="stsrc-button stsrc-button-primary"><?php echo esc_html__( 'Save', 'smoketree-plugin' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

