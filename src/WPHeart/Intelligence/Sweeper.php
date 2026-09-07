<?php
/**
 * Sweeper logic for safe one-click cleanup operations.
 *
 * @package WP_Heart
 */

namespace WPHeart\Intelligence;

use WPHeart\Container\Container;
use WPHeart\Security\ErrorSanitizer;

/**
 * Sweeper logic for safe one-click cleanup operations.
 */
class Sweeper {
	/** @var Container */
	private $c;

	/**
	 * @param Container $c Container.
	 */
	public function __construct( Container $c ) {
		$this->c = $c;
	}

	/**
	 * Sweep a specific target.
	 *
	 * @param string $target Target to sweep (transients, orphaned_postmeta).
	 * @return array|\WP_Error Result summary.
	 */
	public function sweep( $target ) {
		global $wpdb;

		if ( 'transients' === $target ) {
			$prefix = $wpdb->prefix;
			$sql    = "DELETE FROM {$prefix}options WHERE option_name LIKE '\_transient\_%' OR option_name LIKE '\_site\_transient\_%' OR option_name LIKE '\_transient\_timeout\_%' OR option_name LIKE '\_site\_transient\_timeout\_%'";
			$count  = $wpdb->query( $sql );

			if ( false === $count ) {
				return ErrorSanitizer::rest_error( 'wp_heart_sweep_failed', __( 'Failed to sweep transients.', 'wp-heart' ), 500 );
			}
			return array(
				'success' => true,
				'message' => sprintf( __( 'Cleared %d transient records.', 'wp-heart' ), $count ),
				'count'   => $count,
			);
		}

		if ( 'orphaned_postmeta' === $target ) {
			$prefix = $wpdb->prefix;
			// Delete from postmeta where post_id does not exist in posts.
			$sql   = "DELETE pm FROM {$prefix}postmeta pm LEFT JOIN {$prefix}posts wp ON wp.ID = pm.post_id WHERE wp.ID IS NULL";
			$count = $wpdb->query( $sql );

			if ( false === $count ) {
				return ErrorSanitizer::rest_error( 'wp_heart_sweep_failed', __( 'Failed to sweep orphaned postmeta.', 'wp-heart' ), 500 );
			}
			return array(
				'success' => true,
				'message' => sprintf( __( 'Cleared %d orphaned postmeta records.', 'wp-heart' ), $count ),
				'count'   => $count,
			);
		}

		return ErrorSanitizer::rest_error( 'wp_heart_sweep_invalid', __( 'Invalid sweep target.', 'wp-heart' ), 400 );
	}
}
