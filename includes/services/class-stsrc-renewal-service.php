<?php
/**
 * Renewal domain service.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/services
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-renewal-db.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'helpers/class-stsrc-renewal-helpers.php';

/**
 * Renewal service class.
 */
class STSRC_Renewal_Service {

	/**
	 * Get eligibility and idempotency details for a member renewal attempt.
	 *
	 * @param int         $member_id Member ID.
	 * @param string|null $season_key Optional season key.
	 * @return array{
	 *   eligible:bool,
	 *   reason:string,
	 *   season_key:string,
	 *   member_id:int,
	 *   existing_renewal:?array
	 * }
	 */
	public function get_eligibility( int $member_id, ?string $season_key = null ): array {
		$resolved_season_key = $this->resolve_season_key( $season_key );

		if ( ! STSRC_Renewal_Helpers::is_renewal_enabled() ) {
			return array(
				'eligible'         => false,
				'reason'           => 'renewal_disabled',
				'season_key'       => $resolved_season_key,
				'member_id'        => $member_id,
				'existing_renewal' => null,
			);
		}

		$member = STSRC_Member_DB::get_member( $member_id );
		if ( empty( $member ) ) {
			return array(
				'eligible'         => false,
				'reason'           => 'member_not_found',
				'season_key'       => $resolved_season_key,
				'member_id'        => $member_id,
				'existing_renewal' => null,
			);
		}

		if ( ! STSRC_Renewal_Helpers::is_member_eligible_for_current_season( $member ) ) {
			return array(
				'eligible'         => false,
				'reason'           => 'member_not_eligible',
				'season_key'       => $resolved_season_key,
				'member_id'        => $member_id,
				'existing_renewal' => null,
			);
		}

		$eligibility = STSRC_Renewal_DB::get_eligibility( $member_id, $resolved_season_key );

		return array(
			'eligible'         => (bool) ( $eligibility['eligible'] ?? false ),
			'reason'           => (string) ( $eligibility['reason'] ?? 'unknown' ),
			'season_key'       => $resolved_season_key,
			'member_id'        => $member_id,
			'existing_renewal' => $eligibility['existing_renewal'] ?? null,
		);
	}

	/**
	 * Return an existing in-flight/completed renewal to enforce idempotency.
	 *
	 * @param int         $member_id Member ID.
	 * @param string|null $season_key Optional season key.
	 * @return array|null
	 */
	public function get_idempotent_submission_guard( int $member_id, ?string $season_key = null ): ?array {
		$resolved_season_key = $this->resolve_season_key( $season_key );

		return STSRC_Renewal_DB::find_idempotent_renewal( $member_id, $resolved_season_key );
	}

	/**
	 * Build deterministic duplicate-submission response payload.
	 *
	 * @param int         $member_id Member ID.
	 * @param string|null $season_key Optional season key.
	 * @return array{is_duplicate:bool,reason:string,existing_renewal:?array}
	 */
	public function guard_repeated_submission( int $member_id, ?string $season_key = null ): array {
		$resolved_season_key = $this->resolve_season_key( $season_key );
		$existing            = $this->get_idempotent_submission_guard( $member_id, $resolved_season_key );

		if ( empty( $existing ) ) {
			return array(
				'is_duplicate'    => false,
				'reason'          => 'ok_to_submit',
				'existing_renewal' => null,
			);
		}

		$status = (string) ( $existing['status'] ?? '' );
		$reason = STSRC_Renewal_DB::STATUS_COMPLETED === $status
			? 'already_completed'
			: 'already_in_progress';

		return array(
			'is_duplicate'    => true,
			'reason'          => $reason,
			'existing_renewal' => $existing,
		);
	}

	/**
	 * Resolve season key from input or settings.
	 *
	 * @param string|null $season_key Requested season key.
	 * @return string
	 */
	private function resolve_season_key( ?string $season_key ): string {
		$season_key = sanitize_text_field( (string) $season_key );

		if ( '' !== $season_key ) {
			return $season_key;
		}

		return STSRC_Renewal_Helpers::get_season_key();
	}
}

