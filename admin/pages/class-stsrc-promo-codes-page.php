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
	 * @since    1.4.1
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
	 * @since    1.4.1
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
	 * @since    1.4.1
	 * @return   void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smoketree-plugin' ) );
		}

		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-promo-codes-db.php';
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-membership-db.php';
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-affiliate-referrals-db.php';

		$tab         = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'promo-codes';
		$paged       = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$is_active   = isset( $_GET['is_active'] ) && '' !== $_GET['is_active'] ? absint( wp_unslash( $_GET['is_active'] ) ) : null;
		$payout      = isset( $_GET['payout_status'] ) ? sanitize_key( wp_unslash( $_GET['payout_status'] ) ) : '';
		$per_page    = 20;
		$codes       = STSRC_Promo_Codes_DB::get_all_codes(
			array(
				'page'      => $paged,
				'per_page'  => $per_page,
				'search'    => $search,
				'is_active' => $is_active,
			)
		);
		$type_rows   = STSRC_Membership_DB::get_all_membership_types();
		$type_labels = array();
		$referrals   = STSRC_Affiliate_Referrals_DB::get_referral_log(
			array(
				'page'          => $paged,
				'per_page'      => $per_page,
				'payout_status' => in_array( $payout, array( 'pending', 'paid' ), true ) ? $payout : '',
			)
		);

		foreach ( $type_rows as $row ) {
			$type_labels[ (int) $row['membership_type_id'] ] = (string) $row['name'];
		}

		$data = array(
			'codes'       => $codes,
			'tab'         => $tab,
			'paged'       => $paged,
			'search'      => $search,
			'is_active'   => $is_active,
			'payout'      => $payout,
			'per_page'    => $per_page,
			'type_rows'   => $type_rows,
			'type_labels' => $type_labels,
			'referrals'   => $referrals,
		);

		include plugin_dir_path( dirname( __FILE__ ) ) . 'partials/promo-codes-list.php';
	}
}

