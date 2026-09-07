<?php
/**
 * EXPLAIN support for eligible read queries (WH-093). Diagnostic only.
 *
 * @package WP_Heart
 */

namespace WPHeart\Query;

use WPHeart\Database\DatabaseAdapterInterface;

/**
 * EXPLAIN support for eligible read queries (WH-093). Diagnostic only.
 */
class Explainer {
	/** @var DatabaseAdapterInterface */
	private $adapter;
	/** @var QueryValidator */
	private $validator;

	/**
	 * @param DatabaseAdapterInterface $adapter Adapter.
	 * @param QueryValidator           $validator Validator.
	 */
	public function __construct( DatabaseAdapterInterface $adapter, QueryValidator $validator ) {
		$this->adapter   = $adapter;
		$this->validator = $validator;
	}

	/**
	 * @param mixed $sql Raw SQL.
	 * @return array {success, rows, message}
	 */
	public function explain( $sql ) {
		$check = $this->validator->validate( $sql );
		if ( ! $check['valid'] ) {
			return array(
				'success' => false,
				'rows'    => array(),
				'message' => $check['message'],
			);
		}
		$first = strtoupper( strtok( ltrim( $check['sql'] ), " \t\r\n(" ) );
		if ( ! in_array( $first, array( 'SELECT', 'WITH' ), true ) ) {
			return array(
				'success' => false,
				'rows'    => array(),
				'message' => __( 'EXPLAIN is only available for SELECT queries.', 'wp-heart' ),
			);
		}
		$run = $this->adapter->run_read( 'EXPLAIN ' . $check['sql'] );
		if ( '' !== $run['error'] ) {
			return array(
				'success' => false,
				'rows'    => array(),
				'message' => __( 'The database reported an error for this EXPLAIN.', 'wp-heart' ),
			);
		}
		return array(
			'success' => true,
			'rows'    => $run['rows'],
			'message' => '',
		);
	}
}
