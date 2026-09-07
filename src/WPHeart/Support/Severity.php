<?php
/**
 * Diagnostic severity levels. CRITICAL requires clear technical evidence.
 *
 * @package WP_Heart
 */

namespace WPHeart\Support;

/**
 * Diagnostic severity levels. CRITICAL requires clear technical evidence.
 */
final class Severity {
	const INFO     = 'INFO';
	const NOTICE   = 'NOTICE';
	const WARNING  = 'WARNING';
	const ERROR    = 'ERROR';
	const CRITICAL = 'CRITICAL';

	/**
	 * @return string[]
	 */
	public static function all() {
		return array( self::INFO, self::NOTICE, self::WARNING, self::ERROR, self::CRITICAL );
	}

	/**
	 * @param string $level Severity level.
	 * @return int
	 */
	public static function rank( $level ) {
		$order = array_flip( self::all() );
		return isset( $order[ $level ] ) ? $order[ $level ] : -1;
	}
}
