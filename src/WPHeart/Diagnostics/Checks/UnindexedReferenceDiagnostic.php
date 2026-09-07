<?php
/**
 * Unindexed-reference advisor (read-only suggestions, never actions).
 * Flags {entity}_id-style columns with no covering index. The observation
 * (no index) is exact; the reference interpretation is labeled inference.
 *
 * @package WP_Heart
 */

namespace WPHeart\Diagnostics\Checks;

use WPHeart\Diagnostics\DiagnosticInterface;
use WPHeart\Domain\Evidence;
use WPHeart\Domain\HealthIssue;
use WPHeart\Support\Severity;

/**
 * Unindexed-reference advisor.
 */
class UnindexedReferenceDiagnostic implements DiagnosticInterface {
	const MAX_ISSUES = 100;

	/**
	 * @return string
	 */
	public function id() {
		return 'wpheart_unindexed_reference';
	}

	/**
	 * @return string
	 */
	public function name() {
		return __( 'Reference-like columns without an index', 'wp-heart' );
	}

	/**
	 * @return string
	 */
	public function description() {
		return __( 'Suggests indexes for identifier-pattern columns that have none. Suggestions only — nothing is created.', 'wp-heart' );
	}

	/**
	 * @param array $context Context (needs columns + indexes maps).
	 * @return HealthIssue[]
	 */
	public function check( array $context ) {
		$columns_map = isset( $context['columns'] ) && is_array( $context['columns'] ) ? $context['columns'] : array();
		$indexes_map = isset( $context['indexes'] ) && is_array( $context['indexes'] ) ? $context['indexes'] : array();
		$issues      = array();

		foreach ( $columns_map as $table => $columns ) {
			if ( count( $issues ) >= self::MAX_ISSUES ) {
				break;
			}
			$covered = array();
			$pk_cols = array();
			foreach ( (array) ( isset( $indexes_map[ $table ] ) ? $indexes_map[ $table ] : array() ) as $index ) {
				if ( ! $index instanceof \WPHeart\Domain\IndexInfo ) {
					continue;
				}
				$data = $index->to_array();
				$cols = (array) $data['columns'];
				if ( ! empty( $cols ) ) {
					$covered[ strtolower( (string) $cols[0] ) ] = true;
				}
				// Primary-key members already have a structural role;
				// suggesting extra indexes on them is noise, not insight.
				if ( ! empty( $data['primary'] ) ) {
					foreach ( $cols as $pk_col ) {
						$pk_cols[ strtolower( (string) $pk_col ) ] = true;
					}
				}
			}
			foreach ( (array) $columns as $column ) {
				if ( ! $column instanceof \WPHeart\Domain\ColumnInfo ) {
					continue;
				}
				$name  = (string) $column->get( 'name' );
				$lower = strtolower( $name );
				if ( 'id' === $lower || ! preg_match( '/^[a-z0-9_]+_id$/', $lower ) ) {
					continue;
				}
				if ( isset( $covered[ $lower ] ) || isset( $pk_cols[ $lower ] ) ) {
					continue;
				}
				$issues[] = new HealthIssue(
					array(
						'id'             => 'wpheart_unindexed_reference:' . $table . ':' . $name,
						'diagnostic'     => $this->id(),
						'severity'       => Severity::WARNING,
						'affected'       => array( $table ),
						'evidence'       => array(
							new Evidence( Evidence::OTHER, sprintf( 'Column %s.%s has no covering index (verified from index metadata).', $table, $name ), 85 ),
							new Evidence( Evidence::COLUMN_PATTERN, sprintf( 'The %s naming pattern suggests a reference lookup column; this is inference, not a confirmed relationship.', $name ), 30 ),
						),
						'explanation'    => sprintf( 'Queries filtering or joining on %s.%s cannot use an index for that column. Add one only if query patterns justify it.', $table, $name ),
						'recommendation' => __( 'Confirm with production query patterns (EXPLAIN) before creating any index. WP-HEART never creates indexes.', 'wp-heart' ),
					)
				);
				if ( count( $issues ) >= self::MAX_ISSUES ) {
					break;
				}
			}
		}
		return $issues;
	}
}
