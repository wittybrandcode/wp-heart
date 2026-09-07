<?php
/**
 * PSR-4 autoloader for WP-HEART.
 *
 * Maps the `WPHeart\` namespace to `src/WPHeart/`. No composer runtime
 * dependency is required (see Decision D-001 in ROADMAP.md).
 *
 * @package WP_Heart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'WPHeart\\';
		$len    = strlen( $prefix );
		if ( strncmp( $class, $prefix, $len ) !== 0 ) {
			return;
		}
		$relative = substr( $class, $len );
		$path     = WP_HEART_DIR . 'src/WPHeart/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);
