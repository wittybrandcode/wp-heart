<?php
/**
 * WordPress options intelligence: autoload footprint and transient waste.
 * Read-only aggregates; never loads option values into PHP (WH-240).
 *
 * @package WP_Heart
 */

namespace WPHeart\WordPress;

use WPHeart\Database\DatabaseAdapterInterface;
use WPHeart\Support\Accuracy;

/**
 * WordPress options intelligence.
 */
class OptionsInspector {
	/** @var DatabaseAdapterInterface */
	private $adapter;

	/**
	 * @param DatabaseAdapterInterface $adapter Adapter.
	 */
	public function __construct( DatabaseAdapterInterface $adapter ) {
		$this->adapter = $adapter;
	}

	/**
	 * Autoloaded-options footprint (exact aggregate, both autoload schemes).
	 *
	 * @param int $top_n How many of the largest options to include.
	 * @return array {bytes, count, top: [{name, bytes}], accuracy}
	 */
	public function autoload_summary( $top_n = 10 ) {
		$totals = $this->adapter->options_autoload_totals();
		$top    = $this->adapter->top_autoload_options( max( 1, min( 50, (int) $top_n ) ) );
		return array(
			'bytes'    => isset( $totals['bytes'] ) ? (int) $totals['bytes'] : 0,
			'count'    => isset( $totals['count'] ) ? (int) $totals['count'] : 0,
			'top'      => $top,
			'accuracy' => Accuracy::EXACT,
		);
	}

	/**
	 * @return array {values, expired}
	 */
	public function transient_stats() {
		$stats = $this->adapter->transient_stats();
		return array(
			'values'  => isset( $stats['values'] ) ? (int) $stats['values'] : 0,
			'expired' => isset( $stats['expired'] ) ? (int) $stats['expired'] : 0,
		);
	}
}
