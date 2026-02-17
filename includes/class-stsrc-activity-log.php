<?php
/**
 * Activity logging helper for balance-related audit events.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activity log utility class.
 */
class STSRC_Activity_Log {

	/**
	 * Option key storing activity log entries.
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'stsrc_balance_activity_log';

	/**
	 * Maximum number of stored entries.
	 *
	 * @var int
	 */
	private const MAX_ENTRIES = 500;

	/**
	 * Log a balance-related activity event.
	 *
	 * @param int    $member_id     Member ID.
	 * @param string $action_type   Action key, e.g. "manual_payment_recorded".
	 * @param array  $details       Additional context fields.
	 * @param int    $admin_user_id Admin user ID who triggered action.
	 * @return bool                 True when persisted.
	 */
	public static function log_balance_activity( int $member_id, string $action_type, array $details = array(), int $admin_user_id = 0 ): bool {
		$entry = array(
			'timestamp'     => current_time( 'mysql' ),
			'member_id'     => $member_id,
			'action_type'   => sanitize_key( $action_type ),
			'details'       => self::sanitize_details( $details ),
			'admin_user_id' => $admin_user_id > 0 ? $admin_user_id : get_current_user_id(),
		);

		$log = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift( $log, $entry );
		if ( count( $log ) > self::MAX_ENTRIES ) {
			$log = array_slice( $log, 0, self::MAX_ENTRIES );
		}

		return update_option( self::OPTION_KEY, $log, false );
	}

	/**
	 * Sanitize details payload recursively.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return mixed
	 */
	private static function sanitize_details( $value ) {
		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $key => $item ) {
				$clean_key          = is_string( $key ) ? sanitize_key( $key ) : $key;
				$clean[ $clean_key ] = self::sanitize_details( $item );
			}
			return $clean;
		}

		if ( is_numeric( $value ) ) {
			return $value + 0;
		}

		if ( is_bool( $value ) || null === $value ) {
			return $value;
		}

		return sanitize_text_field( (string) $value );
	}
}
