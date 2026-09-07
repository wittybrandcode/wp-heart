<?php
/**
 * Table aggregate: observed metadata + intelligence overlays.
 *
 * @package WP_Heart
 */

namespace WPHeart\Domain;

/**
 * Table aggregate: observed metadata + intelligence overlays.
 */
class TableInfo {
	/** @var string */
	private $name;
	/** @var array */
	private $metadata;
	/** @var ColumnInfo[] */
	private $columns = array();
	/** @var IndexInfo[] */
	private $indexes = array();
	/** @var ConstraintInfo[] */
	private $constraints = array();
	/** @var Relationship[] */
	private $relationships = array();
	/** @var Classification|null */
	private $classification = null;

	/**
	 * @param string $name Actual table name in the database.
	 * @param array  $metadata Observed metadata (engine, rows, size...).
	 */
	public function __construct( $name, array $metadata = array() ) {
		$this->name     = $name;
		$this->metadata = $metadata;
	}

	/**
	 * @return string
	 */
	public function name() {
		return $this->name;
	}

	/**
	 * @param ColumnInfo[] $columns Columns.
	 */
	public function set_columns( array $columns ) {
		$this->columns = $columns;
	}

	/**
	 * @param IndexInfo[] $indexes Indexes.
	 */
	public function set_indexes( array $indexes ) {
		$this->indexes = $indexes;
	}

	/**
	 * @param ConstraintInfo[] $constraints Constraints.
	 */
	public function set_constraints( array $constraints ) {
		$this->constraints = $constraints;
	}

	/**
	 * @param Relationship[] $relationships Relationships.
	 */
	public function set_relationships( array $relationships ) {
		$this->relationships = $relationships;
	}

	/**
	 * @param Classification $classification Classification.
	 */
	public function set_classification( Classification $classification ) {
		$this->classification = $classification;
	}

	/**
	 * @return ColumnInfo[]
	 */
	public function columns() {
		return $this->columns;
	}

	/**
	 * @return IndexInfo[]
	 */
	public function indexes() {
		return $this->indexes;
	}

	/**
	 * @return ConstraintInfo[]
	 */
	public function constraints() {
		return $this->constraints;
	}

	/**
	 * @return Relationship[]
	 */
	public function relationships() {
		return $this->relationships;
	}

	/**
	 * @return Classification|null
	 */
	public function classification() {
		return $this->classification;
	}

	/**
	 * @return bool
	 */
	public function has_primary_key() {
		foreach ( $this->indexes as $index ) {
			if ( $index->get( 'primary' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return string[]
	 */
	public function primary_key_columns() {
		foreach ( $this->indexes as $index ) {
			if ( $index->get( 'primary' ) ) {
				return (array) $index->get( 'columns' );
			}
		}
		return array();
	}

	/**
	 * @return array
	 */
	public function to_array() {
		return array(
			'name'            => $this->name,
			'metadata'        => $this->metadata,
			'columns'         => array_map(
				static function ( ColumnInfo $c ) {
					return $c->to_array();
				},
				$this->columns
			),
			'indexes'         => array_map(
				static function ( IndexInfo $i ) {
					return $i->to_array();
				},
				$this->indexes
			),
			'constraints'     => array_map(
				static function ( ConstraintInfo $c ) {
					return $c->to_array();
				},
				$this->constraints
			),
			'relationships'   => array_map(
				static function ( Relationship $r ) {
					return $r->to_array();
				},
				$this->relationships
			),
			'classification'  => $this->classification ? $this->classification->to_array() : null,
			'has_primary_key' => $this->has_primary_key(),
		);
	}
}
