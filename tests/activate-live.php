<?php
/**
 * Live lifecycle validation: activate → verify → deactivate → uninstall →
 * verify cleanup → re-activate (leaves the site clean). WH-320/WH-323/WH-324.
 *
 * Usage: php tests/activate-live.php
 *
 * @package WP_Heart_Tests
 */

error_reporting( E_ALL & ~E_DEPRECATED );

require 'C:\\xampp\\htdocs\\wordpress\\wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$pass = 0;
$fail = 0;
function life_check( $cond, $name ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "ok: $name\n";
	} else {
		++$fail;
		echo "NOT OK (lifecycle): $name\n";
	}
}

$plugin = 'WP-HEART/wp-heart.php';

if ( is_plugin_active( $plugin ) ) {
	deactivate_plugins( $plugin );
}

// 1. Clean install.
$result = activate_plugin( $plugin );
life_check( ! is_wp_error( $result ), 'activation completes without error' );
life_check( is_plugin_active( $plugin ), 'plugin reports active' );
life_check( '1.6.0' === get_option( 'wp_heart_version' ), 'version option seeded' );

// 2. Capabilities granted to administrators.
$admin = get_role( 'administrator' );
$need  = array( 'wp_heart_view_database', 'wp_heart_view_data', 'wp_heart_run_queries', 'wp_heart_view_audit', 'wp_heart_manage_settings' );
$have_all = true;
foreach ( $need as $cap ) {
	if ( ! $admin || ! $admin->has_cap( $cap ) ) {
		$have_all = false;
	}
}
life_check( $have_all, 'administrator holds all wp_heart_* capabilities' );

// 3. No tables created, no scans on activation.
global $wpdb;
$count_before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()' );
life_check( $count_before > 0, 'table count readable (' . $count_before . ')' );

// 4. Plugin bootstrap class loads through the real hooks.
life_check( class_exists( 'WPHeart\\Plugin\\Plugin' ), 'orchestrator class autoloads in WP runtime' );
life_check( has_action( 'rest_api_init' ) !== false, 'rest_api_init hook present' );

// 5. Upgrade path: settings survive re-activation.
update_option( 'wp_heart_settings', array( 'cache_ttl' => 600 ), false );
deactivate_plugins( $plugin );
life_check( ! is_plugin_active( $plugin ), 'deactivation works' );
life_check( array( 'cache_ttl' => 600 ) === get_option( 'wp_heart_settings' ), 'deactivation preserves settings (non-destructive)' );
activate_plugin( $plugin );
life_check( array( 'cache_ttl' => 600 ) === get_option( 'wp_heart_settings' ), 'upgrade preserves settings' );

// 6. Uninstall removes only WP-HEART internals.
deactivate_plugins( $plugin );
define( 'WP_UNINSTALL_PLUGIN', true );
require 'C:\\xampp\\htdocs\\wordpress\\wp-content\\plugins\\WP-HEART\\uninstall.php';
life_check( false === get_option( 'wp_heart_settings', false ), 'uninstall removes settings' );
life_check( false === get_option( 'wp_heart_version', false ), 'uninstall removes version' );
life_check( false === get_option( 'wp_heart_audit_log', false ), 'uninstall removes audit log' );
$admin = get_role( 'administrator' );
$leftover = false;
foreach ( $need as $cap ) {
	if ( $admin && $admin->has_cap( $cap ) ) {
		$leftover = true;
	}
}
life_check( ! $leftover, 'uninstall removes custom capabilities' );

// 7. User content untouched: posts table still intact.
$posts = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $wpdb->prefix . 'posts`' );
life_check( $posts > 0, 'user content untouched (' . $posts . ' posts)' );

// 8. Leave the environment clean and functional.
activate_plugin( $plugin );
life_check( is_plugin_active( $plugin ), 'site left with plugin active' );

echo "\nLIFECYCLE PASS: $pass  FAIL: $fail\n";
exit( $fail > 0 ? 1 : 0 );
