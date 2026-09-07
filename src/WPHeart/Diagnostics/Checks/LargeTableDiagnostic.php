<?php
/**
 * Large-table notice (NOTICE ≥100k, WARNING ≥1M estimated rows).
 *
 * @package WP_Heart
 */

namespace WPHeart\Diagnostics\Checks;

use WPHeart\Diagnostics\DiagnosticInterface;
use WPHeart\Domain\Evidence;
use WPHeart\Domain\HealthIssue;
use WPHeart\Support\Severity;

/**
 * Large-table notice (NOTICE ≥100k, WARNING ≥1M estimated rows).
 */
class LargeTableDiagnostic implements DiagnosticInterface {
	const NOTICE_THRESHOLD  = 100000;
	const WARNING_THRESHOLD = 1000000;

	/**
	 * @return string
	 */
	public function id() {
		return 'wpheart_large_tables';
	}

	/**
	 * @return string
	 */
	public function name() {
		return __( 'Large tables', 'wp-heart' );
	}

	/**
	 * @return string
	 */
	public function description() {
		return __( 'Flags tables with large estimated row counts so they get bounded handling.', 'wp-heart' );
	}

	/**
	 * @param array $context Context.
	 * @return HealthIssue[]
	 */
	public function check( array $context ) {
		$tables = isset( $context['tables'] ) && is_array( $context['tables'] ) ? $context['tables'] : array();
		$issues = array();
		foreach ( $tables as $table ) {
			if ( ! $table instanceof \WPHeart\Domain\TableInfo ) {
				continue;
			}
			$meta = $table->to_array();
			$rows = isset( $meta['metadata']['estimated_rows'] ) ? $meta['metadata']['estimated_rows'] : null;
			if ( null === $rows ) {
				continue;
			}
			$rows = (int) $rows;
			if ( $rows >= self::WARNING_THRESHOLD ) {
				$severity = Severity::WARNING;
			} elseif ( $rows >= self::NOTICE_THRESHOLD ) {
				$severity = Severity::NOTICE;
			} else {
				continue;
			}
			$issues[] = new HealthIssue(
				array(
					'id'             => 'wpheart_large_tables:' . $table->name(),
					'diagnostic'     => $this->id(),
					'severity'       => $severity,
					'affected'       => array( $table->name() ),
					'evidence'       => array(
						new Evidence( Evidence::OTHER, sprintf( 'Estimated %d rows for %s (engine estimate, not an exact count).', $rows, $table->name() ), 70 ),
					),
					'explanation'    => sprintf( 'Table %s is large. WP-HEART always pages, bounds and caches around it.', $table->name() ),
					'recommendation' => __( 'Use paged browsing and scoped search for this table.', 'wp-heart' ),
				)
			);
		}
		return $issues;
	}
}
