<?php
/**
 * Expired-transient waste diagnostic. Expired rows are normal churn;
 * only unusual accumulation is reported, as a NOTICE.
 *
 * @package WP_Heart
 */

namespace WPHeart\Diagnostics\Checks;

use WPHeart\Diagnostics\DiagnosticInterface;
use WPHeart\Domain\Evidence;
use WPHeart\Domain\HealthIssue;
use WPHeart\Support\Severity;

/**
 * Expired-transient waste diagnostic.
 */
class ExpiredTransientsDiagnostic implements DiagnosticInterface {
	const NOTICE_EXPIRED = 100;

	/**
	 * @return string
	 */
	public function id() {
		return 'wpheart_expired_transients';
	}

	/**
	 * @return string
	 */
	public function name() {
		return __( 'Expired transient waste', 'wp-heart' );
	}

	/**
	 * @return string
	 */
	public function description() {
		return __( 'Counts expired transient rows that have accumulated instead of being cleaned.', 'wp-heart' );
	}

	/**
	 * @param array $context Context (needs transient stats).
	 * @return HealthIssue[]
	 */
	public function check( array $context ) {
		if ( ! isset( $context['transients'] ) || ! is_array( $context['transients'] ) ) {
			return array();
		}
		$expired = isset( $context['transients']['expired'] ) ? (int) $context['transients']['expired'] : 0;
		$values  = isset( $context['transients']['values'] ) ? (int) $context['transients']['values'] : 0;
		if ( $expired < self::NOTICE_EXPIRED ) {
			return array();
		}
		return array(
			new HealthIssue(
				array(
					'id'             => 'wpheart_expired_transients',
					'diagnostic'     => $this->id(),
					'severity'       => Severity::NOTICE,
					'affected'       => array( 'wp_options' ),
					'evidence'       => array(
						new Evidence( Evidence::OTHER, sprintf( 'Exactly %1$d expired transient rows out of %2$d transient values (aggregate counts).', $expired, $values ), 90 ),
					),
					'explanation'    => __( 'Expired transients normally clean themselves on access; a large backlog suggests caching patterns worth reviewing.', 'wp-heart' ),
					'recommendation' => __( 'Review transient lifetimes and cleanup routines; do not hand-delete rows on production without a backup.', 'wp-heart' ),
				)
			),
		);
	}
}
