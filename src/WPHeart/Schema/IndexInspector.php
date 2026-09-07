<?php
/**
 * Index inspector (WH-031). Groups SHOW INDEX rows per index.
 *
 * @package WP_Heart
 */

namespace WPHeart\Schema;

use WPHeart\Database\DatabaseAdapterInterface;
use WPHeart\Database\IdentifierValidator;
use WPHeart\Domain\IndexInfo;

/**
 * Index inspector (WH-031). Groups SHOW INDEX rows per index.
 */
class IndexInspector {
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
	 * @return IndexInfo[]
	 */
	public function inspect( $table ) {
		if ( ! $this->identifiers->valid_name( $table ) ) {
			return array();
		}
		$rows    = $this->adapter->table_indexes( $table );
		$grouped = array();
		foreach ( $rows as $row ) {
			$row = (array) $row;
			if ( ! isset( $row['Key_name'] ) ) {
				continue;
			}
			$name = (string) $row['Key_name'];
			if ( ! isset( $grouped[ $name ] ) ) {
				$grouped[ $name ] = array(
					'name'        => $name,
					'unique'      => isset( $row['Non_unique'] ) ? '0' === (string) $row['Non_unique'] : false,
					'primary'     => 'PRIMARY' === $name,
					'columns'     => array(),
					'cardinality' => null,
					'type'        => isset( $row['Index_type'] ) ? (string) $row['Index_type'] : null,
				);
			}
			if ( isset( $row['Column_name'] ) ) {
				$grouped[ $name ]['columns'][] = (string) $row['Column_name'];
			}
			if ( isset( $row['Cardinality'] ) && null !== $row['Cardinality'] ) {
				$grouped[ $name ]['cardinality'] = (int) $row['Cardinality'];
			}
		}

		$indexes = array();
		foreach ( $grouped as $data ) {
			if ( $data['primary'] ) {
				$data['unique'] = true;
			}
			$indexes[] = new IndexInfo( $data );
		}
		return $indexes;
	}
}
