<?php
/**
 * Missing-primary-key diagnostic. Informational for WordPress reality:
 * many legitimate tables (link tables, logs) have no PK.
 *
 * @package WP_Heart
 */

namespace WPHeart\Diagnostics\Checks;

use WPHeart\Diagnostics\DiagnosticInterface;
use WPHeart\Domain\Evidence;
use WPHeart\Domain\HealthIssue;
use WPHeart\Support\Severity;

/**
 * Missing-primary-key diagnostic. Informational for WordPress reality:.
 */
class MissingPrimaryKeyDiagnostic implements DiagnosticInterface {
	/**
	 * @return string
	 */
	public function id() {
		return 'wpheart_no_pk';
	}

	/**
	 * @return string
	 */
	public function name() {
		return __( 'Tables without a primary key', 'wp-heart' );
	}

	/**
	 * @return string
	 */
	public function description() {
		return __( 'Lists tables that have no primary key. This is an observation, not a defect: many legitimate tables have none.', 'wp-heart' );
	}

	/**
	 * @param array $context Context.
	 * @return HealthIssue[]
	 */
	public function check( array $context ) {
		$issues = array();
		$tables = isset( $context['tables'] ) && is_array( $context['tables'] ) ? $context['tables'] : array();
		foreach ( $tables as $table ) {
			if ( ! $table instanceof \WPHeart\Domain\TableInfo ) {
				continue;
			}
			$meta = $table->to_array();
			if ( isset( $meta['metadata']['comment'] ) && 'VIEW' === strtoupper( (string) $meta['metadata']['comment'] ) ) {
				continue;
			}
			if ( $table->has_primary_key() ) {
				continue;
			}
			$issues[] = new HealthIssue(
				array(
					'id'             => 'wpheart_no_pk:' . $table->name(),
					'diagnostic'     => $this->id(),
					'severity'       => Severity::WARNING,
					'affected'       => array( $table->name() ),
					'evidence'       => array(
						new Evidence( Evidence::DATABASE_CONSTRAINT, sprintf( 'No PRIMARY index reported for %s by SHOW INDEX.', $table->name() ), 85 ),
					),
					'explanation'    => sprintf( 'Table %s has no primary key. Row-level addressing and some replication topologies rely on one, but many legitimate tables intentionally omit it.', $table->name() ),
					'recommendation' => __( 'Inspect the table purpose before taking action. Do not add keys blindly.', 'wp-heart' ),
				)
			);
		}
		return $issues;
	}
}
