<?php
/**
 * Storage-engine mix diagnostic (NOTICE).
 *
 * @package WP_Heart
 */

namespace WPHeart\Diagnostics\Checks;

use WPHeart\Diagnostics\DiagnosticInterface;
use WPHeart\Domain\Evidence;
use WPHeart\Domain\HealthIssue;
use WPHeart\Support\Severity;

/**
 * Storage-engine mix diagnostic (NOTICE).
 */
class EngineMixDiagnostic implements DiagnosticInterface {
	/**
	 * @return string
	 */
	public function id() {
		return 'wpheart_engine_mix';
	}

	/**
	 * @return string
	 */
	public function name() {
		return __( 'Mixed storage engines', 'wp-heart' );
	}

	/**
	 * @return string
	 */
	public function description() {
		return __( 'Reports when tables use more than one storage engine.', 'wp-heart' );
	}

	/**
	 * @param array $context Context.
	 * @return HealthIssue[]
	 */
	public function check( array $context ) {
		$tables  = isset( $context['tables'] ) && is_array( $context['tables'] ) ? $context['tables'] : array();
		$engines = array();
		foreach ( $tables as $table ) {
			if ( ! $table instanceof \WPHeart\Domain\TableInfo ) {
				continue;
			}
			$meta   = $table->to_array();
			$engine = isset( $meta['metadata']['engine'] ) ? (string) $meta['metadata']['engine'] : '';
			if ( '' !== $engine ) {
				$engines[ $engine ] = true;
			}
		}
		if ( count( $engines ) < 2 ) {
			return array();
		}
		return array(
			new HealthIssue(
				array(
					'id'             => 'wpheart_engine_mix',
					'diagnostic'     => $this->id(),
					'severity'       => Severity::NOTICE,
					'affected'       => array_keys( $engines ),
					'evidence'       => array(
						new Evidence( Evidence::OTHER, sprintf( 'Distinct storage engines observed: %s.', implode( ', ', array_keys( $engines ) ) ), 80 ),
					),
					'explanation'    => __( 'Tables use more than one storage engine. This is common in legacy databases and is usually harmless, but transactional behavior differs per engine.', 'wp-heart' ),
					'recommendation' => __( 'No action required unless transactional guarantees matter for the mixed tables.', 'wp-heart' ),
				)
			),
		);
	}
}
