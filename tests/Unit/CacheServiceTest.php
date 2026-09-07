<?php
/**
 * Cache service tests (WH-110–WH-112). Cache is current-state only.
 *
 * @package WP_Heart_Tests
 */

use WPHeart\Cache\CacheService;
use WPHeart\Config\Config;

wh_group( 'CacheService' );

$GLOBALS['wh_transients'] = array();
$cache = new CacheService( new Config() );

$m = $cache->get( 'discovery:v1' );
wh_check( false === $m['hit'] && 'MISS' === $m['freshness'], 'miss shape' );

wh_check( $cache->set( 'discovery:v1', array( 'tables' => array( 1, 2 ) ) ), 'set ok' );
$m = $cache->get( 'discovery:v1' );
wh_check( $m['hit'] && 'FRESH' === $m['freshness'] && 2 === count( $m['value']['tables'] ), 'hit shape' );

wh_check( $cache->delete( 'discovery:v1' ), 'delete ok' );
wh_check( false === $cache->get( 'discovery:v1' )['hit'], 'deleted key misses' );

$cache->set( 'discovery:v1', 1 );
$cache->set( 'health:v1', 2 );
$cache->flush_all();
wh_check( false === $cache->get( 'discovery:v1' )['hit'] && false === $cache->get( 'health:v1' )['hit'], 'flush clears known keys' );

wh_check( 'just now' === CacheService::freshness_label( time() ), 'freshness just now' );
wh_check( '5 min ago' === CacheService::freshness_label( time() - 300 ), 'freshness minutes' );
wh_check( '2 h ago' === CacheService::freshness_label( time() - 7200 ), 'freshness hours' );
wh_check( 0 === strpos( $cache->key( 'discovery:v1' ), 'wp_heart_' ), 'key namespaced' );

// Storage I/O suppresses $wpdb errors and restores the previous state.
$GLOBALS['wpdb'] = new FakeWpdb();
$GLOBALS['wh_transients'] = array();
$cache->set( 'probe:v1', array( 'a' => 1 ), 60 );
$cache->delete( 'probe:v1' );
$cache->get( 'probe:v1' );
$db = $GLOBALS['wpdb'];
wh_check( array( 'on', 'off', 'on', 'off', 'on', 'off' ) === $db->suppress_log, 'error suppression toggled and restored' );
wh_check( false === $db->suppress_state, 'previous error state restored' );
unset( $GLOBALS['wpdb'] );
