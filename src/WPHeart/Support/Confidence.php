<?php
/**
 * Confidence levels for intelligence results.
 *
 * @package WP_Heart
 */

namespace WPHeart\Support;

/**
 * Confidence levels for intelligence results.
 */
final class Confidence {
	const HIGH    = 'HIGH';
	const MEDIUM  = 'MEDIUM';
	const LOW     = 'LOW';
	const UNKNOWN = 'UNKNOWN';

	/**
	 * Numeric rank used for aggregation (higher = stronger).
	 *
	 * @param string $level Confidence level.
	 * @return int
	 */
	public static function rank( $level ) {
		switch ( $level ) {
			case self::HIGH:
				return 3;
			case self::MEDIUM:
				return 2;
			case self::LOW:
				return 1;
			default:
				return 0;
		}
	}

	/**
	 * @param string $value Candidate.
	 * @return bool
	 */
	public static function is_valid( $value ) {
		return in_array( $value, array( self::HIGH, self::MEDIUM, self::LOW, self::UNKNOWN ), true );
	}
}
