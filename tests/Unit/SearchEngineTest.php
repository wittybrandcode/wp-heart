<?php
/**
 * Search engine tests (WH-140): bounded, validated, attributed.
 *
 * @package WP_Heart_Tests
 */

use WPHeart\Config\Config;
use WPHeart\Database\IdentifierValidator;
use WPHeart\Schema\ColumnInspector;
use WPHeart\Schema\IndexInspector;
use WPHeart\Search\SearchEngine;

wh_group( 'SearchEngine' );

$config  = new Config();
$adapter = new FakeAdapter( FakeAdapter::default_tables() );
$engine  = new SearchEngine( $adapter, new IdentifierValidator(), new ColumnInspector( $adapter, new IdentifierValidator() ), $config, new IndexInspector( $adapter, new IdentifierValidator() ) );

$r = $engine->search( 'x' );
wh_check( isset( $r['error'] ) && 'term_too_short' === $r['error'], 'short term rejected' );

$r = $engine->search( str_repeat( 'y', 101 ) );
wh_check( isset( $r['error'] ) && 'term_too_long' === $r['error'], 'long term rejected' );

$r = $engine->search( 'hello' );
wh_check( ! isset( $r['error'] ), 'valid term searches' );
wh_check( isset( $r['items'][0]['table'], $r['items'][0]['column'], $r['items'][0]['preview'] ), 'results carry source attribution' );
wh_check( 'ESTIMATED' === $r['pagination']['total_mode'], 'search totals are ESTIMATED' );

// Scope validation: unknown tables are dropped, not executed.
$r = $engine->search( 'hello', array( 'tables' => array( 'wp_posts', 'nope; DROP TABLE x' ) ) );
wh_check( 1 === $r['tables_searched'], 'malicious scope entries dropped' );

// Preview safety.
wh_check( 'NULL' === $engine->preview( null ), 'null preview' );
wh_check( '[binary data]' === $engine->preview( "a\x00b" ), 'binary preview guarded' );
$long = $engine->preview( str_repeat( 'a', 500 ) );
wh_check( 201 >= mb_strlen( $long ), 'long preview truncated' );
