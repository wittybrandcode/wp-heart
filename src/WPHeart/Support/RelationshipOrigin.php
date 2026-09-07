<?php
/**
 * Relationship origins. INFERRED must never be presented as PHYSICAL.
 *
 * @package WP_Heart
 */

namespace WPHeart\Support;

/**
 * Relationship origins. INFERRED must never be presented as PHYSICAL.
 */
final class RelationshipOrigin {
	const PHYSICAL = 'PHYSICAL';
	const INFERRED = 'INFERRED';
	const NONE     = 'NONE';
	const UNKNOWN  = 'UNKNOWN';

	/**
	 * @return string[]
	 */
	public static function all() {
		return array( self::PHYSICAL, self::INFERRED, self::NONE, self::UNKNOWN );
	}
}
