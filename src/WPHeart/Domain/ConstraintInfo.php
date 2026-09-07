<?php
/**
 * Physical constraint metadata value object.
 *
 * @package WP_Heart
 */

namespace WPHeart\Domain;

/**
 * Physical constraint metadata value object.
 */
class ConstraintInfo {
	/** @var array */
	private $data;

	/**
	 * @param array $data Constraint fields.
	 */
	public function __construct( array $data ) {
		$this->data = array_merge(
			array(
				'name'               => '',
				'type'               => 'FOREIGN KEY',
				'table'              => '',
				'columns'            => array(),
				'referenced_table'   => null,
				'referenced_columns' => array(),
				'update_rule'        => null,
				'delete_rule'        => null,
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
