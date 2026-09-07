<?php
/**
 * WP-CLI command tests with a stubbed WP_CLI surface.
 *
 * @package WP_Heart_Tests
 */

use WPHeart\Cli\CliTable;
use WPHeart\Cli\HeartCommand;

wh_group( 'Cli' );

if ( ! class_exists( 'WP_CLI' ) ) {
	class WP_CLI {
		public static $logs = array();
		public static function log( $message ) {
			self::$logs[] = (string) $message;
		}
		public static function success( $message ) {
			self::$logs[] = (string) $message;
		}
		public static function error( $message ) {
			throw new Exception( 'cli-error: ' . $message );
		}
	}
}

function wh_cli( HeartCommand $cmd, $method, $args = array(), $assoc = array() ) {
	WP_CLI::$logs = array();
	$cmd->$method( $args, $assoc );
	return implode( "\n", WP_CLI::$logs );
}

$GLOBALS['wh_transients'] = array();
$cmd = new HeartCommand( wh_test_container() );

$out = wh_cli( $cmd, 'overview', array(), array( 'format' => 'json' ) );
$decoded = json_decode( $out, true );
wh_check( is_array( $decoded ) && isset( $decoded['table_count'] ), 'cli overview json' );

$out = wh_cli( $cmd, 'tables', array(), array( 'classification' => 'CORE', 'format' => 'count' ) );
wh_check( is_numeric( trim( $out ) ) && (int) trim( $out ) > 0, 'cli tables count' );

$out = wh_cli( $cmd, 'tables', array(), array( 'owner' => 'woocommerce', 'format' => 'count' ) );
wh_check( is_numeric( trim( $out ) ) && (int) trim( $out ) > 0, 'cli tables owner scope' );

$out = wh_cli( $cmd, 'health', array(), array( 'format' => 'count' ) );
wh_check( is_numeric( trim( $out ) ), 'cli health count' );

$out = wh_cli( $cmd, 'search', array( 'hello' ), array( 'format' => 'count' ) );
wh_check( is_numeric( trim( $out ) ), 'cli search count' );

$thrown = false;
try {
	wh_cli( $cmd, 'search', array( 'x' ), array() );
} catch ( Exception $e ) {
	$thrown = 0 === strpos( $e->getMessage(), 'cli-error:' );
}
wh_check( $thrown, 'cli surfaces validation errors' );

$out = wh_cli( $cmd, 'cron', array(), array( 'format' => 'json' ) );
$decoded = json_decode( $out, true );
wh_check( is_array( $decoded ) && isset( $decoded['wp_cron'], $decoded['action_scheduler'] ), 'cli cron json' );

$out = wh_cli( $cmd, 'autoload', array(), array( 'format' => 'count' ) );
wh_check( '88867' === trim( $out ), 'cli autoload count' );

// Table renderer.
$table = CliTable::render( array( 'name', 'rows' ), array( array( 'wp_posts', 100 ), array( 'x', 2 ) ) );
wh_check( false !== strpos( $table, 'wp_posts' ) && false !== strpos( $table, '+--' ), 'ascii table renders' );
$table = CliTable::render( array( 'a' ), array() );
wh_check( false !== strpos( $table, 'a' ), 'empty table renders headers' );

// Snapshot commands end to end (isolated temp store).
$out = wh_cli( $cmd, 'snapshot_create', array(), array( 'label' => 'cli snap' ) );
wh_check( 1 === preg_match( '/snapshot (\S+) captured/', $out, $m ), 'cli snapshot created' );
$snap_id = $m[1];
$out = wh_cli( $cmd, 'snapshots', array(), array() );
wh_check( false !== strpos( $out, $snap_id ), 'cli snapshots list' );
$out = wh_cli( $cmd, 'snapshot_diff', array( $snap_id, $snap_id ), array() );
wh_check( 'added=0 removed=0 changed=0' === trim( $out ), 'cli self-diff empty' );
$out = wh_cli( $cmd, 'snapshot_delete', array( $snap_id ), array() );
wh_check( false !== strpos( $out, $snap_id ), 'cli snapshot deleted' );
