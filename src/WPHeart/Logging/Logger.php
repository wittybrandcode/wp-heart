<?php
/**
 * Internal structured logger. Never logs row content, credentials or secrets.
 *
 * @package WP_Heart
 */

namespace WPHeart\Logging;

/**
 * Internal structured logger. Never logs row content, credentials or secrets.
 */
class Logger {
	const CHANNEL_DISCOVERY   = 'discovery';
	const CHANNEL_REST        = 'rest';
	const CHANNEL_DIAGNOSTICS = 'diagnostics';
	const CHANNEL_QUERY       = 'query';
	const CHANNEL_SECURITY    = 'security';
	const CHANNEL_SYSTEM      = 'system';

	/** @var string[] Keys stripped from context. */
	private static $redacted_keys = array(
		'password',
		'passwd',
		'pwd',
		'secret',
		'token',
		'api_key',
		'apikey',
		'auth_key',
		'salt',
		'db_password',
		'user_pass',
		'session_tokens',
	);

	/**
	 * @param string $channel Channel constant.
	 * @param string $message Message.
	 * @param array  $context Structured context (scalars only).
	 */
	public static function log( $channel, $message, array $context = array() ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		$context = self::redact( $context );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated by WP_DEBUG, internal diagnostics only.
		error_log( sprintf( '[wp-heart][%s] %s %s', $channel, $message, empty( $context ) ? '' : wp_json_encode( $context ) ) );
	}

	/**
	 * @param array $context Context.
	 * @return array
	 */
	public static function redact( array $context ) {
		foreach ( $context as $key => $value ) {
			$lower = strtolower( (string) $key );
			foreach ( self::$redacted_keys as $bad ) {
				if ( false !== strpos( $lower, $bad ) ) {
					$context[ $key ] = '[redacted]';
					continue 2;
				}
			}
			if ( is_array( $value ) ) {
				$context[ $key ] = self::redact( $value );
			} elseif ( ! is_scalar( $value ) && null !== $value ) {
				$context[ $key ] = '[non-scalar]';
			}
		}
		return $context;
	}
}
