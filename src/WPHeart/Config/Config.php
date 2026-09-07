<?php
/**
 * Central configuration layer (WH-004). No secrets stored here.
 *
 * @package WP_Heart
 */

namespace WPHeart\Config;

/**
 * Central configuration layer (WH-004). No secrets stored here.
 */
class Config {
	/** @var array */
	private $settings;

	/**
	 * @param array $overrides Setting overrides (e.g. from options).
	 */
	public function __construct( array $overrides = array() ) {
		$defaults       = array(
			'version'            => defined( 'WP_HEART_VERSION' ) ? WP_HEART_VERSION : '1.0.0',
			'rest_namespace'     => defined( 'WP_HEART_REST_NAMESPACE' ) ? WP_HEART_REST_NAMESPACE : 'wp-heart/v1',
			'cache_ttl'          => 300,
			'cache_group'        => 'wp_heart',
			'default_per_page'   => 20,
			'max_per_page'       => 100,
			'max_query_rows'     => 500,
			'max_query_length'   => 20000,
			'min_search_length'  => 2,
			'max_search_length'  => 100,
			'max_search_tables'  => 50,
			'search_preview_len' => 200,
			'audit_max_entries'  => 500,
			'slow_query_ms'      => 1000,
		);
		$this->settings = array_merge( $defaults, $overrides );
	}

	/**
	 * Load persisted user settings on top of defaults.
	 *
	 * @return Config
	 */
	public static function load() {
		$stored = function_exists( 'get_option' ) ? get_option( 'wp_heart_settings', array() ) : array();
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$allowed = array( 'cache_ttl', 'default_per_page', 'max_per_page', 'max_query_rows', 'slow_query_ms' );
		$clean   = array();
		foreach ( $allowed as $key ) {
			if ( isset( $stored[ $key ] ) && is_numeric( $stored[ $key ] ) ) {
				$clean[ $key ] = (int) $stored[ $key ];
			}
		}
		if ( isset( $clean['cache_ttl'] ) ) {
			$clean['cache_ttl'] = max( 60, min( 3600, $clean['cache_ttl'] ) );
		}
		if ( isset( $clean['default_per_page'] ) ) {
			$clean['default_per_page'] = max( 5, min( 100, $clean['default_per_page'] ) );
		}
		if ( isset( $clean['max_per_page'] ) ) {
			$clean['max_per_page'] = max( 10, min( 200, $clean['max_per_page'] ) );
		}
		if ( isset( $clean['max_query_rows'] ) ) {
			$clean['max_query_rows'] = max( 50, min( 2000, $clean['max_query_rows'] ) );
		}
		return new self( $clean );
	}

	/**
	 * @param string $key Setting key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		return array_key_exists( $key, $this->settings ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * @return array
	 */
	public function all() {
		return $this->settings;
	}
}
