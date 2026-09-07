<?php
/**
 * Audit store tests (WH-160–WH-162): ring buffer + secret redaction.
 *
 * @package WP_Heart_Tests
 */

use WPHeart\Audit\AuditLogger;
use WPHeart\Audit\AuditStore;
use WPHeart\Config\Config;
use WPHeart\Domain\AuditEvent;

wh_group( 'AuditStore' );

$GLOBALS['wh_options'] = array();
$store  = new AuditStore( new Config( array( 'audit_max_entries' => 5 ) ) );
$logger = new AuditLogger( $store );

$logger->log( AuditEvent::TABLE_VIEW, 'wp_posts', array( 'page' => 1 ) );
$logger->log(
	AuditEvent::QUERY_EXECUTION,
	'ad-hoc',
	array( 'row_count' => 3, 'api_token' => 'abc123', 'note' => str_repeat( 'z', 500 ) )
);

$q = $store->query( array( 'page' => 1, 'per_page' => 20 ) );
wh_check( 2 === count( $q['items'] ), 'events stored' );
wh_check( AuditEvent::QUERY_EXECUTION === $q['items'][0]['type'], 'newest first' );
wh_check( '[redacted]' === $q['items'][0]['metadata']['api_token'], 'secrets redacted' );
wh_check( 201 >= mb_strlen( $q['items'][0]['metadata']['note'] ), 'long metadata truncated' );
wh_check( 'EXACT' === $q['pagination']['total_mode'], 'audit totals are exact' );

// Ring buffer cap.
for ( $i = 0; $i < 10; $i++ ) {
	$logger->log( AuditEvent::SEARCH, 't' . $i );
}
$q = $store->query( array( 'page' => 1, 'per_page' => 50 ) );
wh_check( 5 === count( $q['items'] ), 'ring buffer capped' );

// Type filter validation support.
$q = $store->query( array( 'type' => AuditEvent::SEARCH ) );
wh_check( 5 === count( $q['items'] ), 'type filter works' );
