<?php
/**
 * Snapshot value object. Metadata only: schema, indexes, constraints,
 * classification. Never row content, option values, or audit payloads.
 *
 * @package WP_Heart
 */

namespace WPHeart\Domain;

/**
 * Snapshot value object.
 */
class Snapshot {
	/** @var array */
	private $data;

	/**
	 * @param array $data Snapshot document.
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
	 * @return string
	 */
	public function id() {
		return isset( $this->data['id'] ) ? (string) $this->data['id'] : '';
	}

	/**
	 * Validate an imported/decoded document before it is trusted.
	 *
	 * @param mixed $doc Candidate document.
	 * @return bool
	 */
	public static function is_valid_document( $doc ) {
		if ( ! is_array( $doc ) ) {
			return false;
		}
		if ( ! isset( $doc['version'], $doc['tables'] ) || ! is_array( $doc['tables'] ) ) {
			return false;
		}
		if ( count( $doc['tables'] ) > 10000 ) {
			return false;
		}
		foreach ( $doc['tables'] as $table ) {
			if ( ! is_array( $table ) || ! isset( $table['name'] ) || ! is_string( $table['name'] ) ) {
				return false;
			}
		}
		return true;
	}
}
