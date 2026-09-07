<?php
/**
 * PHPUnit bridge: the plain-PHP suites are the canonical tests;
 * these TestCases execute them as subprocesses so CI/PHPUnit runs
 * the exact same assertions. Live suites skip without WordPress.
 *
 * @package WP_Heart_Tests
 */

use PHPUnit\Framework\TestCase;

final class PlainPhpSuiteTest extends TestCase {
	public function test_plain_php_suite_passes() {
		$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( dirname( __DIR__ ) . '/run.php' );
		exec( $cmd . ' 2>&1', $out, $code );
		$this->assertSame( 0, $code, "tests/run.php failed:\n" . implode( "\n", (array) $out ) );
	}
}
