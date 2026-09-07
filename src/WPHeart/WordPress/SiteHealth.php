<?php
/**
 * WordPress Site Health integration. Surfaces WP-HEART findings where every
 * developer already looks. Only cheap aggregates run here — never discovery.
 *
 * @package WP_Heart
 */

namespace WPHeart\WordPress;

/**
 * WordPress Site Health integration.
 */
class SiteHealth {
	/** @var OptionsInspector */
	private $options;

	/**
	 * @param OptionsInspector $options Options inspector.
	 */
	public function __construct( OptionsInspector $options ) {
		$this->options = $options;
	}

	/**
	 * Append WP-HEART tests to the Site Health suite.
	 *
	 * @param array $tests Existing tests.
	 * @return array
	 */
	public function tests( array $tests ) {
		if ( ! isset( $tests['direct'] ) || ! is_array( $tests['direct'] ) ) {
			$tests['direct'] = array();
		}
		$tests['direct']['wp_heart_readonly']   = array(
			'label' => __( 'WP-HEART read-only mode', 'wp-heart' ),
			'test'  => array( $this, 'readonly_test' ),
		);
		$tests['direct']['wp_heart_autoload']   = array(
			'label' => __( 'WP-HEART autoload footprint', 'wp-heart' ),
			'test'  => array( $this, 'autoload_test' ),
		);
		$tests['direct']['wp_heart_transients'] = array(
			'label' => __( 'WP-HEART expired transients', 'wp-heart' ),
			'test'  => array( $this, 'transients_test' ),
		);
		return $tests;
	}

	/**
	 * @return array Site Health result.
	 */
	public function readonly_test() {
		return array(
			'label'       => __( 'WP-HEART observes the database without modifying it', 'wp-heart' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Database', 'wp-heart' ),
				'color' => 'blue',
			),
			'description' => __( 'The observatory enforces a server-side read-only policy: no INSERT, UPDATE, DELETE, or schema changes exist in this release.', 'wp-heart' ),
			'actions'     => '',
			'test'        => 'wp_heart_readonly',
		);
	}

	/**
	 * @return array Site Health result.
	 */
	public function autoload_test() {
		$summary = $this->options->autoload_summary( 1 );
		$bytes   = (int) $summary['bytes'];
		$human   = $this->format_bytes( $bytes );
		if ( $bytes >= 1048576 ) {
			$status = 'critical';
			/* translators: %s: human-readable byte size. */
			$label = sprintf( __( 'Autoloaded options are large (%s)', 'wp-heart' ), $human );
		} elseif ( $bytes >= 524288 ) {
			$status = 'recommended';
			/* translators: %s: human-readable byte size. */
			$label = sprintf( __( 'Autoloaded options are growing (%s)', 'wp-heart' ), $human );
		} else {
			$status = 'good';
			/* translators: %s: human-readable byte size. */
			$label = sprintf( __( 'Autoloaded options footprint is healthy (%s)', 'wp-heart' ), $human );
		}
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'Database', 'wp-heart' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				/* translators: 1: byte count, 2: option count. */
				__( 'Exactly %1$d bytes across %2$d autoloaded options load on every request. Review the largest contributors in WP-HEART diagnostics.', 'wp-heart' ),
				$bytes,
				(int) $summary['count']
			),
			'actions'     => '',
			'test'        => 'wp_heart_autoload',
		);
	}

	/**
	 * @return array Site Health result.
	 */
	public function transients_test() {
		$stats   = $this->options->transient_stats();
		$expired = (int) $stats['expired'];
		if ( $expired >= 100 ) {
			$status = 'recommended';
			/* translators: %d: expired transient row count. */
			$label = sprintf( __( '%d expired transient rows have accumulated', 'wp-heart' ), $expired );
		} else {
			$status = 'good';
			/* translators: %d: expired transient row count. */
			$label = sprintf( __( 'Transient waste is low (%d expired rows)', 'wp-heart' ), $expired );
		}
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'Database', 'wp-heart' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				/* translators: 1: transient value count, 2: expired row count. */
				__( '%1$d transient values stored, %2$d expired. Expired rows clean themselves on access; a large backlog deserves review.', 'wp-heart' ),
				(int) $stats['values'],
				$expired
			),
			'actions'     => '',
			'test'        => 'wp_heart_transients',
		);
	}

	/**
	 * Map a byte count to a Site Health status (pure, unit-testable).
	 *
	 * @param int $bytes Bytes.
	 * @return string good|recommended|critical.
	 */
	public static function status_for_autoload( $bytes ) {
		$bytes = (int) $bytes;
		if ( $bytes >= 1048576 ) {
			return 'critical';
		}
		if ( $bytes >= 524288 ) {
			return 'recommended';
		}
		return 'good';
	}

	/**
	 * @param int $bytes Bytes.
	 * @return string
	 */
	private function format_bytes( $bytes ) {
		if ( $bytes >= 1048576 ) {
			return sprintf( '%.1f MB', $bytes / 1048576 );
		}
		return sprintf( '%d KB', (int) round( $bytes / 1024 ) );
	}
}
