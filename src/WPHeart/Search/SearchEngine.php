<?php
/**
 * Bounded global search engine (WH-140). Schema-aware, paginated,
 * source-attributed; totals are always ESTIMATED.
 *
 * @package WP_Heart
 */

namespace WPHeart\Search;

use WPHeart\Config\Config;
use WPHeart\Database\DatabaseAdapterInterface;
use WPHeart\Database\IdentifierValidator;
use WPHeart\Database\LikeHelper;
use WPHeart\Domain\Pagination;
use WPHeart\Schema\ColumnInspector;
use WPHeart\Schema\IndexInspector;
use WPHeart\Support\Accuracy;

/**
 * Bounded global search engine (WH-140). Schema-aware, paginated,.
 */
class SearchEngine {
	/** @var DatabaseAdapterInterface */
	private $adapter;
	/** @var IdentifierValidator */
	private $identifiers;
	/** @var ColumnInspector */
	private $columns;
	/** @var IndexInspector|null */
	private $indexes;
	/** @var Config */
	private $config;

	/**
	 * @param DatabaseAdapterInterface $adapter Adapter.
	 * @param IdentifierValidator      $identifiers Validator.
	 * @param ColumnInspector          $columns Column inspector.
	 * @param Config                   $config Config.
	 * @param IndexInspector|null      $indexes Index inspector (for row identity).
	 */
	public function __construct( DatabaseAdapterInterface $adapter, IdentifierValidator $identifiers, ColumnInspector $columns, Config $config, $indexes = null ) {
		$this->adapter     = $adapter;
		$this->identifiers = $identifiers;
		$this->columns     = $columns;
		$this->config      = $config;
		$this->indexes     = $indexes;
	}

	/**
	 * @param string $term Search term.
	 * @param array  $args Search arguments: tables, page, per_page.
	 * @return array Result envelope, or array with error/message keys on validation failure.
	 */
	public function search( $term, array $args = array() ) {
		$term = is_string( $term ) ? trim( $term ) : '';
		$min  = (int) $this->config->get( 'min_search_length', 2 );
		$max  = (int) $this->config->get( 'max_search_length', 100 );
		if ( mb_strlen( $term ) < $min ) {
			return array(
				'error'   => 'term_too_short',
				'message' => sprintf( 'Search needs at least %d characters.', $min ),
			);
		}
		if ( mb_strlen( $term ) > $max ) {
			return array(
				'error'   => 'term_too_long',
				'message' => sprintf( 'Search is limited to %d characters.', $max ),
			);
		}

		$known  = $this->adapter->list_table_names();
		$scope  = isset( $args['tables'] ) && is_array( $args['tables'] ) ? $args['tables'] : $known;
		$tables = array();
		foreach ( $scope as $table ) {
			$valid = $this->identifiers->validate_table( $table, $known );
			if ( null !== $valid ) {
				$tables[] = $valid;
			}
		}
		$tables = array_slice( array_values( array_unique( $tables ) ), 0, (int) $this->config->get( 'max_search_tables', 50 ) );

		$pagination = new Pagination(
			isset( $args['page'] ) ? $args['page'] : 1,
			isset( $args['per_page'] ) ? $args['per_page'] : 20,
			50,
			20,
			null,
			Accuracy::ESTIMATED
		);

		$collected = array();
		$truncated = false;
		foreach ( $tables as $table ) {
			$columns = ColumnInspector::searchable( $this->columns->inspect( $table ) );
			if ( empty( $columns ) ) {
				continue;
			}
			$pk = $this->primary_column( $table );
			foreach ( array_slice( $columns, 0, 10 ) as $column ) {
				$col  = $column->get( 'name' );
				$sql  = 'SELECT * FROM ' . $this->identifiers->quote( $table )
					. ' WHERE ' . $this->identifiers->quote( $col ) . ' LIKE %s ESCAPE \'\\\\\' LIMIT 5';
				$like = LikeHelper::contains( $term );
				$rows = $this->adapter->fetch_all( $sql, array( $like ) );
				foreach ( $rows as $row ) {
					$row         = (array) $row;
					$collected[] = array(
						'table'   => $table,
						'column'  => $col,
						'row_id'  => $pk && isset( $row[ $pk ] ) ? (string) $row[ $pk ] : null,
						'preview' => $this->preview( isset( $row[ $col ] ) ? $row[ $col ] : null ),
					);
					if ( count( $collected ) >= 100 ) {
						$truncated = true;
						break 3;
					}
				}
			}
		}

		$page_items                  = array_slice( $collected, $pagination->offset(), $pagination->limit() );
		$envelope                    = $pagination->envelope( $page_items );
		$envelope['truncated']       = $truncated;
		$envelope['tables_searched'] = count( $tables );
		$envelope['term']            = $term;
		return $envelope;
	}

	/**
	 * @param string $table Table name.
	 * @return string|null Single-column PK or null.
	 */
	private function primary_column( $table ) {
		if ( ! $this->indexes ) {
			return null;
		}
		foreach ( $this->indexes->inspect( $table ) as $index ) {
			$data = $index->to_array();
			if ( ! empty( $data['primary'] ) && 1 === count( (array) $data['columns'] ) ) {
				$cols = (array) $data['columns'];
				return $cols[0];
			}
		}
		return null;
	}

	/**
	 * @param mixed $value Raw value.
	 * @return string Safe preview.
	 */
	public function preview( $value ) {
		if ( null === $value ) {
			return 'NULL';
		}
		if ( is_string( $value ) && preg_match( '/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/', $value ) ) {
			return '[binary data]';
		}
		$text = (string) $value;
		$len  = (int) $this->config->get( 'search_preview_len', 200 );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > $len ) {
			return mb_substr( $text, 0, $len ) . '…';
		}
		if ( strlen( $text ) > $len ) {
			return substr( $text, 0, $len ) . '…';
		}
		return $text;
	}
}
