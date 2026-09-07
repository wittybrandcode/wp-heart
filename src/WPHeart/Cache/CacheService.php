<?php
/**
 * Metadata cache: current-state only, never a historical snapshot (WH-110–WH-112).
 *
 * @package WP_Heart
 */

namespace WPHeart\Cache;

use WPHeart\Config\Config;

/**
 * Metadata cache: current-state only, never a historical snapshot (WH-110–WH-112).
 */
class CacheService {
	const PREFIX = 'wp_heart_';

	/** @var Config */
	private $config;
	/** @var bool|null Null = unknown, false = writes observed failing (e.g. read-only DB). */
	private $writable = null;

	/**
	 * @param Config $config Config.
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Run storage I/O with $wpdb error output fully suppressed. On
	 * restricted connections WordPress otherwise logs a database error
	 * for every transient write while the plugin keeps working uncached.
	 * Note: hide_errors() is not enough — print_error() still calls
	 * error_log(); only suppress_errors() silences both.
	 *
	 * @param callable $fn Storage operation.
	 * @return mixed Operation result.
	 */
	private function quietly( $fn ) {
		global $wpdb;
		$has_api = isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'suppress_errors' );
		$prev    = $has_api ? (bool) $wpdb->suppress_errors( true ) : true;
		try {
			$result = $fn();
		} catch ( \Exception $e ) {
			$result = false;
		}
		if ( $has_api ) {
			$wpdb->suppress_errors( $prev );
		}
		return $result;
	}

	/**
	 * @param string $key Logical key.
	 * @return string Namespaced transient key.
	 */
	public function key( $key ) {
		$clean = preg_replace( '/[^A-Za-z0-9_:\-]/', '_', (string) $key );
		return substr( self::PREFIX . $clean, 0, 170 );
	}

	/**
	 * @param string $key Logical key.
	 * @return array {hit, value, freshness} Freshness: FRESH|STALE|MISS.
	 */
	public function get( $key ) {
		$self   = $this;
		$stored = function_exists( 'get_transient' ) ? $self->quietly(
			static function () use ( $self, $key ) {
				return get_transient( $self->key( $key ) );
			}
		) : false;
		if ( ! is_array( $stored ) || ! array_key_exists( 'value', $stored ) ) {
			return array(
				'hit'       => false,
				'value'     => null,
				'freshness' => 'MISS',
			);
		}
		return array(
			'hit'       => true,
			'value'     => $stored['value'],
			'freshness' => 'FRESH',
		);
	}

	/**
	 * @param string   $key Logical key.
	 * @param mixed    $value Value.
	 * @param int|null $ttl TTL seconds (null = configured default).
	 * @return bool
	 */
	public function set( $key, $value, $ttl = null ) {
		if ( ! function_exists( 'set_transient' ) || false === $this->writable ) {
			return false;
		}
		if ( null === $ttl ) {
			$ttl = (int) $this->config->get( 'cache_ttl', 300 );
		}
		$payload = array(
			'value'      => $value,
			'created_at' => time(),
			'ttl'        => (int) $ttl,
		);
		$self    = $this;
		$ok      = (bool) $self->quietly(
			static function () use ( $self, $key, $payload, $ttl ) {
				return set_transient( $self->key( $key ), $payload, (int) $ttl );
			}
		);
		if ( ! $ok ) {
			// Remember for this request: stay functional, just uncached.
			$this->writable = false;
		}
		return $ok;
	}

	/**
	 * @param string $key Logical key.
	 * @return bool
	 */
	public function delete( $key ) {
		if ( ! function_exists( 'delete_transient' ) || false === $this->writable ) {
			return false;
		}
		$self = $this;
		$ok   = (bool) $self->quietly(
			static function () use ( $self, $key ) {
				return delete_transient( $self->key( $key ) );
			}
		);
		if ( ! $ok ) {
			$this->writable = false;
		}
		return $ok;
	}

	/**
	 * Invalidate all WP-HEART cached metadata (explicit refresh path).
	 */
	public function flush_all() {
		foreach ( $this->known_keys() as $key ) {
			$this->delete( $key );
		}
		// Multisite: also clear network-wide keys.
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			foreach ( $this->known_keys() as $key ) {
				if ( function_exists( 'delete_site_transient' ) ) {
					delete_site_transient( $this->key( $key ) );
				}
			}
		}
	}

	/**
	 * Garbage collect expired WP-HEART transients to protect wp_options from bloat.
	 */
	public function cleanup_expired() {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return;
		}
		
		$self = $this;
		$this->quietly(
			static function () use ( $wpdb ) {
				$time = time();
				$sql  = "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d";
				$expired = $wpdb->get_col( $wpdb->prepare( $sql, $wpdb->esc_like( '_transient_timeout_wp_heart_' ) . '%', $time ) );
				if ( ! empty( $expired ) ) {
					foreach ( $expired as $timeout_key ) {
						// Extract transient base name and delete it via standard API
						$key = str_replace( '_transient_timeout_', '', $timeout_key );
						delete_transient( $key );
					}
				}
			}
		);
	}

	/**
	 * @return string[] Logical keys managed by WP-HEART.
	 */
	public function known_keys() {
		return array( 'discovery:v1', 'dbinfo:v1', 'plugins:v1', 'health:v1', 'map:v1' );
	}

	/**
	 * @param int $created_at Unix timestamp.
	 * @return string Human freshness label.
	 */
	public static function freshness_label( $created_at ) {
		$age = time() - (int) $created_at;
		if ( $age < 60 ) {
			return 'just now';
		}
		if ( $age < 3600 ) {
			return sprintf( '%d min ago', (int) floor( $age / 60 ) );
		}
		return sprintf( '%d h ago', (int) floor( $age / 3600 ) );
	}
}
