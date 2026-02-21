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

<section id="member_benefits" class="px-6 py-[80px] bg-[#345365]">
	<div class="container mx-auto">
		<h2 class="text-3xl text-center text-[#ececec] mb-10">Membership Benefits</h2>
		<div class="overflow-x-auto">
			<table class="table-auto w-full border border-gray-300">
				<thead>
					<tr>
						<th class="border px-4 py-2 text-left text-[#ececec]">Benefit</th>
						<?php foreach ( $membership_types as $type ) : ?>
							<th class="border px-4 py-2 text-center text-[#ececec]">
								<?php echo esc_html( $type['name'] ); ?>
							</th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $all_benefits as $benefit ) : ?>
						<tr>
							<td class="border px-4 py-2 text-[#ececec]"><?php echo esc_html( $benefit ); ?></td>
							<?php foreach ( $membership_types as $type ) :
								$type_benefits = is_array( $type['benefits'] ) ? $type['benefits'] : [];
							?>
								<td class="border px-4 py-2 text-center text-[#ececec]">
									<?php if ( in_array( $benefit, $type_benefits, true ) ) echo '&#10003;'; ?>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</section>
