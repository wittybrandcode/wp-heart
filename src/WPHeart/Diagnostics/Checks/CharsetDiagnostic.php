<?php
/**
 * Collation/charset mix diagnostic (NOTICE).
 *
 * @package WP_Heart
 */

namespace WPHeart\Diagnostics\Checks;

use WPHeart\Diagnostics\DiagnosticInterface;
use WPHeart\Domain\Evidence;
use WPHeart\Domain\HealthIssue;
use WPHeart\Support\Severity;

/**
 * Collation/charset mix diagnostic (NOTICE).
 */
class CharsetDiagnostic implements DiagnosticInterface {
	/**
	 * @return string
	 */
	public function id() {
		return 'wpheart_charset_mix';
	}

	/**
	 * @return string
	 */
	public function name() {
		return __( 'Mixed table collations', 'wp-heart' );
	}

	/**
	 * @return string
	 */
	public function description() {
		return __( 'Reports when tables use differing collations.', 'wp-heart' );
	}

	/**
	 * @param array $context Context.
	 * @return HealthIssue[]
	 */
	public function check( array $context ) {
		$tables = isset( $context['tables'] ) && is_array( $context['tables'] ) ? $context['tables'] : array();
		$counts = array();
		$by_col = array();
		foreach ( $tables as $table ) {
			if ( ! $table instanceof \WPHeart\Domain\TableInfo ) {
				continue;
			}
			$meta = $table->to_array();
			$col  = isset( $meta['metadata']['collation'] ) ? (string) $meta['metadata']['collation'] : '';
			if ( '' === $col ) {
				continue;
			}
			if ( ! isset( $counts[ $col ] ) ) {
				$counts[ $col ] = 0;
				$by_col[ $col ] = array();
			}
			++$counts[ $col ];
			if ( count( $by_col[ $col ] ) < 5 ) {
				$by_col[ $col ][] = $table->name();
			}
		}
		if ( count( $counts ) < 2 ) {
			return array();
		}
		return array(
			new HealthIssue(
				array(
					'id'             => 'wpheart_charset_mix',
					'diagnostic'     => $this->id(),
					'severity'       => Severity::NOTICE,
					'affected'       => array_keys( $counts ),
					'evidence'       => array(
						new Evidence( Evidence::OTHER, sprintf( 'Distinct table collations observed: %s.', implode( ', ', array_keys( $counts ) ) ), 75 ),
					),
					'explanation'    => __( 'Tables use differing collations. Mixed collations can cause join comparison issues and surprising sort orders.', 'wp-heart' ),
					'recommendation' => __( 'Align collations only after verifying application impact; conversion rewrites tables.', 'wp-heart' ),
				)
			),
		);
	}
}
