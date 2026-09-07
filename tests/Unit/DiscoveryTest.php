<?php
/**
 * Discovery + database service tests (WH-020–WH-023).
 *
 * @package WP_Heart_Tests
 */

use WPHeart\Cache\CacheService;
use WPHeart\Config\Config;
use WPHeart\Database\CapabilityInspector;
use WPHeart\Database\IdentifierValidator;
use WPHeart\Discovery\DatabaseService;
use WPHeart\Discovery\TableDiscovery;
use WPHeart\Support\Accuracy;

wh_group( 'Discovery' );

$GLOBALS['wh_transients'] = array();
$config     = new Config();
$adapter    = new FakeAdapter( FakeAdapter::default_tables() );
$discovery  = new TableDiscovery( $adapter, new IdentifierValidator(), new CacheService( $config ) );

$r1 = $discovery->discover();
wh_check( 8 === $r1['count'], 'all fixture tables discovered' );
wh_check( Accuracy::EXACT === $r1['freshness'], 'fresh discovery is EXACT' );

$r2 = $discovery->discover();
wh_check( Accuracy::CACHED === $r2['freshness'], 'second discovery served from cache' );

$r3 = $discovery->discover( true );
wh_check( Accuracy::EXACT === $r3['freshness'], 'explicit refresh bypasses cache' );

// Unknown tables preserved with estimated metadata.
$by_name = array();
foreach ( $r1['tables'] as $t ) {
	$by_name[ $t->name() ] = $t->to_array();
}
wh_check( isset( $by_name['wp_xyz_mystery'] ), 'unknown table preserved' );
wh_check( Accuracy::ESTIMATED === $by_name['wp_postmeta']['metadata']['rows_mode'], 'innodb rows are ESTIMATED' );
wh_check( 215000 === $by_name['wp_postmeta']['metadata']['estimated_rows'], 'row estimate passes through' );

// Empty database edge case.
$empty = new TableDiscovery( new FakeAdapter( array() ), new IdentifierValidator(), new CacheService( $config ) );
$GLOBALS['wh_transients'] = array();
$re = $empty->discover( true );
wh_check( 0 === $re['count'] && array() === $re['tables'], 'empty database yields empty set, not error' );

// Allowlist verification on get_table.
$GLOBALS['wh_transients'] = array();
wh_check( null !== $discovery->get_table( 'wp_posts' ), 'known table resolves' );
wh_check( null === $discovery->get_table( 'wp_nope' ), 'unknown name rejected' );
wh_check( null === $discovery->get_table( 'wp_posts; DROP TABLE x' ), 'malicious name rejected' );

// Database summary aggregates without row reads.
$GLOBALS['wh_transients'] = array();
$service = new DatabaseService( $adapter, $discovery, new CapabilityInspector( $adapter ) );
$s       = $service->summary()->to_array();
wh_check( 'wp' === $s['name'], 'db name without credentials' );
wh_check( 8 === $s['table_count'] && Accuracy::EXACT === $s['table_count_mode'], 'exact table count' );
wh_check( Accuracy::ESTIMATED === $s['estimated_size_mode'] && $s['estimated_size'] > 0, 'estimated total size' );
wh_check( 'wp_postmeta' === $s['largest_tables'][0]['name'], 'largest table first' );
wh_check( false !== strpos( $s['collation'], 'utf8mb4' ), 'collation reported' );

// Privilege inspector: graceful UNKNOWN when grants hidden.
$priv = new CapabilityInspector( new FakeAdapter( FakeAdapter::default_tables(), null ) );
$i    = $priv->inspect();
wh_check( 'UNKNOWN' === $i['status'] && 'root@localhost' === $i['current_user'], 'hidden grants degrade to UNKNOWN' );

$priv = new CapabilityInspector( new FakeAdapter( FakeAdapter::default_tables(), 'GRANT ALL PRIVILEGES ON *.* TO root' ) );
$i    = $priv->inspect();
wh_check( 'FULL' === $i['status'], 'full grants detected' );

$priv = new CapabilityInspector( new FakeAdapter( FakeAdapter::default_tables(), 'GRANT SELECT ON wp.* TO reader' ) );
$i    = $priv->inspect();
wh_check( 'LIMITED_VISIBLE' === $i['status'], 'limited grants detected' );
