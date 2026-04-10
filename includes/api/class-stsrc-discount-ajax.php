<?php

/**
 * Discount AJAX handler class.
 *
 * Handles promo and affiliate discount validation plus admin CRUD actions.
 *
 * @link       https://smoketree.us
 * @since      1.4.2
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/api
 */
class STSRC_Discount_Ajax {

	/**
	 * Validate promo code for registration.
	 *
	 * @since    1.4.2
	 * @return   void
	 */
	public function validate_promo_code(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'stsrc_registration_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'smoketree-plugin' ) ), 403 );
		}

		$code               = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		$membership_type_id = isset( $_POST['membership_type_id'] ) ? absint( wp_unslash( $_POST['membership_type_id'] ) ) : 0;

		if ( '' === $code || $membership_type_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Code and membership type are required.', 'smoketree-plugin' ) ), 400 );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-discount-service.php';
		$result = STSRC_Discount_Service::validate_promo_code( $code, $membership_type_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Validate affiliate/referral code for registration.
	 *
	 * @since    1.4.2
	 * @return   void
	 */
	public function validate_affiliate_code(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'stsrc_registration_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'smoketree-plugin' ) ), 403 );
		}

		$code               = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		$membership_type_id = isset( $_POST['membership_type_id'] ) ? absint( wp_unslash( $_POST['membership_type_id'] ) ) : 0;

		if ( '' === $code ) {
			wp_send_json_error( array( 'message' => __( 'Referral code is required.', 'smoketree-plugin' ) ), 400 );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-discount-service.php';
		$result = STSRC_Discount_Service::validate_affiliate_code( $code, $membership_type_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Create promo code (admin).
	 *
	 * @since    1.4.2
	 * @return   void
	 */
	public function create_promo_code(): void {
		$this->assert_admin_request();

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-promo-codes-db.php';
		$result = STSRC_Promo_Codes_DB::create_code( $this->sanitize_promo_payload() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'code_id' => (int) $result ) );
	}

	/**
	 * Update promo code (admin).
	 *
	 * @since    1.4.2
	 * @return   void
	 */
	public function update_promo_code(): void {
		$this->assert_admin_request();

		$code_id = isset( $_POST['code_id'] ) ? absint( wp_unslash( $_POST['code_id'] ) ) : 0;
		if ( $code_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Promo code ID is required.', 'smoketree-plugin' ) ), 400 );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-promo-codes-db.php';
		$result = STSRC_Promo_Codes_DB::update_code( $code_id, $this->sanitize_promo_payload() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'updated' => true ) );
	}

	/**
	 * Soft-delete promo code (admin).
	 *
	 * @since    1.4.2
	 * @return   void
	 */
	public function delete_promo_code(): void {
		$this->assert_admin_request();

		$code_id = isset( $_POST['code_id'] ) ? absint( wp_unslash( $_POST['code_id'] ) ) : 0;
		if ( $code_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Promo code ID is required.', 'smoketree-plugin' ) ), 400 );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-promo-codes-db.php';
		$deleted = STSRC_Promo_Codes_DB::soft_delete_code( $code_id );

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Unable to delete promo code.', 'smoketree-plugin' ) ), 500 );
		}

		wp_send_json_success( array( 'deleted' => true ) );
	}

	/**
	 * Toggle affiliate payout status (admin).
	 *
	 * @since    1.4.2
	 * @return   void
	 */
	public function toggle_payout_status(): void {
		$this->assert_admin_request();

		$referral_id = isset( $_POST['referral_id'] ) ? absint( wp_unslash( $_POST['referral_id'] ) ) : 0;
		$status      = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		if ( $referral_id <= 0 || '' === $status ) {
			wp_send_json_error( array( 'message' => __( 'Referral ID and payout status are required.', 'smoketree-plugin' ) ), 400 );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-affiliate-referrals-db.php';
		$updated = STSRC_Affiliate_Referrals_DB::update_payout_status( $referral_id, $status, get_current_user_id() );

		if ( ! $updated ) {
			wp_send_json_error( array( 'message' => __( 'Unable to update payout status.', 'smoketree-plugin' ) ), 500 );
		}

		wp_send_json_success( array( 'updated' => true ) );
	}

	/**
	 * Regenerate (or generate) an affiliate/referral code for a member (admin).
	 *
	 * @since    1.5.0
	 * @return   void
	 */
	public function reset_member_affiliate_code(): void {
		$this->assert_admin_request();

		$member_id = isset( $_POST['member_id'] ) ? absint( wp_unslash( $_POST['member_id'] ) ) : 0;
		if ( $member_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Member ID is required.', 'smoketree-plugin' ) ), 400 );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-discount-service.php';

		$member = STSRC_Member_DB::get_member( $member_id );
		if ( ! $member ) {
			wp_send_json_error( array( 'message' => __( 'Member not found.', 'smoketree-plugin' ) ), 404 );
		}

		$new_code = STSRC_Discount_Service::generate_affiliate_code( (string) $member['last_name'] );

		global $wpdb;
		$updated = $wpdb->update(
			$wpdb->prefix . 'stsrc_members',
			array( 'affiliate_code' => $new_code ),
			array( 'member_id' => $member_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => __( 'Unable to update referral code.', 'smoketree-plugin' ) ), 500 );
		}

		wp_send_json_success( array( 'affiliate_code' => $new_code ) );
	}

	/**
	 * Run affiliate-code backfill on demand (admin).
	 *
	 * @since    1.4.2
	 * @return   void
	 */
	public function run_affiliate_backfill(): void {
		$this->assert_admin_request();

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-discount-service.php';
		$result = STSRC_Discount_Service::backfill_affiliate_codes();
		$errors = is_array( $result['errors'] ?? null ) ? $result['errors'] : array();

		if ( empty( $errors ) ) {
			update_option( 'stsrc_affiliate_code_backfill_done', '1' );
		}

		wp_send_json_success(
			array(
				'processed' => (int) ( $result['processed'] ?? 0 ),
				'skipped'   => (int) ( $result['skipped'] ?? 0 ),
				'errors'    => $errors,
			)
		);
	}

	/**
	 * Validate admin AJAX permissions and nonce.
	 *
	 * @since    1.4.2
	 * @return   void
	 */
	private function assert_admin_request(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'smoketree-plugin' ) ), 403 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'stsrc_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'smoketree-plugin' ) ), 403 );
		}
	}

	/**
	 * Sanitize incoming promo-code payload.
	 *
	 * @since    1.4.2
	 * @return   array
	 */
	private function sanitize_promo_payload(): array {
		$discount_values = array();
		if ( isset( $_POST['discount_values'] ) ) {
			$raw_values = wp_unslash( $_POST['discount_values'] );
			if ( is_array( $raw_values ) ) {
				foreach ( $raw_values as $type_id => $value ) {
					$type_id = absint( $type_id );
					$value   = (float) $value;
					if ( $type_id > 0 && $value > 0 ) {
						$discount_values[ $type_id ] = $value;
					}
				}
			} elseif ( is_string( $raw_values ) && '' !== $raw_values ) {
				$decoded = json_decode( $raw_values, true );
				if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
					foreach ( $decoded as $type_id => $value ) {
						$type_id = absint( $type_id );
						$value   = (float) $value;
						if ( $type_id > 0 && $value > 0 ) {
							$discount_values[ $type_id ] = $value;
						}
					}
				}
			}
		}

		return array(
			'code_name'       => isset( $_POST['code_name'] ) ? sanitize_text_field( wp_unslash( $_POST['code_name'] ) ) : '',
			'discount_type'   => isset( $_POST['discount_type'] ) ? sanitize_text_field( wp_unslash( $_POST['discount_type'] ) ) : '',
			'discount_values' => $discount_values,
			'expires_at'      => isset( $_POST['expires_at'] ) ? sanitize_text_field( wp_unslash( $_POST['expires_at'] ) ) : null,
			'is_one_time_use' => isset( $_POST['is_one_time_use'] ) ? absint( wp_unslash( $_POST['is_one_time_use'] ) ) : 0,
			'usage_limit'     => isset( $_POST['usage_limit'] ) && '' !== wp_unslash( $_POST['usage_limit'] ) ? absint( wp_unslash( $_POST['usage_limit'] ) ) : null,
			'is_active'       => isset( $_POST['is_active'] ) ? absint( wp_unslash( $_POST['is_active'] ) ) : 0,
		);
	}
}

