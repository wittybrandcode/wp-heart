<?php
/**
 * Database summary value object.
 *
 * @package WP_Heart
 */

namespace WPHeart\Domain;

/**
 * Database summary value object.
 */
class DatabaseSummary {
	/** @var array */
	private $data;

	/**
	 * @param array $data Summary fields.
	 */
	public function __construct( array $data ) {
		$this->data = $data;
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
