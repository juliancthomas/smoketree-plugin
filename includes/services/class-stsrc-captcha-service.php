<?php

/**
 * CAPTCHA service class
 *
 * Handles CAPTCHA verification for spam prevention.
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/services
 */

/**
 * CAPTCHA service class.
 *
 * Provides CAPTCHA verification for Google reCAPTCHA v3 and hCaptcha.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/services
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Captcha_Service {

	/**
	 * Rate limit transient prefix.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const RATE_LIMIT_PREFIX = 'stsrc_rate_';

	/**
	 * CAPTCHA provider type.
	 *
	 * @since    1.0.0
	 * @var      string    $provider    'recaptcha' or 'hcaptcha'
	 */
	private string $provider = 'recaptcha';

	/**
	 * Minimum score threshold for reCAPTCHA v3 (0.0 to 1.0).
	 *
	 * @since    1.0.0
	 * @var      float    $score_threshold
	 */
	private float $score_threshold = 0.5;

	/**
	 * Verify CAPTCHA token (server-side).
	 *
	 * @since    1.0.0
	 * @param    string    $token    CAPTCHA token from client
	 * @return   bool                True if valid, false otherwise
	 */
	public function verify_token( string $token ): bool {
		if ( empty( $token ) ) {
			return false;
		}

		if ( ! $this->is_enabled() ) {
			// If CAPTCHA is not configured, allow (for development/testing)
			return true;
		}

		$secret_key = $this->get_secret_key();
		if ( empty( $secret_key ) ) {
			return false;
		}

		$provider = $this->get_provider();
		if ( 'recaptcha' === $provider ) {
			return $this->verify_recaptcha( $token, $secret_key );
		} elseif ( 'hcaptcha' === $provider ) {
			return $this->verify_hcaptcha( $token, $secret_key );
		}

		return false;
	}

	/**
	 * Verify Google reCAPTCHA v3 token.
	 *
	 * @since    1.0.0
	 * @param    string    $token       reCAPTCHA token
	 * @param    string    $secret_key  reCAPTCHA secret key
	 * @return   bool                   True if valid and score meets threshold
	 */
	private function verify_recaptcha( string $token, string $secret_key ): bool {
		$url = 'https://www.google.com/recaptcha/api/siteverify';

		$response = wp_remote_post(
			$url,
			array(
				'body' => array(
					'secret'   => $secret_key,
					'response' => $token,
					'remoteip' => $this->get_user_ip(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'reCAPTCHA verification error: ' . $response->get_error_message() );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['success'] ) || ! $data['success'] ) {
			return false;
		}

		// Check score threshold for reCAPTCHA v3
		if ( isset( $data['score'] ) ) {
			$score = (float) $data['score'];
			if ( $score < $this->score_threshold ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Verify hCaptcha token.
	 *
	 * @since    1.0.0
	 * @param    string    $token       hCaptcha token
	 * @param    string    $secret_key  hCaptcha secret key
	 * @return   bool                   True if valid
	 */
	private function verify_hcaptcha( string $token, string $secret_key ): bool {
		$url = 'https://hcaptcha.com/siteverify';

		$response = wp_remote_post(
			$url,
			array(
				'body' => array(
					'secret'   => $secret_key,
					'response' => $token,
					'remoteip' => $this->get_user_ip(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'hCaptcha verification error: ' . $response->get_error_message() );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['success'] ) || ! $data['success'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Get CAPTCHA site key.
	 *
	 * @since    1.0.0
	 * @return   string    Site key or empty string
	 */
	public function get_site_key(): string {
		$provider = $this->get_provider();
		$option_name = 'stsrc_captcha_' . $provider . '_site_key';
		return get_option( $option_name, '' );
	}

	/**
	 * Get CAPTCHA secret key.
	 *
	 * @since    1.0.0
	 * @return   string    Secret key or empty string
	 */
	public function get_secret_key(): string {
		$provider = $this->get_provider();
		$option_name = 'stsrc_captcha_' . $provider . '_secret_key';
		return get_option( $option_name, '' );
	}

	/**
	 * Check if CAPTCHA is enabled and configured.
	 *
	 * @since    1.0.0
	 * @return   bool    True if enabled and configured
	 */
	public function is_enabled(): bool {
		$enabled = get_option( 'stsrc_captcha_enabled', '0' );
		if ( '0' === $enabled || ! $enabled ) {
			return false;
		}

		$site_key = $this->get_site_key();
		$secret_key = $this->get_secret_key();

		return ! empty( $site_key ) && ! empty( $secret_key );
	}

	/**
	 * Get CAPTCHA provider type.
	 *
	 * @since    1.0.0
	 * @return   string    'recaptcha' or 'hcaptcha'
	 */
	public function get_provider(): string {
		$provider = get_option( 'stsrc_captcha_provider', 'recaptcha' );
		return in_array( $provider, array( 'recaptcha', 'hcaptcha' ), true ) ? $provider : 'recaptcha';
	}

	/**
	 * Set CAPTCHA provider type.
	 *
	 * @since    1.0.0
	 * @param    string    $provider    'recaptcha' or 'hcaptcha'
	 * @return   void
	 */
	public function set_provider( string $provider ): void {
		if ( in_array( $provider, array( 'recaptcha', 'hcaptcha' ), true ) ) {
			$this->provider = $provider;
		}
	}

	/**
	 * Get score threshold for reCAPTCHA v3.
	 *
	 * @since    1.0.0
	 * @return   float    Score threshold (0.0 to 1.0)
	 */
	public function get_score_threshold(): float {
		$threshold = get_option( 'stsrc_captcha_score_threshold', '0.5' );
		return (float) $threshold;
	}

	/**
	 * Set score threshold for reCAPTCHA v3.
	 *
	 * @since    1.0.0
	 * @param    float    $threshold    Score threshold (0.0 to 1.0)
	 * @return   void
	 */
	public function set_score_threshold( float $threshold ): void {
		if ( $threshold >= 0.0 && $threshold <= 1.0 ) {
			$this->score_threshold = $threshold;
		}
	}

	/**
	 * Determine if the current visitor is rate limited for an action.
	 *
	 * @since 1.0.0
	 * @param string $action Action identifier (e.g. login, register).
	 * @param int    $limit  Maximum attempts within the rate limit window.
	 * @param int    $window Window length in seconds.
	 * @return bool True when the action is currently rate limited.
	 */
	public function is_rate_limited( string $action, int $limit = 5, int $window = 300 ): bool {
		$key    = $this->get_rate_limit_key( $action );
		$record = get_transient( $key );

		if ( ! is_array( $record ) ) {
			return false;
		}

		$expires_at = isset( $record['expires_at'] ) ? (int) $record['expires_at'] : 0;
		if ( time() > $expires_at ) {
			delete_transient( $key );
			return false;
		}

		$count = isset( $record['count'] ) ? (int) $record['count'] : 0;
		return $count >= $limit;
	}

	/**
	 * Increment the stored rate limit counter for an action.
	 *
	 * @since 1.0.0
	 * @param string $action Action identifier (e.g. login, register).
	 * @param int    $window Window length in seconds.
	 * @return void
	 */
	public function increment_rate_limit( string $action, int $window = 300 ): void {
		$key    = $this->get_rate_limit_key( $action );
		$record = get_transient( $key );

		$current_time = time();
		if ( ! is_array( $record ) || $current_time > (int) ( $record['expires_at'] ?? 0 ) ) {
			$record = array(
				'count'      => 0,
				'expires_at' => $current_time + $window,
			);
		}

		$record['count']        = (int) ( $record['count'] ?? 0 ) + 1;
		$record['expires_at']   = $current_time + $window;

		set_transient( $key, $record, $window );
	}

	/**
	 * Clear rate limiting for an action.
	 *
	 * @since 1.0.0
	 * @param string $action Action identifier.
	 * @return void
	 */
	public function clear_rate_limit( string $action ): void {
		$key = $this->get_rate_limit_key( $action );
		delete_transient( $key );
	}

	/**
	 * Build a rate limit transient key.
	 *
	 * @since 1.0.0
	 * @param string $action Action identifier.
	 * @return string Generated transient key.
	 */
	private function get_rate_limit_key( string $action ): string {
		$identifier = is_user_logged_in() ? 'user_' . get_current_user_id() : 'ip_' . ( $this->get_user_ip() ?: 'anonymous' );
		$action_key = sanitize_key( $action );

		return self::RATE_LIMIT_PREFIX . md5( $action_key . '|' . $identifier );
	}

	/**
	 * Get user IP address.
	 *
	 * @since    1.0.0
	 * @return   string    User IP address
	 */
	private function get_user_ip(): string {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_REAL_IP',        // Nginx proxy
			'HTTP_X_FORWARDED_FOR',   // Proxy
			'REMOTE_ADDR',            // Standard
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated IPs (from proxies)
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '';
	}
}

