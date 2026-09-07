<?php
/**
 * Health issue value object (diagnostic output contract).
 *
 * @package WP_Heart
 */

namespace WPHeart\Domain;

use WPHeart\Support\Severity;

/**
 * Health issue value object (diagnostic output contract).
 */
class HealthIssue {
	/** @var array */
	private $data;

	/**
	 * @param array $data Issue fields: id, diagnostic, severity, affected, evidence, explanation, recommendation.
	 */
	public function __construct( array $data ) {
		$defaults = array(
			'id'             => '',
			'diagnostic'     => '',
			'severity'       => Severity::INFO,
			'affected'       => array(),
			'evidence'       => array(),
			'explanation'    => '',
			'recommendation' => '',
		);
		$data     = array_merge( $defaults, $data );
		if ( ! in_array( $data['severity'], Severity::all(), true ) ) {
			$data['severity'] = Severity::INFO;
		}
		$this->data = $data;
	}

	/**
	 * @return array
	 */
	public function to_array() {
		$data = $this->data;
		if ( isset( $data['evidence'] ) && is_array( $data['evidence'] ) ) {
			$data['evidence'] = array_map(
				static function ( $e ) {
					return $e instanceof Evidence ? $e->to_array() : $e;
				},
				$data['evidence']
			);
		}
		return $data;
	}

	/**
	 * @return string
	 */
	public function severity() {
		return $this->data['severity'];
	}
}
