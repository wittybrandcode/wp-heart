<?php
/**
 * Capability model (WH-080). Namespaced form of the WH-080 capability set.
 *
 * @package WP_Heart
 */

namespace WPHeart\Security;

/**
 * Capability model (WH-080). Namespaced form of the WH-080 capability set.
 */
class Capabilities {
	const VIEW_DATABASE   = 'wp_heart_view_database';
	const VIEW_DATA       = 'wp_heart_view_data';
	const RUN_QUERIES     = 'wp_heart_run_queries';
	const VIEW_AUDIT      = 'wp_heart_view_audit';
	const MANAGE_SETTINGS = 'wp_heart_manage_settings';
	const MANAGE_DATABASE = 'wp_heart_manage_database';

	/**
	 * @return string[]
	 */
	public static function all() {
		return array(
			self::VIEW_DATABASE,
			self::VIEW_DATA,
			self::RUN_QUERIES,
			self::VIEW_AUDIT,
			self::MANAGE_SETTINGS,
			self::MANAGE_DATABASE,
		);
	}

	/**
	 * @return array Capability => label.
	 */
	public static function labels() {
		return array(
			self::VIEW_DATABASE   => __( 'View database observatory', 'wp-heart' ),
			self::VIEW_DATA       => __( 'View table data', 'wp-heart' ),
			self::RUN_QUERIES     => __( 'Run read-only queries', 'wp-heart' ),
			self::VIEW_AUDIT      => __( 'View audit log', 'wp-heart' ),
			self::MANAGE_SETTINGS => __( 'Manage WP-HEART settings', 'wp-heart' ),
			self::MANAGE_DATABASE => __( 'Manage and modify database', 'wp-heart' ),
		);
	}
}
