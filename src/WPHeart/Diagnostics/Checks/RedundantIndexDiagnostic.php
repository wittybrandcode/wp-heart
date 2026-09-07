<?php
/**
 * Redundant-index diagnostic (WARNING on duplicate column sets).
 *
 * @package WP_Heart
 */

namespace WPHeart\Diagnostics\Checks;

use WPHeart\Diagnostics\DiagnosticInterface;
use WPHeart\Domain\Evidence;
use WPHeart\Domain\HealthIssue;
use WPHeart\Support\Severity;

/**
 * Redundant-index diagnostic (WARNING on duplicate column sets).
 */
class RedundantIndexDiagnostic implements DiagnosticInterface {
	/**
	 * @return string
	 */
	public function id() {
		return 'wpheart_redundant_indexes';
	}

	/**
	 * @return string
	 */
	public function name() {
		return __( 'Redundant indexes', 'wp-heart' );
	}

	/**
	 * @return string
	 */
	public function description() {
		return __( 'Detects indexes covering identical column sets within one table.', 'wp-heart' );
	}

	/**
	 * @param array $context Context (needs indexes map).
	 * @return HealthIssue[]
	 */
	public function check( array $context ) {
		$map    = isset( $context['indexes'] ) && is_array( $context['indexes'] ) ? $context['indexes'] : array();
		$issues = array();
		foreach ( $map as $table => $indexes ) {
			$seen = array();
			foreach ( (array) $indexes as $index ) {
				if ( ! $index instanceof \WPHeart\Domain\IndexInfo ) {
					continue;
				}
				$data = $index->to_array();
				if ( ! empty( $data['primary'] ) ) {
					continue;
				}
				$key = implode( ',', (array) $data['columns'] );
				if ( '' === $key ) {
					continue;
				}
				if ( isset( $seen[ $key ] ) ) {
					$issues[] = new HealthIssue(
						array(
							'id'             => 'wpheart_redundant_indexes:' . $table . ':' . $data['name'],
							'diagnostic'     => $this->id(),
							'severity'       => Severity::WARNING,
							'affected'       => array( $table ),
							'evidence'       => array(
								new Evidence( Evidence::OTHER, sprintf( 'Indexes %s and %s cover the same columns (%s).', $seen[ $key ], $data['name'], $key ), 65 ),
							),
							'explanation'    => sprintf( 'Table %s has redundant indexes on the same columns, adding write overhead.', $table ),
							'recommendation' => __( 'Review index usage with production query patterns before dropping anything.', 'wp-heart' ),
						)
					);
				} else {
					$seen[ $key ] = $data['name'];
				}
			}
		}
		return $issues;
	}
}
