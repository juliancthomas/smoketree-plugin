<?php
/**
 * Member portal helper class
 *
 * Handles member portal-specific rendering helpers.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/services/class-stsrc-balance-service.php';

/**
 * Member portal helper class.
 *
 * @package Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public
 */
class STSRC_Member_Portal {

	/**
	 * Render balance card for a member when balance is outstanding.
	 *
	 * @param int $member_id Member ID.
	 * @return void
	 */
	public static function render_balance_card( int $member_id ): void {
		if ( $member_id <= 0 ) {
			return;
		}

		$balance_data = STSRC_Balance_Service::get_balance_display_data( $member_id );

		if ( empty( $balance_data ) ) {
			return;
		}

		$balance_owed = (float) ( $balance_data['balance_owed'] ?? 0 );

		// Only show this card for outstanding balances.
		if ( $balance_owed <= 0.01 ) {
			return;
		}

		$balance_card_data = array(
			'member_id'             => $member_id,
			'balance_owed'          => $balance_owed,
			'membership_type_name'  => $balance_data['membership_type_name'] ?? '',
			'original_price'        => (float) ( $balance_data['original_price'] ?? 0 ),
			'total_paid'            => (float) ( $balance_data['total_paid'] ?? 0 ),
			'total_adjustments'     => (float) ( $balance_data['total_adjustments'] ?? 0 ),
			'remaining_balance'     => $balance_owed,
		);

		include plugin_dir_path( __FILE__ ) . 'partials/member-balance-card.php';
	}
}
