<?php
/**
 * Orphan-candidate reporter (INFO). Restates: candidates are informational.
 *
 * @package WP_Heart
 */

namespace WPHeart\Diagnostics\Checks;

use WPHeart\Diagnostics\DiagnosticInterface;
use WPHeart\Domain\Evidence;
use WPHeart\Domain\HealthIssue;
use WPHeart\Support\ClassificationType;
use WPHeart\Support\Severity;

/**
 * Orphan-candidate reporter (INFO). Restates: candidates are informational.
 */
class OrphanCandidateDiagnostic implements DiagnosticInterface {
	/**
	 * @return string
	 */
	public function id() {
		return 'wpheart_orphans';
	}

	/**
	 * @return string
	 */
	public function name() {
		return __( 'Orphan candidate tables', 'wp-heart' );
	}

	/**
	 * @return string
	 */
	public function description() {
		return __( 'Lists tables whose owner may no longer be present. Informational only.', 'wp-heart' );
	}

	/**
	 * @param array $context Context.
	 * @return HealthIssue[]
	 */
	public function check( array $context ) {
		$map   = isset( $context['classifications'] ) && is_array( $context['classifications'] ) ? $context['classifications'] : array();
		$found = array();
		foreach ( $map as $table => $classification ) {
			if ( $classification instanceof \WPHeart\Domain\Classification && ClassificationType::ORPHAN_CANDIDATE === $classification->type() ) {
				$found[] = $table;
			}
		}
		if ( empty( $found ) ) {
			return array();
		}
		return array(
			new HealthIssue(
				array(
					'id'             => 'wpheart_orphans',
					'diagnostic'     => $this->id(),
					'severity'       => Severity::INFO,
					'affected'       => $found,
					'evidence'       => array(
						new Evidence( Evidence::HISTORICAL_SIGNATURE, sprintf( '%d table(s) look like leftovers of absent components.', count( $found ) ), 45 ),
					),
					'explanation'    => __( 'These tables may belong to plugins or themes that are no longer installed. They are reported for investigation, not removal.', 'wp-heart' ),
					'recommendation' => __( 'Verify ownership from backups or staging before any action. WP-HEART never deletes tables.', 'wp-heart' ),
				)
			),
		);
	}
}
