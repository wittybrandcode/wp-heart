<?php
/**
 * Data endpoints: paginated rows + row inspector (WH-103).
 * Composite/no-PK semantics are explicit, never guessed.
 *
 * @package WP_Heart
 */

namespace WPHeart\Rest\Controllers;

use WPHeart\Audit\AuditLogger;
use WPHeart\Container\Container;
use WPHeart\Database\LikeHelper;
use WPHeart\Domain\AuditEvent;
use WPHeart\Domain\Pagination;
use WPHeart\Rest\Presenter;
use WPHeart\Rest\TableAssembler;
use WPHeart\Schema\ColumnInspector;
use WPHeart\Security\Capabilities;
use WPHeart\Security\ErrorSanitizer;
use WPHeart\Security\Permission;

/**
 * Data endpoints: paginated rows + row inspector (WH-103).
 */
class DataController {
	/** @var Container */
	private $c;

	/**
	 * @param Container $c Container.
	 */
	public function __construct( Container $c ) {
		$this->c = $c;
	}

	/**
	 * @param string $namespace REST namespace.
	 */
	public function register( $namespace ) {
		register_rest_route(
			$namespace,
			'/tables/(?P<table>[A-Za-z0-9_$]+)/rows',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rows' ),
				'permission_callback' => Permission::rest( Capabilities::VIEW_DATA ),
				'args'                => array(
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'orderby'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'order'    => array(
						'type'    => 'string',
						'default' => 'ASC',
					),
					'search'   => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/tables/(?P<table>[A-Za-z0-9_$]+)/rows/(?P<id>[^/]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'row' ),
				'permission_callback' => Permission::rest( Capabilities::VIEW_DATA ),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rows( $request ) {
		try {
			$config    = $this->c->make( 'config' );
			$assembler = new TableAssembler( $this->c );
			$table     = $assembler->get( (string) $request->get_param( 'table' ) );
			if ( $table instanceof \WP_Error ) {
				return $table;
			}
			$identifiers = $this->c->make( 'db.identifiers' );
			$adapter     = $this->c->make( 'db.adapter' );

			$data = $table->to_array();
			$cols = array();
			foreach ( $data['columns'] as $col ) {
				$cols[] = $col['name'];
			}

			$orderby   = (string) $request->get_param( 'orderby' );
			$order_sql = '';
			if ( '' !== $orderby ) {
				$valid_col = $identifiers->validate_column( $orderby, $cols );
				if ( null === $valid_col ) {
					return ErrorSanitizer::rest_error( 'wp_heart_bad_orderby', __( 'Invalid orderby column.', 'wp-heart' ), 400 );
				}
				$dir = $identifiers->sort_direction( $request->get_param( 'order' ) );
				if ( null === $dir ) {
					return ErrorSanitizer::rest_error( 'wp_heart_bad_order', __( 'Invalid order direction.', 'wp-heart' ), 400 );
				}
				$order_sql = ' ORDER BY ' . $identifiers->quote( $valid_col ) . ' ' . $dir;
			}

			$pagination = new Pagination(
				(int) $request->get_param( 'page' ),
				(int) $request->get_param( 'per_page' ),
				(int) $config->get( 'max_per_page', 100 ),
				(int) $config->get( 'default_per_page', 20 )
			);

			// Optional bounded row search across text-like columns.
			$search_term      = trim( (string) $request->get_param( 'search' ) );
			$where_sql        = '';
			$params           = array();
			$searched_columns = array();
			if ( '' !== $search_term ) {
				$len = function_exists( 'mb_strlen' ) ? mb_strlen( $search_term ) : strlen( $search_term );
				if ( $len < 2 || $len > 100 ) {
					return ErrorSanitizer::rest_error( 'wp_heart_bad_search', __( 'Row search needs 2–100 characters.', 'wp-heart' ), 400 );
				}
				$likes = array();
				foreach ( array_slice( ColumnInspector::searchable( $table->columns() ), 0, 10 ) as $column ) {
					$valid_col = $identifiers->validate_column( $column->get( 'name' ), $cols );
					if ( null === $valid_col ) {
						continue;
					}
					$likes[]            = $identifiers->quote( $valid_col ) . ' LIKE %s ESCAPE \'\\\\\'';
					$params[]           = LikeHelper::contains( $search_term );
					$searched_columns[] = $valid_col;
				}
				if ( ! empty( $likes ) ) {
					$where_sql = ' WHERE ' . implode( ' OR ', $likes );
				}
			}

			$sql = 'SELECT * FROM ' . $identifiers->quote( $table->name() ) . $where_sql . $order_sql
				. ' LIMIT ' . $pagination->limit() . ' OFFSET ' . $pagination->offset();
			if ( '' !== $where_sql ) {
				$rows     = $adapter->fetch_all( $sql, $params );
				$db_error = '';
			} else {
				$run      = $adapter->run_read( $sql );
				$rows     = $run['rows'];
				$db_error = $run['error'];
			}
			if ( '' !== $db_error ) {
				return ErrorSanitizer::rest_error( 'wp_heart_rows_failed', $db_error, 500 );
			}

			$audit = $this->c->make( 'audit' );
			if ( $audit instanceof AuditLogger ) {
				$audit->log(
					AuditEvent::TABLE_VIEW,
					$table->name(),
					array(
						'page'   => $pagination->page(),
						'search' => '' !== $search_term,
					)
				);
			}

			$envelope                   = $pagination->envelope( $rows );
			$envelope['row_addressing'] = $this->addressing( $table );
			return Presenter::ok(
				$envelope['items'],
				array(
					'pagination'     => $envelope['pagination'],
					'row_addressing' => $envelope['row_addressing'],
					'row_search'     => array(
						'active'  => '' !== $search_term,
						'columns' => $searched_columns,
					),
				)
			);
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_rows_failed', $e->getMessage(), 500 );
		}
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function row( $request ) {
		try {
			$assembler = new TableAssembler( $this->c );
			$table     = $assembler->get( (string) $request->get_param( 'table' ) );
			if ( $table instanceof \WP_Error ) {
				return $table;
			}
			$addr = $this->addressing( $table );
			if ( 'single' !== $addr['mode'] ) {
				return ErrorSanitizer::rest_error(
					'wp_heart_row_addressing',
					'composite_key' === $addr['mode']
						? __( 'This table has a composite primary key; address rows with the full key via the rows endpoint filters.', 'wp-heart' )
						: __( 'This table has no primary key; individual rows cannot be addressed reliably.', 'wp-heart' ),
					400
				);
			}

			$pk_col = $addr['columns'][0];
			$row    = $this->c->make( 'db.adapter' )->fetch_row( $table->name(), $pk_col, (string) $request->get_param( 'id' ) );
			if ( ! $row ) {
				return ErrorSanitizer::rest_error( 'wp_heart_row_not_found', __( 'Row not found.', 'wp-heart' ), 404 );
			}

			$audit = $this->c->make( 'audit' );
			if ( $audit instanceof AuditLogger ) {
				$audit->log( AuditEvent::ROW_VIEW, $table->name(), array( 'pk_column' => $pk_col ) );
			}

			return Presenter::ok(
				array(
					'row'        => $row,
					'addressing' => $addr,
				),
				array()
			);
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_row_failed', $e->getMessage(), 500 );
		}
	}

	/**
	 * @param \WPHeart\Domain\TableInfo $table Table.
	 * @return array {mode: single|composite|none, columns: string[]}
	 */
	private function addressing( $table ) {
		$pk = $table->primary_key_columns();
		if ( 1 === count( $pk ) ) {
			return array(
				'mode'    => 'single',
				'columns' => array_values( $pk ),
			);
		}
		if ( count( $pk ) > 1 ) {
			return array(
				'mode'    => 'composite',
				'columns' => array_values( $pk ),
			);
		}
		return array(
			'mode'    => 'none',
			'columns' => array(),
		);
	}
}
