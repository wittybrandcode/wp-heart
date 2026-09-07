<?php
/**
 * Schema differ: compares two snapshot documents and reports Added,
 * Removed and Changed on tables, columns, indexes and constraints.
 * Read-only analysis; implies no action and executes none.
 *
 * @package WP_Heart
 */

namespace WPHeart\Snapshot;

/**
 * Schema differ.
 */
class SchemaDiff {
	/**
	 * @param array $a Older snapshot document.
	 * @param array $b Newer snapshot document.
	 * @return array Diff with summary counts.
	 */
	public static function compare( array $a, array $b ) {
		$map_a = self::table_map( $a );
		$map_b = self::table_map( $b );

		$added   = array_values( array_diff( array_keys( $map_b ), array_keys( $map_a ) ) );
		$removed = array_values( array_diff( array_keys( $map_a ), array_keys( $map_b ) ) );
		sort( $added );
		sort( $removed );

		$changed = array();
		foreach ( array_intersect( array_keys( $map_a ), array_keys( $map_b ) ) as $table ) {
			$delta = self::diff_table( $map_a[ $table ], $map_b[ $table ] );
			if ( ! empty( $delta ) ) {
				$changed[ $table ] = $delta;
			}
		}
		ksort( $changed );

		$changed_tables = count( $changed );
		return array(
			'tables'  => array(
				'added'   => $added,
				'removed' => $removed,
				'changed' => $changed,
			),
			'summary' => array(
				'tables_added'   => count( $added ),
				'tables_removed' => count( $removed ),
				'tables_changed' => $changed_tables,
				'tables_total_a' => count( $map_a ),
				'tables_total_b' => count( $map_b ),
			),
		);
	}

	/**
	 * @param array $doc Snapshot document.
	 * @return array Tables keyed by name.
	 */
	private static function table_map( array $doc ) {
		$map = array();
		foreach ( isset( $doc['tables'] ) && is_array( $doc['tables'] ) ? $doc['tables'] : array() as $table ) {
			if ( is_array( $table ) && isset( $table['name'] ) ) {
				$map[ (string) $table['name'] ] = $table;
			}
		}
		return $map;
	}

	/**
	 * @param array $a Old table.
	 * @param array $b New table.
	 * @return array Empty when identical.
	 */
	private static function diff_table( array $a, array $b ) {
		$delta = array();

		foreach ( array( 'engine', 'collation' ) as $field ) {
			$va = isset( $a['metadata'][ $field ] ) ? $a['metadata'][ $field ] : null;
			$vb = isset( $b['metadata'][ $field ] ) ? $b['metadata'][ $field ] : null;
			if ( $va !== $vb ) {
				$delta['metadata'][ $field ] = array(
					'from' => $va,
					'to'   => $vb,
				);
			}
		}

		$cols = self::diff_named(
			self::index_by( isset( $a['columns'] ) ? $a['columns'] : array(), 'name' ),
			self::index_by( isset( $b['columns'] ) ? $b['columns'] : array(), 'name' ),
			array( 'data_type', 'nullable', 'default', 'extra' )
		);
		if ( ! empty( $cols['added'] ) || ! empty( $cols['removed'] ) || ! empty( $cols['changed'] ) ) {
			$delta['columns'] = $cols;
		}

		$indexes = self::diff_named(
			self::index_by( isset( $a['indexes'] ) ? $a['indexes'] : array(), 'name' ),
			self::index_by( isset( $b['indexes'] ) ? $b['indexes'] : array(), 'name' ),
			array( 'unique', 'columns' )
		);
		if ( ! empty( $indexes['added'] ) || ! empty( $indexes['removed'] ) || ! empty( $indexes['changed'] ) ) {
			$delta['indexes'] = $indexes;
		}

		$ca = array();
		foreach ( isset( $a['constraints'] ) && is_array( $a['constraints'] ) ? $a['constraints'] : array() as $c ) {
			$c = (array) $c;
			if ( isset( $c['name'] ) ) {
				$ca[ (string) $c['name'] ] = $c;
			}
		}
		$cb = array();
		foreach ( isset( $b['constraints'] ) && is_array( $b['constraints'] ) ? $b['constraints'] : array() as $c ) {
			$c = (array) $c;
			if ( isset( $c['name'] ) ) {
				$cb[ (string) $c['name'] ] = $c;
			}
		}
		$c_added   = array_values( array_diff( array_keys( $cb ), array_keys( $ca ) ) );
		$c_removed = array_values( array_diff( array_keys( $ca ), array_keys( $cb ) ) );
		sort( $c_added );
		sort( $c_removed );
		if ( ! empty( $c_added ) || ! empty( $c_removed ) ) {
			$delta['constraints'] = array(
				'added'   => $c_added,
				'removed' => $c_removed,
			);
		}

		foreach ( array( 'type', 'owner', 'confidence' ) as $field ) {
			$va = isset( $a['classification'][ $field ] ) ? $a['classification'][ $field ] : null;
			$vb = isset( $b['classification'][ $field ] ) ? $b['classification'][ $field ] : null;
			if ( $va !== $vb ) {
				$delta['classification'][ $field ] = array(
					'from' => $va,
					'to'   => $vb,
				);
			}
		}

		return $delta;
	}

	/**
	 * @param array  $items Items.
	 * @param string $key Key field.
	 * @return array Keyed map.
	 */
	private static function index_by( $items, $key ) {
		$map = array();
		foreach ( (array) $items as $item ) {
			$item = (array) $item;
			if ( isset( $item[ $key ] ) ) {
				$map[ (string) $item[ $key ] ] = $item;
			}
		}
		return $map;
	}

	/**
	 * Diff two named maps on a field subset.
	 *
	 * @param array    $a Old map.
	 * @param array    $b New map.
	 * @param string[] $fields Compared fields.
	 * @return array {added, removed, changed: {name: {field: {from, to}}}}
	 */
	private static function diff_named( array $a, array $b, array $fields ) {
		$added   = array_values( array_diff( array_keys( $b ), array_keys( $a ) ) );
		$removed = array_values( array_diff( array_keys( $a ), array_keys( $b ) ) );
		sort( $added );
		sort( $removed );
		$changed = array();
		foreach ( array_intersect( array_keys( $a ), array_keys( $b ) ) as $name ) {
			$fields_changed = array();
			foreach ( $fields as $field ) {
				$va = isset( $a[ $name ][ $field ] ) ? $a[ $name ][ $field ] : null;
				$vb = isset( $b[ $name ][ $field ] ) ? $b[ $name ][ $field ] : null;
				if ( wp_json_encode( $va ) !== wp_json_encode( $vb ) ) {
					$fields_changed[ $field ] = array(
						'from' => $va,
						'to'   => $vb,
					);
				}
			}
			if ( ! empty( $fields_changed ) ) {
				$changed[ $name ] = $fields_changed;
			}
		}
		ksort( $changed );
		return array(
			'added'   => $added,
			'removed' => $removed,
			'changed' => $changed,
		);
	}
}
