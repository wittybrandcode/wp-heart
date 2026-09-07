<?php
/**
 * Largest-autoloaded-options reporter (INFO). Attribution, not accusation:
 * a large option is worth inspecting, not automatically wrong.
 *
 * @package WP_Heart
 */

namespace WPHeart\Diagnostics\Checks;

use WPHeart\Diagnostics\DiagnosticInterface;
use WPHeart\Domain\Evidence;
use WPHeart\Domain\HealthIssue;
use WPHeart\Support\Severity;

/**
 * Largest-autoloaded-options reporter.
 */
class TopAutoloadOptionsDiagnostic implements DiagnosticInterface {
	/**
	 * @return string
	 */
	public function id() {
		return 'wpheart_top_autoload';
	}

	/**
	 * @return string
	 */
	public function name() {
		return __( 'Largest autoloaded options', 'wp-heart' );
	}

	/**
	 * @return string
	 */
	public function description() {
		return __( 'Names the largest autoloaded options so developers know what makes up the footprint.', 'wp-heart' );
	}

	/**
	 * @param array $context Context (needs autoload summary).
	 * @return HealthIssue[]
	 */
	public function check( array $context ) {
		if ( ! isset( $context['autoload']['top'] ) || ! is_array( $context['autoload']['top'] ) ) {
			return array();
		}
		$top = array_slice( $context['autoload']['top'], 0, 10 );
		if ( empty( $top ) ) {
			return array();
		}
		$affected = array();
		$lines    = array();
		foreach ( $top as $row ) {
			$row  = (array) $row;
			$name = isset( $row['name'] ) ? (string) $row['name'] : '';
			if ( '' === $name ) {
				continue;
			}
			$affected[] = 'option:' . $name;
			$lines[]    = sprintf( '%s (%d bytes)', $name, isset( $row['bytes'] ) ? (int) $row['bytes'] : 0 );
		}
		if ( empty( $affected ) ) {
			return array();
		}
		return array(
			new HealthIssue(
				array(
					'id'             => 'wpheart_top_autoload',
					'diagnostic'     => $this->id(),
					'severity'       => Severity::INFO,
					'affected'       => $affected,
					'evidence'       => array(
						new Evidence( Evidence::OTHER, 'Largest autoloaded options: ' . implode( ', ', $lines ), 85 ),
					),
					'explanation'    => __( 'These options contribute most to the autoload footprint. Large does not mean wrong — inspect before acting.', 'wp-heart' ),
					'recommendation' => __( 'Confirm each large option is still needed on every request; consider non-autoloaded storage for rarely-used data.', 'wp-heart' ),
				)
			),
		);
	}
}
