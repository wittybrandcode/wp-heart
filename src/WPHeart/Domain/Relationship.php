<?php
/**
 * Relationship value object with explicit origin semantics.
 *
 * @package WP_Heart
 */

namespace WPHeart\Domain;

use WPHeart\Support\Confidence;
use WPHeart\Support\RelationshipOrigin;

/**
 * Relationship value object with explicit origin semantics.
 */
class Relationship {
	/** @var string */
	private $source_table;
	/** @var string */
	private $source_column;
	/** @var string|null */
	private $target_table;
	/** @var string|null */
	private $target_column;
	/** @var string */
	private $origin;
	/** @var string */
	private $confidence;
	/** @var Evidence[] */
	private $evidence;

	/**
	 * @param string      $source_table Source table.
	 * @param string      $source_column Source column.
	 * @param string|null $target_table Target table (null when NONE/UNKNOWN).
	 * @param string|null $target_column Target column.
	 * @param string      $origin RelationshipOrigin::*.
	 * @param string      $confidence Confidence::*.
	 * @param Evidence[]  $evidence Evidence items (required for INFERRED).
	 */
	public function __construct( $source_table, $source_column, $target_table, $target_column, $origin, $confidence = Confidence::UNKNOWN, array $evidence = array() ) {
		$this->source_table  = $source_table;
		$this->source_column = $source_column;
		$this->target_table  = $target_table;
		$this->target_column = $target_column;
		$this->origin        = in_array( $origin, RelationshipOrigin::all(), true ) ? $origin : RelationshipOrigin::UNKNOWN;
		$this->confidence    = Confidence::is_valid( $confidence ) ? $confidence : Confidence::UNKNOWN;
		$this->evidence      = $evidence;
	}

	/**
	 * @return array
	 */
	public function to_array() {
		return array(
			'source_table'  => $this->source_table,
			'source_column' => $this->source_column,
			'target_table'  => $this->target_table,
			'target_column' => $this->target_column,
			'origin'        => $this->origin,
			'confidence'    => $this->confidence,
			'evidence'      => array_map(
				static function ( Evidence $e ) {
					return $e->to_array();
				},
				$this->evidence
			),
		);
	}

	/**
	 * @return string
	 */
	public function origin() {
		return $this->origin;
	}
}
