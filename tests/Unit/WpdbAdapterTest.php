<?php
/**
 * WpdbAdapter hardening tests: stale error state must never poison reads.
 *
 * @package WP_Heart_Tests
 */

use WPHeart\Database\WpdbAdapter;

wh_group( 'WpdbAdapter' );

$db = new FakeWpdb();
$db->last_error = 'stale failure from an earlier write';
$adapter = new WpdbAdapter( $db );

$run = $adapter->run_read( 'SELECT 1' );
wh_check( '' === $run['error'], 'stale last_error cleared before read' );
wh_check( '' === $db->last_error, 'wpdb error state reset' );
wh_check( isset( $run['rows'], $run['elapsed_ms'] ), 'read envelope intact' );

// Server info degrades gracefully, never invents values.
$info = $adapter->server_info();
wh_check( 'MariaDB' === $info['engine'], 'engine detected from version string' );
wh_check( 'utf8mb4' === $info['charset'], 'charset passed through' );
