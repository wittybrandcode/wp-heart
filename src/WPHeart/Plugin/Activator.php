<?php
/**
 * Activation handler: lightweight, no scans, no destructive ops (WH-001).
 *
 * @package WP_Heart
 */

namespace WPHeart\Plugin;

use WPHeart\Security\Capabilities;

/**
 * Activation handler: lightweight, no scans, no destructive ops (WH-001).
 */
class Activator {
	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		if ( ! get_option( 'wp_heart_version' ) ) {
			add_option( 'wp_heart_version', WP_HEART_VERSION );
		} else {
			update_option( 'wp_heart_version', WP_HEART_VERSION );
		}

		if ( false === get_option( 'wp_heart_settings', false ) ) {
			add_option( 'wp_heart_settings', array() );
		}

		// Grant capabilities to administrators (single-site + network).
		$roles = array( 'administrator' );
		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( Capabilities::all() as $cap ) {
				$role->add_cap( $cap );
			}
		}

		if ( is_multisite() && is_main_site() ) {
			$admins = get_super_admins();
			unset( $admins );
		}

		if ( ! wp_next_scheduled( 'wp_heart_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'wp_heart_daily_cleanup' );
		}
	}
}
