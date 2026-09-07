<?php
/**
 * Validator / executor / explainer tests (WH-091–WH-093).
 *
 * @package WP_Heart_Tests
 */

use WPHeart\Config\Config;
use WPHeart\Query\Explainer;
use WPHeart\Query\QueryExecutor;
use WPHeart\Query\QueryPolicy;
use WPHeart\Query\QueryValidator;

wh_group( 'QueryEngine' );

$config    = new Config();
$policy    = new QueryPolicy( $config );
$validator = new QueryValidator( $policy, $config );
$adapter   = new FakeAdapter( FakeAdapter::default_tables() );
$executor  = new QueryExecutor( $adapter, $validator, $config );
$explainer = new Explainer( $adapter, $validator );

$v = $validator->validate( 'SELECT * FROM wp_posts' );
wh_check( $v['valid'] && '' !== $v['sql'], 'valid select passes' );
$v = $validator->validate( 'DROP TABLE wp_posts' );
wh_check( ! $v['valid'] && '' !== $v['message'], 'drop rejected with safe message' );
wh_check( false === strpos( $v['message'], 'DROP' ), 'rejection message leaks no SQL' );

$r = $executor->execute( 'SELECT * FROM wp_posts' );
wh_check( $r['success'] && 1 === count( $r['rows'] ), 'executor returns rows' );
wh_check( array( 'ID', 'post_title' ) === $r['columns'], 'columns derived' );
wh_check( isset( $r['elapsed_ms'] ), 'elapsed_ms present' );

$r = $executor->execute( 'DELETE FROM wp_posts' );
wh_check( ! $r['success'] && empty( $r['rows'] ), 'executor refuses delete' );

$adapter->set_fail_read( true );
$r = $executor->execute( 'SELECT * FROM wp_posts' );
wh_check( ! $r['success'] && 'query_error' === $r['code'], 'db error mapped safely' );
wh_check( false === strpos( $r['message'], 'crashed' ), 'raw db error never surfaced' );
$adapter->set_fail_read( false );

$e = $explainer->explain( 'SELECT * FROM wp_posts' );
wh_check( $e['success'] && ! empty( $e['rows'] ), 'explain works for select' );
$e = $explainer->explain( 'SHOW TABLES' );
wh_check( ! $e['success'], 'explain refused for non-select' );
$e = $explainer->explain( 'DROP TABLE t' );
wh_check( ! $e['success'], 'explain refused for destructive' );
