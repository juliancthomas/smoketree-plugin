<?php
/**
 * Member portal helper class
 *
 * Handles member portal-specific rendering helpers.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/services/class-stsrc-balance-service.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/helpers/class-stsrc-renewal-helpers.php';

/**
 * Member portal helper class.
 *
 * @package Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public
 */
class STSRC_Member_Portal {

	/**
	 * Build renewal-gate context for member portal rendering.
	 *
	 * @param array $member Member record.
	 * @return array{
	 *   enabled:bool,
	 *   eligible:bool,
	 *   show_section:bool,
	 *   season_key:string
	 * }
	 */
	public static function get_renewal_context( array $member ): array {
		$enabled = STSRC_Renewal_Helpers::is_renewal_enabled();
		$eligible = self::can_render_renewal_section( $member );

		return array(
			'enabled'      => $enabled,
			'eligible'     => $eligible,
			'show_section' => $enabled && $eligible,
			'season_key'   => STSRC_Renewal_Helpers::get_season_key(),
		);
	}

	/**
	 * Determine whether renewal UI should render for this member.
	 *
	 * @param array $member Member record.
	 * @return bool
	 */
	public static function can_render_renewal_section( array $member ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( ! STSRC_Renewal_Helpers::is_renewal_enabled() ) {
			return false;
		}

		return STSRC_Renewal_Helpers::is_member_eligible_for_current_season( $member );
	}

	/**
	 * Render balance card for a member when balance is outstanding.
	 *
	 * @param int $member_id Member ID.
	 * @return void
	 */
	public static function render_balance_card( int $member_id ): void {
		if ( $member_id <= 0 ) {
			return;
		}

		$balance_data = STSRC_Balance_Service::get_balance_display_data( $member_id );

		if ( empty( $balance_data ) ) {
			return;
		}

		$balance_owed = (float) ( $balance_data['balance_owed'] ?? 0 );

		// Only show this card for outstanding balances.
		if ( $balance_owed <= 0.01 ) {
			return;
		}

		$balance_card_data = array(
			'member_id'             => $member_id,
			'balance_owed'          => $balance_owed,
			'membership_type_name'  => $balance_data['membership_type_name'] ?? '',
			'season_price'          => (float) ( $balance_data['season_price'] ?? 0 ),
			'total_paid'            => (float) ( $balance_data['total_paid'] ?? 0 ),
			'total_adjustments'     => (float) ( $balance_data['total_adjustments'] ?? 0 ),
			'remaining_balance'     => $balance_owed,
		);

		include plugin_dir_path( __FILE__ ) . 'partials/member-balance-card.php';
	}

	/**
	 * Render registration success notice with payment instructions.
	 *
	 * Displays a welcome banner and payment-method-specific instructions
	 * pulled from ACF option fields when a member arrives from registration.
	 *
	 * @param string $registration_status Registration query param value.
	 * @param string $payment_type        Payment type query param value.
	 * @return void
	 */
	public static function render_registration_notice( string $registration_status, string $payment_type ): void {
		if ( 'success' !== sanitize_text_field( $registration_status ) ) {
			return;
		}

		$payment_type = sanitize_text_field( $payment_type );

		echo '<div class="stsrc-notice success">';
		echo '<p><strong>' . esc_html__( 'Welcome! Your registration was successful.', 'smoketree-plugin' ) . '</strong></p>';
		echo '<p>' . esc_html__( 'Your membership is pending until payment is received. You will receive an email confirmation shortly.', 'smoketree-plugin' ) . '</p>';

		$acf_field_map = array(
			'zelle'     => 'stsrc_payment_instructions_zelle',
			'check'     => 'stsrc_payment_instructions_check',
			'pay_later' => 'stsrc_payment_instructions_pay_later',
		);

		if ( isset( $acf_field_map[ $payment_type ] ) && function_exists( 'get_field' ) ) {
			$instructions = get_field( $acf_field_map[ $payment_type ], 'option' );
			if ( ! empty( $instructions ) ) {
				echo '<div style="margin-top: 10px; padding: 12px; background: #f9f9f9; border-left: 3px solid #0073aa; border-radius: 3px;">';
				echo '<strong>' . esc_html__( 'Payment Instructions:', 'smoketree-plugin' ) . '</strong>';
				echo '<div style="margin-top: 6px;">' . wp_kses_post( $instructions ) . '</div>';
				echo '</div>';
			}
		}

		echo '</div>';
	}

	/**
	 * Render payment status notice from member portal query params.
	 *
	 * @param string $payment_status Payment status query value.
	 * @return void
	 */
	public static function render_payment_status_notice( string $payment_status ): void {
		$payment_status = sanitize_text_field( $payment_status );

		if ( 'success' === $payment_status ) {
			echo '<div class="stsrc-notice success"><p>' . esc_html__( 'Payment processed successfully!', 'smoketree-plugin' ) . '</p></div>';
			return;
		}

		if ( 'cancelled' === $payment_status ) {
			echo '<div class="stsrc-notice warning"><p>' . esc_html__( 'Payment was cancelled. You can try again whenever you are ready.', 'smoketree-plugin' ) . '</p></div>';
		}
	}

	/**
	 * Render pending renewal notice with payment instructions.
	 *
	 * @param string $renewal_status Renewal query param value (e.g. "pending").
	 * @param string $payment_method Payment method query param value.
	 * @return void
	 */
	public static function render_renewal_pending_notice( string $renewal_status, string $payment_method ): void {
		if ( 'pending' !== sanitize_text_field( $renewal_status ) ) {
			return;
		}

		$payment_method = sanitize_key( $payment_method );

		echo '<div class="stsrc-notice success">';
		echo '<p><strong>' . esc_html__( 'Your renewal has been submitted!', 'smoketree-plugin' ) . '</strong></p>';
		echo '<p>' . esc_html__( 'Your membership will be activated once payment is received and confirmed.', 'smoketree-plugin' ) . '</p>';

		if ( ! function_exists( 'get_field' ) ) {
			echo '</div>';
			return;
		}

		$acf_field_map = array(
			'zelle'        => 'zelle_instructions',
			'check'        => 'check_instructions',
			'cash'         => 'cash_instructions',
			'payment_plan' => 'payment_plan_instructions',
		);

		if ( isset( $acf_field_map[ $payment_method ] ) ) {
			$instructions = get_field( $acf_field_map[ $payment_method ], 'option' );
			if ( ! empty( $instructions ) ) {
				echo '<div style="margin-top: 10px; padding: 12px; background: #f9f9f9; border-left: 3px solid #0073aa; border-radius: 3px;">';
				echo '<strong>' . esc_html__( 'Payment Instructions:', 'smoketree-plugin' ) . '</strong>';
				echo '<div style="margin-top: 6px;">' . wp_kses_post( $instructions ) . '</div>';
				echo '</div>';
			}
		}

		echo '</div>';
	}
}
