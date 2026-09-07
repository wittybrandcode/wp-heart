<?php
/**
 * Classification result: type + confidence + evidence (+ optional owner).
 *
 * @package WP_Heart
 */

namespace WPHeart\Domain;

use WPHeart\Support\ClassificationType;
use WPHeart\Support\Confidence;

/**
 * Classification result: type + confidence + evidence (+ optional owner).
 */
class Classification {
	/** @var string */
	private $type;
	/** @var string */
	private $confidence;
	/** @var Evidence[] */
	private $evidence;
	/** @var string|null */
	private $owner;

	/**
	 * @param string      $type ClassificationType::*.
	 * @param string      $confidence Confidence::*.
	 * @param Evidence[]  $evidence Evidence items.
	 * @param string|null $owner Owner slug/name when known.
	 */
	public function __construct( $type, $confidence, array $evidence = array(), $owner = null ) {
		$this->type       = in_array( $type, ClassificationType::all(), true ) ? $type : ClassificationType::UNKNOWN;
		$this->confidence = Confidence::is_valid( $confidence ) ? $confidence : Confidence::UNKNOWN;
		$this->evidence   = $evidence;
		$this->owner      = $owner;
	}

	/**
	 * @param string $table_name Table name.
	 * @return Classification
	 */
	public static function unknown( $table_name ) {
		return new self(
			ClassificationType::UNKNOWN,
			Confidence::UNKNOWN,
			array( new Evidence( Evidence::OTHER, sprintf( 'No sufficient ownership evidence for table %s.', $table_name ), 0 ) )
		);
	}

	/**
	 * @return array
	 */
	public function to_array() {
		return array(
			'type'       => $this->type,
			'confidence' => $this->confidence,
			'owner'      => $this->owner,
			'evidence'   => array_map(
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
	public function type() {
		return $this->type;
	}

	/**
	 * @return string
	 */
	public function confidence() {
		return $this->confidence;
	}

	/**
	 * @return Evidence[]
	 */
	public function evidence() {
		return $this->evidence;
	}

	/**
	 * @return string|null
	 */
	public function owner() {
		return $this->owner;
	}
}
