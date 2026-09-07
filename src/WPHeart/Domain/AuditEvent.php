<?php
/**
 * Audit event value object. Metadata must never carry row content or secrets.
 *
 * @package WP_Heart
 */

namespace WPHeart\Domain;

/**
 * Audit event value object. Metadata must never carry row content or secrets.
 */
class AuditEvent {
	const DATABASE_SCAN   = 'DATABASE_SCAN';
	const TABLE_VIEW      = 'TABLE_VIEW';
	const ROW_VIEW        = 'ROW_VIEW';
	const SEARCH          = 'SEARCH';
	const QUERY_EXECUTION = 'QUERY_EXECUTION';
	const SETTINGS_CHANGE = 'SETTINGS_CHANGE';
	const CACHE_REFRESH   = 'CACHE_REFRESH';
	const SNAPSHOT_CREATE = 'SNAPSHOT_CREATE';
	const SNAPSHOT_DELETE = 'SNAPSHOT_DELETE';
	const SNAPSHOT_IMPORT = 'SNAPSHOT_IMPORT';

	/** @var array */
	private $data;

	/**
	 * @param array $data Event fields.
	 */
	public function __construct( array $data ) {
		$this->data = array_merge(
			array(
				'id'        => '',
				'type'      => '',
				'actor'     => 0,
				'timestamp' => 0,
				'target'    => '',
				'metadata'  => array(),
				'outcome'   => '',
			),
			$data
		);
	}

	/**
	 * @return array
	 */
	public function to_array() {
		return $this->data;
	}
}
