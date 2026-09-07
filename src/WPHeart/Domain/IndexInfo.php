<?php
/**
 * Index metadata value object.
 *
 * @package WP_Heart
 */

namespace WPHeart\Domain;

/**
 * Index metadata value object.
 */
class IndexInfo {
	/** @var array */
	private $data;

	/**
	 * @param array $data Index fields.
	 */
	public function __construct( array $data ) {
		$this->data = array_merge(
			array(
				'name'        => '',
				'unique'      => false,
				'primary'     => false,
				'columns'     => array(),
				'cardinality' => null,
				'type'        => null,
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
