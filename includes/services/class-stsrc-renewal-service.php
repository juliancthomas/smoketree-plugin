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
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-family-member-db.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-extra-member-db.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'helpers/class-stsrc-renewal-helpers.php';

/**
 * Renewal service class.
 */
class STSRC_Renewal_Service {
	private const TYPE_HOUSEHOLD = 'household';
	private const TYPE_DUO       = 'duo';
	private const TYPE_SINGLE    = 'single';
	private const TYPE_CIVIC     = 'civic';

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
	 * Validate membership transition and produce reconciliation actions.
	 *
	 * @param int   $member_id Member ID.
	 * @param int   $target_membership_type_id Target membership type ID.
	 * @param array $payload Transition payload from client.
	 * @return array{
	 *   valid:bool,
	 *   errors:string[],
	 *   warnings:string[],
	 *   actions:array<string,mixed>
	 * }
	 */
	public function validate_transition( int $member_id, int $target_membership_type_id, array $payload = array() ): array {
		$member      = STSRC_Member_DB::get_member( $member_id );
		$target_type = STSRC_Membership_DB::get_membership_type( $target_membership_type_id );

		if ( empty( $member ) || empty( $target_type ) ) {
			return array(
				'valid'    => false,
				'errors'   => array( 'invalid_member_or_target_type' ),
				'warnings' => array(),
				'actions'  => array(),
			);
		}

		$current_type = STSRC_Membership_DB::get_membership_type( (int) ( $member['membership_type_id'] ?? 0 ) );
		if ( empty( $current_type ) ) {
			return array(
				'valid'    => false,
				'errors'   => array( 'invalid_current_membership_type' ),
				'warnings' => array(),
				'actions'  => array(),
			);
		}

		$current_type_name = strtolower( (string) ( $current_type['name'] ?? '' ) );
		$target_type_name  = strtolower( (string) ( $target_type['name'] ?? '' ) );
		$active_family_ids = STSRC_Family_Member_DB::get_active_ids_by_member( $member_id );
		$active_extra_ids  = STSRC_Extra_Member_DB::get_active_ids_by_member( $member_id );
		$retain_family_ids = $this->normalize_ids( $payload['retain_family_member_ids'] ?? array() );
		$retain_extra_ids  = $this->normalize_ids( $payload['retain_extra_member_ids'] ?? array() );
		$new_family_count  = max( 0, absint( $payload['new_family_member_count'] ?? 0 ) );
		$new_extra_count   = max( 0, absint( $payload['new_extra_member_count'] ?? 0 ) );
		$errors            = array();
		$warnings          = array();

		if ( ! STSRC_Family_Member_DB::member_owns_ids( $member_id, $retain_family_ids ) ) {
			$errors[] = 'invalid_retained_family_members';
		}

		if ( ! STSRC_Extra_Member_DB::member_owns_ids( $member_id, $retain_extra_ids ) ) {
			$errors[] = 'invalid_retained_extra_members';
		}

		$required_family_count = $this->get_required_family_count_by_type( $target_type_name );
		$resulting_family_count = count( $retain_family_ids ) + $new_family_count;

		if ( $resulting_family_count < $required_family_count ) {
			$errors[] = 'insufficient_family_members';
		}

		if ( self::TYPE_HOUSEHOLD === $current_type_name && self::TYPE_DUO === $target_type_name && 1 !== count( $retain_family_ids ) ) {
			$errors[] = 'household_to_duo_requires_one_retained_family_member';
		}

		if (
			self::TYPE_HOUSEHOLD === $current_type_name &&
			in_array( $target_type_name, array( self::TYPE_SINGLE, self::TYPE_CIVIC ), true ) &&
			! empty( $retain_family_ids )
		) {
			$errors[] = 'household_to_single_or_civic_disallows_retained_family_members';
		}

		$resulting_extra_count = count( $retain_extra_ids ) + $new_extra_count;
		if ( self::TYPE_HOUSEHOLD !== $target_type_name ) {
			if ( $resulting_extra_count > 0 ) {
				$warnings[] = 'extra_members_will_be_removed_for_non_household_membership';
			}
			$retain_extra_ids = array();
			$new_extra_count  = 0;
		}

		if ( self::TYPE_HOUSEHOLD === $target_type_name && ! STSRC_Extra_Member_DB::is_valid_household_extra_count( $resulting_extra_count ) ) {
			$errors[] = 'household_extra_member_limit_exceeded';
		}

		$soft_delete_family_ids = array_values( array_diff( $active_family_ids, $retain_family_ids ) );
		$soft_delete_extra_ids  = array_values( array_diff( $active_extra_ids, $retain_extra_ids ) );

		return array(
			'valid'    => empty( $errors ),
			'errors'   => $errors,
			'warnings' => $warnings,
			'actions'  => array(
				'current_membership_type'  => $current_type_name,
				'target_membership_type'   => $target_type_name,
				'required_family_count'    => $required_family_count,
				'resulting_family_count'   => $resulting_family_count,
				'resulting_extra_count'    => $resulting_extra_count,
				'retain_family_ids'        => $retain_family_ids,
				'soft_delete_family_ids'   => $soft_delete_family_ids,
				'retain_extra_ids'         => $retain_extra_ids,
				'soft_delete_extra_ids'    => $soft_delete_extra_ids,
				'new_family_member_count'  => $new_family_count,
				'new_extra_member_count'   => $new_extra_count,
			),
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

	/**
	 * Normalize arbitrary ID arrays to sorted unique integer IDs.
	 *
	 * @param mixed $ids Input IDs.
	 * @return int[]
	 */
	private function normalize_ids( $ids ): array {
		if ( ! is_array( $ids ) ) {
			return array();
		}

		$normalized = array_values( array_unique( array_map( 'absint', $ids ) ) );
		$normalized = array_filter( $normalized );
		sort( $normalized );

		return $normalized;
	}

	/**
	 * Get minimum required active family count for target membership type.
	 *
	 * @param string $type_name Normalized membership type name.
	 * @return int
	 */
	private function get_required_family_count_by_type( string $type_name ): int {
		return match ( $type_name ) {
			self::TYPE_HOUSEHOLD => 2,
			self::TYPE_DUO => 1,
			default => 0,
		};
	}
}

