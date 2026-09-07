<?php
/**
 * Read-only policy tests incl. bypass vectors (WH-090, WH-274).
 *
 * @package WP_Heart_Tests
 */

use WPHeart\Config\Config;
use WPHeart\Query\QueryPolicy;

wh_group( 'QueryPolicy' );

$policy = new QueryPolicy( new Config() );

$allowed = array(
	'SELECT * FROM wp_posts',
	'select id from wp_posts where ID = 1',
	'SHOW TABLES',
	'SHOW TABLE STATUS LIKE \'wp_posts\'',
	'DESCRIBE wp_posts',
	'EXPLAIN SELECT * FROM wp_posts',
	"SELECT * FROM t WHERE title = 'it''s; fine -- ok'",
	'SELECT `select` FROM t',
	'WITH cte AS (SELECT 1 AS a) SELECT * FROM cte',
);
foreach ( $allowed as $i => $sql ) {
	$r = $policy->evaluate( $sql );
	wh_check( $r['allowed'], 'allowed #' . $i . ': ' . substr( $sql, 0, 50 ) );
}

// Unbounded SELECT gets a LIMIT appended.
$r = $policy->evaluate( 'SELECT * FROM wp_posts' );
wh_check( false !== stripos( $r['sql'], 'LIMIT' ), 'auto-limit appended' );
$r = $policy->evaluate( 'SELECT * FROM wp_posts LIMIT 10' );
wh_check( 1 === preg_match_all( '/LIMIT/i', $r['sql'], $m ), 'existing limit preserved' );

// Destructive / state-changing statements.
$denied = array(
	'INSERT INTO t VALUES (1)'                    => 'insert',
	'UPDATE t SET a = 1'                          => 'update',
	'DELETE FROM t'                               => 'delete',
	'DROP TABLE t'                                => 'drop',
	'ALTER TABLE t ADD COLUMN c INT'              => 'alter',
	'TRUNCATE TABLE t'                            => 'truncate',
	'CREATE TABLE t (id INT)'                     => 'create',
	'REPLACE INTO t VALUES (1)'                   => 'replace',
	'GRANT SELECT ON t TO x'                      => 'grant',
	'CALL my_proc()'                              => 'call',
	'DO SLEEP(5)'                                 => 'do/sleep',
	'SELECT * FROM t INTO OUTFILE \'/tmp/x\''     => 'into outfile',
	'SELECT LOAD_FILE(\'/etc/passwd\')'           => 'load_file-ish (load)',
	'SELECT * FROM t; DROP TABLE t'               => 'stacked',
	'SELECT 1 -- comment'                         => 'line comment',
	'SELECT 1 # comment'                          => 'hash comment',
	'SELECT /* hidden */ 1'                       => 'block comment',
	'SELECT * FROM t WHERE a = 1; SELECT 2'       => 'second statement',
	"INSERT INTO t SELECT * FROM u"               => 'insert-select',
	'SELECT * FROM t FOR UPDATE'                  => 'locking read',
	'SELECT * FROM t LOCK IN SHARE MODE'          => 'share lock',
	'SELECT BENCHMARK(1000000, MD5(1))'           => 'benchmark',
	'SET @a = 1'                                  => 'set',
	'USE otherdb'                                 => 'use',
	'select * from t procedure analyse()'         => 'procedure',
	'WITH x AS (DELETE FROM t RETURNING *) SELECT * FROM x' => 'cte smuggling delete',
);
foreach ( $denied as $sql => $label ) {
	$r = $policy->evaluate( $sql );
	wh_check( ! $r['allowed'], 'denied: ' . $label );
}

// Keyword inside a string literal must not cause a false allow,
// and must not break the semicolon/comment guards.
$r = $policy->evaluate( "SELECT * FROM t WHERE note = 'drop; -- table'" );
wh_check( $r['allowed'], 'literals neutralized for guards' );

// Empty / oversized.
wh_check( ! $policy->evaluate( '' )['allowed'], 'empty denied' );
wh_check( ! $policy->evaluate( '   ' )['allowed'], 'blank denied' );
$big = 'SELECT * FROM t WHERE a = \'' . str_repeat( 'x', 30000 ) . '\'';
wh_check( ! $policy->evaluate( $big )['allowed'], 'oversized denied' );
