<?php

/**
 * Email service class
 *
 * Handles email sending with template rendering and batch operations.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/services
 */

/**
 * Email service class.
 *
 * Provides email sending functionality with template support.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/services
 * @author     Smoketree Swim and Recreation Club
 */
require_once __DIR__ . '/class-stsrc-logger.php';

class STSRC_Email_Service {

	/**
	 * Default email rate limit (emails per minute).
	 *
	 * @since    1.0.0
	 * @var      int    $rate_limit
	 */
	private int $rate_limit = 60;

	/**
	 * Request-scoped cache of member demo flags.
	 *
	 * @since    1.5.0
	 * @var      array<int,bool>
	 */
	private static array $demo_member_cache = array();

	/**
	 * Send email using template.
	 *
	 * @since    1.0.0
	 * @param    string    $template      Template filename (e.g., 'welcome.php')
	 * @param    array     $data          Array of template variables
	 * @param    string    $to            Recipient email
	 * @param    string    $subject       Email subject
	 * @param    array     $attachments   Array of file paths
	 * @return   bool                     True on success, false on failure
	 */
	public function send_email( string $template, array $data, string $to, string $subject, array $attachments = array() ): bool {
		if ( $this->is_demo_member( $data ) ) {
			return true;
		}

		// Render template
		$message = $this->render_template( $template, $data );

		if ( empty( $message ) ) {
			STSRC_Logger::warning(
				'Email not sent because rendered message is empty.',
				array(
					'method'   => __METHOD__,
					'template' => $template,
					'recipient'=> $to,
				)
			);
			return false;
		}

		// Set email headers for HTML
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: Smoketree Swim and Recreation Club <no-reply@smoketree.us>',
		);

		// Send email via wp_mail
		$result = wp_mail( $to, $subject, $message, $headers, $attachments );

		if ( ! $result ) {
			STSRC_Logger::error(
				'wp_mail returned false when sending email.',
				array(
					'method'    => __METHOD__,
					'template'  => $template,
					'recipient' => $to,
					'subject'   => $subject,
				)
			);
		}

		// Log email (if email log DB class is available)
		if ( class_exists( 'STSRC_Email_Log_DB' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-email-log-db.php';
			STSRC_Email_Log_DB::log_email(
				array(
					'email_campaign_id' => 'single',
					'recipient_email'   => $to,
					'subject'           => $subject,
					'status'             => $result ? 'sent' : 'failed',
					'sent_at'           => $result ? current_time( 'mysql' ) : null,
				)
			);
		}

		return $result;
	}

	/**
	 * Send batch email to multiple recipients.
	 *
	 * @since    1.0.0
	 * @param    array    $recipients      Array of recipient email addresses or member IDs
	 * @param    string   $template        Template filename
	 * @param    array    $template_data   Base template data (will be merged with individual recipient data)
	 * @param    string   $subject         Email subject
	 * @param    array    $attachments     Array of file paths
	 * @param    string   $campaign_id     Optional campaign ID for logging
	 * @return   array                     Array with 'sent', 'failed', and 'total' counts
	 */
	public function send_batch_email( array $recipients, string $template, array $template_data, string $subject, array $attachments = array(), string $campaign_id = '' ): array {
		$recipients = array_values(
			array_filter(
				$recipients,
				function( $recipient ): bool {
					if ( is_numeric( $recipient ) ) {
						return ! $this->is_demo_member(
							array(
								'member_id' => (int) $recipient,
							)
						);
					}

					if ( is_array( $recipient ) ) {
						return ! $this->is_demo_member( $recipient );
					}

					return true;
				}
			)
		);

		$results = array(
			'sent'   => 0,
			'failed' => 0,
			'total'  => count( $recipients ),
		);

		if ( empty( $campaign_id ) ) {
			$campaign_id = 'batch_' . time();
		}

		$start_time = time();
		$email_count = 0;

		foreach ( $recipients as $recipient ) {
			// Rate limiting: check if we've exceeded the limit
			$current_time = time();
			if ( $email_count > 0 && ( $current_time - $start_time ) < 60 ) {
				// Check if we've sent too many emails in this minute
				if ( $email_count >= $this->rate_limit ) {
					// Wait until next minute
					sleep( 60 - ( $current_time - $start_time ) );
					$start_time = time();
					$email_count = 0;
				}
			} elseif ( ( $current_time - $start_time ) >= 60 ) {
				// Reset counter after a minute
				$start_time = time();
				$email_count = 0;
			}

			// Prepare recipient data
			$recipient_data = $template_data;
			$recipient_email = '';

			// Handle different recipient formats
			if ( is_numeric( $recipient ) ) {
				// Recipient is a member ID
				require_once plugin_dir_path( dirname( __FILE__ ) ) . 'models/class-stsrc-member.php';
				$member = new STSRC_Member();
				if ( $member->load( (int) $recipient ) ) {
					$recipient_email = $member->email;
					$recipient_data  = array_merge(
						$template_data,
						array(
							'first_name' => $member->first_name,
							'last_name'  => $member->last_name,
							'email'      => $member->email,
							'member'     => $member,
						)
					);
				} else {
					STSRC_Logger::warning(
						'Batch email skipped: member record not found.',
						array(
							'method'     => __METHOD__,
							'member_id'  => (int) $recipient,
							'campaign_id'=> $campaign_id,
						)
					);
					$results['failed']++;
					continue;
				}
			} elseif ( is_string( $recipient ) && is_email( $recipient ) ) {
				// Recipient is an email address
				$recipient_email = $recipient;
				if ( isset( $template_data['email'] ) ) {
					$recipient_data['email'] = $recipient;
				}
			} elseif ( is_array( $recipient ) && isset( $recipient['email'] ) ) {
				// Recipient is an array with email and data
				$recipient_email = $recipient['email'];
				$recipient_data  = array_merge( $template_data, $recipient );
			} else {
				$results['failed']++;
				continue;
			}

			// Send email
			$success = $this->send_email( $template, $recipient_data, $recipient_email, $subject, $attachments );

			// Log email
			if ( class_exists( 'STSRC_Email_Log_DB' ) ) {
				require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-email-log-db.php';
				$member_id = ( is_numeric( $recipient ) ) ? (int) $recipient : null;
				STSRC_Email_Log_DB::log_email(
					array(
						'email_campaign_id' => $campaign_id,
						'member_id'        => $member_id,
						'recipient_email'   => $recipient_email,
						'subject'           => $subject,
						'status'            => $success ? 'sent' : 'failed',
						'sent_at'           => $success ? current_time( 'mysql' ) : null,
						'error_message'     => $success ? null : 'Failed to send email',
					)
				);
			}

			if ( ! $success ) {
				STSRC_Logger::warning(
					'Failed to send email to recipient during batch operation.',
					array(
						'method'      => __METHOD__,
						'campaign_id' => $campaign_id,
						'recipient'   => $recipient_email,
					)
				);
			}

			if ( $success ) {
				$results['sent']++;
			} else {
				$results['failed']++;
			}

			$email_count++;

			// Small delay between emails to prevent server overload
			usleep( 100000 ); // 0.1 second delay
		}

		return $results;
	}

	/**
	 * Determine whether the email data references a demo member.
	 *
	 * @since    1.5.0
	 * @param    array $data Email payload data.
	 * @return   bool
	 */
	private function is_demo_member( array $data ): bool {
		$member_id = 0;

		if ( isset( $data['member_id'] ) ) {
			$member_id = (int) $data['member_id'];
		}

		if ( $member_id <= 0 && isset( $data['member'] ) ) {
			$member_data = $data['member'];
			if ( is_array( $member_data ) ) {
				$member_id = (int) ( $member_data['member_id'] ?? 0 );
				if ( 1 === (int) ( $member_data['is_demo'] ?? 0 ) ) {
					return true;
				}
			} elseif ( is_object( $member_data ) ) {
				if ( isset( $member_data->member_id ) ) {
					$member_id = (int) $member_data->member_id;
				}
				if ( isset( $member_data->is_demo ) && 1 === (int) $member_data->is_demo ) {
					return true;
				}
			}
		}

		if ( $member_id <= 0 ) {
			return false;
		}

		if ( isset( self::$demo_member_cache[ $member_id ] ) ) {
			return self::$demo_member_cache[ $member_id ];
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		$member = STSRC_Member_DB::get_member( $member_id );
		$is_demo = ! empty( $member ) && 1 === (int) ( $member['is_demo'] ?? 0 );
		self::$demo_member_cache[ $member_id ] = $is_demo;

		return $is_demo;
	}

	/**
	 * Render email template with data.
	 *
	 * @since    1.0.0
	 * @param    string    $template    Template filename (e.g., 'welcome.php')
	 * @param    array     $data        Array of template variables
	 * @return   string                 Rendered HTML email content
	 */
	public function render_template( string $template, array $data ): string {
		// Get template file path
		$template_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'templates/' . $template;

		if ( ! file_exists( $template_path ) ) {
			STSRC_Logger::error(
				'Email template file not found.',
				array(
					'method'        => __METHOD__,
					'template'      => $template,
					'template_path' => $template_path,
				)
			);
			return '';
		}

		// Extract variables for template
		extract( $data, EXTR_SKIP );

		// Start output buffering
		ob_start();

		// Include template file
		include $template_path;

		// Get buffered content
		$content = ob_get_clean();

		// Replace placeholders if any remain (fallback)
		$content = $this->replace_placeholders( $content, $data );

		return $content;
	}

	/**
	 * Replace placeholders in content.
	 *
	 * @since    1.0.0
	 * @param    string    $content    Content with placeholders
	 * @param    array     $data        Data to replace placeholders
	 * @return   string                Content with placeholders replaced
	 */
	public function replace_placeholders( string $content, array $data ): string {
		// Common placeholder patterns: {variable_name}
		foreach ( $data as $key => $value ) {
			// Skip arrays and objects
			if ( is_array( $value ) || is_object( $value ) ) {
				continue;
			}

			$placeholder = '{' . $key . '}';
			$content     = str_replace( $placeholder, (string) $value, $content );
		}

		return $content;
	}

	/**
	 * Set email rate limit.
	 *
	 * @since    1.0.0
	 * @param    int    $limit    Emails per minute
	 * @return   void
	 */
	public function set_rate_limit( int $limit ): void {
		$this->rate_limit = $limit;
	}

	/**
	 * Get email rate limit.
	 *
	 * @since    1.0.0
	 * @return   int    Emails per minute
	 */
	public function get_rate_limit(): int {
		return $this->rate_limit;
	}

	/**
	 * Send balance payment success email to member.
	 *
	 * @since    1.1.0
	 * @param    int $member_id       Member ID.
	 * @param    int $transaction_id  Transaction ID.
	 * @return   bool                 True on success, false on failure.
	 */
	public function send_balance_payment_success_email( int $member_id, int $transaction_id ): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-transaction-db.php';

		$member = STSRC_Member_DB::get_member( $member_id );
		if ( ! $member || empty( $member['email'] ) ) {
			STSRC_Logger::warning(
				'Unable to send balance payment success email: member not found.',
				array(
					'method'         => __METHOD__,
					'member_id'      => $member_id,
					'transaction_id' => $transaction_id,
				)
			);
			return false;
		}

		$transaction = STSRC_Transaction_DB::get_transaction( $transaction_id );
		if ( ! $transaction ) {
			STSRC_Logger::warning(
				'Unable to send balance payment success email: transaction not found.',
				array(
					'method'         => __METHOD__,
					'member_id'      => $member_id,
					'transaction_id' => $transaction_id,
				)
			);
			return false;
		}

		$amount_paid  = abs( (float) ( $transaction['amount'] ?? 0 ) );
		$new_balance  = (float) ( $transaction['balance_after'] ?? 0 );
		$method_raw   = (string) ( $transaction['payment_method'] ?? '' );
		$created_at   = (string) ( $transaction['created_at'] ?? '' );

		$method_labels = array(
			'card'            => __( 'Credit Card', 'smoketree-plugin' ),
			'us_bank_account' => __( 'Bank Account (ACH)', 'smoketree-plugin' ),
			'check'           => __( 'Check', 'smoketree-plugin' ),
			'zelle'           => __( 'Zelle', 'smoketree-plugin' ),
			'cash'            => __( 'Cash', 'smoketree-plugin' ),
		);

		$data = array(
			'first_name'       => $member['first_name'] ?? '',
			'last_name'        => $member['last_name'] ?? '',
			'email'            => $member['email'],
			'amount_paid'      => '$' . number_format( $amount_paid, 2 ),
			'new_balance'      => $new_balance,
			'new_balance_text' => $new_balance <= 0.01 ? __( 'Paid in Full', 'smoketree-plugin' ) : '$' . number_format( $new_balance, 2 ),
			'payment_method'   => $method_labels[ $method_raw ] ?? ucfirst( str_replace( '_', ' ', $method_raw ) ),
			'transaction_date' => ! empty( $created_at ) ? date_i18n( get_option( 'date_format' ), strtotime( $created_at ) ) : '',
			'portal_url'       => home_url( '/member-portal#stsrc-member-transaction-history' ),
			'is_paid_in_full'  => $new_balance <= 0.01,
		);

		return $this->send_email(
			'balance-payment-success.php',
			$data,
			sanitize_email( $member['email'] ),
			__( 'Balance Payment Received', 'smoketree-plugin' )
		);
	}

	/**
	 * Send balance payment failed email to member.
	 *
	 * @since    1.1.0
	 * @param    int    $member_id Member ID.
	 * @param    float  $amount    Attempted payment amount.
	 * @param    string $reason    Failure reason from Stripe, if available.
	 * @return   bool              True on success, false on failure.
	 */
	public function send_balance_payment_failed_email( int $member_id, float $amount, string $reason = '' ): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';

		$member = STSRC_Member_DB::get_member( $member_id );
		if ( ! $member || empty( $member['email'] ) ) {
			STSRC_Logger::warning(
				'Unable to send balance payment failed email: member not found.',
				array(
					'method'    => __METHOD__,
					'member_id' => $member_id,
				)
			);
			return false;
		}

		$attempted_amount = abs( $amount );
		$current_balance  = (float) ( $member['balance_owed'] ?? 0 );
		$failure_reason   = ! empty( $reason )
			? sanitize_text_field( $reason )
			: __( 'Your bank or card issuer declined the payment attempt.', 'smoketree-plugin' );

		$data = array(
			'first_name'       => $member['first_name'] ?? '',
			'last_name'        => $member['last_name'] ?? '',
			'email'            => $member['email'],
			'attempted_amount' => '$' . number_format( $attempted_amount, 2 ),
			'failure_reason'   => $failure_reason,
			'current_balance'  => '$' . number_format( $current_balance, 2 ),
			'portal_url'       => home_url( '/member-portal' ),
			'secretary_email'  => implode( ', ', array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) get_option( 'stsrc_secretary_email', '' ) ) ) ) ) ),
		);

		return $this->send_email(
			'balance-payment-failed.php',
			$data,
			sanitize_email( $member['email'] ),
			__( 'Balance Payment Failed', 'smoketree-plugin' )
		);
	}

	/**
	 * Send manual payment confirmation email to member.
	 *
	 * @since    1.1.0
	 * @param    int $member_id      Member ID.
	 * @param    int $transaction_id Transaction ID.
	 * @return   bool                True on success, false on failure.
	 */
	public function send_manual_payment_confirmation_email( int $member_id, int $transaction_id ): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-transaction-db.php';

		$member = STSRC_Member_DB::get_member( $member_id );
		if ( ! $member || empty( $member['email'] ) ) {
			STSRC_Logger::warning(
				'Unable to send manual payment confirmation email: member not found.',
				array(
					'method'         => __METHOD__,
					'member_id'      => $member_id,
					'transaction_id' => $transaction_id,
				)
			);
			return false;
		}

		$transaction = STSRC_Transaction_DB::get_transaction( $transaction_id );
		if ( ! $transaction ) {
			STSRC_Logger::warning(
				'Unable to send manual payment confirmation email: transaction not found.',
				array(
					'method'         => __METHOD__,
					'member_id'      => $member_id,
					'transaction_id' => $transaction_id,
				)
			);
			return false;
		}

		$amount_paid = abs( (float) ( $transaction['amount'] ?? 0 ) );
		$new_balance = (float) ( $transaction['balance_after'] ?? 0 );
		$method_raw  = (string) ( $transaction['payment_method'] ?? '' );
		$created_at  = (string) ( $transaction['created_at'] ?? '' );
		$admin_notes = (string) ( $transaction['admin_notes'] ?? '' );

		$method_labels = array(
			'check'           => __( 'Check', 'smoketree-plugin' ),
			'zelle'           => __( 'Zelle', 'smoketree-plugin' ),
			'cash'            => __( 'Cash', 'smoketree-plugin' ),
			'card'            => __( 'Credit Card', 'smoketree-plugin' ),
			'us_bank_account' => __( 'Bank Account (ACH)', 'smoketree-plugin' ),
		);

		$check_number = '';
		if ( 'check' === $method_raw && preg_match( '/Check #:\s*([^\r\n]+)/i', $admin_notes, $matches ) ) {
			$check_number = sanitize_text_field( trim( (string) $matches[1] ) );
		}

		$data = array(
			'first_name'          => $member['first_name'] ?? '',
			'last_name'           => $member['last_name'] ?? '',
			'email'               => $member['email'],
			'payment_method'      => $method_labels[ $method_raw ] ?? ucfirst( str_replace( '_', ' ', $method_raw ) ),
			'check_number'        => $check_number,
			'amount_paid'         => '$' . number_format( $amount_paid, 2 ),
			'date_received'       => ! empty( $created_at ) ? date_i18n( get_option( 'date_format' ), strtotime( $created_at ) ) : '',
			'new_balance_text'    => $new_balance <= 0.01 ? __( 'Paid in Full', 'smoketree-plugin' ) : '$' . number_format( $new_balance, 2 ),
			'is_paid_in_full'     => $new_balance <= 0.01,
			'portal_url'          => home_url( '/member-portal#stsrc-member-transaction-history' ),
			'member_status'       => (string) ( $member['status'] ?? 'pending' ),
			'activation_possible' => $new_balance <= 0.01,
		);

		return $this->send_email(
			'manual-payment-received.php',
			$data,
			sanitize_email( $member['email'] ),
			__( 'Payment Confirmation - Smoketree Membership', 'smoketree-plugin' )
		);
	}

	/**
	 * Send admin notification for a successful balance payment.
	 *
	 * @since    1.1.0
	 * @param    int $member_id      Member ID.
	 * @param    int $transaction_id Transaction ID.
	 * @return   bool                True when at least one email sends successfully.
	 */
	public function send_admin_balance_payment_notification( int $member_id, int $transaction_id ): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-transaction-db.php';

		$member = STSRC_Member_DB::get_member( $member_id );
		if ( ! $member || empty( $member['email'] ) ) {
			STSRC_Logger::warning(
				'Unable to send admin balance payment notification: member not found.',
				array(
					'method'         => __METHOD__,
					'member_id'      => $member_id,
					'transaction_id' => $transaction_id,
				)
			);
			return false;
		}

		$transaction = STSRC_Transaction_DB::get_transaction( $transaction_id );
		if ( ! $transaction ) {
			STSRC_Logger::warning(
				'Unable to send admin balance payment notification: transaction not found.',
				array(
					'method'         => __METHOD__,
					'member_id'      => $member_id,
					'transaction_id' => $transaction_id,
				)
			);
			return false;
		}

		$amount_paid = abs( (float) ( $transaction['amount'] ?? 0 ) );
		$new_balance = (float) ( $transaction['balance_after'] ?? 0 );
		$method_raw  = (string) ( $transaction['payment_method'] ?? '' );
		$created_at  = (string) ( $transaction['created_at'] ?? '' );

		$method_labels = array(
			'card'            => __( 'Credit Card', 'smoketree-plugin' ),
			'us_bank_account' => __( 'Bank Account (ACH)', 'smoketree-plugin' ),
			'check'           => __( 'Check', 'smoketree-plugin' ),
			'zelle'           => __( 'Zelle', 'smoketree-plugin' ),
			'cash'            => __( 'Cash', 'smoketree-plugin' ),
		);

		$admin_members_url = admin_url( 'admin.php?page=stsrc-members' );
		$member_admin_url  = add_query_arg(
			array(
				'action'    => 'edit',
				'member_id' => (int) $member_id,
			),
			$admin_members_url
		);

		$data = array(
			'member_name'             => trim( (string) ( $member['first_name'] ?? '' ) . ' ' . (string) ( $member['last_name'] ?? '' ) ),
			'member_email'            => $member['email'],
			'amount_paid'             => '$' . number_format( $amount_paid, 2 ),
			'payment_method'          => $method_labels[ $method_raw ] ?? ucfirst( str_replace( '_', ' ', $method_raw ) ),
			'new_balance'             => '$' . number_format( $new_balance, 2 ),
			'is_paid_in_full'         => $new_balance <= 0.01,
			'transaction_date'        => ! empty( $created_at ) ? date_i18n( get_option( 'date_format' ), strtotime( $created_at ) ) : '',
			'member_admin_url'        => $member_admin_url,
			'member_activated_notice' => $new_balance <= 0.01,
		);

		$subject = sprintf(
			/* translators: %s: member full name */
			__( 'Balance Payment Received - %s', 'smoketree-plugin' ),
			$data['member_name']
		);

		$admin_emails = $this->get_admin_notification_recipients();
		if ( empty( $admin_emails ) ) {
			return false;
		}

		$sent_any = false;
		foreach ( $admin_emails as $president_email) {
			$result = $this->send_email(
				'notify-admin-balance-payment.php',
				$data,
				$president_email,
				$subject
			);
			if ( $result ) {
				$sent_any = true;
			}
		}

		return $sent_any;
	}

	/**
	 * Send admin overpayment alert email.
	 *
	 * @since    1.1.0
	 * @param    int $member_id      Member ID.
	 * @param    int $transaction_id Transaction ID.
	 * @return   bool                True when at least one email sends successfully.
	 */
	public function send_admin_overpayment_alert( int $member_id, int $transaction_id ): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-member-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-transaction-db.php';

		$member = STSRC_Member_DB::get_member( $member_id );
		if ( ! $member || empty( $member['email'] ) ) {
			STSRC_Logger::warning(
				'Unable to send admin overpayment alert: member not found.',
				array(
					'method'         => __METHOD__,
					'member_id'      => $member_id,
					'transaction_id' => $transaction_id,
				)
			);
			return false;
		}

		$transaction = STSRC_Transaction_DB::get_transaction( $transaction_id );
		if ( ! $transaction ) {
			STSRC_Logger::warning(
				'Unable to send admin overpayment alert: transaction not found.',
				array(
					'method'         => __METHOD__,
					'member_id'      => $member_id,
					'transaction_id' => $transaction_id,
				)
			);
			return false;
		}

		$new_balance       = (float) ( $transaction['balance_after'] ?? 0 );
		$overpayment_amount = abs( min( 0.0, $new_balance ) );
		$payment_amount    = abs( (float) ( $transaction['amount'] ?? 0 ) );
		$method_raw        = (string) ( $transaction['payment_method'] ?? '' );
		$created_at        = (string) ( $transaction['created_at'] ?? '' );

		$method_labels = array(
			'card'            => __( 'Credit Card', 'smoketree-plugin' ),
			'us_bank_account' => __( 'Bank Account (ACH)', 'smoketree-plugin' ),
			'check'           => __( 'Check', 'smoketree-plugin' ),
			'zelle'           => __( 'Zelle', 'smoketree-plugin' ),
			'cash'            => __( 'Cash', 'smoketree-plugin' ),
		);

		$admin_members_url = admin_url( 'admin.php?page=stsrc-members' );
		$member_admin_url  = add_query_arg(
			array(
				'action'    => 'edit',
				'member_id' => (int) $member_id,
			),
			$admin_members_url
		);

		$data = array(
			'member_name'       => trim( (string) ( $member['first_name'] ?? '' ) . ' ' . (string) ( $member['last_name'] ?? '' ) ),
			'member_email'      => $member['email'],
			'overpayment_amount'=> '$' . number_format( $overpayment_amount, 2 ),
			'payment_amount'    => '$' . number_format( $payment_amount, 2 ),
			'payment_method'    => $method_labels[ $method_raw ] ?? ucfirst( str_replace( '_', ' ', $method_raw ) ),
			'transaction_date'  => ! empty( $created_at ) ? date_i18n( get_option( 'date_format' ), strtotime( $created_at ) ) : '',
			'new_balance'       => '$' . number_format( $new_balance, 2 ),
			'member_admin_url'  => $member_admin_url,
		);

		$subject = sprintf(
			/* translators: %s: member full name */
			__( 'Member Overpayment Alert - %s', 'smoketree-plugin' ),
			$data['member_name']
		);

		$admin_emails = $this->get_admin_notification_recipients();
		if ( empty( $admin_emails ) ) {
			return false;
		}

		$sent_any = false;
		foreach ( $admin_emails as $president_email) {
			$result = $this->send_email(
				'notify-admin-overpayment.php',
				$data,
				$president_email,
				$subject
			);
			if ( $result ) {
				$sent_any = true;
			}
		}

		return $sent_any;
	}

	/**
	 * Get admin recipients for operational notification emails.
	 *
	 * @since    1.1.0
	 * @return   array<string> Sanitized unique email addresses.
	 */
	private function get_admin_notification_recipients(): array {
		$secretary_emails = $this->parse_csv_emails( (string) $this->get_option_or_field( 'stsrc_secretary_email' ) );
		$emails = array_filter(
			array_merge(
				array(
					sanitize_email( (string) $this->get_option_or_field( 'stsrc_president_email' ) ),
					sanitize_email( (string) $this->get_option_or_field( 'stsrc_vice_president_email' ) ),
					sanitize_email( (string) $this->get_option_or_field( 'stsrc_treasurer_email' ) ),
				),
				$secretary_emails
			)
		);

		$admin_users = get_users( array( 'role' => 'administrator' ) );
		foreach ( $admin_users as $admin_user ) {
			if ( ! empty( $admin_user->user_email ) ) {
				$emails[] = sanitize_email( (string) $admin_user->user_email );
			}
		}

		$emails = array_filter( $emails, 'is_email' );
		$emails = array_values( array_unique( $emails ) );

		return $emails;
	}

	/**
	 * Send admin renewal notification email to all configured recipients.
	 *
	 * @param array $renewal Renewal row.
	 * @param array $member Member row.
	 * @return bool
	 */
	public function send_admin_renewal_notice( array $renewal, array $member ): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';

		$type_id         = (int) ( $renewal['new_membership_type_id'] ?? 0 );
		$membership_type = STSRC_Membership_DB::get_membership_type( $type_id );
		$method          = sanitize_key( (string) ( $renewal['payment_method'] ?? '' ) );
		$method_label_map = array(
			'card'            => __( 'Credit/Debit Card', 'smoketree-plugin' ),
			'ach'             => __( 'Bank Account (ACH)', 'smoketree-plugin' ),
			'bank_account'    => __( 'Bank Account (ACH)', 'smoketree-plugin' ),
			'us_bank_account' => __( 'Bank Account (ACH)', 'smoketree-plugin' ),
			'zelle'           => __( 'Zelle', 'smoketree-plugin' ),
			'check'           => __( 'Check', 'smoketree-plugin' ),
		);
		$member_id = (int) ( $renewal['member_id'] ?? 0 );
		$data      = array(
			'member_name'        => trim( (string) ( $member['first_name'] ?? '' ) . ' ' . (string) ( $member['last_name'] ?? '' ) ),
			'member_email'       => (string) ( $member['email'] ?? '' ),
			'season_key'         => (string) ( $renewal['season_key'] ?? '' ),
			'membership_type_name' => (string) ( $membership_type['name'] ?? '' ),
			'payment_method_label' => $method_label_map[ $method ] ?? ucfirst( str_replace( '_', ' ', $method ) ),
			'total_amount'       => (float) ( $renewal['total_amount'] ?? 0 ),
			'member_admin_url'   => add_query_arg(
				array(
					'action'    => 'edit',
					'member_id' => $member_id,
				),
				admin_url( 'admin.php?page=stsrc-members' )
			),
		);
		$subject   = sprintf(
			/* translators: %s: member full name */
			__( 'Renewal Completed - %s', 'smoketree-plugin' ),
			$data['member_name']
		);
		$recipients = $this->get_admin_notification_recipients();
		if ( empty( $recipients ) ) {
			return false;
		}

		$sent_any = false;
		foreach ( $recipients as $recipient ) {
			$result = $this->send_email( 'email/notify-admin-renewal.php', $data, $recipient, $subject );
			if ( $result ) {
				$sent_any = true;
			}
		}

		return $sent_any;
	}

	/**
	 * Send admin notification for a renewal awaiting offline payment.
	 *
	 * @param array $renewal Renewal row.
	 * @param array $member  Member row.
	 * @return bool
	 */
	public function send_admin_renewal_pending_notice( array $renewal, array $member ): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';

		$type_id         = (int) ( $renewal['new_membership_type_id'] ?? 0 );
		$membership_type = STSRC_Membership_DB::get_membership_type( $type_id );
		$method          = sanitize_key( (string) ( $renewal['payment_method'] ?? '' ) );
		$method_label_map = array(
			'zelle'        => __( 'Zelle', 'smoketree-plugin' ),
			'check'        => __( 'Check', 'smoketree-plugin' ),
			'cash'         => __( 'Cash', 'smoketree-plugin' ),
			'payment_plan' => __( 'Payment Plan', 'smoketree-plugin' ),
		);
		$member_id = (int) ( $renewal['member_id'] ?? 0 );
		$data      = array(
			'member_name'          => trim( (string) ( $member['first_name'] ?? '' ) . ' ' . (string) ( $member['last_name'] ?? '' ) ),
			'member_email'         => (string) ( $member['email'] ?? '' ),
			'season_key'           => (string) ( $renewal['season_key'] ?? '' ),
			'membership_type_name' => (string) ( $membership_type['name'] ?? '' ),
			'payment_method_label' => $method_label_map[ $method ] ?? ucfirst( str_replace( '_', ' ', $method ) ),
			'total_amount'         => (float) ( $renewal['total_amount'] ?? 0 ),
			'member_admin_url'     => add_query_arg(
				array(
					'action'    => 'edit',
					'member_id' => $member_id,
				),
				admin_url( 'admin.php?page=stsrc-members' )
			),
		);
		$subject    = sprintf(
			/* translators: %s: member full name */
			__( 'Renewal Submitted (Awaiting Payment) - %s', 'smoketree-plugin' ),
			$data['member_name']
		);
		$recipients = $this->get_admin_notification_recipients();
		if ( empty( $recipients ) ) {
			return false;
		}

		$sent_any = false;
		foreach ( $recipients as $recipient ) {
			$result = $this->send_email( 'email/notify-admin-renewal-pending.php', $data, $recipient, $subject );
			if ( $result ) {
				$sent_any = true;
			}
		}

		return $sent_any;
	}

	/**
	 * Send renewal pending-payment email to member with payment instructions.
	 *
	 * Triggered immediately after a non-Stripe renewal submission so the
	 * member has a permanent reference for how to pay.
	 *
	 * @param array $renewal Renewal record.
	 * @param array $member  Member record.
	 * @return bool
	 */
	public function send_member_renewal_pending_email( array $renewal, array $member ): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';

		$to_email = sanitize_email( (string) ( $member['email'] ?? '' ) );
		if ( '' === $to_email ) {
			return false;
		}

		$type_id          = (int) ( $renewal['new_membership_type_id'] ?? 0 );
		$membership_type  = STSRC_Membership_DB::get_membership_type( $type_id );
		$method           = sanitize_key( (string) ( $renewal['payment_method'] ?? '' ) );
		$method_label_map = array(
			'zelle'        => __( 'Zelle', 'smoketree-plugin' ),
			'check'        => __( 'Check', 'smoketree-plugin' ),
			'cash'         => __( 'Cash', 'smoketree-plugin' ),
			'payment_plan' => __( 'Payment Plan', 'smoketree-plugin' ),
		);

		$acf_field_map = array(
			'zelle'        => 'zelle_instructions',
			'check'        => 'check_instructions',
			'cash'         => 'cash_instructions',
			'payment_plan' => 'payment_plan_instructions',
		);

		$payment_instructions = '';
		if ( isset( $acf_field_map[ $method ] ) ) {
			$payment_instructions = (string) $this->get_option_or_field( $acf_field_map[ $method ] );
		}

		$data = array(
			'first_name'           => (string) ( $member['first_name'] ?? '' ),
			'last_name'            => (string) ( $member['last_name'] ?? '' ),
			'member_id'            => (int) ( $member['member_id'] ?? 0 ),
			'season_key'           => (string) ( $renewal['season_key'] ?? '' ),
			'membership_type_name' => (string) ( $membership_type['name'] ?? '' ),
			'total_amount'         => (float) ( $renewal['total_amount'] ?? 0 ),
			'payment_method_label' => $method_label_map[ $method ] ?? ucfirst( str_replace( '_', ' ', $method ) ),
			'payment_instructions' => $payment_instructions,
		);

		return $this->send_email(
			'email/renewal-pending-payment.php',
			$data,
			$to_email,
			__( 'Your Smoketree Renewal — Payment Instructions', 'smoketree-plugin' )
		);
	}

	/**
	 * Send renewal confirmation email to member.
	 *
	 * @param int   $member_id Member ID.
	 * @param array $renewal Renewal record.
	 * @param array $member Member record.
	 * @return bool
	 */
	public function send_member_renewal_confirmation( int $member_id, array $renewal, array $member ): bool {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-stsrc-membership-db.php';

		$to_email = sanitize_email( (string) ( $member['email'] ?? '' ) );
		if ( '' === $to_email ) {
			return false;
		}

		$type_id          = (int) ( $renewal['new_membership_type_id'] ?? 0 );
		$membership_type  = STSRC_Membership_DB::get_membership_type( $type_id );
		$type_name        = (string) ( $membership_type['name'] ?? '' );
		$method           = sanitize_key( (string) ( $renewal['payment_method'] ?? '' ) );
		$template         = 'email/renewal-confirmation.php';
		$subject          = __( 'Your Smoketree Renewal Confirmation', 'smoketree-plugin' );
		$method_label_map = array(
			'card'            => __( 'Credit/Debit Card', 'smoketree-plugin' ),
			'ach'             => __( 'Bank Account (ACH)', 'smoketree-plugin' ),
			'bank_account'    => __( 'Bank Account (ACH)', 'smoketree-plugin' ),
			'us_bank_account' => __( 'Bank Account (ACH)', 'smoketree-plugin' ),
			'zelle'           => __( 'Zelle', 'smoketree-plugin' ),
			'check'           => __( 'Check', 'smoketree-plugin' ),
		);

		if ( 'civic' === strtolower( $type_name ) ) {
			$template = 'email/renewal-confirmation-civic.php';
		}

		$payment_instructions = '';
		if ( in_array( $method, array( 'zelle', 'check' ), true ) ) {
			$payment_instructions = (string) get_option( 'stsrc_payment_instructions_' . $method, '' );
		}

		$data = array(
			'first_name'          => (string) ( $member['first_name'] ?? '' ),
			'last_name'           => (string) ( $member['last_name'] ?? '' ),
			'member_id'           => $member_id,
			'season_key'          => (string) ( $renewal['season_key'] ?? '' ),
			'membership_type_name' => $type_name,
			'total_amount'        => (float) ( $renewal['total_amount'] ?? 0 ),
			'payment_method_label' => $method_label_map[ $method ] ?? ucfirst( str_replace( '_', ' ', $method ) ),
			'payment_instructions' => $payment_instructions,
		);

		return $this->send_email( $template, $data, $to_email, $subject );
	}

	/**
	 * Read an option from ACF first, then fallback to wp_options.
	 *
	 * @param string $key Option key.
	 * @return mixed
	 */
	private function get_option_or_field( string $key ) {
		if ( function_exists( 'get_field' ) ) {
			$value = get_field( $key, 'option' );
			if ( null !== $value && '' !== $value ) {
				return $value;
			}
		}

		return get_option( $key, '' );
	}

	/**
	 * Parse comma-separated emails into unique valid list.
	 *
	 * @param string $value CSV email string.
	 * @return array<string>
	 */
	private function parse_csv_emails( string $value ): array {
		$emails = array_filter(
			array_map(
				'sanitize_email',
				array_map( 'trim', explode( ',', $value ) )
			),
			'is_email'
		);

		return array_values( array_unique( $emails ) );
	}
}

