<?php
/**
 * Relationship engine (WH-033). PHYSICAL needs constraint evidence;
 * INFERRED carries evidence + confidence and is never a FK claim.
 *
 * @package WP_Heart
 */

namespace WPHeart\Schema;

use WPHeart\Domain\ColumnInfo;
use WPHeart\Domain\ConstraintInfo;
use WPHeart\Domain\Evidence;
use WPHeart\Domain\Relationship;
use WPHeart\Intelligence\WpContextService;
use WPHeart\Support\Confidence;
use WPHeart\Support\RelationshipOrigin;

/**
 * Relationship engine (WH-033). PHYSICAL needs constraint evidence;.
 */
class RelationshipEngine {
	/** @var WpContextService */
	private $context;

	/**
	 * @param WpContextService $context WP context.
	 */
	public function __construct( WpContextService $context ) {
		$this->context = $context;
	}

	/**
	 * Build relationships for one table.
	 *
	 * @param string           $table Table name.
	 * @param ColumnInfo[]     $columns Columns.
	 * @param ConstraintInfo[] $constraints Physical constraints.
	 * @param string[]         $known_tables All discovered tables (for target resolution).
	 * @param bool             $constraints_available False when metadata was unreachable.
	 * @return Relationship[]
	 */
	public function for_table( $table, array $columns, array $constraints, array $known_tables, $constraints_available = true ) {
		$relationships = array();

		foreach ( $constraints as $constraint ) {
			$cols      = (array) $constraint->get( 'columns' );
			$ref_cols  = (array) $constraint->get( 'referenced_columns' );
			$ref_table = $constraint->get( 'referenced_table' );
			foreach ( $cols as $i => $col ) {
				$relationships[] = new Relationship(
					$table,
					(string) $col,
					is_string( $ref_table ) ? $ref_table : null,
					isset( $ref_cols[ $i ] ) ? (string) $ref_cols[ $i ] : null,
					RelationshipOrigin::PHYSICAL,
					Confidence::HIGH,
					array(
						new Evidence(
							Evidence::DATABASE_CONSTRAINT,
							sprintf( 'Foreign key %s references %s.', $constraint->get( 'name' ), $ref_table ),
							95
						),
					)
				);
			}
		}

		$physical_cols = array();
		foreach ( $relationships as $rel ) {
			$data                                    = $rel->to_array();
			$physical_cols[ $data['source_column'] ] = true;
		}

		foreach ( $columns as $column ) {
			$col_name = (string) $column->get( 'name' );
			if ( isset( $physical_cols[ $col_name ] ) || 'ID' === $col_name ) {
				continue;
			}
			$inferred = $this->infer( $table, $col_name, $known_tables );
			if ( $inferred ) {
				$relationships[] = $inferred;
			}
		}

		if ( ! $constraints_available ) {
			$relationships[] = new Relationship(
				$table,
				'',
				null,
				null,
				RelationshipOrigin::UNKNOWN,
				Confidence::UNKNOWN,
				array( new Evidence( Evidence::OTHER, 'Constraint metadata was unavailable; relationship state is unknown.', 0 ) )
			);
		}

		return $relationships;
	}

	/**
	 * Heuristic inference for a single column.
	 *
	 * @param string   $table Table name.
	 * @param string   $column Column name.
	 * @param string[] $known_tables Known tables.
	 * @return Relationship|null
	 */
	private function infer( $table, $column, array $known_tables ) {
		$prefix = $this->context->prefix();

		// Core WordPress conventions (medium confidence, schema-signature backed).
		$core_map = array(
			'post_author'      => array( 'users', 'ID' ),
			'post_parent'      => array( 'posts', 'ID' ),
			'comment_post_ID'  => array( 'posts', 'ID' ),
			'comment_parent'   => array( 'comments', 'ID' ),
			'user_id'          => array( 'users', 'ID' ),
			'object_id'        => null, // polymorphic — deliberately unresolved.
			'term_id'          => array( 'terms', 'term_id' ),
			'term_taxonomy_id' => array( 'term_taxonomy', 'term_taxonomy_id' ),
			'term_order'       => null,
		);
		if ( array_key_exists( $column, $core_map ) ) {
			$target = $core_map[ $column ];
			if ( null === $target ) {
				return null;
			}
			$target_table = $prefix . $target[0];
			if ( ! in_array( $target_table, $known_tables, true ) ) {
				return null;
			}
			return new Relationship(
				$table,
				$column,
				$target_table,
				$target[1],
				RelationshipOrigin::INFERRED,
				Confidence::MEDIUM,
				array(
					new Evidence( Evidence::KNOWN_SCHEMA_SIGNATURE, sprintf( 'Column %s follows the WordPress core convention targeting %s.', $column, $target_table ), 65 ),
					new Evidence( Evidence::COLUMN_PATTERN, sprintf( 'Naming pattern %s matches core schema signature.', $column ), 45 ),
				)
			);
		}

		// Generic {entity}_id convention (low confidence, pattern only).
		if ( preg_match( '/^(.+)_id$/i', $column, $m ) ) {
			$entity    = strtolower( $m[1] );
			$candidate = array( $prefix . $entity . 's', $prefix . $entity );
			foreach ( $candidate as $target_table ) {
				if ( $target_table !== $table && in_array( $target_table, $known_tables, true ) ) {
					return new Relationship(
						$table,
						$column,
						$target_table,
						'id',
						RelationshipOrigin::INFERRED,
						Confidence::LOW,
						array( new Evidence( Evidence::COLUMN_PATTERN, sprintf( 'Column %s matches the generic {entity}_id pattern for %s; not a confirmed constraint.', $column, $target_table ), 30 ) )
					);
				}
			}
		}

		return null;
	}
}
