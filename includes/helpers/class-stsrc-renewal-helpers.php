<?php
/**
 * Renewal helper utilities.
 *
 * Centralizes feature-gate and season-key resolution helpers for the
 * member renewal experience.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/helpers
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renewal helpers class.
 */
class STSRC_Renewal_Helpers {

	/**
	 * Check whether the renewal feature is enabled.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public static function is_renewal_enabled(): bool {
		$value = function_exists( 'get_field' )
			? get_field( 'stsrc_renewal_enabled', 'option' )
			: get_option( 'stsrc_renewal_enabled', '0' );

		return ! empty( $value ) && '0' !== (string) $value;
	}

	/**
	 * Resolve the configured season renewal date.
	 *
	 * @since  1.0.0
	 * @return string Y-m-d date when configured, empty string otherwise.
	 */
	public static function get_season_renewal_date(): string {
		$date = function_exists( 'get_field' )
			? (string) get_field( 'stsrc_season_renewal_date', 'option' )
			: (string) get_option( 'stsrc_season_renewal_date', '' );

		$date = sanitize_text_field( $date );
		$ts   = strtotime( $date );

		if ( false === $ts ) {
			return '';
		}

		return gmdate( 'Y-m-d', $ts );
	}

	/**
	 * Resolve the renewal season key (YYYY).
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public static function get_season_key(): string {
		$renewal_date = self::get_season_renewal_date();

		if ( empty( $renewal_date ) ) {
			return gmdate( 'Y' );
		}

		$ts = strtotime( $renewal_date );
		if ( false === $ts ) {
			return gmdate( 'Y' );
		}

		return gmdate( 'Y', $ts );
	}

	/**
	 * Check whether a member is eligible for renewal portal actions.
	 *
	 * Step 1 eligibility is intentionally conservative and only checks account
	 * context/state. Season idempotency checks are added when renewal ledger
	 * persistence is introduced in subsequent implementation steps.
	 *
	 * @since  1.0.0
	 * @param  array $member Member record.
	 * @return bool
	 */
	public static function is_member_eligible_for_current_season( array $member ): bool {
		$member_id          = (int) ( $member['member_id'] ?? 0 );
		$membership_type_id = (int) ( $member['membership_type_id'] ?? 0 );
		$status             = sanitize_key( (string) ( $member['status'] ?? '' ) );

		if ( $member_id <= 0 || $membership_type_id <= 0 ) {
			return false;
		}

		return 'cancelled' !== $status;
	}
}

