<?php
/**
 * Options audit tests: inspector normalization + autoload/transient diagnostics.
 *
 * @package WP_Heart_Tests
 */

use WPHeart\Diagnostics\Checks\AutoloadSizeDiagnostic;
use WPHeart\Diagnostics\Checks\ExpiredTransientsDiagnostic;
use WPHeart\Diagnostics\Checks\TopAutoloadOptionsDiagnostic;
use WPHeart\Support\Severity;
use WPHeart\WordPress\OptionsInspector;

wh_group( 'OptionsAudit' );

$inspector = new OptionsInspector( new FakeAdapter( FakeAdapter::default_tables() ) );

$sum = $inspector->autoload_summary();
wh_check( 88867 === $sum['bytes'] && 213 === $sum['count'], 'autoload totals pass through' );
wh_check( 'EXACT' === $sum['accuracy'], 'autoload totals are EXACT aggregates' );
wh_check( 3 === count( $sum['top'] ) && 'active_plugins' === $sum['top'][0]['name'], 'top options ordered' );

$sum = $inspector->autoload_summary( 2 );
wh_check( 2 === count( $sum['top'] ), 'top list honored limit' );
$sum = $inspector->autoload_summary( 9999 );
wh_check( 3 === count( $sum['top'] ), 'top list clamped, never unbounded' );

$stats = $inspector->transient_stats();
wh_check( 67 === $stats['values'] && 3 === $stats['expired'], 'transient stats pass through' );

// Autoload size thresholds (live DB sits at ~96KB: silent by design).
$diag = new AutoloadSizeDiagnostic();
wh_check( array() === $diag->check( array() ), 'missing context degrades to no issues' );
wh_check( array() === $diag->check( array( 'autoload' => array( 'bytes' => 96000, 'count' => 300 ) ) ), 'healthy footprint silent' );
$issues = $diag->check( array( 'autoload' => array( 'bytes' => 600000, 'count' => 900 ) ) );
wh_check( 1 === count( $issues ) && Severity::NOTICE === $issues[0]->severity(), '512KB+ is NOTICE' );
$issues = $diag->check( array( 'autoload' => array( 'bytes' => 2097152, 'count' => 1500 ) ) );
wh_check( 1 === count( $issues ) && Severity::WARNING === $issues[0]->severity(), '1MB+ is WARNING, never CRITICAL' );
$d = $issues[0]->to_array();
wh_check( in_array( 'wp_options', $d['affected'], true ), 'affected names the options table' );

// Top options attribution.
$top = new TopAutoloadOptionsDiagnostic();
wh_check( array() === $top->check( array( 'autoload' => array( 'top' => array() ) ) ), 'empty top yields nothing' );
$issues = $top->check( array( 'autoload' => array( 'top' => array( array( 'name' => 'cron', 'bytes' => 5000 ) ) ) ) );
wh_check( 1 === count( $issues ) && Severity::INFO === $issues[0]->severity(), 'top list is INFO only' );
$d = $issues[0]->to_array();
wh_check( in_array( 'option:cron', $d['affected'], true ), 'option affected uses option: prefix' );

// Expired transients.
$exp = new ExpiredTransientsDiagnostic();
wh_check( array() === $exp->check( array( 'transients' => array( 'values' => 67, 'expired' => 3 ) ) ), 'normal churn silent' );
$issues = $exp->check( array( 'transients' => array( 'values' => 400, 'expired' => 150 ) ) );
wh_check( 1 === count( $issues ) && Severity::NOTICE === $issues[0]->severity(), 'backlog is NOTICE, never ERROR' );
