<?php
/**
 * Renewal AJAX API handlers.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/api
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-renewal-service.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-logger.php';

/**
 * Renewal API class.
 */
class STSRC_Renewal_API {

	/**
	 * Handle eligibility check.
	 *
	 * @return void
	 */
	public function eligibility(): void {
		$member = $this->get_authenticated_member_context();
		if ( null === $member ) {
			return;
		}

		$post_data = wp_unslash( $_POST );
		$season_key = sanitize_text_field( $post_data['season_key'] ?? '' );
		$service    = new STSRC_Renewal_Service();
		$result     = $service->get_eligibility( (int) $member['member_id'], $season_key );

		wp_send_json_success(
			array(
				'eligibility' => $result,
			)
		);
	}

	/**
	 * Handle quote calculation.
	 *
	 * @return void
	 */
	public function quote(): void {
		$member = $this->get_authenticated_member_context();
		if ( null === $member ) {
			return;
		}

		$post_data = wp_unslash( $_POST );
		$target_id = absint( $post_data['target_membership_type_id'] ?? 0 );

		if ( $target_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid membership type.', 'smoketree-plugin' ) ), 400 );
			return;
		}

		$payment_method = $this->normalize_payment_method( (string) ( $post_data['payment_method'] ?? 'card' ) );
		$payload        = $this->normalize_transition_payload( $post_data );
		$service        = new STSRC_Renewal_Service();
		$quote_result   = $service->get_quote( (int) $member['member_id'], $target_id, $payment_method, $payload );

		if ( empty( $quote_result['valid'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Unable to calculate quote for the selected transition.', 'smoketree-plugin' ),
					'errors'  => $quote_result['errors'] ?? array(),
				),
				422
			);
			return;
		}

		wp_send_json_success(
			array(
				'quote'      => $quote_result['quote'] ?? array(),
				'transition' => $quote_result['transition'] ?? array(),
			)
		);
	}

	/**
	 * Handle final intent submit.
	 *
	 * @return void
	 */
	public function submit(): void {
		$member = $this->get_authenticated_member_context();
		if ( null === $member ) {
			return;
		}

		$post_data = wp_unslash( $_POST );
		$target_id = absint( $post_data['target_membership_type_id'] ?? 0 );

		if ( $target_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid membership type.', 'smoketree-plugin' ) ), 400 );
			return;
		}

		$payment_method     = $this->normalize_payment_method( (string) ( $post_data['payment_method'] ?? 'card' ) );
		$auto_renewal_optin = ! empty( $post_data['auto_renewal_optin'] ) && '1' === $post_data['auto_renewal_optin'];
		$season_key         = sanitize_text_field( $post_data['season_key'] ?? '' );
		if ( '' !== $season_key && ! preg_match( '/^[a-z0-9_-]{2,16}$/i', $season_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid season key.', 'smoketree-plugin' ) ), 400 );
			return;
		}
		$payload        = $this->normalize_transition_payload( $post_data );
		$service        = new STSRC_Renewal_Service();
		$result         = $service->create_intent(
			(int) $member['member_id'],
			$target_id,
			$payment_method,
			$payload,
			$season_key
		);

		if ( 'rejected' === ( $result['status'] ?? '' ) ) {
			$reason = $result['reason'] ?? 'rejected';
			wp_send_json_error(
				array(
					'message' => $this->get_rejection_message( $reason ),
					'reason'  => $reason,
					'context' => $result['context'] ?? array(),
				),
				409
			);
			return;
		}

		if ( 'error' === ( $result['status'] ?? '' ) ) {
			$reason = $result['reason'] ?? 'error';
			wp_send_json_error(
				array(
					'message' => $this->get_error_message( $reason ),
					'reason'  => $reason,
				),
				500
			);
			return;
		}

		$stripe_methods = array( 'card', 'bank_account' );
		$enable_auto    = $auto_renewal_optin && in_array( $payment_method, $stripe_methods, true ) ? 1 : 0;
		STSRC_Member_DB::update_member( (int) $member['member_id'], array( 'auto_renewal_enabled' => $enable_auto ) );

		wp_send_json_success(
			array(
				'status'     => $result['status'] ?? 'initiated',
				'renewal_id' => (int) ( $result['renewal_id'] ?? 0 ),
				'season_key' => $result['season_key'] ?? '',
				'quote'      => $result['quote'] ?? array(),
				'redirect_url' => $result['redirect_url'] ?? '',
				'message'    => STSRC_Renewal_DB::STATUS_PENDING_PAYMENT === ( $result['status'] ?? '' )
					? __( 'Renewal submitted. Your membership remains pending until offline payment is confirmed.', 'smoketree-plugin' )
					: __( 'Renewal checkout session created successfully.', 'smoketree-plugin' ),
			)
		);
	}

	/**
	 * Cancel a stuck or stale renewal from admin.
	 *
	 * @return void
	 */
	public function admin_cancel_renewal(): void {
		if ( ! $this->validate_admin_request() ) {
			return;
		}

		$post_data  = wp_unslash( $_POST );
		$renewal_id = absint( $post_data['renewal_id'] ?? 0 );

		if ( $renewal_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid renewal ID.', 'smoketree-plugin' ) ), 400 );
			return;
		}

		$renewal = STSRC_Renewal_DB::get_renewal( $renewal_id );
		if ( empty( $renewal ) ) {
			wp_send_json_error( array( 'message' => __( 'Renewal record not found.', 'smoketree-plugin' ) ), 404 );
			return;
		}

		$cancellable = array(
			STSRC_Renewal_DB::STATUS_INITIATED,
			STSRC_Renewal_DB::STATUS_PENDING_PAYMENT,
		);
		$status = (string) ( $renewal['status'] ?? '' );

		if ( ! in_array( $status, $cancellable, true ) ) {
			wp_send_json_error(
				array( 'message' => sprintf(
					/* translators: %s renewal status */
					__( 'Cannot cancel a renewal with status "%s".', 'smoketree-plugin' ),
					$status
				) ),
				409
			);
			return;
		}

		$admin_user = get_current_user_id();
		$notes      = sprintf( 'Cancelled by admin user %d.', $admin_user );

		$applied = STSRC_Renewal_DB::transition_status(
			$renewal_id,
			$cancellable,
			STSRC_Renewal_DB::STATUS_CANCELLED,
			array( 'notes' => $notes )
		);

		if ( ! $applied ) {
			wp_send_json_error( array( 'message' => __( 'Failed to cancel renewal. Please try again.', 'smoketree-plugin' ) ), 500 );
			return;
		}

		STSRC_Logger::info(
			'Admin cancelled renewal.',
			array(
				'method'     => __METHOD__,
				'renewal_id' => $renewal_id,
				'member_id'  => (int) ( $renewal['member_id'] ?? 0 ),
				'admin_user' => $admin_user,
			)
		);

		wp_send_json_success( array( 'message' => __( 'Renewal cancelled. The member can now submit a new renewal.', 'smoketree-plugin' ) ) );
	}

	/**
	 * Confirm a pending offline renewal payment from admin.
	 *
	 * @return void
	 */
	public function confirm_offline_payment(): void {
		if ( ! $this->validate_admin_request() ) {
			return;
		}

		$post_data   = wp_unslash( $_POST );
		$renewal_id  = absint( $post_data['renewal_id'] ?? 0 );
		$notes       = sanitize_textarea_field( $post_data['notes'] ?? '' );
		$admin_user  = get_current_user_id();

		if ( $renewal_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid renewal ID.', 'smoketree-plugin' ) ), 400 );
			return;
		}

		$service = new STSRC_Renewal_Service();
		$result  = $service->confirm_offline_payment( $renewal_id, $admin_user, $notes );
		if ( empty( $result['applied'] ) ) {
			STSRC_Logger::warning(
				'Admin offline renewal confirmation failed.',
				array(
					'method'       => __METHOD__,
					'renewal_id'   => $renewal_id,
					'admin_user'   => $admin_user,
					'failure_code' => (string) ( $result['reason'] ?? 'unknown' ),
				)
			);
			wp_send_json_error(
				array(
					'message' => __( 'Unable to confirm offline renewal payment.', 'smoketree-plugin' ),
					'reason'  => $result['reason'] ?? 'error',
				),
				409
			);
			return;
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Offline renewal payment confirmed and membership activated.', 'smoketree-plugin' ),
				'renewal_id' => (int) ( $result['renewal_id'] ?? 0 ),
			)
		);
		STSRC_Logger::info(
			'Admin offline renewal confirmation succeeded.',
			array(
				'method'     => __METHOD__,
				'renewal_id' => $renewal_id,
				'admin_user' => $admin_user,
			)
		);
	}

	/**
	 * Validate request context and resolve authenticated member.
	 *
	 * @return array|null
	 */
	private function get_authenticated_member_context(): ?array {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', 'smoketree-plugin' ) ), 401 );
			return null;
		}

		$post_data = wp_unslash( $_POST );
		$nonce     = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_renewal_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'smoketree-plugin' ) ), 403 );
			return null;
		}

		$current_user = wp_get_current_user();
		$member       = STSRC_Member_DB::get_member_by_email( (string) ( $current_user->user_email ?? '' ) );
		if ( empty( $member ) ) {
			wp_send_json_error( array( 'message' => __( 'Member account not found.', 'smoketree-plugin' ) ), 404 );
			return null;
		}

		$submitted_member_id = absint( $post_data['member_id'] ?? 0 );
		$actual_member_id    = (int) ( $member['member_id'] ?? 0 );
		if ( $submitted_member_id > 0 && $submitted_member_id !== $actual_member_id ) {
			wp_send_json_error( array( 'message' => __( 'Member ownership validation failed.', 'smoketree-plugin' ) ), 403 );
			return null;
		}

		return $member;
	}

	/**
	 * Validate admin capability and nonce for admin-only renewal actions.
	 *
	 * @return bool
	 */
	private function validate_admin_request(): bool {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Admin access required.', 'smoketree-plugin' ) ), 403 );
			return false;
		}

		$post_data = wp_unslash( $_POST );
		$nonce     = sanitize_text_field( $post_data['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'stsrc_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'smoketree-plugin' ) ), 403 );
			return false;
		}

		return true;
	}

	/**
	 * Normalize transition payload fields from POST.
	 *
	 * @param array $post_data Unslashed POST data.
	 * @return array
	 */
	private function normalize_transition_payload( array $post_data ): array {
		$retain_family = $post_data['retain_family_member_ids'] ?? array();
		$retain_extra  = $post_data['retain_extra_member_ids'] ?? array();

		return array(
			'retain_family_member_ids' => is_array( $retain_family ) ? array_map( 'absint', $retain_family ) : array(),
			'retain_extra_member_ids'  => is_array( $retain_extra ) ? array_map( 'absint', $retain_extra ) : array(),
			'new_family_member_count'  => min( 20, absint( $post_data['new_family_member_count'] ?? 0 ) ),
			'new_extra_member_count'   => min( 20, absint( $post_data['new_extra_member_count'] ?? 0 ) ),
		);
	}

	/**
	 * Normalize and validate renewal payment method.
	 *
	 * @param string $method Raw payment method.
	 * @return string
	 */
	private function normalize_payment_method( string $method ): string {
		$method  = sanitize_key( $method );
		$allowed = array( 'card', 'ach', 'bank_account', 'us_bank_account', 'payment_plan', 'cash', 'zelle', 'check' );

		if ( ! in_array( $method, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid payment method.', 'smoketree-plugin' ) ), 400 );
			exit;
		}

		return $method;
	}

	/**
	 * Map a rejection reason code to a user-facing message.
	 *
	 * @param string $reason Reason code from the renewal service.
	 * @return string
	 */
	private function get_rejection_message( string $reason ): string {
		$messages = array(
			'renewal_disabled'     => __( 'The renewal window is currently closed. Please check back later or contact the club for assistance.', 'smoketree-plugin' ),
			'member_not_found'     => __( 'We could not locate your member record. Please contact the club for assistance.', 'smoketree-plugin' ),
			'member_not_eligible'  => __( 'Your account is not currently eligible for renewal. This may be due to a cancelled membership. Please contact the club for details.', 'smoketree-plugin' ),
			'already_completed'    => __( 'Your renewal for this season has already been completed. No further action is needed.', 'smoketree-plugin' ),
			'already_in_progress'  => __( 'A renewal for this season is already in progress. Please check your email for payment instructions or contact the club if you need assistance.', 'smoketree-plugin' ),
			'duplicate_submission' => __( 'A renewal submission for this season already exists. Please check your email or contact the club for status.', 'smoketree-plugin' ),
			'invalid_quote'        => __( 'We were unable to calculate pricing for your selected plan. Please try a different membership option or contact the club.', 'smoketree-plugin' ),
			'not_eligible'         => __( 'Your account is not eligible for renewal at this time. Please contact the club for more information.', 'smoketree-plugin' ),
		);

		return $messages[ $reason ] ?? sprintf(
			/* translators: %s reason code */
			__( 'Renewal could not be processed (reason: %s). Please contact the club for assistance.', 'smoketree-plugin' ),
			sanitize_text_field( $reason )
		);
	}

	/**
	 * Map an error reason code to a user-facing message.
	 *
	 * @param string $reason Reason code from the renewal service.
	 * @return string
	 */
	private function get_error_message( string $reason ): string {
		$messages = array(
			'member_not_found'              => __( 'We could not locate your member record. Please refresh the page and try again.', 'smoketree-plugin' ),
			'intent_create_failed'          => __( 'We encountered a problem saving your renewal request. Please try again in a few moments.', 'smoketree-plugin' ),
			'stripe_checkout_create_failed' => __( 'We were unable to set up the payment checkout. Please try again or select a different payment method.', 'smoketree-plugin' ),
		);

		return $messages[ $reason ] ?? __( 'Something went wrong while processing your renewal. Please try again or contact the club for assistance.', 'smoketree-plugin' );
	}
}

