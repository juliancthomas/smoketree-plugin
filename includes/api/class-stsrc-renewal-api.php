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

		$payment_method = sanitize_text_field( $post_data['payment_method'] ?? 'card' );
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

		$payment_method = sanitize_text_field( $post_data['payment_method'] ?? 'card' );
		$season_key     = sanitize_text_field( $post_data['season_key'] ?? '' );
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
			wp_send_json_error(
				array(
					'message' => __( 'Renewal is not eligible for submission.', 'smoketree-plugin' ),
					'reason'  => $result['reason'] ?? 'rejected',
					'context' => $result['context'] ?? array(),
				),
				409
			);
			return;
		}

		if ( 'error' === ( $result['status'] ?? '' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to create renewal intent.', 'smoketree-plugin' ),
					'reason'  => $result['reason'] ?? 'error',
				),
				500
			);
			return;
		}

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
			'new_family_member_count'  => absint( $post_data['new_family_member_count'] ?? 0 ),
			'new_extra_member_count'   => absint( $post_data['new_extra_member_count'] ?? 0 ),
		);
	}
}

