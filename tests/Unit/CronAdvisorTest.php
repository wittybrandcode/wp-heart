<?php
/**
 * Cron intelligence + advisor + Site Health tests.
 *
 * @package WP_Heart_Tests
 */

use WPHeart\Diagnostics\Checks\CronHealthDiagnostic;
use WPHeart\Diagnostics\Checks\UnindexedReferenceDiagnostic;
use WPHeart\Domain\ColumnInfo;
use WPHeart\Domain\IndexInfo;
use WPHeart\Intelligence\WpContextService;
use WPHeart\Support\Severity;
use WPHeart\WordPress\CronInspector;
use WPHeart\WordPress\SiteHealth;

wh_group( 'CronAdvisor' );

// wp-cron flattening with an injected schedule.
$past   = time() - 3600;
$future = time() + 3600;
$fetcher = static function () use ( $past, $future ) {
	return array(
		$past   => array(
			'old_hook' => array( array( 'schedule' => 'hourly', 'args' => array( 1 ) ) ),
		),
		$future => array(
			'new_hook' => array( array( 'schedule' => 'daily', 'args' => array() ) ),
		),
		12345  => 'garbage-ignored',
	);
};
$cron = new CronInspector( new FakeAdapter( array() ), new WpContextService( 'wp_', false, 1 ), $fetcher );
$ev   = $cron->wp_cron_events();
wh_check( 2 === $ev['total'] && 1 === $ev['overdue'], 'cron flattened with overdue counted' );
wh_check( 'old_hook' === $ev['events'][0]['hook'] && $ev['events'][0]['overdue'], 'soonest first, overdue flagged' );
wh_check( 1 === $ev['events'][0]['args'] && 'hourly' === $ev['events'][0]['schedule'], 'schedule + arg count kept, args not dumped' );

// Limit guard.
$many = array();
for ( $i = 0; $i < 205; $i++ ) {
	$many[ time() + $i ] = array( 'h' . $i => array( array() ) );
}
$ev = ( new CronInspector( new FakeAdapter( array() ), null, static function () use ( $many ) { return $many; } ) )->wp_cron_events();
wh_check( 200 === count( $ev['items'] ?? $ev['events'] ), 'event list capped at 200' );

// Action Scheduler awareness.
$as_adapter = new FakeAdapter( array( 'wp_actionscheduler_actions' => array() ) );
$as         = new CronInspector( $as_adapter, new WpContextService( 'wp_', false, 1 ) );
$info       = $as->action_scheduler();
wh_check( true === $info['available'], 'AS detected' );
wh_check( 96 === $info['by_status']['complete'] && 5 === $info['by_status']['failed'], 'AS status distribution' );
wh_check( 2 === $info['overdue_pending'], 'AS overdue counted' );
wh_check( 2 === count( $info['failed_recent'] ) && 'fetch_patterns' === $info['failed_recent'][0]['hook'], 'failed hooks listed without args' );
wh_check( ! isset( $info['failed_recent'][0]['args'] ), 'no callback arguments exposed' );

// Absent AS degrades cleanly.
$none = new CronInspector( new FakeAdapter( FakeAdapter::default_tables() ), new WpContextService( 'wp_', false, 1 ) );
wh_check( false === $none->action_scheduler()['available'], 'missing AS tables degrade' );

// Cron health diagnostic.
$diag = new CronHealthDiagnostic();
wh_check( array() === $diag->check( array() ), 'missing context silent' );
wh_check( array() === $diag->check( array( 'cron' => array( 'wp_overdue' => array(), 'as_failed' => array(), 'as_overdue' => 0 ) ) ), 'healthy schedule silent' );
$issues = $diag->check(
	array(
		'cron' => array(
			'wp_overdue' => array( array( 'hook' => 'stuck_hook' ) ),
			'as_failed'  => array( array( 'hook' => 'broken_hook' ) ),
			'as_overdue' => 2,
			'tables'     => array( 'wp_actionscheduler_actions' ),
		),
	)
);
wh_check( 1 === count( $issues ) && Severity::WARNING === $issues[0]->severity(), 'cron problems are WARNING' );
$d = $issues[0]->to_array();
wh_check( in_array( 'wp_options', $d['affected'], true ) && in_array( 'wp_actionscheduler_actions', $d['affected'], true ), 'affected names real tables' );

// Index advisor.
$adv = new UnindexedReferenceDiagnostic();
$col = static function ( $name ) {
	return new ColumnInfo( array( 'name' => $name ) );
};
$idx = static function ( $name, $cols ) {
	return new IndexInfo( array( 'name' => $name, 'unique' => false, 'primary' => false, 'columns' => $cols ) );
};
$pkidx = static function ( $cols ) {
	return new IndexInfo( array( 'name' => 'PRIMARY', 'unique' => true, 'primary' => true, 'columns' => $cols ) );
};
wh_check( array() === $adv->check( array() ), 'missing maps silent' );
$issues = $adv->check(
	array(
		'columns' => array( 't' => array( $col( 'order_id' ) ) ),
		'indexes' => array( 't' => array() ),
	)
);
wh_check( 1 === count( $issues ) && Severity::WARNING === $issues[0]->severity(), 'unindexed reference suggested' );
wh_check( array() === $adv->check(
	array(
		'columns' => array( 't' => array( $col( 'order_id' ) ) ),
		'indexes' => array( 't' => array( $idx( 'order_id', array( 'order_id' ) ) ) ),
	)
), 'covered column quiet' );
wh_check( array() === $adv->check(
	array(
		'columns' => array( 't' => array( $col( 'ID' ), $col( 'name' ) ) ),
		'indexes' => array( 't' => array() ),
	)
), 'ID and plain names ignored' );
wh_check( array() === $adv->check(
	array(
		'columns' => array( 't' => array( $col( 'a_id' ), $col( 'b_id' ) ) ),
		'indexes' => array( 't' => array( $pkidx( array( 'a_id', 'b_id' ) ) ) ),
	)
), 'composite-PK members never suggested' );
$many_cols = array();
for ( $i = 0; $i < 150; $i++ ) {
	$many_cols[] = $col( 'ref_' . $i . '_id' );
}
$issues = $adv->check( array( 'columns' => array( 't' => $many_cols ), 'indexes' => array() ) );
wh_check( 100 === count( $issues ), 'advisor capped at 100 issues' );

// Site Health mapping.
wh_check( 'good' === SiteHealth::status_for_autoload( 96000 ), 'healthy autoload good' );
wh_check( 'recommended' === SiteHealth::status_for_autoload( 600000 ), '512KB recommended' );
wh_check( 'critical' === SiteHealth::status_for_autoload( 2097152 ), '1MB critical' );

$health = new SiteHealth( new WPHeart\WordPress\OptionsInspector( new FakeAdapter( FakeAdapter::default_tables() ) ) );
$tests  = $health->tests( array() );
wh_check( isset( $tests['direct']['wp_heart_readonly'], $tests['direct']['wp_heart_autoload'], $tests['direct']['wp_heart_transients'] ), 'three site-health tests registered' );
wh_check( 'good' === $tests['direct']['wp_heart_readonly']['test']()['status'], 'read-only test good' );
wh_check( 'good' === $tests['direct']['wp_heart_autoload']['test']()['status'], 'fixture autoload good' );

$big = new FakeAdapter( FakeAdapter::default_tables() );
$big->options_totals = array( 'count' => 1500, 'bytes' => 2097152 );
$health_big = new SiteHealth( new WPHeart\WordPress\OptionsInspector( $big ) );
$tests_big  = $health_big->tests( array() );
wh_check( 'critical' === $tests_big['direct']['wp_heart_autoload']['test']()['status'], 'bloated autoload critical' );
