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
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-transaction-db.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'helpers/class-stsrc-renewal-helpers.php';
require_once plugin_dir_path( __FILE__ ) . 'class-stsrc-renewal-pricing-service.php';
require_once plugin_dir_path( __FILE__ ) . 'class-stsrc-payment-service.php';
require_once plugin_dir_path( __FILE__ ) . 'class-stsrc-logger.php';

/**
 * Renewal service class.
 */
class STSRC_Renewal_Service {
	private const TYPE_HOUSEHOLD    = 'household';
	private const TYPE_DUO         = 'duo';
	private const TYPE_SINGLE      = 'single';
	private const TYPE_CIVIC       = 'civic';
	private const MAX_FAMILY_HOUSEHOLD = 4;

	/**
	 * Quote pricing for a requested transition and payment method.
	 *
	 * @param int    $member_id Member ID.
	 * @param int    $target_membership_type_id Target membership type ID.
	 * @param string $payment_method Requested payment method.
	 * @param array  $payload Transition payload.
	 * @return array{
	 *   valid:bool,
	 *   errors:string[],
	 *   transition:array<string,mixed>,
	 *   quote:?array
	 * }
	 */
	public function get_quote( int $member_id, int $target_membership_type_id, string $payment_method, array $payload = array() ): array {
		$transition = $this->validate_transition( $member_id, $target_membership_type_id, $payload );
		if ( empty( $transition['valid'] ) ) {
			return array(
				'valid'      => false,
				'errors'     => $transition['errors'] ?? array( 'invalid_transition' ),
				'transition' => $transition,
				'quote'      => null,
			);
		}

		$member      = STSRC_Member_DB::get_member( $member_id );
		$target_type = STSRC_Membership_DB::get_membership_type( $target_membership_type_id );
		if ( empty( $member ) || empty( $target_type ) ) {
			return array(
				'valid'      => false,
				'errors'     => array( 'invalid_member_or_target_type' ),
				'transition' => $transition,
				'quote'      => null,
			);
		}

		$pricing_service = new STSRC_Renewal_Pricing_Service();
		$quote           = $pricing_service->calculate_quote(
			(float) ( $target_type['price'] ?? 0.00 ),
			(int) ( $transition['actions']['resulting_extra_count'] ?? 0 ),
			(float) ( $member['balance_owed'] ?? 0.00 ),
			$payment_method
		);

		return array(
			'valid'      => true,
			'errors'     => array(),
			'transition' => $transition,
			'quote'      => $quote,
		);
	}

	/**
	 * Build deterministic submit context (eligibility + idempotency + quote).
	 *
	 * @param int    $member_id Member ID.
	 * @param int    $target_membership_type_id Target membership type ID.
	 * @param string $payment_method Payment method.
	 * @param array  $payload Transition payload.
	 * @param string|null $season_key Optional season key.
	 * @return array<string,mixed>
	 */
	public function build_submit_context(
		int $member_id,
		int $target_membership_type_id,
		string $payment_method,
		array $payload = array(),
		?string $season_key = null
	): array {
		$eligibility = $this->get_eligibility( $member_id, $season_key );
		if ( empty( $eligibility['eligible'] ) ) {
			return array(
				'can_submit'  => false,
				'reason'      => $eligibility['reason'] ?? 'not_eligible',
				'eligibility' => $eligibility,
				'duplicate'   => null,
				'pricing'     => null,
			);
		}

		$duplicate = $this->guard_repeated_submission( $member_id, $season_key );
		if ( ! empty( $duplicate['is_duplicate'] ) ) {
			return array(
				'can_submit'  => false,
				'reason'      => $duplicate['reason'] ?? 'duplicate_submission',
				'eligibility' => $eligibility,
				'duplicate'   => $duplicate,
				'pricing'     => null,
			);
		}

		$pricing = $this->get_quote( $member_id, $target_membership_type_id, $payment_method, $payload );

		return array(
			'can_submit'  => ! empty( $pricing['valid'] ),
			'reason'      => ! empty( $pricing['valid'] ) ? 'ready_to_submit' : 'invalid_quote',
			'eligibility' => $eligibility,
			'duplicate'   => $duplicate,
			'pricing'     => $pricing,
		);
	}

	/**
	 * Persist a renewal intent snapshot before payment processing.
	 *
	 * @param int         $member_id Member ID.
	 * @param int         $target_membership_type_id Target membership type ID.
	 * @param string      $payment_method Selected payment method.
	 * @param array       $payload Transition payload.
	 * @param string|null $season_key Optional season key.
	 * @return array<string,mixed>
	 */
	public function create_intent(
		int $member_id,
		int $target_membership_type_id,
		string $payment_method,
		array $payload = array(),
		?string $season_key = null
	): array {
		$context = $this->build_submit_context(
			$member_id,
			$target_membership_type_id,
			$payment_method,
			$payload,
			$season_key
		);

		if ( empty( $context['can_submit'] ) ) {
			return array(
				'status' => 'rejected',
				'reason' => $context['reason'] ?? 'cannot_submit',
				'context' => $context,
			);
		}

		$member = STSRC_Member_DB::get_member( $member_id );
		if ( empty( $member ) ) {
			return array(
				'status'  => 'error',
				'reason'  => 'member_not_found',
				'context' => $context,
			);
		}

		$season_key_resolved = (string) ( $context['eligibility']['season_key'] ?? $this->resolve_season_key( $season_key ) );
		$pricing             = $context['pricing'] ?? array();
		$transition          = $pricing['transition'] ?? array();
		$quote               = $pricing['quote'] ?? array();
		$actions             = $transition['actions'] ?? array();

		$created_ids = $this->create_new_members_on_submit( $member_id, $actions );
		if ( ! empty( $created_ids['family_ids'] ) ) {
			$actions['retain_family_ids'] = array_values( array_unique(
				array_merge( $actions['retain_family_ids'] ?? array(), $created_ids['family_ids'] )
			) );
			$actions['created_family_ids'] = $created_ids['family_ids'];
		}
		if ( ! empty( $created_ids['extra_ids'] ) ) {
			$actions['retain_extra_ids'] = array_values( array_unique(
				array_merge( $actions['retain_extra_ids'] ?? array(), $created_ids['extra_ids'] )
			) );
			$actions['created_extra_ids'] = $created_ids['extra_ids'];
		}
		$transition['actions'] = $actions;

		$snapshot_json = wp_json_encode(
			array(
				'season_key'      => $season_key_resolved,
				'payment_method'  => sanitize_key( $payment_method ),
				'payload'         => $payload,
				'transition'      => $transition,
				'quote'           => $quote,
				'created_at'      => current_time( 'mysql' ),
			)
		);

		$renewal_id = STSRC_Renewal_DB::create_intent_record(
			$member_id,
			$season_key_resolved,
			(int) ( $member['membership_type_id'] ?? 0 ),
			$target_membership_type_id,
			sanitize_key( $payment_method ),
			$quote,
			(string) $snapshot_json
		);

		if ( false === $renewal_id ) {
			return array(
				'status'  => 'error',
				'reason'  => 'intent_create_failed',
				'context' => $context,
			);
		}

		$is_stripe_method = in_array( sanitize_key( $payment_method ), array( 'card', 'ach', 'bank_account', 'us_bank_account' ), true );
		$status           = STSRC_Renewal_DB::STATUS_INITIATED;
		$checkout         = null;

		if ( $is_stripe_method ) {
			$payment_service = new STSRC_Payment_Service();
			$target_type     = STSRC_Membership_DB::get_membership_type( $target_membership_type_id );
			$checkout        = $payment_service->create_renewal_checkout_session(
				$renewal_id,
				$member_id,
				$season_key_resolved,
				(string) ( $target_type['name'] ?? 'Membership' ),
				$quote,
				sanitize_key( $payment_method )
			);

			if ( false === $checkout ) {
				STSRC_Renewal_DB::transition_status(
					$renewal_id,
					array( STSRC_Renewal_DB::STATUS_INITIATED ),
					STSRC_Renewal_DB::STATUS_FAILED,
					array( 'notes' => 'Stripe checkout session creation failed.' )
				);

				return array(
					'status'  => 'error',
					'reason'  => 'stripe_checkout_create_failed',
					'context' => $context,
				);
			}

			STSRC_Renewal_DB::update_renewal(
				$renewal_id,
				array(
					'stripe_checkout_session_id' => $checkout['id'] ?? '',
				)
			);
		} else {
			STSRC_Renewal_DB::mark_pending_payment( $renewal_id );
			$status = STSRC_Renewal_DB::STATUS_PENDING_PAYMENT;

			STSRC_Member_DB::mark_pending_renewal_payment(
				$member_id,
				(float) ( $quote['total'] ?? 0.00 ),
				sanitize_key( $payment_method )
			);

			$renewal_record = STSRC_Renewal_DB::get_renewal( $renewal_id );
			if ( ! empty( $renewal_record ) ) {
				require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-email-service.php';
				$email_service = new STSRC_Email_Service();
				$email_service->send_admin_renewal_pending_notice( $renewal_record, $member );
				$email_service->send_member_renewal_pending_email( $renewal_record, $member );
			}
		}

		return array(
			'status'      => $status,
			'renewal_id'  => $renewal_id,
			'season_key'  => $season_key_resolved,
			'quote'       => $quote,
			'transition'  => $transition,
			'redirect_url' => ( $checkout['url'] ?? null ),
		);
	}

	/**
	 * Finalize Stripe checkout completion for renewal context.
	 *
	 * @param array  $session Checkout session payload from Stripe event.
	 * @param string $event_id Stripe event ID.
	 * @return array{applied:bool,reason:string,renewal_id?:int}
	 */
	public function finalize_stripe_renewal( array $session, string $event_id ): array {
		$session_id = sanitize_text_field( (string) ( $session['id'] ?? '' ) );
		$metadata   = $session['metadata'] ?? array();
		$renewal_id = absint( $metadata['renewal_id'] ?? 0 );

		if ( $renewal_id <= 0 && '' === $session_id ) {
			return array( 'applied' => false, 'reason' => 'missing_identifiers' );
		}

		$renewal = $renewal_id > 0
			? STSRC_Renewal_DB::get_renewal( $renewal_id )
			: STSRC_Renewal_DB::get_by_checkout_session_id( $session_id );

		if ( empty( $renewal ) ) {
			return array( 'applied' => false, 'reason' => 'renewal_not_found' );
		}

		$renewal_id = (int) ( $renewal['renewal_id'] ?? 0 );
		$status     = (string) ( $renewal['status'] ?? '' );

		if ( STSRC_Renewal_DB::STATUS_COMPLETED === $status ) {
			return array( 'applied' => false, 'reason' => 'already_completed', 'renewal_id' => $renewal_id );
		}

		$allowed_from = array(
			STSRC_Renewal_DB::STATUS_INITIATED,
			STSRC_Renewal_DB::STATUS_PENDING_PAYMENT,
		);

		if ( ! in_array( $status, $allowed_from, true ) ) {
			return array( 'applied' => false, 'reason' => 'invalid_status_transition', 'renewal_id' => $renewal_id );
		}

		$payment_intent_id = sanitize_text_field( (string) ( $session['payment_intent'] ?? '' ) );
		$expected_member   = absint( $metadata['member_id'] ?? 0 );
		$expected_season   = sanitize_text_field( (string) ( $metadata['season_key'] ?? '' ) );

		if ( $expected_member > 0 && $expected_member !== (int) ( $renewal['member_id'] ?? 0 ) ) {
			return array( 'applied' => false, 'reason' => 'member_mismatch', 'renewal_id' => $renewal_id );
		}

		if ( '' !== $expected_season && $expected_season !== (string) ( $renewal['season_key'] ?? '' ) ) {
			return array( 'applied' => false, 'reason' => 'season_mismatch', 'renewal_id' => $renewal_id );
		}

		if ( '' !== $session_id && $session_id !== (string) ( $renewal['stripe_checkout_session_id'] ?? '' ) ) {
			STSRC_Renewal_DB::update_renewal(
				$renewal_id,
				array( 'stripe_checkout_session_id' => $session_id )
			);
		}

		$completion = $this->complete_renewal_transaction(
			$renewal,
			$allowed_from,
			array(
				'stripe_payment_intent_id' => $payment_intent_id,
				'notes'                    => sprintf( 'Completed by Stripe webhook event %s', sanitize_text_field( $event_id ) ),
			)
		);
		if ( empty( $completion['applied'] ) ) {
			STSRC_Logger::warning(
				'Stripe renewal completion failed during transaction.',
				array(
					'method'     => __METHOD__,
					'renewal_id' => $renewal_id,
					'reason'     => (string) ( $completion['reason'] ?? 'unknown' ),
				)
			);
			return array(
				'applied'    => false,
				'reason'     => (string) ( $completion['reason'] ?? 'no_update_applied' ),
				'renewal_id' => $renewal_id,
			);
		}

		$member = STSRC_Member_DB::get_member( (int) ( $renewal['member_id'] ?? 0 ) );
		if ( ! empty( $member ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-email-service.php';
			$email_service = new STSRC_Email_Service();
			$email_service->send_member_renewal_confirmation( (int) ( $renewal['member_id'] ?? 0 ), $renewal, $member );
			$email_service->send_admin_renewal_notice( $renewal, $member );
		}
		STSRC_Logger::info(
			'Stripe renewal completed successfully.',
			array(
				'method'     => __METHOD__,
				'renewal_id' => $renewal_id,
				'member_id'  => (int) ( $renewal['member_id'] ?? 0 ),
			)
		);

		return array( 'applied' => true, 'reason' => 'completed', 'renewal_id' => $renewal_id );
	}

	/**
	 * Confirm an offline renewal payment (check/zelle) from admin.
	 *
	 * @param int    $renewal_id Renewal ID.
	 * @param int    $admin_user_id Admin user ID confirming payment.
	 * @param string $notes Optional admin notes.
	 * @return array{applied:bool,reason:string,renewal_id?:int}
	 */
	public function confirm_offline_payment( int $renewal_id, int $admin_user_id, string $notes = '' ): array {
		$renewal = STSRC_Renewal_DB::get_renewal( $renewal_id );
		if ( empty( $renewal ) ) {
			return array( 'applied' => false, 'reason' => 'renewal_not_found' );
		}

		$status         = (string) ( $renewal['status'] ?? '' );
		$payment_method = sanitize_key( (string) ( $renewal['payment_method'] ?? '' ) );
		if ( STSRC_Renewal_DB::STATUS_PENDING_PAYMENT !== $status ) {
			return array( 'applied' => false, 'reason' => 'invalid_status_transition', 'renewal_id' => $renewal_id );
		}

		if ( ! in_array( $payment_method, array( 'zelle', 'check' ), true ) ) {
			return array( 'applied' => false, 'reason' => 'invalid_payment_method', 'renewal_id' => $renewal_id );
		}

		$note_parts = array(
			sprintf( 'Offline payment confirmed by admin user %d', $admin_user_id ),
		);
		if ( '' !== trim( $notes ) ) {
			$note_parts[] = sanitize_text_field( $notes );
		}

		$completion = $this->complete_renewal_transaction(
			$renewal,
			array( STSRC_Renewal_DB::STATUS_PENDING_PAYMENT ),
			array(
				'notes' => implode( ' - ', $note_parts ),
			)
		);
		if ( empty( $completion['applied'] ) ) {
			STSRC_Logger::warning(
				'Offline renewal confirmation failed during transaction.',
				array(
					'method'     => __METHOD__,
					'renewal_id' => $renewal_id,
					'reason'     => (string) ( $completion['reason'] ?? 'unknown' ),
				)
			);
			return array(
				'applied'    => false,
				'reason'     => (string) ( $completion['reason'] ?? 'no_update_applied' ),
				'renewal_id' => $renewal_id,
			);
		}

		$member = STSRC_Member_DB::get_member( (int) ( $renewal['member_id'] ?? 0 ) );
		if ( ! empty( $member ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-email-service.php';
			$email_service = new STSRC_Email_Service();
			$email_service->send_member_renewal_confirmation( (int) ( $renewal['member_id'] ?? 0 ), $renewal, $member );
			$email_service->send_admin_renewal_notice( $renewal, $member );
		}
		STSRC_Logger::info(
			'Offline renewal confirmed successfully.',
			array(
				'method'        => __METHOD__,
				'renewal_id'    => $renewal_id,
				'member_id'     => (int) ( $renewal['member_id'] ?? 0 ),
				'admin_user_id' => $admin_user_id,
			)
		);

		return array( 'applied' => true, 'reason' => 'completed', 'renewal_id' => $renewal_id );
	}

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

		$payload_has_family = ! empty( $payload['retain_family_member_ids'] ) || ! empty( $payload['new_family_member_count'] ) || ! empty( $payload['new_family_members'] );
		$payload_has_extra  = ! empty( $payload['retain_extra_member_ids'] ) || ! empty( $payload['new_extra_member_count'] ) || ! empty( $payload['new_extra_members'] );
		$is_simple_renewal  = ! $payload_has_family && ! $payload_has_extra;

		$retain_family_ids = $payload_has_family
			? $this->normalize_ids( $payload['retain_family_member_ids'] ?? array() )
			: $active_family_ids;
		$retain_extra_ids  = $payload_has_extra
			? $this->normalize_ids( $payload['retain_extra_member_ids'] ?? array() )
			: $active_extra_ids;
		$errors            = array();
		$warnings          = array();

		if ( ! $is_simple_renewal && ! STSRC_Family_Member_DB::member_owns_ids( $member_id, $retain_family_ids ) ) {
			$errors[] = 'invalid_retained_family_members';
		}

		if ( ! $is_simple_renewal && ! STSRC_Extra_Member_DB::member_owns_ids( $member_id, $retain_extra_ids ) ) {
			$errors[] = 'invalid_retained_extra_members';
		}

		$new_family_members = $payload['new_family_members'] ?? array();
		$new_extra_members  = $payload['new_extra_members'] ?? array();

		if ( ! is_array( $new_family_members ) ) {
			$new_family_members = array();
		}
		if ( ! is_array( $new_extra_members ) ) {
			$new_extra_members = array();
		}

		$new_family_count = count( $new_family_members );
		$new_extra_count  = count( $new_extra_members );

		foreach ( $new_family_members as $nfm ) {
			if ( empty( $nfm['first_name'] ) || empty( $nfm['last_name'] ) ) {
				$errors[] = 'new_family_member_missing_name';
				break;
			}
		}
		foreach ( $new_extra_members as $nem ) {
			if ( empty( $nem['first_name'] ) || empty( $nem['last_name'] ) ) {
				$errors[] = 'new_extra_member_missing_name';
				break;
			}
		}

		$required_family_count = $this->get_required_family_count_by_type( $target_type_name );
		$resulting_family_count = count( $retain_family_ids ) + $new_family_count;

		if ( ! $is_simple_renewal && $resulting_family_count < $required_family_count ) {
			$errors[] = 'insufficient_family_members';
		}

		if ( ! $is_simple_renewal && self::TYPE_DUO === $target_type_name && $resulting_family_count > 1 ) {
			$errors[] = 'duo_allows_exactly_one_family_member';
		}

		if ( ! $is_simple_renewal && self::TYPE_HOUSEHOLD === $target_type_name && $resulting_family_count > self::MAX_FAMILY_HOUSEHOLD ) {
			$errors[] = 'household_family_member_limit_exceeded';
		}

		if ( ! $is_simple_renewal && self::TYPE_HOUSEHOLD === $current_type_name && self::TYPE_DUO === $target_type_name && $resulting_family_count !== 1 ) {
			$errors[] = 'household_to_duo_requires_exactly_one_family_member';
		}

		if (
			! $is_simple_renewal &&
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
			$retain_extra_ids  = array();
			$new_extra_count   = 0;
			$new_extra_members = array();
		}

		if ( ! $is_simple_renewal && self::TYPE_HOUSEHOLD === $target_type_name && ! STSRC_Extra_Member_DB::is_valid_household_extra_count( $resulting_extra_count ) ) {
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
				'new_family_members'       => $new_family_members,
				'new_extra_members'        => $new_extra_members,
			),
		);
	}

	/**
	 * Apply final member-field updates for a completed renewal.
	 *
	 * @param array $renewal Renewal row.
	 * @return bool
	 */
	private function apply_member_completion_updates( array $renewal ): bool {
		$member_id   = (int) ( $renewal['member_id'] ?? 0 );
		$new_type_id = (int) ( $renewal['new_membership_type_id'] ?? 0 );

		if ( $member_id <= 0 || $new_type_id <= 0 ) {
			return false;
		}

		$target_type = STSRC_Membership_DB::get_membership_type( $new_type_id );
		if ( empty( $target_type ) ) {
			return false;
		}

		$snapshot = $this->decode_snapshot( (string) ( $renewal['transition_snapshot_json'] ?? '' ) );
		$quote    = $snapshot['quote'] ?? array();
		$method   = sanitize_key( (string) ( $renewal['payment_method'] ?? '' ) );
		$season_renewal_date = STSRC_Renewal_Helpers::get_season_renewal_date();
		$start_ts = strtotime( $season_renewal_date );

		if ( false === $start_ts ) {
			$start_ts = current_time( 'timestamp' );
		}

		$expiration_period_days = max( 0, absint( $target_type['expiration_period'] ?? 0 ) );
		$expiration_ts          = strtotime( '+' . $expiration_period_days . ' days', $start_ts );
		$expiration_date        = gmdate( 'Y-m-d', $expiration_ts ?: $start_ts );

		$season_price = (float) ( $quote['membership_base'] ?? ( $target_type['price'] ?? 0.00 ) );

		$member_updated = STSRC_Member_DB::apply_renewal_completion(
			$member_id,
			array(
				'membership_type_id'      => $new_type_id,
				'expiration_date'         => $expiration_date,
				'season_membership_price' => $season_price,
				'balance_owed'            => 0.00,
				'status'                  => 'active',
				'final_payment_method'    => $method,
			)
		);

		if ( ! $member_updated ) {
			return false;
		}

		$target_name  = (string) ( $target_type['name'] ?? 'Membership' );
		$season_key   = STSRC_Renewal_Helpers::get_season_key();
		$description  = sprintf(
			'Renewal - %s ($%s) - Season %s',
			$target_name,
			number_format( $season_price, 2 ),
			$season_key
		);

		STSRC_Transaction_DB::create_transaction(
			$member_id,
			array(
				'transaction_type' => 'initial',
				'payment_method'   => 'initial',
				'amount'           => 0.00,
				'balance_after'    => 0.00,
				'description'      => $description,
			)
		);

		return true;
	}

	/**
	 * Apply family/extra member reconciliation from transition snapshot.
	 *
	 * @param array $renewal Renewal row.
	 * @return bool
	 */
	private function apply_household_completion_updates( array $renewal ): bool {
		$member_id = (int) ( $renewal['member_id'] ?? 0 );
		if ( $member_id <= 0 ) {
			return false;
		}

		$snapshot = $this->decode_snapshot( (string) ( $renewal['transition_snapshot_json'] ?? '' ) );
		$actions  = $snapshot['transition']['actions'] ?? array();
		$retain_family = $this->normalize_ids( $actions['retain_family_ids'] ?? array() );
		$retain_extra  = $this->normalize_ids( $actions['retain_extra_ids'] ?? array() );

		$family_ok = STSRC_Family_Member_DB::apply_renewal_selection( $member_id, $retain_family );
		$extra_ok  = STSRC_Extra_Member_DB::apply_renewal_selection( $member_id, $retain_extra );

		return $family_ok && $extra_ok;
	}

	/**
	 * Execute renewal completion updates in a DB transaction.
	 *
	 * @param array    $renewal Renewal row.
	 * @param string[] $allowed_from Allowed from statuses.
	 * @param array    $transition_data Additional transition columns.
	 * @return array{applied:bool,reason:string}
	 */
	private function complete_renewal_transaction( array $renewal, array $allowed_from, array $transition_data ): array {
		$renewal_id = (int) ( $renewal['renewal_id'] ?? 0 );
		if ( $renewal_id <= 0 ) {
			return array( 'applied' => false, 'reason' => 'invalid_renewal_id' );
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		$member_updated = $this->apply_member_completion_updates( $renewal );
		if ( ! $member_updated ) {
			$wpdb->query( 'ROLLBACK' );
			return array( 'applied' => false, 'reason' => 'member_update_failed' );
		}

		$household_updated = $this->apply_household_completion_updates( $renewal );
		if ( ! $household_updated ) {
			$wpdb->query( 'ROLLBACK' );
			return array( 'applied' => false, 'reason' => 'household_update_failed' );
		}

		$applied = STSRC_Renewal_DB::transition_status(
			$renewal_id,
			$allowed_from,
			STSRC_Renewal_DB::STATUS_COMPLETED,
			$transition_data
		);
		if ( ! $applied ) {
			$wpdb->query( 'ROLLBACK' );
			return array( 'applied' => false, 'reason' => 'no_update_applied' );
		}

		$wpdb->query( 'COMMIT' );
		return array( 'applied' => true, 'reason' => 'completed' );
	}

	/**
	 * Decode renewal transition snapshot JSON.
	 *
	 * @param string $snapshot_json Snapshot JSON.
	 * @return array
	 */
	private function decode_snapshot( string $snapshot_json ): array {
		$decoded = json_decode( $snapshot_json, true );

		return is_array( $decoded ) ? $decoded : array();
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

	/**
	 * Create new family and extra member records at intent submission time.
	 *
	 * @param int   $member_id Member ID.
	 * @param array $actions   Validated transition actions.
	 * @return array{family_ids:int[],extra_ids:int[]}
	 */
	private function create_new_members_on_submit( int $member_id, array $actions ): array {
		$family_ids = array();
		$extra_ids  = array();

		$new_family = $actions['new_family_members'] ?? array();
		if ( is_array( $new_family ) ) {
			foreach ( $new_family as $fm_data ) {
				if ( empty( $fm_data['first_name'] ) || empty( $fm_data['last_name'] ) ) {
					continue;
				}
				$id = STSRC_Family_Member_DB::add_family_member(
					$member_id,
					array(
						'first_name' => sanitize_text_field( (string) $fm_data['first_name'] ),
						'last_name'  => sanitize_text_field( (string) $fm_data['last_name'] ),
						'email'      => sanitize_email( (string) ( $fm_data['email'] ?? '' ) ),
					)
				);
				if ( false !== $id ) {
					$family_ids[] = (int) $id;
				}
			}
		}

		$new_extras = $actions['new_extra_members'] ?? array();
		if ( is_array( $new_extras ) ) {
			foreach ( $new_extras as $em_data ) {
				if ( empty( $em_data['first_name'] ) || empty( $em_data['last_name'] ) ) {
					continue;
				}
				$id = STSRC_Extra_Member_DB::add_extra_member(
					$member_id,
					array(
						'first_name' => sanitize_text_field( (string) $em_data['first_name'] ),
						'last_name'  => sanitize_text_field( (string) $em_data['last_name'] ),
						'email'      => sanitize_email( (string) ( $em_data['email'] ?? '' ) ),
					)
				);
				if ( false !== $id ) {
					$extra_ids[] = (int) $id;
				}
			}
		}

		return array(
			'family_ids' => $family_ids,
			'extra_ids'  => $extra_ids,
		);
	}
}

