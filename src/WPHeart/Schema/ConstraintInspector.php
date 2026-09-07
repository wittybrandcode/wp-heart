<?php
/**
 * Constraint inspector (WH-032). Missing FKs are facts, never failures.
 *
 * @package WP_Heart
 */

namespace WPHeart\Schema;

use WPHeart\Database\DatabaseAdapterInterface;
use WPHeart\Database\IdentifierValidator;
use WPHeart\Domain\ConstraintInfo;

/**
 * Constraint inspector (WH-032). Missing FKs are facts, never failures.
 */
class ConstraintInspector {
	/** @var DatabaseAdapterInterface */
	private $adapter;
	/** @var IdentifierValidator */
	private $identifiers;

	/**
	 * @param DatabaseAdapterInterface $adapter Adapter.
	 * @param IdentifierValidator      $identifiers Validator.
	 */
	public function __construct( DatabaseAdapterInterface $adapter, IdentifierValidator $identifiers ) {
		$this->adapter     = $adapter;
		$this->identifiers = $identifiers;
	}

	/**
	 * @param string $table Validated table name.
	 * @return array {constraints: ConstraintInfo[], availability: AVAILABLE|UNAVAILABLE|NONE_DETECTED}
	 */
	public function inspect( $table ) {
		if ( ! $this->identifiers->valid_name( $table ) ) {
			return array(
				'constraints'  => array(),
				'availability' => 'UNAVAILABLE',
			);
		}
		$rows = $this->adapter->foreign_keys( $table );

		$grouped = array();
		foreach ( $rows as $row ) {
			$row = (array) $row;
			if ( ! isset( $row['name'] ) ) {
				continue;
			}
			$name = (string) $row['name'];
			if ( ! isset( $grouped[ $name ] ) ) {
				$grouped[ $name ] = array(
					'name'               => $name,
					'type'               => 'FOREIGN KEY',
					'table'              => $table,
					'columns'            => array(),
					'referenced_table'   => isset( $row['ref_table'] ) ? $row['ref_table'] : null,
					'referenced_columns' => array(),
					'update_rule'        => isset( $row['update_rule'] ) ? $row['update_rule'] : null,
					'delete_rule'        => isset( $row['delete_rule'] ) ? $row['delete_rule'] : null,
				);
			}
			if ( isset( $row['col'] ) ) {
				$grouped[ $name ]['columns'][] = (string) $row['col'];
			}
			if ( isset( $row['ref_col'] ) ) {
				$grouped[ $name ]['referenced_columns'][] = (string) $row['ref_col'];
			}
		}

		$constraints = array();
		foreach ( $grouped as $data ) {
			$constraints[] = new ConstraintInfo( $data );
		}

		return array(
			'constraints'  => $constraints,
			'availability' => count( $constraints ) > 0 ? 'AVAILABLE' : 'NONE_DETECTED',
		);
	}
}
