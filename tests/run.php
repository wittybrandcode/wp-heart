<?php
/**
 * Plain-PHP test runner (Decision D-002). No phpunit binary required:
 *   php tests/run.php
 *
 * Exit code 0 when every check passes, 1 otherwise.
 *
 * @package WP_Heart_Tests
 */

error_reporting( E_ALL );

require_once __DIR__ . '/support/stubs.php';
require_once WP_HEART_DIR . 'autoload.php';
require_once __DIR__ . '/support/Fakes.php';
require_once __DIR__ . '/support/container.php';

$GLOBALS['wh_checks'] = array( 'pass' => 0, 'fail' => 0, 'failures' => array(), 'group' => '' );

if ( ! function_exists( 'wh_group' ) ) {
	function wh_group( $name ) {
		$GLOBALS['wh_checks']['group'] = $name;
		echo '# ' . $name . "\n";
	}
}
if ( ! function_exists( 'wh_check' ) ) {
	function wh_check( $cond, $name ) {
		if ( $cond ) {
			++$GLOBALS['wh_checks']['pass'];
		} else {
			++$GLOBALS['wh_checks']['fail'];
			$label = $GLOBALS['wh_checks']['group'] . ' :: ' . $name;
			$GLOBALS['wh_checks']['failures'][] = $label;
			echo "NOT OK: $label\n";
		}
	}
}

$files = array_merge(
	glob( __DIR__ . '/Unit/*Test.php' ) ?: array(),
	glob( __DIR__ . '/Cli/*Test.php' ) ?: array(),
	glob( __DIR__ . '/Rest/*Test.php' ) ?: array()
);
sort( $files );
foreach ( $files as $file ) {
	require_once $file;
}

$pass = $GLOBALS['wh_checks']['pass'];
$fail = $GLOBALS['wh_checks']['fail'];
echo "\nPASS: $pass  FAIL: $fail  TOTAL: " . ( $pass + $fail ) . "\n";
if ( $fail > 0 ) {
	echo "FAILURES:\n - " . implode( "\n - ", $GLOBALS['wh_checks']['failures'] ) . "\n";
	exit( 1 );
}
echo "ALL TESTS PASSED\n";
exit( 0 );
