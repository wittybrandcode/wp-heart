<?php
/**
 * Query execution: Authorization → Validation → Policy → Service → Adapter.
 * Result rows are capped; only metadata is audited (WH-092).
 *
 * @package WP_Heart
 */

namespace WPHeart\Query;

use WPHeart\Audit\AuditLogger;
use WPHeart\Config\Config;
use WPHeart\Database\DatabaseAdapterInterface;
use WPHeart\Domain\AuditEvent;

/**
 * Query execution: Authorization → Validation → Policy → Service → Adapter.
 */
class QueryExecutor {
	/** @var DatabaseAdapterInterface */
	private $adapter;
	/** @var QueryValidator */
	private $validator;
	/** @var Config */
	private $config;
	/** @var AuditLogger|null */
	private $audit;

	/**
	 * @param DatabaseAdapterInterface $adapter Adapter.
	 * @param QueryValidator           $validator Validator.
	 * @param Config                   $config Config.
	 * @param AuditLogger|null         $audit Audit logger.
	 */
	public function __construct( DatabaseAdapterInterface $adapter, QueryValidator $validator, Config $config, $audit = null ) {
		$this->adapter   = $adapter;
		$this->validator = $validator;
		$this->config    = $config;
		$this->audit     = $audit;
	}

	/**
	 * @param mixed $sql Raw SQL.
	 * @return array Result envelope.
	 */
	public function execute( $sql ) {
		$check = $this->validator->validate( $sql );
		if ( ! $check['valid'] ) {
			return array(
				'success' => false,
				'code'    => $check['code'],
				'message' => $check['message'],
				'rows'    => array(),
				'columns' => array(),
			);
		}

		$started = microtime( true );
		$run     = $this->adapter->run_read( $check['sql'] );
		$max     = (int) $this->config->get( 'max_query_rows', 500 );
		$rows    = array_slice( $run['rows'], 0, $max );
		$elapsed = isset( $run['elapsed_ms'] ) ? (float) $run['elapsed_ms'] : round( ( microtime( true ) - $started ) * 1000, 2 );

		if ( $this->audit ) {
			$this->audit->log(
				AuditEvent::QUERY_EXECUTION,
				'ad-hoc',
				array(
					'row_count' => count( $rows ),
					'truncated' => count( $run['rows'] ) > $max,
					'error'     => '' !== $run['error'],
				),
				'' === $run['error'] ? 'success' : 'error'
			);
		}

		if ( '' !== $run['error'] ) {
			return array(
				'success'    => false,
				'code'       => 'query_error',
				'message'    => __( 'The database reported an error for this query.', 'wp-heart' ),
				'rows'       => array(),
				'columns'    => array(),
				'elapsed_ms' => $elapsed,
			);
		}

		$columns = array();
		if ( ! empty( $rows ) ) {
			$columns = array_keys( (array) $rows[0] );
		}

		return array(
			'success'    => true,
			'code'       => 'ok',
			'message'    => '',
			'rows'       => $rows,
			'columns'    => $columns,
			'row_count'  => count( $rows ),
			'truncated'  => count( $run['rows'] ) > $max,
			'elapsed_ms' => $elapsed,
		);
	}
}
