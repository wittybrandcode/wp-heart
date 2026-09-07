<?php
/**
 * Performance regression benchmark on the live database (WH-240–WH-243).
 * Run: php tests/benchmark.php. Budgets are guardrails against full
 * table/database loading regressions, not absolute promises.
 *
 * @package WP_Heart_Tests
 */

error_reporting( E_ALL & ~E_DEPRECATED );

$wp_load = 'C:\\xampp\\htdocs\\wordpress\\wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	echo "SKIP: wp-load.php not found\n";
	exit( 2 );
}
require_once $wp_load;

foreach ( array( 'WP_HEART_VERSION', 'WP_HEART_DIR', 'WP_HEART_REST_NAMESPACE' ) as $const ) {
	if ( ! defined( $const ) ) {
		define(
			$const,
			'WP_HEART_VERSION' === $const ? '1.0.0' : ( 'WP_HEART_DIR' === $const ? dirname( __DIR__ ) . '/' : 'wp-heart/v1' )
		);
	}
}
require_once dirname( __DIR__ ) . '/autoload.php';

use WPHeart\Cache\CacheService;
use WPHeart\Config\Config;
use WPHeart\Database\IdentifierValidator;
use WPHeart\Database\WpdbAdapter;
use WPHeart\Discovery\TableDiscovery;
use WPHeart\Schema\ColumnInspector;
use WPHeart\Schema\IndexInspector;

global $wpdb;
$config  = new Config();
$adapter = new WpdbAdapter( $wpdb );
$ids     = new IdentifierValidator();
$cache   = new CacheService( $config );

function bench( $label, $fn, $budget_ms ) {
	$start = microtime( true );
	$result = $fn();
	$ms = round( ( microtime( true ) - $start ) * 1000, 1 );
	$ok = $ms <= $budget_ms ? 'OK  ' : 'OVER';
	echo sprintf( "[%s] %-42s %8.1f ms (budget %d ms)\n", $ok, $label, $ms, $budget_ms );
	return array( $result, $ms <= $budget_ms );
}

$all_ok = true;

list( $found, $ok ) = bench(
	'discovery (cold, 51 tables)',
	static function () use ( $adapter, $ids, $cache, $config ) {
		$d = new TableDiscovery( $adapter, $ids, $cache, $config );
		return $d->discover( true );
	},
	5000
);
$all_ok = $all_ok && $ok;

list( $cached, $ok ) = bench(
	'discovery (warm cache)',
	static function () use ( $adapter, $ids, $cache, $config ) {
		$d = new TableDiscovery( $adapter, $ids, $cache, $config );
		return $d->discover( false );
	},
	500
);
$all_ok = $all_ok && $ok;

$cols = new ColumnInspector( $adapter, $ids );
$idx  = new IndexInspector( $adapter, $ids );
list( $tmp, $ok ) = bench(
	'schema inspect largest table (postmeta cols+indexes)',
	static function () use ( $cols, $idx, $wpdb ) {
		return array( $cols->inspect( $wpdb->prefix . 'postmeta' ), $idx->inspect( $wpdb->prefix . 'postmeta' ) );
	},
	2000
);
$all_ok = $all_ok && $ok;

list( $page, $ok ) = bench(
	'paged read 20 rows from 215k-row postmeta',
	static function () use ( $adapter ) {
		global $wpdb;
		return $adapter->run_read( 'SELECT * FROM `' . $wpdb->prefix . 'postmeta` LIMIT 20 OFFSET 0' );
	},
	2000
);
$all_ok = $all_ok && $ok;
echo 'Paged payload rows: ' . count( $page['rows'] ) . ' (never the full table)' . "\n";

list( $search, $ok ) = bench(
	'scoped LIKE search on posts',
	static function () use ( $adapter ) {
		global $wpdb;
		return $adapter->fetch_all(
			'SELECT * FROM `' . $wpdb->prefix . 'posts` WHERE `post_title` LIKE %s ESCAPE \'\\\\\' LIMIT 5',
			array( '%hello%' )
		);
	},
	2000
);
$all_ok = $all_ok && $ok;

echo $all_ok ? "BENCHMARKS WITHIN BUDGET\n" : "BENCHMARK BUDGET EXCEEDED\n";
exit( $all_ok ? 0 : 1 );
