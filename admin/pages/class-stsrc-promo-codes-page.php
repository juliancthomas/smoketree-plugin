<?php

/**
 * Promo codes admin page.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/pages
 */
class STSRC_Promo_Codes_Page {

	/**
	 * Register promo codes submenu.
	 *
	 * @since    1.4.2
	 * @return   void
	 */
	public function register_submenu(): void {
		add_submenu_page(
			'stsrc-dashboard',
			__( 'Promo Codes', 'smoketree-plugin' ),
			__( 'Promo Codes', 'smoketree-plugin' ),
			'manage_options',
			'stsrc-promo-codes',
			array( $this, 'render' )
		);
	}

	/**
	 * Conditionally enqueue promo admin assets.
	 *
	 * @since    1.4.2
	 * @param    string $hook Current page hook.
	 * @return   void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'smoketree-club_page_stsrc-promo-codes' !== $hook ) {
			return;
		}

		$base_url  = plugin_dir_url( dirname( __FILE__ ) );
		$base_path = plugin_dir_path( dirname( __FILE__ ) );

		wp_enqueue_style(
			'stsrc-promo-codes-admin',
			$base_url . 'css/promo-codes-admin.css',
			array(),
			file_exists( $base_path . 'css/promo-codes-admin.css' ) ? (string) filemtime( $base_path . 'css/promo-codes-admin.css' ) : '1.0.0'
		);

		wp_enqueue_script(
			'stsrc-promo-codes-admin',
			$base_url . 'js/promo-codes-admin.js',
			array( 'jquery' ),
			file_exists( $base_path . 'js/promo-codes-admin.js' ) ? (string) filemtime( $base_path . 'js/promo-codes-admin.js' ) : '1.0.0',
			true
		);

		wp_localize_script(
			'stsrc-promo-codes-admin',
			'stsrcPromoAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'stsrc_admin_nonce' ),
				'strings' => array(
					'confirmDelete' => __( 'Delete this promo code? This action can be undone only by recreating it.', 'smoketree-plugin' ),
					'confirmDeactivate' => __( 'Deactivate this promo code?', 'smoketree-plugin' ),
					'confirmActivate' => __( 'Activate this promo code?', 'smoketree-plugin' ),
				),
			)
		);
	}

	/**
	 * Render promo codes admin page.
	 *
	 * @since    1.4.2
	 * @return   void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smoketree-plugin' ) );
		}

		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-promo-codes-db.php';
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-membership-db.php';
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-affiliate-referrals-db.php';
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-member-db.php';

		$tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'promo-codes';
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$per_page = 20;

		$referral_settings_saved = false;
		if ( 'referral-codes' === $tab && isset( $_POST['stsrc_referral_settings_nonce'] ) ) {
			$referral_settings_saved = $this->handle_referral_settings_save();
		}

		$search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$is_active = isset( $_GET['is_active'] ) && '' !== $_GET['is_active'] ? absint( wp_unslash( $_GET['is_active'] ) ) : null;
		$payout    = isset( $_GET['payout_status'] ) ? sanitize_key( wp_unslash( $_GET['payout_status'] ) ) : '';

		$codes     = STSRC_Promo_Codes_DB::get_all_codes(
			array(
				'page'      => $paged,
				'per_page'  => $per_page,
				'search'    => $search,
				'is_active' => $is_active,
			)
		);
		$type_rows = STSRC_Membership_DB::get_all_membership_types();
		$referrals = STSRC_Affiliate_Referrals_DB::get_referral_log(
			array(
				'page'          => $paged,
				'per_page'      => $per_page,
				'payout_status' => in_array( $payout, array( 'pending', 'paid' ), true ) ? $payout : '',
			)
		);

		$type_labels = array();
		foreach ( $type_rows as $row ) {
			$type_labels[ (int) $row['membership_type_id'] ] = (string) $row['name'];
		}

		// Load member referral codes for the referral-codes tab.
		$member_search    = isset( $_GET['ms'] ) ? sanitize_text_field( wp_unslash( $_GET['ms'] ) ) : '';
		$affiliate_members = array();
		if ( 'referral-codes' === $tab ) {
			$affiliate_members = STSRC_Member_DB::get_members(
				array(
					'search' => $member_search,
				)
			);
		}

		$raw_discounts = get_option( 'stsrc_affiliate_type_discounts', '' );
		if ( is_string( $raw_discounts ) && '' !== $raw_discounts ) {
			$raw_discounts = json_decode( $raw_discounts, true );
		}

		$data = array(
			'codes'                   => $codes,
			'tab'                     => $tab,
			'paged'                   => $paged,
			'search'                  => $search,
			'is_active'               => $is_active,
			'payout'                  => $payout,
			'per_page'                => $per_page,
			'type_rows'               => $type_rows,
			'type_labels'             => $type_labels,
			'referrals'               => $referrals,
			'affiliate_members'       => $affiliate_members,
			'member_search'           => $member_search,
			'referral_settings_saved' => $referral_settings_saved,
			'affiliate_settings'      => array(
				'type_discounts'  => is_array( $raw_discounts ) ? $raw_discounts : array(),
				'referrer_credit' => get_option( 'stsrc_affiliate_referrer_credit', 50 ),
			),
		);

		include plugin_dir_path( dirname( __FILE__ ) ) . 'partials/promo-codes-list.php';
	}

	/**
	 * Save affiliate/referral settings from the Referral Codes tab POST.
	 *
	 * @since    1.5.0
	 * @return   bool True on success, false on nonce failure.
	 */
	private function handle_referral_settings_save(): bool {
		$nonce = isset( $_POST['stsrc_referral_settings_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['stsrc_referral_settings_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'stsrc_save_referral_settings' ) ) {
			return false;
		}

		// Save per-membership-type discounts as JSON.
		$discounts = array();
		if ( isset( $_POST['affiliate_type_discounts'] ) && is_array( $_POST['affiliate_type_discounts'] ) ) {
			foreach ( wp_unslash( $_POST['affiliate_type_discounts'] ) as $type_id => $value ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$type_id = absint( $type_id );
				$value   = (float) $value;
				if ( $type_id > 0 && $value > 0 ) {
					$discounts[ $type_id ] = $value;
				}
			}
		}
		update_option( 'stsrc_affiliate_type_discounts', wp_json_encode( $discounts ) );

		// Save referrer credit amount.
		$credit = isset( $_POST['affiliate_referrer_credit'] ) ? (float) wp_unslash( $_POST['affiliate_referrer_credit'] ) : 50;
		update_option( 'stsrc_affiliate_referrer_credit', $credit );

		return true;
	}
}

