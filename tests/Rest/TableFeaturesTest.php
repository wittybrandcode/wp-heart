<?php
/**
 * Row search + engine filter + LIKE helper tests (light UI features).
 * Requires ContractTest.php helpers (loaded first alphabetically).
 *
 * @package WP_Heart_Tests
 */

use WPHeart\Database\LikeHelper;
use WPHeart\Rest\Controllers\DataController;
use WPHeart\Rest\Controllers\TablesController;

wh_group( 'TableFeatures' );

// LIKE escaping (fallback path without $wpdb).
unset( $GLOBALS['wpdb'] );
wh_check( 'a\\%b\\_c\\\\d' === LikeHelper::escape( 'a%b_c\\d' ), 'like special chars escaped' );
wh_check( '%hi%' === LikeHelper::contains( 'hi' ), 'contains pattern built' );

$GLOBALS['wh_transients'] = array();
$data_c = new DataController( wh_test_container() );

// Row search bounds.
$res = $data_c->rows( wh_req( array( 'table' => 'wp_posts', 'page' => 1, 'per_page' => 20, 'orderby' => '', 'order' => 'ASC', 'search' => 'x' ) ) );
wh_check( $res instanceof WP_Error && 400 === $res->get_error_data()['status'], 'row search too short 400' );

$res = $data_c->rows( wh_req( array( 'table' => 'wp_posts', 'page' => 1, 'per_page' => 20, 'orderby' => '', 'order' => 'ASC', 'search' => 'hello' ) ) );
wh_check( ! ( $res instanceof WP_Error ), 'row search executes' );
$d = $res->get_data();
wh_check( isset( $d['meta']['row_search'] ) && true === $d['meta']['row_search']['active'], 'row_search meta active' );
wh_check( in_array( 'post_title', $d['meta']['row_search']['columns'], true ), 'search scoped to text columns' );
wh_check( 1 === count( $d['data'] ), 'row search returns matches' );

// Search with LIKE metacharacters does not break the query.
$res = $data_c->rows( wh_req( array( 'table' => 'wp_posts', 'page' => 1, 'per_page' => 20, 'orderby' => '', 'order' => 'ASC', 'search' => '100%_x' ) ) );
wh_check( ! ( $res instanceof WP_Error ), 'metachar search safe' );

// Plain listing still reports inactive search.
$res = $data_c->rows( wh_req( array( 'table' => 'wp_posts', 'page' => 1, 'per_page' => 20, 'orderby' => '', 'order' => 'ASC', 'search' => '' ) ) );
wh_check( false === $res->get_data()['meta']['row_search']['active'], 'plain listing inactive search meta' );

// Engine filter.
$tables = new TablesController( wh_test_container() );
$res    = $tables->index( wh_req( array( 'page' => 1, 'per_page' => 20, 'search' => '', 'classification' => '', 'engine' => 'INNODB', 'orderby' => 'name', 'order' => 'ASC' ) ) );
$only_inno = true;
foreach ( $res->get_data()['data'] as $row ) {
	if ( 'InnoDB' !== $row['metadata']['engine'] ) {
		$only_inno = false;
	}
}
wh_check( $only_inno, 'engine filter keeps InnoDB only' );

$res = $tables->index( wh_req( array( 'page' => 1, 'per_page' => 20, 'search' => '', 'classification' => '', 'engine' => 'MYISAM', 'orderby' => 'name', 'order' => 'ASC' ) ) );
wh_check( 1 === count( $res->get_data()['data'] ) && 'legacy_log' === $res->get_data()['data'][0]['name'], 'engine filter finds MyISAM table' );

$res = $tables->index( wh_req( array( 'page' => 1, 'per_page' => 20, 'search' => '', 'classification' => '', 'engine' => 'X; DROP', 'orderby' => 'name', 'order' => 'ASC' ) ) );
wh_check( $res instanceof WP_Error && 400 === $res->get_error_data()['status'], 'malicious engine 400' );

// Owners directory.
$res  = $tables->owners( wh_req() );
$owns = $res->get_data();
wh_check( ! ( $res instanceof WP_Error ) && isset( $owns['data'][0]['slug'], $owns['data'][0]['count'] ), 'owners envelope carries slug+count' );
$found_wc = null;
foreach ( $owns['data'] as $o ) {
	if ( 'woocommerce' === $o['slug'] ) {
		$found_wc = $o;
	}
	wh_check( isset( $o['name'], $o['installed'], $o['active'] ), 'owner row complete' );
}
wh_check( null !== $found_wc && $found_wc['count'] >= 1, 'woocommerce owner observed with count' );

// Owner scoping: explicit, never silent.
$base = array( 'page' => 1, 'per_page' => 20, 'search' => '', 'classification' => '', 'engine' => '', 'orderby' => 'name', 'order' => 'ASC' );
$res  = $tables->index( wh_req( array_merge( $base, array( 'owner' => 'woocommerce' ) ) ) );
$data = $res->get_data();
$all_owned = true;
foreach ( $data['data'] as $row ) {
	if ( ! isset( $row['classification']['owner'] ) || 'woocommerce' !== strtolower( $row['classification']['owner'] ) ) {
		$all_owned = false;
	}
}
wh_check( $all_owned && count( $data['data'] ) >= 1, 'owner scope keeps only that owner' );

$res = $tables->index( wh_req( array_merge( $base, array( 'owner' => 'WooCommerce' ) ) ) );
wh_check( ! ( $res instanceof WP_Error ) && count( $res->get_data()['data'] ) >= 1, 'owner matching is case-insensitive' );

$res = $tables->index( wh_req( array_merge( $base, array( 'owner' => 'no-such-owner-xyz' ) ) ) );
wh_check( ! ( $res instanceof WP_Error ) && 0 === count( $res->get_data()['data'] ) && 'EXACT' === $res->get_data()['meta']['pagination']['total_mode'], 'unknown owner yields empty exact list, not error' );

$res = $tables->index( wh_req( array_merge( $base, array( 'owner' => 'x; DROP TABLE y' ) ) ) );
wh_check( $res instanceof WP_Error && 400 === $res->get_error_data()['status'], 'malicious owner 400' );
