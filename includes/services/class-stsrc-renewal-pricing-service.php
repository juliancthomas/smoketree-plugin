<?php
/**
 * Renewal pricing calculations.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/services
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renewal pricing service.
 */
class STSRC_Renewal_Pricing_Service {
	private const EXTRA_MEMBER_PRICE = 50.00;

	/**
	 * Calculate an authoritative renewal quote.
	 *
	 * @param float  $membership_base_price Target membership base price.
	 * @param int    $extra_member_count Household extra member count.
	 * @param float  $existing_balance Existing member balance.
	 * @param string $payment_method Selected payment method.
	 * @return array{
	 *   membership_base:float,
	 *   extra_member_count:int,
	 *   extra_members_amount:float,
	 *   previous_balance_amount:float,
	 *   subtotal:float,
	 *   processing_fee:float,
	 *   total:float,
	 *   payment_method:string
	 * }
	 */
	public function calculate_quote(
		float $membership_base_price,
		int $extra_member_count,
		float $existing_balance,
		string $payment_method
	): array {
		$extra_member_count = max( 0, $extra_member_count );
		$payment_method     = $this->normalize_payment_method( $payment_method );

		$extra_members_amount = round( $extra_member_count * self::EXTRA_MEMBER_PRICE, 2 );
		$raw_subtotal         = $membership_base_price + $extra_members_amount + $existing_balance;
		$subtotal             = max( 0.00, round( $raw_subtotal, 2 ) );
		$processing_fee       = $this->calculate_processing_fee( $subtotal, $payment_method );
		$total                = round( $subtotal + $processing_fee, 2 );

		return array(
			'membership_base'        => round( $membership_base_price, 2 ),
			'extra_member_count'     => $extra_member_count,
			'extra_members_amount'   => $extra_members_amount,
			'previous_balance_amount' => round( $existing_balance, 2 ),
			'subtotal'               => $subtotal,
			'processing_fee'         => $processing_fee,
			'total'                  => $total,
			'payment_method'         => $payment_method,
		);
	}

	/**
	 * Calculate processing fee by payment method.
	 *
	 * @param float  $subtotal Subtotal.
	 * @param string $payment_method Payment method.
	 * @return float
	 */
	public function calculate_processing_fee( float $subtotal, string $payment_method ): float {
		if ( $subtotal <= 0 ) {
			return 0.00;
		}

		$payment_method = $this->normalize_payment_method( $payment_method );

		return match ( $payment_method ) {
			'card' => round( ( $subtotal * 0.029 ) + 0.30, 2 ),
			'ach' => min( round( $subtotal * 0.008, 2 ), 5.00 ),
			default => 0.00,
		};
	}

	/**
	 * Normalize payment method aliases.
	 *
	 * @param string $payment_method Raw method.
	 * @return string
	 */
	private function normalize_payment_method( string $payment_method ): string {
		$payment_method = sanitize_key( $payment_method );

		return match ( $payment_method ) {
			'bank_account', 'us_bank_account' => 'ach',
			default => $payment_method,
		};
	}
}

