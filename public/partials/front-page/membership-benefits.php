<?php
/**
 * Membership Benefits Section Partial
 *
 * Expects: $membership_types (array from STSRC_Membership_DB)
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/partials/front-page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $membership_types ) ) {
	return;
}

$all_benefits = [];
foreach ( $membership_types as $type ) {
	if ( ! empty( $type['benefits'] ) && is_array( $type['benefits'] ) ) {
		foreach ( $type['benefits'] as $benefit ) {
			if ( ! in_array( $benefit, $all_benefits, true ) ) {
				$all_benefits[] = $benefit;
			}
		}
	}
}

if ( empty( $all_benefits ) ) {
	return;
}
?>

<section id="member_benefits" class="stsrc-benefits">
	<div class="stsrc-benefits__inner">
		<h2 class="stsrc-benefits__title">Membership Benefits</h2>
		<div class="stsrc-benefits__table-wrap">
			<table class="stsrc-benefits__table">
				<thead>
					<tr>
						<th>Benefit</th>
						<?php foreach ( $membership_types as $type ) : ?>
							<th><?php echo esc_html( $type['name'] ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $all_benefits as $benefit ) : ?>
						<tr>
							<td><?php echo esc_html( $benefit ); ?></td>
							<?php foreach ( $membership_types as $type ) :
								$type_benefits = is_array( $type['benefits'] ) ? $type['benefits'] : [];
							?>
								<td><?php if ( in_array( $benefit, $type_benefits, true ) ) echo '&#10003;'; ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</section>
