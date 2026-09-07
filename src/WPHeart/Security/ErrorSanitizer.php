<?php
/**
 * Error sanitization (WH-084). User-facing messages never leak paths,
 * credentials, or SQL internals.
 *
 * @package WP_Heart
 */

namespace WPHeart\Security;

/**
 * Error sanitization (WH-084). User-facing messages never leak paths,.
 */
class ErrorSanitizer {
	/**
	 * Strip sensitive internals from a message.
	 *
	 * @param string $message Raw message.
	 * @return string Safe message.
	 */
	public static function sanitize( $message ) {
		$message = (string) $message;
		// Filesystem paths (Windows + Unix).
		$message = preg_replace( '/[A-Za-z]:\\\\[^\s"\']*/', '[path]', $message );
		$message = preg_replace( '#/(var|home|Users|srv|etc|tmp|xampp)[^\s"\']*#i', '[path]', $message );
		// Possible credential fragments.
		$message = preg_replace( '/(password|passwd|pwd|secret|token)\s*[:=]\s*[^\s,;]+/i', '$1=[redacted]', $message );
		// WordPress DB error prefix noise.
		$message = preg_replace( '/^WordPress database error\s*/i', '', $message );
		$message = trim( $message );
		if ( '' === $message ) {
			return __( 'An unexpected database error occurred.', 'wp-heart' );
		}
		return mb_substr( $message, 0, 500 );
	}

	/**
	 * @param string $code Error code.
	 * @param string $message Raw message.
	 * @param int    $status HTTP status.
	 * @return \WP_Error
	 */
	public static function rest_error( $code, $message, $status = 400 ) {
		if ( class_exists( 'WP_Error' ) ) {
			return new \WP_Error( $code, self::sanitize( $message ), array( 'status' => (int) $status ) );
		}
		return new \WP_Error( $code, $message );
	}
}
