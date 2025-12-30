<?php
/**
 * Membership types list template
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$membership_types = $data['membership_types'] ?? array();
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Membership Types', 'smoketree-plugin' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-memberships&action=edit' ) ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Add New', 'smoketree-plugin' ); ?>
	</a>
	<hr class="wp-header-end">

	<!-- Membership Types Table -->
	<table class="wp-list-table widefat fixed striped table-view-list">
		<thead>
			<tr>
				<th class="manage-column"><?php echo esc_html__( 'Name', 'smoketree-plugin' ); ?></th>
				<th class="manage-column"><?php echo esc_html__( 'Price', 'smoketree-plugin' ); ?></th>
				<th class="manage-column"><?php echo esc_html__( 'Expiration Period', 'smoketree-plugin' ); ?></th>
				<th class="manage-column"><?php echo esc_html__( 'Stripe Product ID', 'smoketree-plugin' ); ?></th>
				<th class="manage-column"><?php echo esc_html__( 'Selectable', 'smoketree-plugin' ); ?></th>
				<th class="manage-column"><?php echo esc_html__( 'Best Seller', 'smoketree-plugin' ); ?></th>
				<th class="manage-column"><?php echo esc_html__( 'Additional Members', 'smoketree-plugin' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $membership_types ) ) : ?>
				<?php foreach ( $membership_types as $type ) : ?>
					<tr>
						<td class="column-name">
							<strong>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-memberships&action=edit&membership_type_id=' . $type['membership_type_id'] ) ); ?>">
									<?php echo esc_html( $type['name'] ); ?>
								</a>
							</strong>
							<div class="row-actions">
								<span class="edit">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=stsrc-memberships&action=edit&membership_type_id=' . $type['membership_type_id'] ) ); ?>">
										<?php echo esc_html__( 'Edit', 'smoketree-plugin' ); ?>
									</a> |
								</span>
								<span class="delete">
									<a href="#" class="stsrc-delete-membership-type" data-id="<?php echo esc_attr( $type['membership_type_id'] ); ?>" data-name="<?php echo esc_attr( $type['name'] ); ?>">
										<?php echo esc_html__( 'Delete', 'smoketree-plugin' ); ?>
									</a>
								</span>
							</div>
						</td>
						<td>$<?php echo esc_html( number_format( floatval( $type['price'] ), 2 ) ); ?></td>
						<td><?php echo esc_html( $type['expiration_period'] ); ?> <?php echo esc_html__( 'days', 'smoketree-plugin' ); ?></td>
						<td>
							<?php if ( ! empty( $type['stripe_product_id'] ) ) : ?>
								<code><?php echo esc_html( $type['stripe_product_id'] ); ?></code>
							<?php else : ?>
								<span class="description"><?php echo esc_html__( 'Not set', 'smoketree-plugin' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( ! empty( $type['is_selectable'] ) ) : ?>
								<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
							<?php else : ?>
								<span class="dashicons dashicons-dismiss" style="color: red;"></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( ! empty( $type['is_best_seller'] ) ) : ?>
								<span class="dashicons dashicons-star-filled" style="color: gold;"></span>
							<?php else : ?>
								<span class="dashicons dashicons-star-empty"></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( ! empty( $type['can_have_additional_members'] ) ) : ?>
								<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
							<?php else : ?>
								<span class="dashicons dashicons-dismiss" style="color: red;"></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="7"><?php echo esc_html__( 'No membership types found.', 'smoketree-plugin' ); ?></td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>
</div>

