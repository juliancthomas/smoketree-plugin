<?php

/**
 * Discount service class.
 *
 * Handles promo/referral validation, affiliate code generation, and usage recording.
 *
 * @link       https://smoketree.us
 * @since      1.4.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/services
 */
class STSRC_Discount_Service {

	/**
	 * Validate a promo code against business rules.
	 *
	 * @since    1.4.0
	 * @param    string $code               Promo code text.
	 * @param    int    $membership_type_id Selected membership type ID.
	 * @return   array|WP_Error
	 */
	public static function validate_promo_code( string $code, int $membership_type_id ): array|WP_Error {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-promo-codes-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';

		$normalized_code = strtoupper( sanitize_text_field( $code ) );
		if ( '' === $normalized_code ) {
			return new WP_Error( 'invalid_promo_code', __( 'Invalid promo code.', 'smoketree-plugin' ) );
		}

		$promo = STSRC_Promo_Codes_DB::get_code_by_name( $normalized_code );
		if ( ! $promo ) {
			return new WP_Error( 'invalid_promo_code', __( 'Invalid promo code.', 'smoketree-plugin' ) );
		}

		if ( (int) $promo->is_active !== 1 ) {
			return new WP_Error( 'inactive_promo_code', __( 'This promo code is no longer active.', 'smoketree-plugin' ) );
		}

		if ( ! empty( $promo->expires_at ) && strtotime( (string) $promo->expires_at ) < current_time( 'timestamp' ) ) {
			return new WP_Error( 'expired_promo_code', __( 'This promo code has expired.', 'smoketree-plugin' ) );
		}

		global $wpdb;
		$usage_table = $wpdb->prefix . 'stsrc_promo_code_usages';

		$usage_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$usage_table} WHERE code_id = %d",
				(int) $promo->code_id
			)
		);

		if ( (int) $promo->is_one_time_use === 1 && $usage_count > 0 ) {
			return new WP_Error( 'promo_code_consumed', __( 'This promo code has already been used.', 'smoketree-plugin' ) );
		}

		if ( ! empty( $promo->usage_limit ) && $usage_count >= (int) $promo->usage_limit ) {
			return new WP_Error( 'promo_usage_limit_reached', __( "This promo code's usage limit has been reached.", 'smoketree-plugin' ) );
		}

		$discount_values = json_decode( (string) ( $promo->discount_values ?? '{}' ), true );
		if ( ! is_array( $discount_values ) ) {
			$discount_values = array();
		}

		$type_key       = (string) $membership_type_id;
		$discount_value = isset( $discount_values[ $type_key ] ) ? (float) $discount_values[ $type_key ] : 0.00;

		if ( $discount_value <= 0 ) {
			return new WP_Error( 'invalid_membership_type', __( 'This promo code is not valid for the selected membership type.', 'smoketree-plugin' ) );
		}

		$membership_type = STSRC_Membership_DB::get_membership_type( $membership_type_id );
		if ( ! $membership_type ) {
			return new WP_Error( 'invalid_membership_type', __( 'Please select a valid membership type.', 'smoketree-plugin' ) );
		}

		$base_price       = (float) ( $membership_type['price'] ?? 0 );
		$computed_amount  = self::compute_discounted_total( $base_price, (string) $promo->discount_type, $discount_value );
		$discount_applied = max( 0.00, $base_price - $computed_amount );

		$label = 'Promo: ' . (string) $promo->code_name . ' - -$' . number_format( $discount_applied, 2 );

		return array(
			'code_id'          => (int) $promo->code_id,
			'discount_type'    => (string) $promo->discount_type,
			'discount_value'   => $discount_value,
			'computed_amount'  => $discount_applied,
			'label'            => $label,
			'base_amount'      => $base_price,
			'final_amount'     => $computed_amount,
			'code'             => (string) $promo->code_name,
		);
	}

	/**
	 * Validate an affiliate code.
	 *
	 * @since    1.4.0
	 * @param    string $code Affiliate code.
	 * @return   array|WP_Error
	 */
	public static function validate_affiliate_code( string $code ): array|WP_Error {
		global $wpdb;

		$members_table = $wpdb->prefix . 'stsrc_members';
		$normalized    = strtoupper( sanitize_text_field( $code ) );

		if ( '' === $normalized ) {
			return new WP_Error( 'invalid_referral_code', __( 'Invalid referral code.', 'smoketree-plugin' ) );
		}

		$member = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT member_id, first_name, last_name, email, status, affiliate_code
				FROM {$members_table}
				WHERE UPPER(affiliate_code) = UPPER(%s)
				LIMIT 1",
				$normalized
			)
		);

		if ( ! $member ) {
			return new WP_Error( 'invalid_referral_code', __( 'Invalid referral code.', 'smoketree-plugin' ) );
		}

		if ( 'active' !== (string) $member->status ) {
			return new WP_Error( 'inactive_referral_code', __( 'This referral code is no longer active.', 'smoketree-plugin' ) );
		}

		$discount_amount = self::get_affiliate_new_member_discount();
		$label           = 'Referral Discount - -$' . number_format( $discount_amount, 2 );

		return array(
			'referrer_member_id' => (int) $member->member_id,
			'referrer_name'      => trim( (string) $member->first_name . ' ' . (string) $member->last_name ),
			'referrer_email'     => (string) $member->email,
			'discount_amount'    => $discount_amount,
			'label'              => $label,
			'code'               => (string) $member->affiliate_code,
		);
	}

	/**
	 * Compute final total after a discount.
	 *
	 * @since    1.4.0
	 * @param    float  $base           Original amount.
	 * @param    string $discount_type  flat|percentage.
	 * @param    float  $discount_value Discount value.
	 * @return   float
	 */
	public static function compute_discounted_total( float $base, string $discount_type, float $discount_value ): float {
		$base = max( 0.00, $base );

		if ( 'percentage' === $discount_type ) {
			$discount_amount = round( $base * ( $discount_value / 100 ), 2 );
		} else {
			$discount_amount = $discount_value;
		}

		return max( 0.00, round( $base - $discount_amount, 2 ) );
	}

	/**
	 * Generate a unique affiliate code.
	 *
	 * @since    1.4.0
	 * @param    string $last_name Member last name.
	 * @return   string
	 */
	public static function generate_affiliate_code( string $last_name ): string {
		global $wpdb;

		$members_table = $wpdb->prefix . 'stsrc_members';
		$name_part     = strtoupper( preg_replace( '/[^A-Za-z]/', '', $last_name ) );
		$name_part     = substr( $name_part, 0, 10 );

		if ( '' === $name_part ) {
			$name_part = 'MEMBER';
		}

		for ( $attempt = 1; $attempt <= 20; $attempt++ ) {
			$suffix = str_pad( (string) random_int( 0, 9999 ), 4, '0', STR_PAD_LEFT );
			$code   = sprintf( 'REF-%s-%s', $name_part, $suffix );

			$exists = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$members_table} WHERE affiliate_code = %s",
					$code
				)
			);

			if ( 0 === $exists ) {
				return $code;
			}
		}

		do {
			$suffix = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
			$code   = sprintf( 'REF-%s-%s', $name_part, $suffix );
			$exists = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$members_table} WHERE affiliate_code = %s",
					$code
				)
			);
		} while ( $exists > 0 );

		return $code;
	}

	/**
	 * Backfill affiliate codes for members that do not have one.
	 *
	 * @since    1.4.0
	 * @return   array
	 */
	public static function backfill_affiliate_codes(): array {
		global $wpdb;

		$members_table = $wpdb->prefix . 'stsrc_members';
		$processed     = 0;
		$skipped       = 0;
		$errors        = array();
		$limit         = 100;

		while ( true ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT member_id, last_name, affiliate_code
					FROM {$members_table}
					WHERE affiliate_code IS NULL OR affiliate_code = ''
					ORDER BY member_id ASC
					LIMIT %d",
					$limit
				)
			);

			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				if ( ! empty( $row->affiliate_code ) ) {
					$skipped++;
					continue;
				}

				$code = self::generate_affiliate_code( (string) $row->last_name );
				$ok   = $wpdb->update(
					$members_table,
					array( 'affiliate_code' => $code ),
					array( 'member_id' => (int) $row->member_id ),
					array( '%s' ),
					array( '%d' )
				);

				if ( false === $ok ) {
					$errors[] = sprintf( 'Failed to set affiliate code for member_id=%d', (int) $row->member_id );
					continue;
				}

				$processed++;
			}
		}

		return array(
			'processed' => $processed,
			'skipped'   => $skipped,
			'errors'    => $errors,
		);
	}

	/**
	 * Record usage for promo or affiliate discount payloads.
	 *
	 * @since    1.4.0
	 * @param    int   $member_id         Newly registered member ID.
	 * @param    array $discount_payload  Discount payload.
	 * @return   void
	 */
	public static function record_discount_usage( int $member_id, array $discount_payload ): void {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-promo-codes-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-affiliate-referrals-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-email-service.php';

		if ( empty( $discount_payload['type'] ) ) {
			return;
		}

		if ( 'promo' === $discount_payload['type'] ) {
			self::record_promo_usage( $member_id, $discount_payload );
			return;
		}

		if ( 'affiliate' === $discount_payload['type'] ) {
			self::record_affiliate_usage( $member_id, $discount_payload );
		}
	}

	/**
	 * Persist promo-code usage with one-time guard.
	 *
	 * @since    1.4.0
	 * @param    int   $member_id Newly registered member ID.
	 * @param    array $payload   Discount payload.
	 * @return   void
	 */
	private static function record_promo_usage( int $member_id, array $payload ): void {
		global $wpdb;

		$promo_table = $wpdb->prefix . 'stsrc_promo_codes';
		$usage_table = $wpdb->prefix . 'stsrc_promo_code_usages';
		$code_id     = isset( $payload['code_id'] ) ? (int) $payload['code_id'] : 0;

		if ( $code_id <= 0 ) {
			return;
		}

		$already_used_by_member = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$usage_table} WHERE member_id = %d AND code_id = %d",
				$member_id,
				$code_id
			)
		);

		if ( $already_used_by_member > 0 ) {
			return;
		}

		$wpdb->query( 'START TRANSACTION' );

		$promo_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT code_id, is_one_time_use FROM {$promo_table} WHERE code_id = %d FOR UPDATE",
				$code_id
			)
		);

		if ( ! $promo_row ) {
			$wpdb->query( 'ROLLBACK' );
			return;
		}

		if ( (int) $promo_row->is_one_time_use === 1 ) {
			$existing_usage = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$usage_table} WHERE code_id = %d",
					$code_id
				)
			);

			if ( $existing_usage > 0 ) {
				$wpdb->query( 'ROLLBACK' );
				return;
			}
		}

		$usage_id = STSRC_Promo_Codes_DB::record_usage(
			$code_id,
			$member_id,
			(float) ( $payload['discount_amount'] ?? 0 ),
			(int) ( $payload['membership_type_id'] ?? 0 )
		);

		if ( $usage_id > 0 ) {
			STSRC_Promo_Codes_DB::increment_usage_count( $code_id );
			$wpdb->query( 'COMMIT' );
		} else {
			$wpdb->query( 'ROLLBACK' );
		}
	}

	/**
	 * Persist affiliate referral usage with idempotency.
	 *
	 * @since    1.4.0
	 * @param    int   $member_id Newly registered member ID.
	 * @param    array $payload   Discount payload.
	 * @return   void
	 */
	private static function record_affiliate_usage( int $member_id, array $payload ): void {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';

		$existing = STSRC_Affiliate_Referrals_DB::get_by_new_member_id( $member_id );
		if ( $existing ) {
			return;
		}

		$referrer_member_id = isset( $payload['referrer_member_id'] ) ? (int) $payload['referrer_member_id'] : 0;
		if ( $referrer_member_id <= 0 ) {
			return;
		}

		$credit_amount = self::get_affiliate_referrer_credit();
		$discount      = isset( $payload['discount_amount'] ) ? (float) $payload['discount_amount'] : self::get_affiliate_new_member_discount();

		$result = STSRC_Affiliate_Referrals_DB::create_referral(
			array(
				'referral_code'       => (string) ( $payload['code'] ?? '' ),
				'referrer_member_id'  => $referrer_member_id,
				'new_member_id'       => $member_id,
				'new_member_discount' => $discount,
				'referrer_credit'     => $credit_amount,
				'payout_status'       => 'pending',
			)
		);

		if ( is_wp_error( $result ) ) {
			return;
		}

		$referrer = STSRC_Member_DB::get_member( $referrer_member_id );
		$new      = STSRC_Member_DB::get_member( $member_id );

		if ( ! $referrer || ! $new ) {
			return;
		}

		$treasurer_email = sanitize_email( (string) get_option( 'stsrc_treasurer_email', '' ) );
		if ( '' === $treasurer_email ) {
			return;
		}

		$email_service = new STSRC_Email_Service();
		$email_service->send_email(
			'treasurer-referral-credit.php',
			array(
				'referrer_name'    => trim( (string) $referrer['first_name'] . ' ' . (string) $referrer['last_name'] ),
				'referrer_email'   => (string) ( $referrer['email'] ?? '' ),
				'new_member_name'  => trim( (string) $new['first_name'] . ' ' . (string) $new['last_name'] ),
				'new_member_email' => (string) ( $new['email'] ?? '' ),
				'credit_amount'    => $credit_amount,
				'registration_date'=> current_time( 'mysql' ),
			),
			$treasurer_email,
			sprintf(
				/* translators: %s: referrer name */
				__( 'Referral Credit Due — %s', 'smoketree-plugin' ),
				trim( (string) $referrer['first_name'] . ' ' . (string) $referrer['last_name'] )
			)
		);
	}

	/**
	 * Get configured affiliate discount amount with fallback.
	 *
	 * @since    1.4.0
	 * @return   float
	 */
	private static function get_affiliate_new_member_discount(): float {
		$value = get_option( 'stsrc_affiliate_new_member_discount', 500 );
		if ( function_exists( 'get_field' ) ) {
			$field_value = call_user_func( 'get_field', 'stsrc_affiliate_new_member_discount', 'option' );
			if ( null !== $field_value && '' !== $field_value ) {
				$value = $field_value;
			}
		}

		return max( 0.00, (float) $value );
	}

	/**
	 * Get configured affiliate credit amount with fallback.
	 *
	 * @since    1.4.0
	 * @return   float
	 */
	private static function get_affiliate_referrer_credit(): float {
		$value = get_option( 'stsrc_affiliate_referrer_credit', 50 );
		if ( function_exists( 'get_field' ) ) {
			$field_value = call_user_func( 'get_field', 'stsrc_affiliate_referrer_credit', 'option' );
			if ( null !== $field_value && '' !== $field_value ) {
				$value = $field_value;
			}
		}

		return max( 0.00, (float) $value );
	}
}

