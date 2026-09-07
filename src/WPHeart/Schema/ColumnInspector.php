<?php
/**
 * Column inspector (WH-030). Normalizes SHOW FULL COLUMNS output.
 *
 * @package WP_Heart
 */

namespace WPHeart\Schema;

use WPHeart\Database\DatabaseAdapterInterface;
use WPHeart\Database\IdentifierValidator;
use WPHeart\Domain\ColumnInfo;

/**
 * Column inspector (WH-030). Normalizes SHOW FULL COLUMNS output.
 */
class ColumnInspector {
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
	 * @return ColumnInfo[]
	 */
	public function inspect( $table ) {
		if ( ! $this->identifiers->valid_name( $table ) ) {
			return array();
		}
		$rows    = $this->adapter->table_columns( $table );
		$columns = array();
		$pos     = 0;
		foreach ( $rows as $row ) {
			$row = (array) $row;
			if ( ! isset( $row['Field'] ) ) {
				continue;
			}
			++$pos;
			$columns[] = new ColumnInfo(
				array(
					'name'        => (string) $row['Field'],
					'position'    => $pos,
					'data_type'   => isset( $row['Type'] ) ? strtolower( (string) $row['Type'] ) : null,
					'native_type' => isset( $row['Type'] ) ? (string) $row['Type'] : null,
					'nullable'    => isset( $row['Null'] ) ? 'YES' === strtoupper( (string) $row['Null'] ) : null,
					'default'     => array_key_exists( 'Default', $row ) ? $row['Default'] : null,
					'extra'       => isset( $row['Extra'] ) ? (string) $row['Extra'] : null,
					'charset'     => isset( $row['Collation'] ) && null !== $row['Collation'] ? (string) $row['Collation'] : null,
					'collation'   => isset( $row['Collation'] ) ? $row['Collation'] : null,
					'comment'     => isset( $row['Comment'] ) ? (string) $row['Comment'] : null,
					'privileges'  => isset( $row['Privileges'] ) ? (string) $row['Privileges'] : null,
				)
			);
		}
		return $columns;
	}

	/**
	 * Text-searchable column names (bounded global search scope).
	 *
	 * @param ColumnInfo[] $columns Columns.
	 * @return ColumnInfo[]
	 */
	public static function searchable( array $columns ) {
		$out = array();
		foreach ( $columns as $column ) {
			$type = strtolower( (string) $column->get( 'data_type' ) );
			if ( preg_match( '/char|text|enum|set|json/', $type ) ) {
				$out[] = $column;
			}
		}
		return $out;
	}
}
