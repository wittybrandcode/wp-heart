<?php
/**
 * Audit store: options-backed ring buffer, no custom tables (WH-160, D-004).
 * Metadata is redacted and truncated — never row content.
 *
 * @package WP_Heart
 */

namespace WPHeart\Audit;

use WPHeart\Config\Config;
use WPHeart\Domain\AuditEvent;
use WPHeart\Domain\Pagination;
use WPHeart\Logging\Logger;

/**
 * Audit store: options-backed ring buffer, no custom tables (WH-160, D-004).
 */
class AuditStore {
	const OPTION = 'wp_heart_audit_log';

	/** @var Config */
	private $config;
	
	/** @var bool Graceful degradation flag */
	private static $disabled = false;

	/**
	 * @param Config $config Config.
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * @param AuditEvent $event Event.
	 */
	public function push( AuditEvent $event ) {
		if ( self::$disabled ) {
			return;
		}
		
		$start = microtime( true );
		$entries   = $this->read();
		$entries[] = $event->to_array();
		$max       = (int) $this->config->get( 'audit_max_entries', 50 );
		if ( count( $entries ) > $max ) {
			$entries = array_slice( $entries, count( $entries ) - $max );
		}
		
		// Ensure serialized size is under 500KB (512000 bytes) to protect Object Cache
		while ( count( $entries ) > 0 && strlen( serialize( $entries ) ) > 512000 ) {
			array_shift( $entries );
		}

		if ( function_exists( 'update_option' ) ) {
			// On read-only connections the audit cannot persist; the
			// observation itself must never fatal or log DB errors.
			// Note: suppress_errors (not hide_errors) also silences error_log.
			global $wpdb;
			$has_api = isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'suppress_errors' );
			$prev    = $has_api ? (bool) $wpdb->suppress_errors( true ) : true;
			update_option( self::OPTION, $entries, false );
			if ( $has_api ) {
				$wpdb->suppress_errors( $prev );
			}
		}
		
		// Disable for the remainder of the request if storage is too slow (>500ms)
		if ( ( microtime( true ) - $start ) > 0.5 ) {
			self::$disabled = true;
		}
	}

	/**
	 * @param array $args Query arguments: page, per_page, type.
	 * @return array Paginated envelope (newest first).
	 */
	public function query( array $args = array() ) {
		$entries = array_reverse( $this->read() );
		if ( isset( $args['type'] ) && '' !== $args['type'] ) {
			$entries = array_values(
				array_filter(
					$entries,
					static function ( $e ) use ( $args ) {
						return isset( $e['type'] ) && $e['type'] === $args['type'];
					}
				)
			);
		}
		$count      = count( $entries );
		$pagination = new Pagination(
			isset( $args['page'] ) ? $args['page'] : 1,
			isset( $args['per_page'] ) ? $args['per_page'] : 20,
			100,
			20,
			$count,
			'EXACT'
		);
		return $pagination->envelope( array_slice( $entries, $pagination->offset(), $pagination->limit() ) );
	}

	/**
	 * @return array[]
	 */
	private function read() {
		$entries = function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : array();
		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * Redact + truncate audit metadata (WH-162).
	 *
	 * @param array $metadata Metadata.
	 * @return array
	 */
	public static function clean_metadata( array $metadata ) {
		$metadata = Logger::redact( $metadata );
		foreach ( $metadata as $key => $value ) {
			if ( is_string( $value ) && strlen( $value ) > 200 ) {
				$metadata[ $key ] = substr( $value, 0, 200 ) . '…';
			}
		}
		return $metadata;
	}
}
