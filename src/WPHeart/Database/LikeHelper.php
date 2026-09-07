<?php
/**
 * LIKE-pattern escaping helper. Prefers $wpdb->esc_like() at runtime
 * and falls back to addcslashes outside WordPress (tests).
 *
 * @package WP_Heart
 */

namespace WPHeart\Database;

/**
 * LIKE-pattern escaping helper.
 */
class LikeHelper {
	/**
	 * Escape a term for use inside a LIKE pattern.
	 *
	 * @param string $term Raw term.
	 * @return string Escaped term (without surrounding % wildcards).
	 */
	public static function escape( $term ) {
		global $wpdb;
		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'esc_like' ) ) {
			return $wpdb->esc_like( (string) $term );
		}
		return addcslashes( (string) $term, '\\%_' );
	}

	/**
	 * Build a %contains% pattern with escaping applied.
	 *
	 * @param string $term Raw term.
	 * @return string LIKE pattern (bind via placeholder, never interpolate).
	 */
	public static function contains( $term ) {
		return '%' . self::escape( $term ) . '%';
	}
}
