<?php
/**
 * Minimal WordPress function stubs for the plain-PHP test runner.
 * Every stub is guarded so the file is harmless under real WordPress.
 *
 * @package WP_Heart_Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}
if ( ! defined( 'WP_HEART_VERSION' ) ) {
	define( 'WP_HEART_VERSION', '1.6.0' );
}
if ( ! defined( 'WP_HEART_DIR' ) ) {
	define( 'WP_HEART_DIR', dirname( dirname( __DIR__ ) ) . '/' );
}
if ( ! defined( 'WP_HEART_REST_NAMESPACE' ) ) {
	define( 'WP_HEART_REST_NAMESPACE', 'wp-heart/v1' );
}
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'DB_NAME' ) ) {
	define( 'DB_NAME', 'wp_test_stub' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}
		public function get_error_code() {
			return $this->code;
		}
		public function get_error_message() {
			return $this->message;
		}
		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		private $data;
		private $status;
		public function __construct( $data = null, $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}
		public function get_data() {
			return $this->data;
		}
		public function get_status() {
			return $this->status;
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request implements ArrayAccess {
		private $params = array();
		private $route  = '';
		public function __construct( $params = array(), $route = '' ) {
			$this->params = $params;
			$this->route  = $route;
		}
		public function get_param( $key ) {
			return array_key_exists( $key, $this->params ) ? $this->params[ $key ] : null;
		}
		public function get_route() {
			return $this->route;
		}
		public function set_query_params( $params ) {
			$this->params = array_merge( $this->params, (array) $params );
		}
		#[\ReturnTypeWillChange]
		public function offsetExists( $offset ) {
			return isset( $this->params[ $offset ] );
		}
		#[\ReturnTypeWillChange]
		public function offsetGet( $offset ) {
			return $this->get_param( $offset );
		}
		#[\ReturnTypeWillChange]
		public function offsetSet( $offset, $value ) {
			$this->params[ $offset ] = $value;
		}
		#[\ReturnTypeWillChange]
		public function offsetUnset( $offset ) {
			unset( $this->params[ $offset ] );
		}
	}
}

$GLOBALS['wh_options']    = isset( $GLOBALS['wh_options'] ) ? $GLOBALS['wh_options'] : array();
$GLOBALS['wh_transients'] = isset( $GLOBALS['wh_transients'] ) ? $GLOBALS['wh_transients'] : array();
$GLOBALS['wh_routes']     = isset( $GLOBALS['wh_routes'] ) ? $GLOBALS['wh_routes'] : array();

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
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return array_key_exists( $key, $GLOBALS['wh_options'] ) ? $GLOBALS['wh_options'][ $key ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = null ) {
		$GLOBALS['wh_options'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'add_option' ) ) {
	function add_option( $key, $value, $deprecated = '', $autoload = 'yes' ) {
		if ( ! array_key_exists( $key, $GLOBALS['wh_options'] ) ) {
			$GLOBALS['wh_options'][ $key ] = $value;
		}
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $key ) {
		unset( $GLOBALS['wh_options'][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return array_key_exists( $key, $GLOBALS['wh_transients'] ) ? $GLOBALS['wh_transients'][ $key ] : false;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $ttl = 0 ) {
		$GLOBALS['wh_transients'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['wh_transients'][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap ) {
		$caps = isset( $GLOBALS['wh_user_caps'] ) ? $GLOBALS['wh_user_caps'] : array( 'manage_options' );
		return in_array( $cap, (array) $caps, true );
	}
}
if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in() {
		return array_key_exists( 'wh_logged_in', $GLOBALS ) ? (bool) $GLOBALS['wh_logged_in'] : true;
	}
}
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 1;
	}
}
if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() {
		return sprintf( 'test-%06d', count( $GLOBALS['wh_options'] ) + rand( 1, 999999 ) );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data );
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
if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $ns, $route, $args = array() ) {
		$GLOBALS['wh_routes'][] = array( 'ns' => $ns, 'route' => $route, 'args' => $args );
		return true;
	}
}
if ( ! class_exists( 'FakeRole' ) ) {
	class FakeRole {
		public $caps = array();
		public function add_cap( $cap ) {
			$this->caps[ $cap ] = true;
		}
		public function remove_cap( $cap ) {
			unset( $this->caps[ $cap ] );
		}
	}
}
if ( ! function_exists( 'get_role' ) ) {
	function get_role( $name ) {
		if ( ! isset( $GLOBALS['wh_roles'] ) ) {
			$GLOBALS['wh_roles'] = array();
		}
		if ( ! isset( $GLOBALS['wh_roles'][ $name ] ) ) {
			$GLOBALS['wh_roles'][ $name ] = new FakeRole();
		}
		return $GLOBALS['wh_roles'][ $name ];
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $cb, $prio = 10, $args = 1 ) {
		return true;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $cb, $prio = 10, $args = 1 ) {
		return true;
	}
}
if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $cb ) {
		$GLOBALS['wh_activation_hook'] = $cb;
		return true;
	}
}
if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $file, $cb ) {
		$GLOBALS['wh_deactivation_hook'] = $cb;
		return true;
	}
}
if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return rtrim( dirname( $file ), '/\\' ) . '/';
	}
}
if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'https://example.test/wp-content/plugins/wp-heart/';
	}
}
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return 'wp-heart/' . basename( $file );
	}
}
if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( $domain, $dep = false, $path = '' ) {
		return true;
	}
}
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' ) . '/';
	}
}
if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $dir ) {
		if ( is_dir( $dir ) ) {
			return true;
		}
		return mkdir( $dir, 0777, true );
	}
}
if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook, $args = array() ) {
		return false; // Simulate no scheduled event.
	}
}
if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {
		return true;
	}
}
if ( ! function_exists( 'wp_unschedule_event' ) ) {
	function wp_unschedule_event( $timestamp, $hook, $args = array() ) {
		return true;
	}
}
if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( $hook, $args = array() ) {
		return 0;
	}
}
