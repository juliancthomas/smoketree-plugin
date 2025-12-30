<?php

/**
 * Member service class
 *
 * Orchestrates member-related business logic.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/services
 */

/**
 * Member service class.
 *
 * Provides business logic for member operations.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/services
 * @author     Smoketree Swim and Recreation Club
 */
require_once __DIR__ . '/class-stsrc-logger.php';

class STSRC_Member_Service {

	/**
	 * Create member account (WordPress user + member record).
	 *
	 * @since    1.0.0
	 * @param    array    $data    Member data array
	 * @return   int|false          Member ID on success, false on failure
	 */
	public function create_member_account( array $data ): int|false {
		// Check for duplicate email
		if ( $this->check_duplicate_email( $data['email'] ?? '' ) ) {
			return false;
		}

		// Create WordPress user
		$user_id = wp_create_user(
			$data['email'],
			$data['password'] ?? wp_generate_password(),
			$data['email']
		);

		if ( is_wp_error( $user_id ) ) {
			STSRC_Logger::error(
				'Failed to create WordPress user during member account creation.',
				array(
					'method' => __METHOD__,
					'email'  => $data['email'] ?? '',
					'error'  => $user_id->get_error_message(),
				)
			);
			return false;
		}

		// Set user role
		$user = new WP_User( $user_id );
		$user->set_role( 'stsrc_member' );

		// Update user meta
		if ( ! empty( $data['first_name'] ) ) {
			update_user_meta( $user_id, 'first_name', sanitize_text_field( $data['first_name'] ) );
		}
		if ( ! empty( $data['last_name'] ) ) {
			update_user_meta( $user_id, 'last_name', sanitize_text_field( $data['last_name'] ) );
		}

		// Prepare member data for database
		$member_data = array(
			'user_id'            => $user_id,
			'membership_type_id' => intval( $data['membership_type_id'] ?? 0 ),
			'status'             => sanitize_text_field( $data['status'] ?? 'pending' ),
			'payment_type'       => sanitize_text_field( $data['payment_type'] ?? '' ),
			'first_name'         => sanitize_text_field( $data['first_name'] ?? '' ),
			'last_name'          => sanitize_text_field( $data['last_name'] ?? '' ),
			'email'              => sanitize_email( $data['email'] ?? '' ),
			'phone'              => sanitize_text_field( $data['phone'] ?? '' ),
			'street_1'           => sanitize_text_field( $data['street_1'] ?? '' ),
			'street_2'           => sanitize_text_field( $data['street_2'] ?? '' ),
			'city'               => sanitize_text_field( $data['city'] ?? 'Tucker' ),
			'state'              => sanitize_text_field( $data['state'] ?? 'GA' ),
			'zip'                => sanitize_text_field( $data['zip'] ?? '30084' ),
			'country'            => sanitize_text_field( $data['country'] ?? 'US' ),
			'referral_source'    => sanitize_text_field( $data['referral_source'] ?? '' ),
			'waiver_full_name'   => sanitize_text_field( $data['waiver_full_name'] ?? '' ),
			'waiver_signed_date' => sanitize_text_field( $data['waiver_signed_date'] ?? '' ),
		);

		// Add optional fields
		if ( ! empty( $data['stripe_customer_id'] ) ) {
			$member_data['stripe_customer_id'] = sanitize_text_field( $data['stripe_customer_id'] );
		}

		// Calculate expiration date if membership type provided
		if ( ! empty( $data['membership_type_id'] ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';
			$membership_type = STSRC_Membership_DB::get_membership_type( intval( $data['membership_type_id'] ) );
			if ( $membership_type && ! empty( $membership_type['expiration_period'] ) ) {
				$expiration_date = date( 'Y-m-d', strtotime( '+' . $membership_type['expiration_period'] . ' days' ) );
				$member_data['expiration_date'] = $expiration_date;
			}
		}

		// Create member record
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		$member_id = STSRC_Member_DB::create_member( $member_data );

		if ( false === $member_id ) {
			// Cleanup: delete WordPress user if member creation failed
			wp_delete_user( $user_id );
			STSRC_Logger::error(
				'Failed to create member database record after WordPress user creation.',
				array(
					'method' => __METHOD__,
					'user_id'=> $user_id,
					'email'  => $data['email'] ?? '',
				)
			);
			return false;
		}

		return $member_id;
	}

	/**
	 * Activate member account.
	 *
	 * @since    1.0.0
	 * @param    int    $member_id    Member ID
	 * @return   bool                 True on success, false on failure
	 */
	public function activate_member( int $member_id ): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';

		$member = STSRC_Member_DB::get_member( $member_id );
		if ( ! $member ) {
			STSRC_Logger::warning(
				'Attempted to activate a member that does not exist.',
				array(
					'method'    => __METHOD__,
					'member_id' => $member_id,
				)
			);
			return false;
		}

		// Update status to active
		$result = STSRC_Member_DB::update_member(
			$member_id,
			array( 'status' => 'active' )
		);

		if ( ! $result ) {
			STSRC_Logger::error(
				'Failed to update member status during activation.',
				array(
					'method'    => __METHOD__,
					'member_id' => $member_id,
				)
			);
			return false;
		}

		// Send welcome email
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-email-service.php';
		$email_service = new STSRC_Email_Service();

		// Determine which welcome template to use
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';
		$membership_type = STSRC_Membership_DB::get_membership_type( $member['membership_type_id'] );
		$template = ( $membership_type && stripos( $membership_type['name'], 'civic' ) !== false ) ? 'welcome-civic.php' : 'welcome.php';

		$sent = $email_service->send_email(
			$template,
			array(
				'first_name'     => $member['first_name'],
				'last_name'      => $member['last_name'],
				'email'          => $member['email'],
				'membership_type' => $membership_type['name'] ?? '',
			),
			$member['email'],
			'Welcome to Smoketree Swim and Recreation Club!'
		);

		if ( ! $sent ) {
			STSRC_Logger::warning(
				'Welcome email failed to send during member activation.',
				array(
					'method'    => __METHOD__,
					'member_id' => $member_id,
					'template'  => $template,
				)
			);
		}

		return true;
	}

	/**
	 * Update member profile.
	 *
	 * @since    1.0.0
	 * @param    int      $member_id    Member ID
	 * @param    array    $data         Fields to update
	 * @return   bool                   True on success, false on failure
	 */
	public function update_member_profile( int $member_id, array $data ): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';

		$member = STSRC_Member_DB::get_member( $member_id );
		if ( ! $member ) {
			STSRC_Logger::warning(
				'Attempted to update profile for missing member.',
				array(
					'method'    => __METHOD__,
					'member_id' => $member_id,
				)
			);
			return false;
		}

		// Sanitize data
		$update_data = array();
		$allowed_fields = array(
			'first_name',
			'last_name',
			'phone',
			'street_1',
			'street_2',
			'city',
			'state',
			'zip',
			'country',
		);

		foreach ( $allowed_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$update_data[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		// Update email if provided (also update WordPress user)
		if ( ! empty( $data['email'] ) && $data['email'] !== $member['email'] ) {
			// Check for duplicate
			if ( $this->check_duplicate_email( $data['email'], $member_id ) ) {
				STSRC_Logger::info(
					'Profile update blocked due to duplicate email attempt.',
					array(
						'method'     => __METHOD__,
						'member_id'  => $member_id,
						'new_email'  => $data['email'],
					)
				);
				return false;
			}

			$update_data['email'] = sanitize_email( $data['email'] );

			// Update WordPress user email
			$wp_user_update = wp_update_user(
				array(
					'ID'         => $member['user_id'],
					'user_email' => $update_data['email'],
				)
			);

			if ( is_wp_error( $wp_user_update ) ) {
				STSRC_Logger::error(
					'Failed to update WordPress user email during profile update.',
					array(
						'method'    => __METHOD__,
						'member_id' => $member_id,
						'error'     => $wp_user_update->get_error_message(),
					)
				);
				return false;
			}
		}

		// Update member record
		if ( ! empty( $update_data ) ) {
			$updated = STSRC_Member_DB::update_member( $member_id, $update_data );

			if ( ! $updated ) {
				STSRC_Logger::error(
					'Failed to update member record with profile changes.',
					array(
						'method'    => __METHOD__,
						'member_id' => $member_id,
						'fields'    => array_keys( $update_data ),
					)
				);
			}

			return (bool) $updated;
		}

		return true;
	}

	/**
	 * Change member password.
	 *
	 * @since    1.0.0
	 * @param    int      $member_id       Member ID
	 * @param    string   $current_password    Current password
	 * @param    string   $new_password     New password
	 * @return   bool                       True on success, false on failure
	 */
	public function change_password( int $member_id, string $current_password, string $new_password ): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';

		$member = STSRC_Member_DB::get_member( $member_id );
		if ( ! $member ) {
			STSRC_Logger::warning(
				'Attempted to change password for missing member record.',
				array(
					'method'    => __METHOD__,
					'member_id' => $member_id,
				)
			);
			return false;
		}

		// Verify current password
		$user = wp_authenticate( $member['email'], $current_password );
		if ( is_wp_error( $user ) ) {
			STSRC_Logger::info(
				'Password change blocked due to invalid current password.',
				array(
					'method'    => __METHOD__,
					'member_id' => $member_id,
					'error'     => $user->get_error_code(),
				)
			);
			return false;
		}

		// Update password
		wp_set_password( $new_password, $member['user_id'] );

		return true;
	}

	/**
	 * Update auto-renewal preference for a member.
	 *
	 * @since    1.0.0
	 * @param    int   $member_id Member ID
	 * @param    bool  $enabled   Whether auto-renewal should be enabled
	 * @return   bool             True on success, false on failure
	 */
	public function set_auto_renewal_preference( int $member_id, bool $enabled ): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';

		$member = STSRC_Member_DB::get_member( $member_id );
		if ( ! $member ) {
			STSRC_Logger::warning(
				'Attempted to update auto-renewal preference for missing member.',
				array(
					'method'    => __METHOD__,
					'member_id' => $member_id,
				)
			);
			return false;
		}

		$updated = STSRC_Member_DB::update_member(
			$member_id,
			array(
				'auto_renewal_enabled' => $enabled ? 1 : 0,
			)
		);

		if ( ! $updated ) {
			STSRC_Logger::error(
				'Failed to persist auto-renewal preference.',
				array(
					'method'    => __METHOD__,
					'member_id' => $member_id,
					'enabled'   => $enabled,
				)
			);
		}

		if ( $updated ) {
			/**
			 * Fires after a member's auto-renewal preference has been updated.
			 *
			 * @since 1.0.0
			 *
			 * @param int  $member_id Member ID.
			 * @param bool $enabled   Whether auto-renewal is now enabled.
			 * @param array $member   Member record prior to update.
			 */
			do_action( 'stsrc_member_auto_renewal_updated', $member_id, $enabled, $member );
		}

		return $updated;
	}

	/**
	 * Get member data.
	 *
	 * @since    1.0.0
	 * @param    int    $member_id    Member ID
	 * @return   array|null           Member data array or null if not found
	 */
	public function get_member_data( int $member_id ): ?array {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';

		$member = STSRC_Member_DB::get_member( $member_id );
		if ( ! $member ) {
			return null;
		}

		// Get WordPress user data
		$user = get_userdata( $member['user_id'] );
		if ( $user ) {
			$member['wp_user'] = array(
				'user_login' => $user->user_login,
				'user_email' => $user->user_email,
				'user_registered' => $user->user_registered,
			);
		}

		// Get membership type
		if ( ! empty( $member['membership_type_id'] ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';
			$membership_type = STSRC_Membership_DB::get_membership_type( $member['membership_type_id'] );
			if ( $membership_type ) {
				$member['membership_type'] = $membership_type;
			}
		}

		// Get family members
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-family-member-db.php';
		$member['family_members'] = STSRC_Family_Member_DB::get_family_members( $member_id );

		// Get extra members
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-extra-member-db.php';
		$member['extra_members'] = STSRC_Extra_Member_DB::get_extra_members( $member_id );

		return $member;
	}

	/**
	 * Check for duplicate email.
	 *
	 * @since    1.0.0
	 * @param    string    $email        Email address to check
	 * @param    int       $exclude_id   Optional member ID to exclude from check
	 * @return   bool                    True if duplicate exists, false otherwise
	 */
	public function check_duplicate_email( string $email, int $exclude_id = 0 ): bool {
		if ( empty( $email ) ) {
			return false;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';

		$member = STSRC_Member_DB::get_member_by_email( $email );

		if ( ! $member ) {
			return false;
		}

		// If exclude_id is provided and matches, it's not a duplicate
		if ( $exclude_id > 0 && (int) $member['member_id'] === $exclude_id ) {
			return false;
		}

		return true;
	}
}

