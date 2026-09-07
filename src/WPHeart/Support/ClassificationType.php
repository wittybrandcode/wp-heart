<?php
/**
 * Table classification states.
 *
 * @package WP_Heart
 */

namespace WPHeart\Support;

/**
 * Table classification states.
 */
final class ClassificationType {
	const CORE             = 'CORE';
	const PLUGIN           = 'PLUGIN';
	const THEME_CUSTOM     = 'THEME_CUSTOM';
	const UNKNOWN          = 'UNKNOWN';
	const ORPHAN_CANDIDATE = 'ORPHAN_CANDIDATE';

	/**
	 * @return string[]
	 */
	public static function all() {
		return array( self::CORE, self::PLUGIN, self::THEME_CUSTOM, self::UNKNOWN, self::ORPHAN_CANDIDATE );
	}
}
