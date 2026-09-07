<?php
/**
 * Confidence scoring from aggregated evidence (WH-052).
 *
 * @package WP_Heart
 */

namespace WPHeart\Intelligence;

use WPHeart\Domain\Evidence;
use WPHeart\Support\Confidence;

/**
 * Confidence scoring from aggregated evidence (WH-052).
 */
class ConfidenceScorer {
	/**
	 * Normalize evidence into a confidence level.
	 *
	 * @param Evidence[] $evidence Evidence items.
	 * @return string Confidence::*.
	 */
	public function score( array $evidence ) {
		if ( empty( $evidence ) ) {
			return Confidence::UNKNOWN;
		}
		$max = 0;
		foreach ( $evidence as $item ) {
			if ( $item instanceof Evidence ) {
				$max = max( $max, $item->strength() );
			}
		}
		if ( $max >= 75 ) {
			return Confidence::HIGH;
		}
		if ( $max >= 50 ) {
			return Confidence::MEDIUM;
		}
		if ( $max >= 20 ) {
			return Confidence::LOW;
		}
		return Confidence::UNKNOWN;
	}
}
