<?php

/**
 * Auto-renewal service class
 *
 * Centralizes business logic for the season-based auto-renewal system:
 * - Selecting members eligible for renewal / notification
 * - Sending renewal notification emails
 * - Attempting to process renewal charges (off-session) via Stripe
 * - Bulk status operations used for season resets (admin workflow added later)
 *
 * Notes / assumptions:
 * - Auto-renewal is season-based (a single renewal date for all members), not Stripe subscriptions.
 * - Stripe off-session charges require the customer to have a saved default payment method.
 * - This class is intentionally "service-only" in Step 34; cron wiring and portal/admin UI come later.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/services
 */

/**
 * Auto-renewal service class.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/services
 * @author     Smoketree Swim and Recreation Club
 */
require_once __DIR__ . '/class-stsrc-logger.php';

class STSRC_Auto_Renewal_Service {

	/**
	 * Cron hook fired to send renewal notification emails.
	 *
	 * @since 1.0.0
	 */
	public const CRON_HOOK_NOTIFICATION = 'stsrc_auto_renewal_send_notifications';

	/**
	 * Cron hook fired to process auto-renewal payments.
	 *
	 * @since 1.0.0
	 */
	public const CRON_HOOK_PROCESS = 'stsrc_auto_renewal_process';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$lead_days = apply_filters( 'stsrc_auto_renewal_notification_lead_days', $this->notification_lead_days );
		if ( is_numeric( $lead_days ) ) {
			$lead_days = (int) $lead_days;
		} else {
			$lead_days = $this->notification_lead_days;
		}

		$this->notification_lead_days = max( 1, $lead_days );
	}

	/**
	 * Renewal notification lead time (days before renewal date).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private int $notification_lead_days = 7;

	/**
	 * Get eligible members for renewal/notification.
	 *
	 * For now this targets members who are:
	 * - active
	 * - auto_renewal_enabled = 1
	 * - payment_type is card or bank_account (Stripe-capable)
	 *
	 * @since  1.0.0
	 * @param  array $args Optional query args.
	 *                     Supported keys:
	 *                     - status (string) default 'active'
	 *                     - include_payment_types (string[]) default ['card','bank_account']
	 *                     - require_stripe_customer (bool) default true
	 * @return array        Array of member rows (associative arrays).
	 */
	public function get_members_for_renewal( array $args = array() ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'stsrc_members';

		$status               = sanitize_text_field( $args['status'] ?? 'active' );
		$include_payment_types = $args['include_payment_types'] ?? array( 'card', 'bank_account' );
		$require_customer      = (bool) ( $args['require_stripe_customer'] ?? true );

		$where   = array();
		$values  = array();

		$where[]  = 'status = %s';
		$values[] = $status;

		$where[] = 'auto_renewal_enabled = 1';

		// Payment types filter (prepared placeholders).
		$include_payment_types = array_values(
			array_filter(
				array_map(
					static fn( $t ) => sanitize_text_field( (string) $t ),
					(array) $include_payment_types
				),
				static fn( $t ) => '' !== $t
			)
		);

		if ( ! empty( $include_payment_types ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $include_payment_types ), '%s' ) );
			$where[]      = "payment_type IN ({$placeholders})";
			foreach ( $include_payment_types as $t ) {
				$values[] = $t;
			}
		}

		if ( $require_customer ) {
			$where[] = "stripe_customer_id IS NOT NULL AND stripe_customer_id <> ''";
		}

		$sql = "SELECT * FROM {$table_name}";
		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		$sql .= ' ORDER BY member_id ASC';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared below.
		$sql = $wpdb->prepare( $sql, $values );
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		return apply_filters( 'stsrc_auto_renewal_members', $rows ? $rows : array(), $args );
	}

	/**
	 * Ensure cron events for auto-renewal are scheduled.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ensure_cron_events(): void {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}

		$initial_timestamp = self::get_initial_cron_timestamp();

		if ( ! wp_next_scheduled( self::CRON_HOOK_NOTIFICATION ) ) {
			wp_schedule_event( $initial_timestamp, 'daily', self::CRON_HOOK_NOTIFICATION );
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK_PROCESS ) ) {
			wp_schedule_event( $initial_timestamp, 'daily', self::CRON_HOOK_PROCESS );
		}
	}

	/**
	 * Clear cron events registered for auto-renewal.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function clear_cron_events(): void {
		if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
			return;
		}

		wp_clear_scheduled_hook( self::CRON_HOOK_NOTIFICATION );
		wp_clear_scheduled_hook( self::CRON_HOOK_PROCESS );
	}

	/**
	 * Send renewal notification emails.
	 *
	 * The intended behavior is "7 days before season renewal date", but this method can
	 * also be called manually (e.g., from a future admin action) by passing $force_send.
	 *
	 * @since  1.0.0
	 * @param  bool $force_send If true, sends notifications regardless of current date.
	 * @return array            Summary: sent/failed/total/skipped + optional error message.
	 */
	public function send_renewal_notifications( bool $force_send = false ): array {
		$renewal_date = $this->get_season_renewal_date();
		if ( empty( $renewal_date ) ) {
			STSRC_Logger::warning(
				'Auto-renewal notifications skipped because season renewal date is not configured.',
				array( 'method' => __METHOD__ )
			);
			return array(
				'sent'   => 0,
				'failed' => 0,
				'total'  => 0,
				'skipped' => 0,
				'error'  => __( 'Season renewal date not configured.', 'smoketree-plugin' ),
			);
		}

		$today             = gmdate( 'Y-m-d' );
		$notify_target_day = gmdate( 'Y-m-d', strtotime( $renewal_date . ' -' . $this->notification_lead_days . ' days' ) );

		if ( ! $force_send && $today !== $notify_target_day ) {
			STSRC_Logger::debug(
				'Auto-renewal notifications skipped because current date is outside the notification window.',
				array(
					'method'           => __METHOD__,
					'today'            => $today,
					'notification_day' => $notify_target_day,
					'force_send'       => $force_send,
				)
			);
			return array(
				'sent'    => 0,
				'failed'  => 0,
				'total'   => 0,
				'skipped' => 1,
				'note'    => __( 'Not within notification window.', 'smoketree-plugin' ),
			);
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-email-service.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-payment-service.php';

		$email_service   = new STSRC_Email_Service();
		$payment_service = new STSRC_Payment_Service();

		$members = $this->get_members_for_renewal();
		$members = apply_filters( 'stsrc_auto_renewal_notification_members', $members, $renewal_date, $force_send );

		$results = array(
			'sent'   => 0,
			'failed' => 0,
			'total'  => count( $members ),
			'skipped' => 0,
		);

		foreach ( $members as $member ) {
			$membership_type = STSRC_Membership_DB::get_membership_type( (int) ( $member['membership_type_id'] ?? 0 ) );
			if ( ! $membership_type ) {
				STSRC_Logger::warning(
					'Auto-renewal notification skipped because membership type is missing.',
					array(
						'method'    => __METHOD__,
						'member_id' => $member['member_id'] ?? null,
					)
				);
				$results['failed']++;
				continue;
			}

			$amounts = $this->calculate_membership_amounts( $membership_type );
			$amount_due = '$' . number_format( (float) $amounts['total'], 2 );

			// Best-effort "payment link": customer portal if possible, otherwise member portal.
			$payment_link = home_url( '/member-portal' );
			if ( ! empty( $member['stripe_customer_id'] ) ) {
				$portal_url = $payment_service->get_customer_portal_url(
					(string) $member['stripe_customer_id'],
					home_url( '/member-portal' )
				);
				if ( $portal_url ) {
					$payment_link = $portal_url;
				}
			}

			$email_context = apply_filters(
				'stsrc_auto_renewal_email_context',
				array(
					'first_name'   => $member['first_name'] ?? '',
					'last_name'    => $member['last_name'] ?? '',
					'email'        => $member['email'] ?? '',
					'amount_due'   => $amount_due,
					'due_date'     => $renewal_date,
					'payment_link' => $payment_link,
				),
				$member,
				$membership_type,
				$renewal_date,
				$amounts
			);

			$subject = apply_filters(
				'stsrc_auto_renewal_email_subject',
				__( 'Smoketree Membership Renewal Reminder', 'smoketree-plugin' ),
				$member,
				$membership_type,
				$renewal_date
			);

			$sent = $email_service->send_email(
				'payment-reminder.php',
				$email_context,
				(string) ( $member['email'] ?? '' ),
				$subject
			);

			if ( $sent ) {
				$results['sent']++;
				do_action( 'stsrc_auto_renewal_notification_sent', $member, $email_context, $renewal_date );
			} else {
				$results['failed']++;
				do_action( 'stsrc_auto_renewal_notification_failed', $member, $email_context, $renewal_date );
			}
		}

		return apply_filters( 'stsrc_auto_renewal_notification_results', $results, $members, $renewal_date, $force_send );
	}

	/**
	 * Process season renewals (attempt Stripe off-session charges).
	 *
	 * IMPORTANT: Stripe off-session requires a saved default payment method on the customer.
	 * If unavailable, we log and skip the member.
	 *
	 * This method is built to be callable by a future WP-Cron handler (Step 36).
	 *
	 * @since  1.0.0
	 * @param  bool $force_process If true, processes regardless of current date.
	 * @return array               Summary: succeeded/failed/total/skipped + optional errors.
	 */
	public function process_renewals( bool $force_process = false ): array {
		$renewal_date = $this->get_season_renewal_date();
		if ( empty( $renewal_date ) ) {
			STSRC_Logger::warning(
				'Auto-renewal processing skipped because season renewal date is not configured.',
				array( 'method' => __METHOD__ )
			);
			return array(
				'succeeded' => 0,
				'failed'    => 0,
				'total'     => 0,
				'skipped'   => 0,
				'error'     => __( 'Season renewal date not configured.', 'smoketree-plugin' ),
			);
		}

		$today = gmdate( 'Y-m-d' );
		if ( ! $force_process && $today !== $renewal_date ) {
			STSRC_Logger::debug(
				'Auto-renewal processing skipped because current date is outside renewal window.',
				array(
					'method'        => __METHOD__,
					'today'         => $today,
					'renewal_date'  => $renewal_date,
					'force_process' => $force_process,
				)
			);
			return array(
				'succeeded' => 0,
				'failed'    => 0,
				'total'     => 0,
				'skipped'   => 1,
				'note'      => __( 'Not within renewal window.', 'smoketree-plugin' ),
			);
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-payment-log-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/class-stsrc-email-service.php';

		$members = $this->get_members_for_renewal();
		$members = apply_filters( 'stsrc_auto_renewal_charge_members', $members, $renewal_date, $force_process );

		$results = array(
			'succeeded' => 0,
			'failed'    => 0,
			'total'     => count( $members ),
			'skipped'   => 0,
		);

		$this->init_stripe();

		// If Stripe isn't available, we can only log failures.
		$stripe_ready = class_exists( '\Stripe\PaymentIntent' ) && class_exists( '\Stripe\Customer' );

		$email_service = new STSRC_Email_Service();

		foreach ( $members as $member ) {
			$member_id = (int) ( $member['member_id'] ?? 0 );
			if ( $member_id <= 0 ) {
				$results['failed']++;
				continue;
			}

			$membership_type = STSRC_Membership_DB::get_membership_type( (int) ( $member['membership_type_id'] ?? 0 ) );
			if ( ! $membership_type ) {
				$results['failed']++;
				STSRC_Logger::warning(
					'Auto-renewal processing skipped due to unknown membership type.',
					array(
						'method'    => __METHOD__,
						'member_id' => $member_id,
					)
				);
				continue;
			}

			$amounts = $this->calculate_membership_amounts( $membership_type );
			$total_amount = (float) $amounts['total'];

			// Always create a payment log for traceability.
			$payment_log_id = STSRC_Payment_Log_DB::log_payment(
				array(
					'member_id'    => $member_id,
					'amount'       => $total_amount,
					'fee_amount'   => (float) $amounts['fee'],
					'payment_type' => 'auto_renewal',
					'status'       => 'pending',
					'metadata'     => array(
						'season_renewal_date' => $renewal_date,
						'membership_type_id'  => (int) ( $member['membership_type_id'] ?? 0 ),
					),
				)
			);

			if ( false === $payment_log_id ) {
				STSRC_Logger::warning(
					'Auto-renewal payment log creation failed.',
					array(
						'method'    => __METHOD__,
						'member_id' => $member_id,
						'amount'    => $total_amount,
					)
				);
				$payment_log_id = 0;
			}

			if ( ! $stripe_ready ) {
				if ( $payment_log_id ) {
					STSRC_Payment_Log_DB::update_payment_status( (int) $payment_log_id, 'failed' );
				}
				$results['failed']++;
				do_action( 'stsrc_auto_renewal_stripe_unavailable', $member );
			STSRC_Logger::error(
				'Auto-renewal processing skipped because Stripe classes are unavailable.',
				array(
					'method'    => __METHOD__,
					'member_id' => $member_id,
				)
			);
				continue;
			}

			if ( empty( $member['stripe_customer_id'] ) ) {
				if ( $payment_log_id ) {
					STSRC_Payment_Log_DB::update_payment_status( (int) $payment_log_id, 'failed' );
				}
				$results['skipped']++;
				do_action( 'stsrc_auto_renewal_missing_customer', $member, $membership_type, $amounts );
			STSRC_Logger::info(
				'Auto-renewal skipped because member does not have a Stripe customer ID.',
				array(
					'method'    => __METHOD__,
					'member_id' => $member_id,
				)
			);
				continue;
			}

			try {
				$customer = \Stripe\Customer::retrieve( (string) $member['stripe_customer_id'] );
				$default_payment_method = $customer->invoice_settings->default_payment_method ?? null;

				if ( empty( $default_payment_method ) ) {
					if ( $payment_log_id ) {
						STSRC_Payment_Log_DB::update_payment_status( (int) $payment_log_id, 'failed' );
					}
					$results['skipped']++;
					continue;
				}

				$intent_params = apply_filters(
					'stsrc_auto_renewal_payment_intent_params',
					array(
						'amount'         => (int) round( $total_amount * 100 ),
						'currency'       => 'usd',
						'customer'       => (string) $member['stripe_customer_id'],
						'payment_method' => (string) $default_payment_method,
						'off_session'    => true,
						'confirm'        => true,
						'description'    => 'Smoketree membership auto-renewal',
						'metadata'       => array(
							'payment_type'        => 'auto_renewal',
							'member_id'           => $member_id,
							'membership_type_id'  => (int) ( $member['membership_type_id'] ?? 0 ),
							'season_renewal_date' => $renewal_date,
						),
					),
					$member,
					$membership_type,
					$amounts,
					$renewal_date
				);

				$intent = \Stripe\PaymentIntent::create( $intent_params );

				// Update payment log with intent id and status.
				if ( $payment_log_id ) {
					STSRC_Payment_Log_DB::update_payment_status( (int) $payment_log_id, 'succeeded' );
				}

				// Update expiration date based on renewal date + membership expiration period (days).
				$new_expiration = $this->calculate_new_expiration_date( $renewal_date, $membership_type );
				STSRC_Member_DB::update_member(
					$member_id,
					array(
						'expiration_date' => $new_expiration,
						'status'          => 'active',
					)
				);

				// Send confirmation email (best-effort).
				$email_service->send_email(
					'payment-success.php',
					array(
						'first_name'    => $member['first_name'] ?? '',
						'last_name'     => $member['last_name'] ?? '',
						'email'         => $member['email'] ?? '',
						'amount'        => '$' . number_format( $total_amount, 2 ),
						'payment_type'  => 'Auto-Renewal',
						'stripe_intent' => $intent->id ?? '',
					),
					(string) ( $member['email'] ?? '' ),
					'Smoketree Membership Renewal Payment Successful'
				);

				$results['succeeded']++;
				do_action( 'stsrc_auto_renewal_payment_succeeded', $member, $intent, $membership_type, $amounts, (int) $payment_log_id );
			} catch ( \Exception $e ) {
				if ( $payment_log_id ) {
					STSRC_Payment_Log_DB::update_payment_status( (int) $payment_log_id, 'failed' );
				}
				STSRC_Logger::exception(
					$e,
					array(
						'method'    => __METHOD__,
						'member_id' => $member_id,
						'amount'    => $total_amount,
					)
				);
				$results['failed']++;
				do_action( 'stsrc_auto_renewal_payment_failed', $member, $membership_type, $amounts, $e, (int) $payment_log_id );
			}
		}

		return apply_filters( 'stsrc_auto_renewal_processing_results', $results, $members, $renewal_date, $force_process );
	}

	/**
	 * Bulk update member status (season reset helper).
	 *
	 * Example use (future admin UI):
	 * - Start new season: mark all active -> cancelled, optionally clear auto-renewal flags and/or reset guest passes.
	 *
	 * @since  1.0.0
	 * @param  string $from_status Current status to match (e.g., 'active').
	 * @param  string $to_status   New status to set (e.g., 'cancelled').
	 * @param  array  $options     Optional behavior:
	 *                             - clear_auto_renewal (bool) default false
	 *                             - reset_guest_pass_balance (bool) default false
	 * @return int                 Number of rows affected.
	 */
	public function bulk_update_status( string $from_status, string $to_status, array $options = array() ): int {
		global $wpdb;

		$from_status = sanitize_text_field( $from_status );
		$to_status   = sanitize_text_field( $to_status );

		// Very small whitelist to prevent accidental injection of arbitrary states.
		$allowed_statuses = array( 'active', 'pending', 'cancelled', 'inactive' );
		if ( ! in_array( $from_status, $allowed_statuses, true ) || ! in_array( $to_status, $allowed_statuses, true ) ) {
			return 0;
		}

		$clear_auto_renewal       = (bool) ( $options['clear_auto_renewal'] ?? false );
		$reset_guest_pass_balance = (bool) ( $options['reset_guest_pass_balance'] ?? false );

		$table_name = $wpdb->prefix . 'stsrc_members';

		$set_parts   = array( 'status = %s' );
		$set_values  = array( $to_status );

		if ( $clear_auto_renewal ) {
			$set_parts[] = 'auto_renewal_enabled = 0';
		}
		if ( $reset_guest_pass_balance ) {
			$set_parts[] = 'guest_pass_balance = 0';
		}

		$set_parts[] = 'updated_at = %s';
		$set_values[] = current_time( 'mysql' );

		$sql = "UPDATE {$table_name} SET " . implode( ', ', $set_parts ) . ' WHERE status = %s';
		$set_values[] = $from_status;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared below.
		$prepared = $wpdb->prepare( $sql, $set_values );
		$result   = $wpdb->query( $prepared );

		return is_numeric( $result ) ? (int) $result : 0;
	}

	/**
	 * Handle cron callback for renewal notifications.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_notification_cron(): void {
		$results = $this->send_renewal_notifications();
		do_action( 'stsrc_auto_renewal_notification_cron_completed', $results );
	}

	/**
	 * Handle cron callback for renewal processing.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_processing_cron(): void {
		$results = $this->process_renewals();
		do_action( 'stsrc_auto_renewal_processing_cron_completed', $results );
	}

	/**
	 * Read the season renewal date (Y-m-d) from configuration.
	 *
	 * Uses ACF option field if available; falls back to wp option.
	 *
	 * @since  1.0.0
	 * @return string Season renewal date in Y-m-d format, or empty string if not set/invalid.
	 */
	private function get_season_renewal_date(): string {
		$date = '';

		if ( function_exists( 'get_field' ) ) {
			$date = (string) get_field( 'stsrc_season_renewal_date', 'option' );
		}

		if ( empty( $date ) ) {
			$date = (string) get_option( 'stsrc_season_renewal_date', '' );
		}

		$date = sanitize_text_field( $date );

		// Normalize to Y-m-d if possible.
		$ts = strtotime( $date );
		if ( false === $ts ) {
			return '';
		}

		return gmdate( 'Y-m-d', $ts );
	}

	/**
	 * Initialize Stripe SDK and set API key.
	 *
	 * Duplicates minimal setup from STSRC_Payment_Service because its init is private.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function init_stripe(): void {
		if ( ! class_exists( '\Stripe\Stripe' ) ) {
			$stripe_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'vendor/stripe/stripe-php/init.php';
			if ( file_exists( $stripe_path ) ) {
				require_once $stripe_path;
			}
		}

		$secret_key = get_option( 'stsrc_stripe_secret_key', '' );
		$test_mode  = get_option( 'stsrc_stripe_test_mode', '0' );
		if ( '1' === $test_mode ) {
			$secret_key = get_option( 'stsrc_stripe_test_secret_key', $secret_key );
		}

		if ( ! empty( $secret_key ) && class_exists( '\Stripe\Stripe' ) ) {
			\Stripe\Stripe::setApiKey( $secret_key );
		}
	}

	/**
	 * Determine the initial timestamp used when scheduling cron events.
	 *
	 * @since 1.0.0
	 * @return int Cron start timestamp.
	 */
	private static function get_initial_cron_timestamp(): int {
		$default = time() + HOUR_IN_SECONDS;
		$timestamp = apply_filters( 'stsrc_auto_renewal_cron_start', $default );

		if ( ! is_numeric( $timestamp ) ) {
			$timestamp = $default;
		}

		// Ensure the event is at least one minute in the future.
		return max( time() + MINUTE_IN_SECONDS, (int) $timestamp );
	}

	/**
	 * Calculate membership amounts (base + flat fee + total) based on membership type.
	 *
	 * @since  1.0.0
	 * @param  array $membership_type Membership type row from DB.
	 * @return array                  { base, fee, total } as floats.
	 */
	private function calculate_membership_amounts( array $membership_type ): array {
		$base = (float) ( $membership_type['price'] ?? 0.0 );
		$fee  = $this->get_flat_fee_from_membership_name( (string) ( $membership_type['name'] ?? '' ) );

		return array(
			'base'  => $base,
			'fee'   => $fee,
			'total' => $base + $fee,
		);
	}

	/**
	 * Determine flat fee using the membership type name.
	 *
	 * This mirrors STSRC_Payment_Service::get_flat_fee() but derives the "slug"
	 * from membership type name (since membership table doesn't store a slug).
	 *
	 * @since  1.0.0
	 * @param  string $membership_name Membership name from DB.
	 * @return float                   Flat fee amount.
	 */
	private function get_flat_fee_from_membership_name( string $membership_name ): float {
		$name = strtolower( $membership_name );

		$slug = 'single';
		if ( false !== strpos( $name, 'household' ) ) {
			$slug = 'household';
		} elseif ( false !== strpos( $name, 'duo' ) ) {
			$slug = 'duo';
		} elseif ( false !== strpos( $name, 'single' ) ) {
			$slug = 'single';
		}

		// Flat fee schedule.
		return match ( $slug ) {
			'household' => 10.00,
			'duo'       => 8.00,
			default     => 6.00,
		};
	}

	/**
	 * Calculate a new expiration date based on the season renewal date.
	 *
	 * @since  1.0.0
	 * @param  string $renewal_date    Season renewal date (Y-m-d).
	 * @param  array  $membership_type Membership type row.
	 * @return string                  New expiration date (Y-m-d).
	 */
	private function calculate_new_expiration_date( string $renewal_date, array $membership_type ): string {
		$period_days = (int) ( $membership_type['expiration_period'] ?? 365 );
		$ts          = strtotime( $renewal_date . ' +' . $period_days . ' days' );
		if ( false === $ts ) {
			// Fallback: 1 year from today (best-effort).
			return gmdate( 'Y-m-d', strtotime( '+365 days' ) );
		}

		return gmdate( 'Y-m-d', $ts );
	}
}


