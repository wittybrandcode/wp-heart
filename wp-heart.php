<?php
/**
 * Plugin Name:       WP-HEART
 * Plugin URI:        https://example.com/wp-heart
 * Description:       Database Observatory & Intelligence Platform for WordPress. Observe, classify, search and diagnose the real database. Read-only in 1.0.
 * Version:           1.6.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            WP-HEART
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-heart
 * Domain Path:       /languages
 *
 * @package WP_Heart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_HEART_VERSION', '1.6.0' );
define( 'WP_HEART_FILE', __FILE__ );
define( 'WP_HEART_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_HEART_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_HEART_REST_NAMESPACE', 'wp-heart/v1' );

require_once WP_HEART_DIR . 'autoload.php';

register_activation_hook( __FILE__, array( 'WPHeart\\Plugin\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPHeart\\Plugin\\Deactivator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'wp-heart', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		WPHeart\Plugin\Plugin::init();
	}
);
