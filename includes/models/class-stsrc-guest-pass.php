<?php

/**
 * Guest pass model class
 *
 * Represents a guest pass entry with object-oriented interface.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/models
 */

/**
 * Guest pass model class.
 *
 * Provides object-oriented interface for guest pass data.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/models
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Guest_Pass {

	/**
	 * Guest pass ID.
	 *
	 * @since    1.0.0
	 * @var      int    $guest_pass_id
	 */
	public int $guest_pass_id = 0;

	/**
	 * Member ID.
	 *
	 * @since    1.0.0
	 * @var      int    $member_id
	 */
	public int $member_id = 0;

	/**
	 * Quantity.
	 *
	 * @since    1.0.0
	 * @var      int    $quantity
	 */
	public int $quantity = 1;

	/**
	 * Amount.
	 *
	 * @since    1.0.0
	 * @var      float    $amount
	 */
	public float $amount = 0.00;

	/**
	 * Stripe payment intent ID.
	 *
	 * @since    1.0.0
	 * @var      string|null    $stripe_payment_intent_id
	 */
	public ?string $stripe_payment_intent_id = null;

	/**
	 * Used at timestamp.
	 *
	 * @since    1.0.0
	 * @var      string|null    $used_at
	 */
	public ?string $used_at = null;

	/**
	 * Payment status.
	 *
	 * @since    1.0.0
	 * @var      string    $payment_status
	 */
	public string $payment_status = 'pending';

	/**
	 * Admin adjusted flag.
	 *
	 * @since    1.0.0
	 * @var      bool    $admin_adjusted
	 */
	public bool $admin_adjusted = false;

	/**
	 * Adjusted by user ID.
	 *
	 * @since    1.0.0
	 * @var      int|null    $adjusted_by
	 */
	public ?int $adjusted_by = null;

	/**
	 * Notes.
	 *
	 * @since    1.0.0
	 * @var      string|null    $notes
	 */
	public ?string $notes = null;

	/**
	 * Created at timestamp.
	 *
	 * @since    1.0.0
	 * @var      string    $created_at
	 */
	public string $created_at = '';

	/**
	 * Load guest pass data from database.
	 *
	 * Note: This is a simplified model. Guest passes are primarily managed through
	 * the STSRC_Guest_Pass_DB class for balance and logging operations.
	 *
	 * @since    1.0.0
	 * @param    int    $guest_pass_id    Guest pass ID
	 * @return   bool                     True on success, false on failure
	 */
	public function load( int $guest_pass_id ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_guest_passes';

		$data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE guest_pass_id = %d",
				$guest_pass_id
			),
			ARRAY_A
		);

		if ( null === $data ) {
			return false;
		}

		$this->populate_from_array( $data );
		return true;
	}

	/**
	 * Populate object from array.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Guest pass data array
	 * @return   void
	 */
	public function populate_from_array( array $data ): void {
		foreach ( $data as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				// Type casting for specific properties
				if ( 'guest_pass_id' === $key || 'member_id' === $key || 'quantity' === $key || 'adjusted_by' === $key ) {
					$this->$key = (int) $value;
				} elseif ( 'amount' === $key ) {
					$this->$key = (float) $value;
				} elseif ( 'admin_adjusted' === $key ) {
					$this->$key = (bool) $value;
				} else {
					$this->$key = $value;
				}
			}
		}
	}

	/**
	 * Convert object to array.
	 *
	 * @since    1.0.0
	 * @return   array    Guest pass data array
	 */
	public function to_array(): array {
		return array(
			'guest_pass_id'            => $this->guest_pass_id,
			'member_id'               => $this->member_id,
			'quantity'                => $this->quantity,
			'amount'                  => $this->amount,
			'stripe_payment_intent_id' => $this->stripe_payment_intent_id,
			'used_at'                 => $this->used_at,
			'payment_status'          => $this->payment_status,
			'admin_adjusted'          => $this->admin_adjusted ? 1 : 0,
			'adjusted_by'             => $this->adjusted_by,
			'notes'                   => $this->notes,
			'created_at'              => $this->created_at,
		);
	}

	/**
	 * Check if guest pass has been used.
	 *
	 * @since    1.0.0
	 * @return   bool    True if used
	 */
	public function is_used(): bool {
		return ! empty( $this->used_at );
	}

	/**
	 * Check if payment is completed.
	 *
	 * @since    1.0.0
	 * @return   bool    True if payment status is 'paid'
	 */
	public function is_paid(): bool {
		return 'paid' === $this->payment_status;
	}

	/**
	 * Check if this is an admin adjustment.
	 *
	 * @since    1.0.0
	 * @return   bool    True if admin adjusted
	 */
	public function is_admin_adjusted(): bool {
		return $this->admin_adjusted;
	}

	/**
	 * Get formatted amount.
	 *
	 * @since    1.0.0
	 * @return   string    Formatted amount with currency symbol
	 */
	public function get_formatted_amount(): string {
		return '$' . number_format( $this->amount, 2 );
	}
}

