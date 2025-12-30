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
}

