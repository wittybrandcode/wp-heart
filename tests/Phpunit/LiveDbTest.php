<?php
/**
 * Live read-only integration via PHPUnit (skips without WordPress).
 *
 * @package WP_Heart_Tests
 */

use PHPUnit\Framework\TestCase;

final class LiveDbTest extends TestCase {
	public static function wp_load_path() {
		$candidates = array(
			dirname( dirname( dirname( __DIR__ ) ) ) . '/wp-load.php',
			'C:\\xampp\\htdocs\\wordpress\\wp-load.php',
		);
		foreach ( $candidates as $path ) {
			if ( file_exists( $path ) ) {
				return $path;
			}
		}
		return null;
	}

	public function test_live_db_suite_passes() {
		if ( null === self::wp_load_path() ) {
			$this->markTestSkipped( 'WordPress not available.' );
		}
		$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( dirname( __DIR__ ) . '/live-db.php' );
		exec( $cmd . ' 2>&1', $out, $code );
		$this->assertSame( 0, $code, "tests/live-db.php failed:\n" . implode( "\n", (array) $out ) );
	}

	public function test_benchmarks_within_budget() {
		if ( null === self::wp_load_path() ) {
			$this->markTestSkipped( 'WordPress not available.' );
		}
		if ( '1' !== getenv( 'WP_HEART_RUN_PERF' ) ) {
			$this->markTestSkipped( 'Set WP_HEART_RUN_PERF=1 to run benchmarks.' );
		}
		$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( dirname( __DIR__ ) . '/benchmark.php' );
		exec( $cmd . ' 2>&1', $out, $code );
		$this->assertSame( 0, $code, "tests/benchmark.php over budget:\n" . implode( "\n", (array) $out ) );
	}
}
