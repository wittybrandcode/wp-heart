<?php
/**
 * PHPStan bootstrap: WordPress core symbols for static analysis.
 * Runtime behavior is unaffected (analysis-only).
 *
 * @package WP_Heart
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'DB_NAME' ) ) {
	define( 'DB_NAME', 'analysis' );
}
if ( ! defined( 'DB_COLLATE' ) ) {
	define( 'DB_COLLATE', '' );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', '/tmp/wordpress/wp-content/plugins' );
}
if ( ! defined( 'WP_HEART_VERSION' ) ) {
	define( 'WP_HEART_VERSION', '1.1.0' );
}
if ( ! defined( 'WP_HEART_FILE' ) ) {
	define( 'WP_HEART_FILE', '/tmp/wordpress/wp-content/plugins/wp-heart/wp-heart.php' );
}
if ( ! defined( 'WP_HEART_DIR' ) ) {
	define( 'WP_HEART_DIR', '/tmp/wordpress/wp-content/plugins/wp-heart/' );
}
if ( ! defined( 'WP_HEART_URL' ) ) {
	define( 'WP_HEART_URL', 'https://example.test/wp-content/plugins/wp-heart/' );
}
if ( ! defined( 'WP_HEART_REST_NAMESPACE' ) ) {
	define( 'WP_HEART_REST_NAMESPACE', 'wp-heart/v1' );
}
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', false );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public function __construct( $code = '', $message = '', $data = '' ) {}
		public function get_error_code() {}
		public function get_error_message() {}
		public function get_error_data() {}
	}
}
if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		public function __construct( $data = null, $status = 200 ) {}
		public function get_data() {}
		public function get_status() {}
	}
}
if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		public function get_param( $key ) {}
		public function get_route() {}
		public function set_query_params( $params ) {}
	}
}
if ( ! class_exists( 'WP_Role' ) ) {
	class WP_Role {
		public function add_cap( $cap ) {}
		public function remove_cap( $cap ) {}
		public function has_cap( $cap ) {}
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = null ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = null ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return $url;
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return $default;
	}
}
if ( ! function_exists( 'get_site_option' ) ) {
	function get_site_option( $key, $default = false ) {
		return $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = null ) {
		return true;
	}
}
if ( ! function_exists( 'add_option' ) ) {
	function add_option( $key, $value ) {
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $key ) {
		return true;
	}
}
if ( ! function_exists( 'delete_site_option' ) ) {
	function delete_site_option( $key ) {
		return true;
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return false;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $ttl = 0 ) {
		return true;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		return true;
	}
}
if ( ! function_exists( 'delete_site_transient' ) ) {
	function delete_site_transient( $key ) {
		return true;
	}
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap ) {
		return false;
	}
}
if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in() {
		return false;
	}
}
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 0;
	}
}
if ( ! function_exists( 'wp_set_current_user' ) ) {
	function wp_set_current_user( $id ) {
		return null;
	}
}
if ( ! function_exists( 'get_role' ) ) {
	function get_role( $name ) {
		return null;
	}
}
if ( ! function_exists( 'get_super_admins' ) ) {
	function get_super_admins() {
		return array();
	}
}
if ( ! function_exists( 'is_main_site' ) ) {
	function is_main_site() {
		return true;
	}
}
if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() {
		return '';
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return '';
	}
}
if ( ! function_exists( 'is_multisite' ) ) {
	function is_multisite() {
		return false;
	}
}
if ( ! function_exists( 'get_current_blog_id' ) ) {
	function get_current_blog_id() {
		return 1;
	}
}
if ( ! function_exists( 'get_current_network_id' ) ) {
	function get_current_network_id() {
		return 1;
	}
}
if ( ! function_exists( 'get_locale' ) ) {
	function get_locale() {
		return 'en_US';
	}
}
if ( ! function_exists( 'is_rtl' ) ) {
	function is_rtl() {
		return false;
	}
}
if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $ns, $route, $args = array() ) {
		return true;
	}
}
if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( $path = '' ) {
		return $path;
	}
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action ) {
		return '';
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $cb, $prio = 10, $args = 1 ) {
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $cb, $prio = 10, $args = 1 ) {
		return true;
	}
}
if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $cb ) {
		return true;
	}
}
if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $file, $cb ) {
		return true;
	}
}
if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return '';
	}
}
if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return '';
	}
}
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return '';
	}
}
if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( $domain, $dep = false, $path = '' ) {
		return true;
	}
}
if ( ! function_exists( 'wp_register_script' ) ) {
	function wp_register_script( $handle, $src, $deps = array(), $ver = false, $in_footer = false ) {}
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle ) {}
}
if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $name, $data ) {}
}
if ( ! function_exists( 'wp_register_style' ) ) {
	function wp_register_style( $handle, $src, $deps = array(), $ver = false ) {}
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle ) {}
}
if ( ! function_exists( 'wp_style_add_data' ) ) {
	function wp_style_add_data( $handle, $key, $value ) {}
}
if ( ! function_exists( 'wp_set_script_translations' ) ) {
	function wp_set_script_translations( $handle, $domain, $path = '' ) {}
}
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '' ) {}
}
if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( $a = null, $b = null, $c = null, $d = null, $e = null, $f = null, $g = null ) {}
}
if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( $a = null, $b = null, $c = null, $d = null, $e = null, $f = null ) {}
}
if ( ! function_exists( 'get_plugins' ) ) {
	function get_plugins() {
		return array();
	}
}
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' ) . '/';
	}
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' );
	}
}
if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $dir ) {
		return true;
	}
}
if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		return array( 'basedir' => '/tmp/wordpress/uploads' );
	}
}
if ( ! function_exists( 'wp_get_theme' ) ) {
	function wp_get_theme() {
		return null;
	}
}
