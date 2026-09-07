<?php
/**
 * REST contract + authorization tests (WH-273, WH-274).
 * Controllers are exercised with a fake-backed container.
 *
 * @package WP_Heart_Tests
 */

use WPHeart\Audit\AuditLogger;
use WPHeart\Audit\AuditStore;
use WPHeart\Cache\CacheService;
use WPHeart\Classification\ClassificationEngine;
use WPHeart\Config\Config;
use WPHeart\Container\Container;
use WPHeart\Database\CapabilityInspector;
use WPHeart\Database\IdentifierValidator;
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
use WPHeart\Query\Explainer;
use WPHeart\Query\QueryExecutor;
use WPHeart\Query\QueryPolicy;
use WPHeart\Query\QueryValidator;
use WPHeart\Rest\Controllers\AuditController;
use WPHeart\Rest\Controllers\CronController;
use WPHeart\Rest\Controllers\DatabaseController;
use WPHeart\Rest\Controllers\SnapshotsController;
use WPHeart\Rest\Controllers\DataController;
use WPHeart\Rest\Controllers\QueryController;
use WPHeart\Rest\Controllers\SettingsController;
use WPHeart\Rest\Controllers\TablesController;
use WPHeart\Rest\RestBootstrap;
use WPHeart\Schema\ColumnInspector;
use WPHeart\Schema\ConstraintInspector;
use WPHeart\Schema\IndexInspector;
use WPHeart\Schema\RelationshipEngine;
use WPHeart\Search\SearchEngine;
use WPHeart\Security\Capabilities;
use WPHeart\Security\Permission;
use WPHeart\WordPress\OptionsInspector;

wh_group( 'RestContract' );

$GLOBALS['wh_transients'] = array();
$GLOBALS['wh_options']    = array();

// Route registration contract.
$GLOBALS['wh_routes'] = array();
$boot = new RestBootstrap( wh_test_container() );
$boot->register();
wh_check( count( $GLOBALS['wh_routes'] ) >= 14, 'all routes registered (' . count( $GLOBALS['wh_routes'] ) . ')' );
$ns_ok = true;
foreach ( $GLOBALS['wh_routes'] as $r ) {
	if ( 'wp-heart/v1' !== $r['ns'] ) {
		$ns_ok = false;
	}
}
wh_check( $ns_ok, 'versioned namespace on every route' );
$has_permission = true;
foreach ( $GLOBALS['wh_routes'] as $r ) {
	$args = $r['args'];
	$list = isset( $args['permission_callback'] ) ? array( $args ) : ( is_array( $args ) ? $args : array() );
	foreach ( $list as $def ) {
		if ( is_array( $def ) && ! isset( $def['permission_callback'] ) ) {
			$has_permission = false;
		}
	}
}
wh_check( $has_permission, 'every route has a permission callback' );

// Tables index envelope.
$GLOBALS['wh_transients'] = array();
$tables = new TablesController( wh_test_container() );
$res    = $tables->index( wh_req( array( 'page' => 1, 'per_page' => 5, 'search' => '', 'classification' => '', 'orderby' => 'name', 'order' => 'ASC' ) ) );
$data   = $res->get_data();
wh_check( isset( $data['data'], $data['meta']['pagination'] ), 'tables envelope {data, meta.pagination}' );
wh_check( 5 === count( $data['data'] ), 'tables pagination honored' );
wh_check( 'EXACT' === $data['meta']['pagination']['total_mode'], 'table totals are EXACT' );

// Classification filter.
$res  = $tables->index( wh_req( array( 'page' => 1, 'per_page' => 20, 'search' => '', 'classification' => 'CORE', 'orderby' => 'name', 'order' => 'ASC' ) ) );
$data = $res->get_data();
$core_only = true;
foreach ( $data['data'] as $row ) {
	if ( ! isset( $row['classification']['type'] ) || 'CORE' !== $row['classification']['type'] ) {
		$core_only = false;
	}
}
wh_check( $core_only && count( $data['data'] ) >= 1, 'classification filter works' );

// Unknown table → 404, malicious → 404 (never 500, never SQL).
$res = $tables->show( wh_req( array( 'table' => 'wp_nope' ), '/tables/wp_nope' ) );
wh_check( $res instanceof WP_Error && 404 === $res->get_error_data()['status'], 'unknown table 404' );
$res = $tables->show( wh_req( array( 'table' => 'x' ), '/tables/x/schema' ) );
wh_check( $res instanceof WP_Error, 'unlisted table rejected on sub-routes' );

// Detail sections.
$res  = $tables->show( wh_req( array( 'table' => 'wp_posts' ), '/tables/wp_posts' ) );
$data = $res->get_data();
wh_check( isset( $data['data']['columns'], $data['data']['indexes'], $data['data']['classification'] ), 'detail carries schema + classification' );

// Data endpoints.
$data_c = new DataController( wh_test_container() );
$res    = $data_c->rows( wh_req( array( 'table' => 'wp_posts', 'page' => 1, 'per_page' => 20, 'orderby' => '', 'order' => 'ASC' ) ) );
$d      = $res->get_data();
wh_check( isset( $d['data'], $d['meta']['row_addressing'] ), 'rows envelope with addressing' );
wh_check( 'single' === $d['meta']['row_addressing']['mode'], 'posts addressing is single' );

$res = $data_c->rows( wh_req( array( 'table' => 'wp_posts', 'page' => 1, 'per_page' => 20, 'orderby' => 'ID; DROP', 'order' => 'ASC' ) ) );
wh_check( $res instanceof WP_Error && 400 === $res->get_error_data()['status'], 'orderby injection 400' );

$res = $data_c->rows( wh_req( array( 'table' => 'wp_posts', 'page' => 1, 'per_page' => 20, 'orderby' => 'ID', 'order' => 'SIDEWAYS' ) ) );
wh_check( $res instanceof WP_Error && 400 === $res->get_error_data()['status'], 'bad direction 400' );

$GLOBALS['wpdb'] = new RowWpdb();
$res = $data_c->row( wh_req( array( 'table' => 'wp_posts', 'id' => '1' ) ) );
wh_check( ! ( $res instanceof WP_Error ) && 'Hello' === $res->get_data()['data']['row']['post_title'], 'single row resolves' );
$res = $data_c->row( wh_req( array( 'table' => 'wp_posts', 'id' => '999' ) ) );
wh_check( $res instanceof WP_Error && 404 === $res->get_error_data()['status'], 'missing row 404' );
$res = $data_c->row( wh_req( array( 'table' => 'rel_pair', 'id' => '1' ) ) );
wh_check( $res instanceof WP_Error && 400 === $res->get_error_data()['status'], 'composite key explicit 400' );
$res = $data_c->row( wh_req( array( 'table' => 'legacy_log', 'id' => '1' ) ) );
wh_check( $res instanceof WP_Error && 400 === $res->get_error_data()['status'], 'no-pk explicit 400' );

// Query endpoint: destructive rejected at transport too.
$query = new QueryController( wh_test_container() );
$res   = $query->query( wh_req( array( 'sql' => 'DROP TABLE wp_posts' ) ) );
wh_check( $res instanceof WP_Error && 400 === $res->get_error_data()['status'], 'destructive query 400 at REST' );
$res = $query->query( wh_req( array( 'sql' => 'SELECT * FROM wp_posts' ) ) );
wh_check( ! ( $res instanceof WP_Error ) && true === $res->get_data()['meta']['read_only'], 'read query ok with read_only meta' );

// Audit type allowlist.
$audit = new AuditController( wh_test_container() );
$res   = $audit->index( wh_req( array( 'page' => 1, 'per_page' => 20, 'type' => 'HACK' ) ) );
wh_check( $res instanceof WP_Error && 400 === $res->get_error_data()['status'], 'bad audit type 400' );
$res = $audit->index( wh_req( array( 'page' => 1, 'per_page' => 20, 'type' => '' ) ) );
wh_check( ! ( $res instanceof WP_Error ), 'audit index ok' );

// Settings validation + clamping.
$settings = new SettingsController( wh_test_container() );
$res      = $settings->update( wh_req( array( 'cache_ttl' => 'not-a-number' ) ) );
wh_check( $res instanceof WP_Error && 400 === $res->get_error_data()['status'], 'non-numeric setting 400' );
$res = $settings->update( wh_req( array( 'cache_ttl' => 99999 ) ) );
wh_check( ! ( $res instanceof WP_Error ) && 3600 === $res->get_data()['data']['settings']['cache_ttl'], 'setting clamped to max' );

// Authorization matrix.
$GLOBALS['wh_logged_in'] = false;
$cb  = Permission::rest( Capabilities::VIEW_DATABASE );
$res = $cb();
wh_check( $res instanceof WP_Error && 401 === $res->get_error_data()['status'], 'logged-out 401' );

$GLOBALS['wh_logged_in'] = true;
$GLOBALS['wh_user_caps'] = array( 'read' );
$res = $cb();
wh_check( $res instanceof WP_Error && 403 === $res->get_error_data()['status'], 'unauthorized 403' );

$GLOBALS['wh_user_caps'] = array( 'wp_heart_view_database' );
$res = $cb();
wh_check( true === $res, 'capability holder allowed' );

$GLOBALS['wh_user_caps'] = array( 'manage_options' );
$res = $cb();
wh_check( true === $res, 'admin fallback allowed' );

$GLOBALS['wh_user_caps'] = array( 'manage_options' );
unset( $GLOBALS['wh_logged_in'] );

// Overview carries the autoload footprint block.
$GLOBALS['wh_transients'] = array();
$db_c = new DatabaseController( wh_test_container() );
$res  = $db_c->overview( wh_req() );
wh_check( ! ( $res instanceof WP_Error ), 'overview executes' );
$ov = $res->get_data()['data'];
wh_check( isset( $ov['autoload']['bytes'], $ov['autoload']['count'], $ov['autoload']['top'] ), 'overview has autoload block' );
wh_check( 88867 === $ov['autoload']['bytes'] && 'EXACT' === $ov['autoload']['accuracy'], 'autoload block exact' );

// Cron endpoint contract.
$has_cron_route = false;
foreach ( $GLOBALS['wh_routes'] as $r ) {
	if ( '/cron' === $r['route'] ) {
		$has_cron_route = true;
	}
}
wh_check( $has_cron_route, '/cron route registered' );
$cron_c = new CronController( wh_test_container() );
$res    = $cron_c->status( wh_req() );
wh_check( ! ( $res instanceof WP_Error ), 'cron status executes' );
$cron_data = $res->get_data()['data'];
wh_check( isset( $cron_data['wp_cron']['total'], $cron_data['action_scheduler']['available'] ), 'cron envelope shape' );

// Snapshots lifecycle: create → get → compare → download → import → delete.
$snap_c = new SnapshotsController( wh_test_container() );
$res    = $snap_c->create( wh_req( array( 'label' => 'contract snap' ) ) );
wh_check( ! ( $res instanceof WP_Error ) && 201 === $res->get_status(), 'snapshot created' );
$id = $res->get_data()['data']['id'];

$res = $snap_c->show( wh_req( array( 'id' => $id ) ) );
wh_check( ! ( $res instanceof WP_Error ) && 'contract snap' === $res->get_data()['data']['label'], 'snapshot retrieved' );

$res = $snap_c->show( wh_req( array( 'id' => 'no-such-id' ) ) );
wh_check( $res instanceof WP_Error && 404 === $res->get_error_data()['status'], 'unknown snapshot 404' );

$res = $snap_c->compare( wh_req( array( 'a' => $id, 'b' => $id ) ) );
$cmp = $res->get_data();
wh_check( ! ( $res instanceof WP_Error ) && 0 === $cmp['data']['summary']['tables_changed'], 'self-compare empty' );

$res = $snap_c->compare( wh_req( array( 'a' => $id, 'b' => 'no-such-id' ) ) );
wh_check( $res instanceof WP_Error && 404 === $res->get_error_data()['status'], 'compare missing 404' );

$res = $snap_c->download( wh_req( array( 'id' => $id ) ) );
$dl  = $res->get_data()['data'];
wh_check( ! ( $res instanceof WP_Error ) && false !== strpos( $dl['content'], '"tables"' ), 'download carries document' );

$res = $snap_c->import( wh_req( array( 'snapshot' => 'garbage' ) ) );
wh_check( $res instanceof WP_Error && 400 === $res->get_error_data()['status'], 'garbage import 400' );

$res = $snap_c->import( wh_req( array( 'label' => 'imp', 'snapshot' => json_decode( $dl['content'], true ) ) ) );
wh_check( ! ( $res instanceof WP_Error ) && 201 === $res->get_status(), 'valid import accepted' );
$imported_id = $res->get_data()['data']['id'];
wh_check( $imported_id !== $id, 'import re-identified (input id not trusted)' );

$res = $snap_c->destroy( wh_req( array( 'id' => $id ) ) );
wh_check( ! ( $res instanceof WP_Error ) && true === $res->get_data()['data']['deleted'], 'snapshot deleted' );
$snap_c->destroy( wh_req( array( 'id' => $imported_id ) ) );
$res = $snap_c->destroy( wh_req( array( 'id' => $id ) ) );
wh_check( $res instanceof WP_Error && 404 === $res->get_error_data()['status'], 'double delete 404' );
