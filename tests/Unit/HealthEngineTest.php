<?php
/**
 * Health engine tests (WH-070–WH-073): modular, evidence-backed, severity-aware.
 *
 * @package WP_Heart_Tests
 */

use WPHeart\Classification\ClassificationEngine;
use WPHeart\Diagnostics\Checks\LargeTableDiagnostic;
use WPHeart\Diagnostics\Checks\MissingCoreTableDiagnostic;
use WPHeart\Diagnostics\Checks\MissingPrimaryKeyDiagnostic;
use WPHeart\Diagnostics\HealthEngine;
use WPHeart\Domain\Classification;
use WPHeart\Domain\TableInfo;
use WPHeart\Intelligence\ConfidenceScorer;
use WPHeart\Intelligence\CoreTableRecognizer;
use WPHeart\Intelligence\OrphanDetector;
use WPHeart\Intelligence\PluginRegistry;
use WPHeart\Intelligence\PluginTableDetector;
use WPHeart\Intelligence\ThemeDetector;
use WPHeart\Intelligence\WpContextService;
use WPHeart\Schema\ColumnInspector;
use WPHeart\Schema\IndexInspector;
use WPHeart\Database\IdentifierValidator;
use WPHeart\Support\ClassificationType;
use WPHeart\Support\Confidence;
use WPHeart\Support\Severity;

wh_group( 'HealthEngine' );

$adapter = new FakeAdapter( FakeAdapter::default_tables() );
$ids     = new IdentifierValidator();
$col_in  = new ColumnInspector( $adapter, $ids );
$idx_in  = new IndexInspector( $adapter, $ids );

// Hydrate TableInfos like the controller does.
$tables = array();
$col_map = array();
foreach ( $adapter->list_table_names() as $name ) {
	$status = $adapter->table_status( $name );
	$t      = new TableInfo(
		$name,
		array(
			'engine'         => $status['Engine'],
			'collation'      => $status['Collation'],
			'estimated_rows' => $status['Rows'],
			'size_bytes'     => $status['Data_length'] + $status['Index_length'],
		)
	);
	$t->set_indexes( $idx_in->inspect( $name ) );
	$t->set_columns( $col_in->inspect( $name ) );
	$col_map[ $name ] = $t->columns();
	$tables[] = $t;
}

$context = new WpContextService( 'wp_', false, 1 );
$engine  = new ClassificationEngine(
	new CoreTableRecognizer( $context ),
	new PluginTableDetector( new PluginRegistry(), $context ),
	new ThemeDetector( $context ),
	new OrphanDetector( new PluginRegistry(), $context ),
	new ConfidenceScorer()
);
$map = $engine->classify_all(
	array_map(
		static function ( $t ) {
			return $t->name();
		},
		$tables
	),
	FakeAdapter::default_plugins()
);

$names = array_keys( $map );
$ctx   = array(
	'tables'          => $tables,
	'table_names'     => $names,
	'classifications' => $map,
	'expected_core'   => array( 'wp_posts', 'wp_options', 'wp_users' ),
	'indexes'         => array(),
	'columns'         => $col_map,
	'autoload'        => array( 'bytes' => 96000, 'count' => 300, 'top' => array() ),
	'transients'      => array( 'values' => 67, 'expired' => 3 ),
	'cron'            => array( 'wp_overdue' => array(), 'as_failed' => array(), 'as_overdue' => 0 ),
);

foreach ( $tables as $table ) {
	$idx_list = $table->indexes();
	$ctx['indexes'][ $table->name() ] = $idx_list;
}

$health = HealthEngine::with_defaults( null, new CoreTableRecognizer( $context ), $context );
wh_check( 12 === count( $health->registered() ), 'twelve default diagnostics registered' );

$run = $health->run( $ctx );
$by_diag = array();
foreach ( $run['issues'] as $issue ) {
	$d = $issue->to_array();
	$by_diag[ $d['diagnostic'] ][] = $d;
}

// New diagnostics stay silent on the healthy fixture context.
wh_check( ! isset( $by_diag['wpheart_unindexed_reference'] ), 'advisor quiet on indexed fixtures' );
wh_check( ! isset( $by_diag['wpheart_autoload_size'] ), 'healthy autoload silent in engine' );
wh_check( ! isset( $by_diag['wpheart_expired_transients'] ), 'normal transients silent in engine' );
wh_check( ! isset( $by_diag['wpheart_cron'] ), 'healthy schedule silent in engine' );

// legacy_log has no PK → flagged; wp_posts has PK → not flagged.
wh_check( isset( $by_diag['wpheart_no_pk'] ), 'missing-PK diagnostic fires' );
$flagged = array();
foreach ( $by_diag['wpheart_no_pk'] as $i ) {
	$flagged = array_merge( $flagged, $i['affected'] );
}
wh_check( in_array( 'legacy_log', $flagged, true ), 'pk-less table flagged' );
wh_check( ! in_array( 'wp_posts', $flagged, true ), 'pk table not flagged' );

// wp_options + wp_users absent from fixture names? names include only fixture keys:
// wp_options/wp_users are NOT fixtures → both ERROR; boot-critical? wp_options,
// wp_users, usermeta/posts... expected list here is posts/options/users.
wh_check( isset( $by_diag['wpheart_missing_core'] ), 'missing-core fires' );
foreach ( $by_diag['wpheart_missing_core'] as $i ) {
	if ( in_array( 'wp_options', $i['affected'], true ) ) {
		wh_check( Severity::CRITICAL === $i['severity'], 'missing boot-critical table is CRITICAL' );
	}
	if ( in_array( 'wp_users', $i['affected'], true ) ) {
		wh_check( Severity::CRITICAL === $i['severity'], 'missing users table is CRITICAL' );
	}
}
wh_check( ! isset( $by_diag['wpheart_missing_core'] ) || 2 === count( $by_diag['wpheart_missing_core'] ), 'only truly-missing core tables flagged' );

// Engine mix: InnoDB + MyISAM present.
wh_check( isset( $by_diag['wpheart_engine_mix'] ), 'engine mix noticed' );
wh_check( Severity::NOTICE === $by_diag['wpheart_engine_mix'][0]['severity'], 'engine mix is NOTICE, not WARNING' );

// Charset mix: latin1 vs utf8mb4.
wh_check( isset( $by_diag['wpheart_charset_mix'] ), 'charset mix noticed' );

// Orphans: wp_wordfence_hits fixture.
wh_check( isset( $by_diag['wpheart_orphans'] ), 'orphan reporter fires' );
wh_check( Severity::INFO === $by_diag['wpheart_orphans'][0]['severity'], 'orphans are INFO only' );

// Large tables: wp_postmeta 215k → NOTICE (>=100k, <1M).
wh_check( isset( $by_diag['wpheart_large_tables'] ), 'large table noticed' );
wh_check( Severity::NOTICE === $by_diag['wpheart_large_tables'][0]['severity'], '215k rows is NOTICE, not WARNING' );

// Severity ordering: run() sorts descending.
$prev = PHP_INT_MAX;
$ordered = true;
foreach ( $run['issues'] as $issue ) {
	$rank = Severity::rank( $issue->severity() );
	if ( $rank > $prev ) {
		$ordered = false;
	}
	$prev = $rank;
}
wh_check( $ordered, 'issues sorted by severity descending' );

// Diagnostic failure isolation: a throwing diagnostic must not kill the run.
$broken = new class() implements \WPHeart\Diagnostics\DiagnosticInterface {
	public function id() {
		return 'boom';
	}
	public function name() {
		return 'boom';
	}
	public function description() {
		return 'boom';
	}
	public function check( array $context ) {
		throw new \Exception( 'boom' );
	}
};
$health->register( $broken );
$run2 = $health->run( $ctx );
wh_check( count( $run2['issues'] ) === count( $run['issues'] ), 'broken diagnostic isolated' );

// Every issue carries the evidence contract.
$contract_ok = true;
foreach ( $run['issues'] as $issue ) {
	$d = $issue->to_array();
	foreach ( array( 'id', 'diagnostic', 'severity', 'affected', 'evidence', 'explanation', 'recommendation' ) as $k ) {
		if ( ! array_key_exists( $k, $d ) ) {
			$contract_ok = false;
		}
	}
}
wh_check( $contract_ok, 'issue contract complete' );
