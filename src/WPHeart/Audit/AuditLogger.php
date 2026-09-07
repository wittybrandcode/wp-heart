<?php
/**
 * Audit logger facade (WH-161).
 *
 * @package WP_Heart
 */

namespace WPHeart\Audit;

use WPHeart\Domain\AuditEvent;

/**
 * Audit logger facade (WH-161).
 */
class AuditLogger {
	/** @var AuditStore */
	private $store;

	/**
	 * @param AuditStore $store Store.
	 */
	public function __construct( AuditStore $store ) {
		$this->store = $store;
	}

	/**
	 * @param string $type AuditEvent::*.
	 * @param string $target Target identifier (table name, endpoint...).
	 * @param array  $metadata Redacted metadata.
	 * @param string $outcome Outcome label.
	 */
	public function log( $type, $target, array $metadata = array(), $outcome = 'success' ) {
		$actor = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$this->store->push(
			new AuditEvent(
				array(
					'id'        => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'wh_', true ),
					'type'      => $type,
					'actor'     => $actor,
					'timestamp' => time(),
					'target'    => substr( (string) $target, 0, 191 ),
					'metadata'  => AuditStore::clean_metadata( $metadata ),
					'outcome'   => substr( (string) $outcome, 0, 50 ),
				)
			)
		);
	}
}
