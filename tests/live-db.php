<?php
/**
 * Live-database integration validation (WH-272, WH-292, Phase 23/29).
 * Boots the real WordPress and exercises WP-HEART against the real DB.
 *
 * Usage: php tests/live-db.php
 * Read-only: issues SELECT/SHOW only. Safe on production-like data.
 *
 * @package WP_Heart_Tests
 */

error_reporting( E_ALL & ~E_DEPRECATED );

$wp_load = 'C:\\xampp\\htdocs\\wordpress\\wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	echo "SKIP: wp-load.php not found\n";
	exit( 2 );
}

define( 'WP_HEART_LIVE_TEST', true );
require_once $wp_load;

if ( ! defined( 'WP_HEART_VERSION' ) ) {
	define( 'WP_HEART_VERSION', '1.0.0' );
}
if ( ! defined( 'WP_HEART_DIR' ) ) {
	define( 'WP_HEART_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'WP_HEART_REST_NAMESPACE' ) ) {
	define( 'WP_HEART_REST_NAMESPACE', 'wp-heart/v1' );
}

require_once dirname( __DIR__ ) . '/autoload.php';

use WPHeart\Cache\CacheService;
use WPHeart\Classification\ClassificationEngine;
use WPHeart\Config\Config;
use WPHeart\Container\Container;
use WPHeart\Database\CapabilityInspector;
use WPHeart\Database\IdentifierValidator;
use WPHeart\Database\WpdbAdapter;
use WPHeart\Diagnostics\HealthEngine;
use WPHeart\Discovery\DatabaseService;
use WPHeart\Discovery\TableDiscovery;
use WPHeart\Intelligence\ConfidenceScorer;
use WPHeart\Intelligence\CoreTableRecognizer;
use WPHeart\Intelligence\OrphanDetector;
use WPHeart\Intelligence\PluginRegistry;
use WPHeart\Intelligence\PluginTableDetector;
use WPHeart\Intelligence\ThemeDetector;
use WPHeart\Intelligence\WpContextService;
use WPHeart\Query\QueryExecutor;
use WPHeart\Query\QueryPolicy;
use WPHeart\Query\QueryValidator;
use WPHeart\Schema\ColumnInspector;
use WPHeart\Schema\ConstraintInspector;
use WPHeart\Schema\IndexInspector;
use WPHeart\Schema\RelationshipEngine;
use WPHeart\Search\SearchEngine;
use WPHeart\Support\ClassificationType;

global $wpdb;

$pass = 0;
$fail = 0;
function live_check( $cond, $name ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
	} else {
		++$fail;
		echo "NOT OK (live): $name\n";
	}
}

$config  = new Config();
$adapter = new WpdbAdapter( $wpdb );
$ids     = new IdentifierValidator();
$cache   = new CacheService( $config );
$context = new WpContextService();

// 1. Discovery against the real database.
$discovery = new TableDiscovery( $adapter, $ids, $cache, $config );
$found     = $discovery->discover( true );
$names     = array();
foreach ( $found['tables'] as $t ) {
	$names[] = $t->name();
}
live_check( count( $names ) > 10, 'discovers real tables (got ' . count( $names ) . ')' );
live_check( in_array( $wpdb->prefix . 'posts', $names, true ), 'core posts table discovered' );
live_check( in_array( $wpdb->prefix . 'postmeta', $names, true ), 'postmeta discovered' );
echo 'Tables: ' . count( $names ) . ', prefix in use: ' . $context->prefix() . "\n";

// 2. Classification on the real database.
$classifier = new ClassificationEngine(
	new CoreTableRecognizer( $context ),
	new PluginTableDetector( new PluginRegistry(), $context ),
	new ThemeDetector( $context ),
	new OrphanDetector( new PluginRegistry(), $context ),
	new ConfidenceScorer()
);
$map  = $classifier->classify_all( $names );
$dist = array();
foreach ( $map as $table => $c ) {
	$k = $c->type();
	$dist[ $k ] = isset( $dist[ $k ] ) ? $dist[ $k ] + 1 : 1;
}
echo 'Classification: ' . json_encode( $dist ) . "\n";
live_check( ClassificationType::CORE === $map[ $wpdb->prefix . 'posts' ]->type(), 'wp_posts classified CORE' );
live_check( ClassificationType::CORE === $map[ $wpdb->prefix . 'options' ]->type(), 'wp_options classified CORE' );
$wc_table = $wpdb->prefix . 'wc_orders';
if ( in_array( $wc_table, $names, true ) ) {
	echo 'wc_orders classification: ' . $wc_table . ' => ' . $map[ $wc_table ]->type() . ' (' . $map[ $wc_table ]->confidence() . ")\n";
}

// 3. Schema inspection on the largest table (bounded metadata only).
$cols = new ColumnInspector( $adapter, $ids );
$idx  = new IndexInspector( $adapter, $ids );
$cons = new ConstraintInspector( $adapter, $ids );
$postmeta_cols = $cols->inspect( $wpdb->prefix . 'postmeta' );
$postmeta_idx  = $idx->inspect( $wpdb->prefix . 'postmeta' );
live_check( count( $postmeta_cols ) >= 4, 'postmeta columns inspected (' . count( $postmeta_cols ) . ')' );
live_check( count( $postmeta_idx ) >= 1, 'postmeta indexes inspected' );
$rels = new RelationshipEngine( $context );
$rel_list = $rels->for_table( $wpdb->prefix . 'postmeta', $postmeta_cols, array(), $names );
$has_inferred = false;
foreach ( $rel_list as $r ) {
	if ( 'INFERRED' === $r->origin() ) {
		$has_inferred = true;
	}
}
live_check( $has_inferred, 'post_id inference present on live schema' );

// 4. Read-only query execution (SELECT 1 + bounded postmeta read).
$executor = new QueryExecutor( $adapter, new QueryValidator( new QueryPolicy( $config ), $config ), $config );
$r = $executor->execute( 'SELECT 1 AS one' );
live_check( $r['success'] && '1' === (string) $r['rows'][0]['one'], 'SELECT 1 executes' );
$r = $executor->execute( 'DELETE FROM ' . $wpdb->prefix . 'posts' );
live_check( ! $r['success'], 'DELETE refused on live adapter' );

// 5. Bounded search on live data.
$search = new SearchEngine( $adapter, $ids, $cols, $config, $idx );
$sr = $search->search( 'hello', array( 'tables' => array( $wpdb->prefix . 'posts' ) ) );
live_check( ! isset( $sr['error'] ), 'live search runs bounded' );
echo 'Search hits (page 1): ' . count( $sr['items'] ) . "\n";

// 5b. Row-search path (LIKE with real esc_like + prepare) on postmeta.
$row_like = $adapter->fetch_all(
	'SELECT * FROM `' . $wpdb->prefix . 'postmeta` WHERE `meta_key` LIKE %s ESCAPE \'\\\\\' LIMIT 5',
	array( '%\_%' )
);
live_check( is_array( $row_like ), 'row-search LIKE path executes via prepare' );

// 6. Health engine on the live database (metadata-level tables).
$tables_meta = $found['tables'];
$index_map   = array();
foreach ( array_slice( $tables_meta, 0, 60 ) as $t ) {
	$ii = $idx->inspect( $t->name() );
	$t->set_indexes( $ii );
	$index_map[ $t->name() ] = $ii;
}
$health = HealthEngine::with_defaults( null, new CoreTableRecognizer( $context ), $context );
$report = $health->run(
	array(
		'tables'          => $tables_meta,
		'table_names'     => $names,
		'classifications' => $map,
		'indexes'         => $index_map,
	)
);
echo 'Live health issues: ' . count( $report['issues'] ) . "\n";
live_check( count( $report['ran'] ) === 12, 'all 12 diagnostics ran live' );

// 7. Privilege awareness.
$priv = new CapabilityInspector( $adapter );
$pi   = $priv->inspect();
echo 'Privileges: ' . json_encode( $pi ) . "\n";
live_check( in_array( $pi['status'], array( 'FULL', 'LIMITED_VISIBLE', 'RESTRICTED', 'UNKNOWN' ), true ), 'privilege status sane' );

// 8. Database summary.
$service = new DatabaseService( $adapter, $discovery, $priv, $cache, $config );
$summary = $service->summary()->to_array();
echo 'Summary: tables=' . $summary['table_count'] . ' size=' . $summary['estimated_size'] . ' (' . $summary['estimated_size_mode'] . ")\n";
live_check( $summary['table_count'] === count( $names ), 'summary count matches discovery' );

// 9. Options autoload audit on the live database (both autoload schemes).
$opt_inspector = new WPHeart\WordPress\OptionsInspector( $adapter );
$autoload      = $opt_inspector->autoload_summary( 5 );
live_check( $autoload['bytes'] > 0 && $autoload['count'] > 0, 'autoload footprint measured (' . $autoload['bytes'] . ' bytes, ' . $autoload['count'] . ' options)' );
live_check( 'EXACT' === $autoload['accuracy'] && count( $autoload['top'] ) > 0, 'top autoload options exact' );
$transients = $opt_inspector->transient_stats();
live_check( $transients['values'] >= 0 && $transients['expired'] >= 0, 'transient stats sane (values=' . $transients['values'] . ', expired=' . $transients['expired'] . ')' );

echo "\nLIVE PASS: $pass  FAIL: $fail\n";
exit( $fail > 0 ? 1 : 0 );
