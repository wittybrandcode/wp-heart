<?php
/**
 * Smart Entity Exporter.
 *
 * @package WP_Heart
 */

namespace WPHeart\Intelligence;

use WPHeart\Container\Container;
use WPHeart\Rest\TableAssembler;

/**
 * Smart Entity Exporter.
 */
class EntityExporter {
	/** @var Container */
	private $c;

	/**
	 * @param Container $c Container.
	 */
	public function __construct( Container $c ) {
		$this->c = $c;
	}

	/**
	 * Export an entity and its relationships.
	 *
	 * @param string $table_name Table name.
	 * @param array  $pks        Primary key values.
	 * @return array
	 */
	public function export( $table_name, array $pks ) {
		global $wpdb;
		$assembler = new TableAssembler( $this->c );
		$table     = $assembler->table( $table_name );
		if ( ! $table ) {
			return array();
		}

		$indexes = $this->c->make( 'schema.indexes' )->inspect( $table->name() );
		$pk_cols = null;
		foreach ( $indexes as $idx ) {
			if ( $idx['primary'] ) {
				$pk_cols = $idx['columns'];
				break;
			}
		}

		if ( ! $pk_cols || count( $pk_cols ) !== count( $pks ) ) {
			return array();
		}

		$where = array();
		$where_sql = array();
		foreach ( $pk_cols as $i => $col ) {
			$where[ $col ] = $pks[ $i ];
			$where_sql[]   = "`{$col}` = '" . esc_sql( $pks[ $i ] ) . "'";
		}

		// 1. Fetch main row
		$main_sql = "SELECT * FROM `{$table->name()}` WHERE " . implode( ' AND ', $where_sql );
		$main_row = $wpdb->get_row( $main_sql, ARRAY_A );

		if ( ! $main_row ) {
			return array();
		}

		$export = array(
			'entity_table' => $table->name(),
			'keys'         => $where,
			'data'         => $main_row,
			'related'      => array(),
		);

		// 2. Fetch related child rows
		$all_tables = $assembler->list( false )['tables'];
		foreach ( $all_tables as $child_table ) {
			if ( $child_table->name() === $table->name() ) {
				continue; // Skip self
			}

			// We need to fetch relationships for child_table to see if it points to us.
			// However, relationships are resolved by the assembler when full=true or via specific calls.
			// Let's resolve relationships for this child table.
			$rels = $this->c->make( 'schema.relationships' )->inspect( $child_table->name() );
			
			foreach ( $rels as $rel ) {
				$r = $rel->to_array();
				if ( $r['target_table'] === $table->name() && in_array( $r['target_column'], $pk_cols, true ) ) {
					// We found a child table pointing to our entity!
					$fk_col = $r['source_column'];
					
					// Which PK value corresponds to this target column?
					$pk_idx = array_search( $r['target_column'], $pk_cols, true );
					$pk_val = $pks[ $pk_idx ];

					$child_sql  = "SELECT * FROM `{$child_table->name()}` WHERE `{$fk_col}` = '" . esc_sql( $pk_val ) . "'";
					$child_rows = $wpdb->get_results( $child_sql, ARRAY_A );
					
					if ( ! empty( $child_rows ) ) {
						if ( ! isset( $export['related'][ $child_table->name() ] ) ) {
							$export['related'][ $child_table->name() ] = array();
						}
						// Avoid duplicates if multiple relations exist
						foreach ( $child_rows as $cr ) {
							$export['related'][ $child_table->name() ][] = $cr;
						}
					}
				}
			}
		}

		// Remove duplicates from related arrays
		foreach ( $export['related'] as $ct => $rows ) {
			$export['related'][ $ct ] = array_map( 'unserialize', array_unique( array_map( 'serialize', $rows ) ) );
		}

		return $export;
	}
}
