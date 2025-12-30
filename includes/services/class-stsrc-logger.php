<?php
/**
 * Logger utility class.
 *
 * Centralizes plugin logging and ensures messages are sanitized
 * before being passed to WordPress' error logging facilities.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/includes/services
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Smoketree logger wrapper.
 *
 * @since 1.0.0
 */
class STSRC_Logger {

	/**
	 * Allowed log levels.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private const LEVELS = array(
		'debug',
		'info',
		'notice',
		'warning',
		'error',
		'critical',
		'alert',
		'emergency',
	);

	/**
	 * Log a message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $level   Log level.
	 * @param string $message Log message.
	 * @param array  $context Optional context.
	 * @return void
	 */
	public static function log( string $level, string $message, array $context = array() ): void {
		if ( ! apply_filters( 'stsrc_logger_enabled', true, $level, $message, $context ) ) {
			return;
		}

		$level = self::normalize_level( $level );

		$line = sprintf(
			'[%1$s] [%2$s] %3$s%4$s',
			current_time( 'Y-m-d H:i:s' ),
			strtoupper( $level ),
			self::sanitize_message( $message ),
			self::format_context( $context )
		);

		/**
		 * Filter the final line before it is written to the error log.
		 *
		 * @since 1.0.0
		 *
		 * @param string $line    Log line.
		 * @param string $level   Log level.
		 * @param string $message Original message.
		 * @param array  $context Context data.
		 */
		$line = apply_filters( 'stsrc_logger_line', $line, $level, $message, $context );

		if ( is_string( $line ) && '' !== $line ) {
			error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Convenience helper for debug level.
	 *
	 * @since 1.0.0
	 */
	public static function debug( string $message, array $context = array() ): void {
		self::log( 'debug', $message, $context );
	}

	/**
	 * Convenience helper for info level.
	 *
	 * @since 1.0.0
	 */
	public static function info( string $message, array $context = array() ): void {
		self::log( 'info', $message, $context );
	}

	/**
	 * Convenience helper for warning level.
	 *
	 * @since 1.0.0
	 */
	public static function warning( string $message, array $context = array() ): void {
		self::log( 'warning', $message, $context );
	}

	/**
	 * Convenience helper for error level.
	 *
	 * @since 1.0.0
	 */
	public static function error( string $message, array $context = array() ): void {
		self::log( 'error', $message, $context );
	}

	/**
	 * Log an exception with context.
	 *
	 * @since 1.0.0
	 *
	 * @param \Throwable $exception Exception instance.
	 * @param array      $context   Additional context.
	 * @return void
	 */
	public static function exception( \Throwable $exception, array $context = array() ): void {
		$context['exception'] = array(
			'class'   => get_class( $exception ),
			'code'    => $exception->getCode(),
			'message' => $exception->getMessage(),
			'file'    => $exception->getFile(),
			'line'    => $exception->getLine(),
			'trace'   => self::prepare_trace( $exception->getTrace() ),
		);

		self::log( 'error', $exception->getMessage(), $context );
	}

	/**
	 * Normalize log level.
	 *
	 * @since 1.0.0
	 *
	 * @param string $level Level name.
	 * @return string
	 */
	private static function normalize_level( string $level ): string {
		$level = strtolower( trim( $level ) );
		if ( in_array( $level, self::LEVELS, true ) ) {
			return $level;
		}

		return 'info';
	}

	/**
	 * Sanitize message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Raw message.
	 * @return string
	 */
	private static function sanitize_message( string $message ): string {
		$allowed = array(
			'a'      => array( 'href' => true ),
			'code'   => true,
			'strong' => true,
			'em'     => true,
		);

		$sanitized = wp_kses( $message, $allowed );

		return trim( preg_replace( '/\s+/', ' ', $sanitized ) );
	}

	/**
	 * Format context as JSON (sanitized).
	 *
	 * @since 1.0.0
	 *
	 * @param array $context Context array.
	 * @return string
	 */
	private static function format_context( array $context ): string {
		if ( empty( $context ) ) {
			return '';
		}

		$sanitized = self::sanitize_context( $context );
		$json      = wp_json_encode( $sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( false === $json || '' === $json ) {
			return '';
		}

		return ' ' . $json;
	}

	/**
	 * Sanitize context array.
	 *
	 * Recursively removes objects and redacts sensitive values.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Context value.
	 * @param mixed $key   Context key when recursing.
	 * @return mixed
	 */
	private static function sanitize_context_value( $value, $key = null ) {
		if ( is_array( $value ) ) {
			$sanitized = array();
			foreach ( $value as $child_key => $child_value ) {
				$sanitized[ $child_key ] = self::sanitize_context_value( $child_value, $child_key );
			}
			return $sanitized;
		}

		if ( is_object( $value ) ) {
			if ( $value instanceof \JsonSerializable ) {
				return self::sanitize_context_value( $value->jsonSerialize(), $key );
			}
			if ( $value instanceof \Throwable ) {
				return array(
					'class'   => get_class( $value ),
					'code'    => $value->getCode(),
					'message' => $value->getMessage(),
				);
			}
			return array( 'object' => get_class( $value ) );
		}

		if ( is_bool( $value ) || is_null( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		$value = (string) $value;

		if ( self::is_sensitive_key( (string) $key ) ) {
			return '[redacted]';
		}

		return mb_substr( trim( wp_strip_all_tags( $value ) ), 0, 500 );
	}

	/**
	 * Sanitize entire context array.
	 *
	 * @since 1.0.0
	 *
	 * @param array $context Context array.
	 * @return array
	 */
	private static function sanitize_context( array $context ): array {
		$sanitized = array();

		foreach ( $context as $key => $value ) {
			$sanitized[ $key ] = self::sanitize_context_value( $value, $key );
		}

		return $sanitized;
	}

	/**
	 * Determine if key likely contains sensitive information.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Context key.
	 * @return bool
	 */
	private static function is_sensitive_key( string $key ): bool {
		$key = strtolower( $key );

		$sensitive_fragments = array(
			'password',
			'pass',
			'secret',
			'token',
			'key',
			'nonce',
			'auth',
			'cookie',
		);

		foreach ( $sensitive_fragments as $fragment ) {
			if ( str_contains( $key, $fragment ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Prepare stack trace for logging.
	 *
	 * @since 1.0.0
	 *
	 * @param array $trace Raw trace.
	 * @return array
	 */
	private static function prepare_trace( array $trace ): array {
		$prepared = array();

		foreach ( $trace as $frame ) {
			$prepared[] = array(
				'file'     => isset( $frame['file'] ) ? $frame['file'] : null,
				'line'     => isset( $frame['line'] ) ? (int) $frame['line'] : null,
				'function' => $frame['function'] ?? null,
				'class'    => $frame['class'] ?? null,
			);
		}

		return $prepared;
	}
}


