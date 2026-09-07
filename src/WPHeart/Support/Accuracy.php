<?php
/**
 * Accuracy modes for metadata values (Estimated vs Exact, etc.).
 *
 * @package WP_Heart
 */

namespace WPHeart\Support;

/**
 * Accuracy modes for metadata values (Estimated vs Exact, etc.).
 */
final class Accuracy {
	const EXACT       = 'EXACT';
	const ESTIMATED   = 'ESTIMATED';
	const CACHED      = 'CACHED';
	const UNKNOWN     = 'UNKNOWN';
	const UNAVAILABLE = 'UNAVAILABLE';

	/**
	 * @param string $value Accuracy candidate.
	 * @return bool
	 */
	public static function is_valid( $value ) {
		return in_array( $value, self::all(), true );
	}

	/**
	 * @return string[]
	 */
	public static function all() {
		return array( self::EXACT, self::ESTIMATED, self::CACHED, self::UNKNOWN, self::UNAVAILABLE );
	}
}
