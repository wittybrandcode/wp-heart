<?php
/**
 * Deactivation handler: stops hooks, keeps data (re-activation safe).
 *
 * @package WP_Heart
 */

namespace WPHeart\Plugin;

/**
 * Deactivation handler: stops hooks, keeps data (re-activation safe).
 */
class Deactivator {
	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		// Intentionally non-destructive: settings, cache and audit are kept so
		// re-activation restores state. Full removal happens in uninstall.php.
		delete_transient( 'wp_heart_discovery_lock' );

		$timestamp = wp_next_scheduled( 'wp_heart_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wp_heart_daily_cleanup' );
		}
	}
}
