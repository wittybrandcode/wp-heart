<?php
/**
 * Column metadata value object (observed reality, never invented).
 *
 * @package WP_Heart
 */

namespace WPHeart\Domain;

/**
 * Column metadata value object (observed reality, never invented).
 */
class ColumnInfo {
	/** @var array */
	private $data;

	/**
	 * @param array $data Column fields.
	 */
	public function __construct( array $data ) {
		$this->data = array_merge(
			array(
				'name'        => '',
				'position'    => 0,
				'data_type'   => null,
				'native_type' => null,
				'nullable'    => null,
				'default'     => null,
				'extra'       => null,
				'charset'     => null,
				'collation'   => null,
				'comment'     => null,
			),
			$data
		);
	}

	/**
	 * @return array
	 */
	public function to_array() {
		return $this->data;
	}

	/**
	 * @param string $key Field name.
	 * @return mixed
	 */
	public function get( $key ) {
		return isset( $this->data[ $key ] ) ? $this->data[ $key ] : null;
	}
}
