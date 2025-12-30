<?php

/**
 * Membership type model class
 *
 * Represents a membership type with object-oriented interface.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/models
 */

/**
 * Membership type model class.
 *
 * Provides object-oriented interface for membership type data.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/models
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Membership_Type {

	/**
	 * Membership type ID.
	 *
	 * @since    1.0.0
	 * @var      int    $membership_type_id
	 */
	public int $membership_type_id = 0;

	/**
	 * Name.
	 *
	 * @since    1.0.0
	 * @var      string    $name
	 */
	public string $name = '';

	/**
	 * Description.
	 *
	 * @since    1.0.0
	 * @var      string|null    $description
	 */
	public ?string $description = null;

	/**
	 * Price.
	 *
	 * @since    1.0.0
	 * @var      float    $price
	 */
	public float $price = 0.00;

	/**
	 * Expiration period in days.
	 *
	 * @since    1.0.0
	 * @var      int    $expiration_period
	 */
	public int $expiration_period = 0;

	/**
	 * Stripe product ID.
	 *
	 * @since    1.0.0
	 * @var      string|null    $stripe_product_id
	 */
	public ?string $stripe_product_id = null;

	/**
	 * Is selectable (show in registration).
	 *
	 * @since    1.0.0
	 * @var      bool    $is_selectable
	 */
	public bool $is_selectable = true;

	/**
	 * Is best seller.
	 *
	 * @since    1.0.0
	 * @var      bool    $is_best_seller
	 */
	public bool $is_best_seller = false;

	/**
	 * Can have additional members.
	 *
	 * @since    1.0.0
	 * @var      bool    $can_have_additional_members
	 */
	public bool $can_have_additional_members = false;

	/**
	 * Benefits array.
	 *
	 * @since    1.0.0
	 * @var      array    $benefits
	 */
	public array $benefits = array();

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
	 * Load membership type data from database.
	 *
	 * @since    1.0.0
	 * @param    int    $membership_type_id    Membership type ID
	 * @return   bool                          True on success, false on failure
	 */
	public function load( int $membership_type_id ): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';

		$data = STSRC_Membership_DB::get_membership_type( $membership_type_id );

		if ( null === $data ) {
			return false;
		}

		$this->populate_from_array( $data );
		return true;
	}

	/**
	 * Save membership type data to database.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success, false on failure
	 */
	public function save(): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';

		$data = $this->to_array();

		if ( $this->membership_type_id > 0 ) {
			// Update existing membership type
			unset( $data['membership_type_id'], $data['created_at'] ); // Don't update these
			return STSRC_Membership_DB::update_membership_type( $this->membership_type_id, $data );
		} else {
			// Create new membership type
			$membership_type_id = STSRC_Membership_DB::create_membership_type( $data );
			if ( false !== $membership_type_id ) {
				$this->membership_type_id = $membership_type_id;
				return true;
			}
			return false;
		}
	}

	/**
	 * Populate object from array.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Membership type data array
	 * @return   void
	 */
	public function populate_from_array( array $data ): void {
		foreach ( $data as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				// Type casting for specific properties
				if ( 'membership_type_id' === $key || 'expiration_period' === $key ) {
					$this->$key = (int) $value;
				} elseif ( 'price' === $key ) {
					$this->$key = (float) $value;
				} elseif ( 'is_selectable' === $key || 'is_best_seller' === $key || 'can_have_additional_members' === $key ) {
					$this->$key = (bool) $value;
				} elseif ( 'benefits' === $key ) {
					// Benefits is already decoded from JSON in the DB class
					$this->$key = is_array( $value ) ? $value : array();
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
	 * @return   array    Membership type data array
	 */
	public function to_array(): array {
		return array(
			'membership_type_id'         => $this->membership_type_id,
			'name'                      => $this->name,
			'description'               => $this->description,
			'price'                     => $this->price,
			'expiration_period'         => $this->expiration_period,
			'stripe_product_id'         => $this->stripe_product_id,
			'is_selectable'             => $this->is_selectable ? 1 : 0,
			'is_best_seller'            => $this->is_best_seller ? 1 : 0,
			'can_have_additional_members' => $this->can_have_additional_members ? 1 : 0,
			'benefits'                  => $this->benefits, // Will be JSON encoded in DB class
			'created_at'                => $this->created_at,
			'updated_at'                => $this->updated_at,
		);
	}

	/**
	 * Check if membership type has a specific benefit.
	 *
	 * @since    1.0.0
	 * @param    string    $benefit    Benefit ID to check
	 * @return   bool                  True if benefit exists
	 */
	public function has_benefit( string $benefit ): bool {
		return in_array( $benefit, $this->benefits, true );
	}

	/**
	 * Get formatted price.
	 *
	 * @since    1.0.0
	 * @return   string    Formatted price with currency symbol
	 */
	public function get_formatted_price(): string {
		return '$' . number_format( $this->price, 2 );
	}
}

