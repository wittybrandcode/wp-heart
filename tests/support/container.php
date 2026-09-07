<?php
/**
 * Shared fake-backed container for controller/command tests.
 * Loaded by tests/run.php before any test file.
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
use WPHeart\Schema\ColumnInspector;
use WPHeart\Schema\ConstraintInspector;
use WPHeart\Schema\IndexInspector;
use WPHeart\Schema\RelationshipEngine;
use WPHeart\Search\SearchEngine;
use WPHeart\Snapshot\SnapshotService;
use WPHeart\Snapshot\SnapshotStore;
use WPHeart\WordPress\CronInspector;
use WPHeart\WordPress\OptionsInspector;

if ( ! class_exists( 'RowWpdb' ) ) {
	class RowWpdb extends FakeWpdb {
		public function get_row( $sql, $output = ARRAY_A ) {
			if ( false !== strpos( $sql, 'wp_posts' ) && false !== strpos( $sql, "'1'" ) ) {
				return array( 'ID' => '1', 'post_author' => '1', 'post_title' => 'Hello' );
			}
			return null;
		}
	}
}

if ( ! function_exists( 'wh_test_container' ) ) {
	function wh_test_container() {
		$config = new Config();
		$c      = new Container();
		$c->singleton( 'config', static function () use ( $config ) { return $config; } );
		$c->singleton( 'db.adapter', static function () { return new FakeAdapter( FakeAdapter::default_tables() ); } );
		$c->singleton( 'db.identifiers', static function () { return new IdentifierValidator(); } );
		$c->singleton( 'cache', static function ( $c ) { return new CacheService( $c->make( 'config' ) ); } );
		$c->singleton( 'wp.context', static function () { return new WpContextService( 'wp_', false, 1 ); } );
		$c->singleton( 'intel.core', static function ( $c ) { return new CoreTableRecognizer( $c->make( 'wp.context' ) ); } );
		$c->singleton( 'intel.plugins', static function () { return new PluginRegistry(); } );
		$c->singleton(
			'intel.plugin_tables',
			static function ( $c ) {
				return new PluginTableDetector( $c->make( 'intel.plugins' ), $c->make( 'wp.context' ) );
			}
		);
		$c->singleton( 'intel.themes', static function ( $c ) { return new ThemeDetector( $c->make( 'wp.context' ) ); } );
		$c->singleton( 'intel.confidence', static function () { return new ConfidenceScorer(); } );
		$c->singleton(
			'intel.orphans',
			static function ( $c ) {
				return new OrphanDetector( $c->make( 'intel.plugins' ), $c->make( 'wp.context' ) );
			}
		);
		$c->singleton(
			'classifier',
			static function ( $c ) {
				return new ClassificationEngine( $c->make( 'intel.core' ), $c->make( 'intel.plugin_tables' ), $c->make( 'intel.themes' ), $c->make( 'intel.orphans' ), $c->make( 'intel.confidence' ) );
			}
		);
		$c->singleton( 'discovery', static function ( $c ) { return new TableDiscovery( $c->make( 'db.adapter' ), $c->make( 'db.identifiers' ), $c->make( 'cache' ) ); } );
		$c->singleton( 'schema.columns', static function ( $c ) { return new ColumnInspector( $c->make( 'db.adapter' ), $c->make( 'db.identifiers' ) ); } );
		$c->singleton( 'schema.indexes', static function ( $c ) { return new IndexInspector( $c->make( 'db.adapter' ), $c->make( 'db.identifiers' ) ); } );
		$c->singleton( 'schema.constraints', static function ( $c ) { return new ConstraintInspector( $c->make( 'db.adapter' ), $c->make( 'db.identifiers' ) ); } );
		$c->singleton( 'schema.relationships', static function ( $c ) { return new RelationshipEngine( $c->make( 'wp.context' ) ); } );
		$c->singleton( 'query.policy', static function ( $c ) { return new QueryPolicy( $c->make( 'config' ) ); } );
		$c->singleton( 'query.validator', static function ( $c ) { return new QueryValidator( $c->make( 'query.policy' ), $c->make( 'config' ) ); } );
		$c->singleton( 'query.executor', static function ( $c ) { return new QueryExecutor( $c->make( 'db.adapter' ), $c->make( 'query.validator' ), $c->make( 'config' ) ); } );
		$c->singleton( 'query.explainer', static function ( $c ) { return new Explainer( $c->make( 'db.adapter' ), $c->make( 'query.validator' ) ); } );
		$c->singleton( 'search', static function ( $c ) { return new SearchEngine( $c->make( 'db.adapter' ), $c->make( 'db.identifiers' ), $c->make( 'schema.columns' ), $c->make( 'config' ), $c->make( 'schema.indexes' ) ); } );
		$c->singleton( 'audit.store', static function ( $c ) { return new AuditStore( $c->make( 'config' ) ); } );
		$c->singleton( 'audit', static function ( $c ) { return new AuditLogger( $c->make( 'audit.store' ) ); } );
		$c->singleton( 'db.privileges', static function ( $c ) { return new CapabilityInspector( $c->make( 'db.adapter' ) ); } );
		$c->singleton( 'database.service', static function ( $c ) { return new DatabaseService( $c->make( 'db.adapter' ), $c->make( 'discovery' ), $c->make( 'db.privileges' ) ); } );
		$c->singleton(
			'health',
			static function ( $c ) {
				return HealthEngine::with_defaults( $c->make( 'schema.indexes' ), $c->make( 'intel.core' ), $c->make( 'wp.context' ) );
			}
		);
		$c->singleton( 'options', static function ( $c ) { return new OptionsInspector( $c->make( 'db.adapter' ) ); } );
		$c->singleton( 'cron.inspector', static function ( $c ) { return new CronInspector( $c->make( 'db.adapter' ), $c->make( 'wp.context' ) ); } );
		$c->singleton(
			'snapshots.store',
			static function () {
				$dir = sys_get_temp_dir() . '/wh-snap-tests';
				if ( ! is_dir( $dir ) ) {
					mkdir( $dir, 0777, true );
				}
				return new SnapshotStore( $dir );
			}
		);
		$c->singleton(
			'snapshots.service',
			static function ( $c ) {
				return new SnapshotService(
					$c->make( 'discovery' ),
					$c->make( 'schema.columns' ),
					$c->make( 'schema.indexes' ),
					$c->make( 'schema.constraints' ),
					$c->make( 'classifier' ),
					$c->make( 'snapshots.store' )
				);
			}
		);
		return $c;
	}
}

if ( ! function_exists( 'wh_req' ) ) {
	function wh_req( $params = array(), $route = '' ) {
		return new WP_REST_Request( $params, $route );
	}
}
