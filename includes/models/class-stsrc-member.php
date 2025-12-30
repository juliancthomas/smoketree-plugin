<?php

/**
 * Member model class
 *
 * Represents a member with object-oriented interface.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/models
 */

/**
 * Member model class.
 *
 * Provides object-oriented interface for member data.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/models
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Member {

	/**
	 * Member ID.
	 *
	 * @since    1.0.0
	 * @var      int    $member_id
	 */
	public int $member_id = 0;

	/**
	 * WordPress user ID.
	 *
	 * @since    1.0.0
	 * @var      int    $user_id
	 */
	public int $user_id = 0;

	/**
	 * Membership type ID.
	 *
	 * @since    1.0.0
	 * @var      int    $membership_type_id
	 */
	public int $membership_type_id = 0;

	/**
	 * Member status.
	 *
	 * @since    1.0.0
	 * @var      string    $status
	 */
	public string $status = 'pending';

	/**
	 * Payment type.
	 *
	 * @since    1.0.0
	 * @var      string    $payment_type
	 */
	public string $payment_type = '';

	/**
	 * Stripe customer ID.
	 *
	 * @since    1.0.0
	 * @var      string|null    $stripe_customer_id
	 */
	public ?string $stripe_customer_id = null;

	/**
	 * First name.
	 *
	 * @since    1.0.0
	 * @var      string    $first_name
	 */
	public string $first_name = '';

	/**
	 * Last name.
	 *
	 * @since    1.0.0
	 * @var      string    $last_name
	 */
	public string $last_name = '';

	/**
	 * Email address.
	 *
	 * @since    1.0.0
	 * @var      string    $email
	 */
	public string $email = '';

	/**
	 * Phone number.
	 *
	 * @since    1.0.0
	 * @var      string    $phone
	 */
	public string $phone = '';

	/**
	 * Street address line 1.
	 *
	 * @since    1.0.0
	 * @var      string    $street_1
	 */
	public string $street_1 = '';

	/**
	 * Street address line 2.
	 *
	 * @since    1.0.0
	 * @var      string|null    $street_2
	 */
	public ?string $street_2 = null;

	/**
	 * City.
	 *
	 * @since    1.0.0
	 * @var      string    $city
	 */
	public string $city = 'Tucker';

	/**
	 * State.
	 *
	 * @since    1.0.0
	 * @var      string    $state
	 */
	public string $state = 'GA';

	/**
	 * ZIP code.
	 *
	 * @since    1.0.0
	 * @var      string    $zip
	 */
	public string $zip = '30084';

	/**
	 * Country.
	 *
	 * @since    1.0.0
	 * @var      string    $country
	 */
	public string $country = 'US';

	/**
	 * Referral source.
	 *
	 * @since    1.0.0
	 * @var      string|null    $referral_source
	 */
	public ?string $referral_source = null;

	/**
	 * Waiver full name.
	 *
	 * @since    1.0.0
	 * @var      string    $waiver_full_name
	 */
	public string $waiver_full_name = '';

	/**
	 * Waiver signed date.
	 *
	 * @since    1.0.0
	 * @var      string    $waiver_signed_date
	 */
	public string $waiver_signed_date = '';

	/**
	 * Guest pass balance.
	 *
	 * @since    1.0.0
	 * @var      int    $guest_pass_balance
	 */
	public int $guest_pass_balance = 0;

	/**
	 * Auto renewal enabled.
	 *
	 * @since    1.0.0
	 * @var      bool    $auto_renewal_enabled
	 */
	public bool $auto_renewal_enabled = false;

	/**
	 * Expiration date.
	 *
	 * @since    1.0.0
	 * @var      string|null    $expiration_date
	 */
	public ?string $expiration_date = null;

	/**
	 * Created at timestamp.
	 *
	 * @since    1.0.0
	 * @var      string    $created_at
	 */
	public string $created_at = '';

	/**
	 * Updated at timestamp.
	 *
	 * @since    1.0.0
	 * @var      string    $updated_at
	 */
	public string $updated_at = '';

	/**
	 * Load member data from database.
	 *
	 * @since    1.0.0
	 * @param    int    $member_id    Member ID
	 * @return   bool                 True on success, false on failure
	 */
	public function load( int $member_id ): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';

		$data = STSRC_Member_DB::get_member( $member_id );

		if ( null === $data ) {
			return false;
		}

		$this->populate_from_array( $data );
		return true;
	}

	/**
	 * Load member data by email.
	 *
	 * @since    1.0.0
	 * @param    string    $email    Member email
	 * @return   bool                True on success, false on failure
	 */
	public function load_by_email( string $email ): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';

		$data = STSRC_Member_DB::get_member_by_email( $email );

		if ( null === $data ) {
			return false;
		}

		$this->populate_from_array( $data );
		return true;
	}

	/**
	 * Save member data to database.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success, false on failure
	 */
	public function save(): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';

		$data = $this->to_array();

		if ( $this->member_id > 0 ) {
			// Update existing member
			unset( $data['member_id'], $data['created_at'] ); // Don't update these
			return STSRC_Member_DB::update_member( $this->member_id, $data );
		} else {
			// Create new member
			$member_id = STSRC_Member_DB::create_member( $data );
			if ( false !== $member_id ) {
				$this->member_id = $member_id;
				return true;
			}
			return false;
		}
	}

	/**
	 * Populate object from array.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Member data array
	 * @return   void
	 */
	public function populate_from_array( array $data ): void {
		foreach ( $data as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				// Type casting for specific properties
				if ( 'member_id' === $key || 'user_id' === $key || 'membership_type_id' === $key || 'guest_pass_balance' === $key ) {
					$this->$key = (int) $value;
				} elseif ( 'auto_renewal_enabled' === $key ) {
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
	 * @return   array    Member data array
	 */
	public function to_array(): array {
		return array(
			'member_id'            => $this->member_id,
			'user_id'              => $this->user_id,
			'membership_type_id'   => $this->membership_type_id,
			'status'               => $this->status,
			'payment_type'         => $this->payment_type,
			'stripe_customer_id'   => $this->stripe_customer_id,
			'first_name'           => $this->first_name,
			'last_name'            => $this->last_name,
			'email'                => $this->email,
			'phone'                => $this->phone,
			'street_1'             => $this->street_1,
			'street_2'             => $this->street_2,
			'city'                 => $this->city,
			'state'                => $this->state,
			'zip'                  => $this->zip,
			'country'              => $this->country,
			'referral_source'      => $this->referral_source,
			'waiver_full_name'     => $this->waiver_full_name,
			'waiver_signed_date'   => $this->waiver_signed_date,
			'guest_pass_balance'   => $this->guest_pass_balance,
			'auto_renewal_enabled' => $this->auto_renewal_enabled ? 1 : 0,
			'expiration_date'      => $this->expiration_date,
			'created_at'           => $this->created_at,
			'updated_at'           => $this->updated_at,
		);
	}

	/**
	 * Get full name.
	 *
	 * @since    1.0.0
	 * @return   string    Full name
	 */
	public function get_full_name(): string {
		return trim( $this->first_name . ' ' . $this->last_name );
	}

	/**
	 * Get full address.
	 *
	 * @since    1.0.0
	 * @return   string    Full address
	 */
	public function get_full_address(): string {
		$address = $this->street_1;
		if ( ! empty( $this->street_2 ) ) {
			$address .= ', ' . $this->street_2;
		}
		$address .= ', ' . $this->city . ', ' . $this->state . ' ' . $this->zip;
		return $address;
	}

	/**
	 * Check if member is active.
	 *
	 * @since    1.0.0
	 * @return   bool    True if active
	 */
	public function is_active(): bool {
		return 'active' === $this->status;
	}

	/**
	 * Check if member has paid.
	 *
	 * @since    1.0.0
	 * @return   bool    True if payment type is card or bank_account
	 */
	public function has_paid(): bool {
		return in_array( $this->payment_type, array( 'card', 'bank_account' ), true );
	}
}

