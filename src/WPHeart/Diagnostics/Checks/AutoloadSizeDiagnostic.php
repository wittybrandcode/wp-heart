<?php
/**
 * Autoloaded-options footprint diagnostic. Autoloaded data loads on every
 * request, so its size is a first-order performance fact, not trivia.
 *
 * @package WP_Heart
 */

namespace WPHeart\Diagnostics\Checks;

use WPHeart\Diagnostics\DiagnosticInterface;
use WPHeart\Domain\Evidence;
use WPHeart\Domain\HealthIssue;
use WPHeart\Support\Severity;

/**
 * Autoloaded-options footprint diagnostic.
 */
class AutoloadSizeDiagnostic implements DiagnosticInterface {
	const NOTICE_BYTES  = 524288; // 512 KB.
	const WARNING_BYTES = 1048576; // 1 MB.

	/**
	 * @return string
	 */
	public function id() {
		return 'wpheart_autoload_size';
	}

	/**
	 * @return string
	 */
	public function name() {
		return __( 'Autoloaded options footprint', 'wp-heart' );
	}

	/**
	 * @return string
	 */
	public function description() {
		return __( 'Measures the total size of autoloaded options, which load on every WordPress request.', 'wp-heart' );
	}

	/**
	 * @param array $context Context (needs autoload summary).
	 * @return HealthIssue[]
	 */
	public function check( array $context ) {
		if ( ! isset( $context['autoload'] ) || ! is_array( $context['autoload'] ) ) {
			return array();
		}
		$bytes = isset( $context['autoload']['bytes'] ) ? (int) $context['autoload']['bytes'] : 0;
		$count = isset( $context['autoload']['count'] ) ? (int) $context['autoload']['count'] : 0;
		if ( $bytes >= self::WARNING_BYTES ) {
			$severity = Severity::WARNING;
		} elseif ( $bytes >= self::NOTICE_BYTES ) {
			$severity = Severity::NOTICE;
		} else {
			return array();
		}
		return array(
			new HealthIssue(
				array(
					'id'             => 'wpheart_autoload_size',
					'diagnostic'     => $this->id(),
					'severity'       => $severity,
					'affected'       => array( 'wp_options' ),
					'evidence'       => array(
						new Evidence( Evidence::OTHER, sprintf( 'Exactly %1$d bytes across %2$d autoloaded options (aggregate query, not an estimate).', $bytes, $count ), 90 ),
					),
					'explanation'    => sprintf( 'Autoloaded options total %s and load on every request. See the largest contributors before changing anything.', $this->format_bytes( $bytes ) ),
					'recommendation' => __( 'Move rarely-used flags off autoload and delete stale plugin leftovers; verify with a staging measurement.', 'wp-heart' ),
				)
			),
		);
	}

	/**
	 * @param int $bytes Bytes.
	 * @return string
	 */
	private function format_bytes( $bytes ) {
		if ( $bytes >= 1048576 ) {
			return sprintf( '%.1f MB', $bytes / 1048576 );
		}
		return sprintf( '%d KB', (int) round( $bytes / 1024 ) );
	}
}
