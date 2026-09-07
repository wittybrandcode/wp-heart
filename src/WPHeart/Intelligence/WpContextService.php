<?php
/**
 * WordPress context service (WH-040). Prefix is always dynamic — never 'wp_'.
 *
 * @package WP_Heart
 */

namespace WPHeart\Intelligence;

/**
 * WordPress context service (WH-040). Prefix is always dynamic — never 'wp_'.
 */
class WpContextService {
	/** @var string|null */
	private $prefix_override;
	/** @var bool|null */
	private $multisite_override;
	/** @var int|null */
	private $blog_override;

	/**
	 * @param string|null $prefix Prefix override (tests).
	 * @param bool|null   $is_multisite Multisite override.
	 * @param int|null    $blog_id Blog id override.
	 */
	public function __construct( $prefix = null, $is_multisite = null, $blog_id = null ) {
		$this->prefix_override    = $prefix;
		$this->multisite_override = $is_multisite;
		$this->blog_override      = $blog_id;
	}

	/**
	 * @return string Actual table prefix (empty string when undetectable).
	 */
	public function prefix() {
		if ( null !== $this->prefix_override ) {
			return $this->prefix_override;
		}
		global $wpdb;
		if ( isset( $wpdb->prefix ) && is_string( $wpdb->prefix ) ) {
			return $wpdb->prefix;
		}
		return '';
	}

	/**
	 * @return string Base prefix for multisite global tables.
	 */
	public function base_prefix() {
		global $wpdb;
		if ( isset( $wpdb->base_prefix ) && is_string( $wpdb->base_prefix ) ) {
			return $wpdb->base_prefix;
		}
		return $this->prefix();
	}

	/**
	 * @return bool
	 */
	public function is_multisite() {
		if ( null !== $this->multisite_override ) {
			return (bool) $this->multisite_override;
		}
		return function_exists( 'is_multisite' ) && is_multisite();
	}

	/**
	 * @return int
	 */
	public function blog_id() {
		if ( null !== $this->blog_override ) {
			return (int) $this->blog_override;
		}
		return function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;
	}

	/**
	 * @param int $blog_id Blog ID.
	 * @return string Expected prefix for the given blog.
	 */
	public function blog_prefix( $blog_id ) {
		global $wpdb;
		if ( isset( $wpdb ) && method_exists( $wpdb, 'get_blog_prefix' ) ) {
			return $wpdb->get_blog_prefix( $blog_id );
		}
		// Fallback for isolated environments or missing wpdb method
		return $this->base_prefix() . (int) $blog_id . '_';
	}

	/**
	 * Tables registered by WordPress itself (full names, actual prefix).
	 *
	 * @return string[]
	 */
	public function registered_tables() {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! method_exists( $wpdb, 'tables' ) ) {
			return array();
		}
		$tables = $wpdb->tables( 'all', true );
		return is_array( $tables ) ? array_values( $tables ) : array();
	}

	/**
	 * Strip the site prefix from a table name ('' when it has no WP prefix).
	 *
	 * @param string $table Table name.
	 * @return string|null Remainder or null when prefix absent.
	 */
	public function strip_prefix( $table ) {
		foreach ( array( $this->prefix(), $this->base_prefix() ) as $prefix ) {
			if ( '' !== $prefix && 0 === strpos( $table, $prefix ) ) {
				return substr( $table, strlen( $prefix ) );
			}
		}
		return null;
	}
}
