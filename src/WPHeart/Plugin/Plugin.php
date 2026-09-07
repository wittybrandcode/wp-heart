<?php
/**
 * Plugin bootstrap: wires the container and hooks (WH-001, WH-003).
 *
 * @package WP_Heart
 */

namespace WPHeart\Plugin;

use WPHeart\Audit\AuditLogger;
use WPHeart\Audit\AuditStore;
use WPHeart\Cache\CacheService;
use WPHeart\Classification\ClassificationEngine;
use WPHeart\Config\Config;
use WPHeart\Container\Container;
use WPHeart\Database\CapabilityInspector;
use WPHeart\Database\IdentifierValidator;
use WPHeart\Database\WpdbAdapter;
use WPHeart\Diagnostics\HealthEngine;
use WPHeart\Discovery\DatabaseService;
use WPHeart\Discovery\TableDiscovery;
use WPHeart\Intelligence\ConfidenceScorer;
use WPHeart\Intelligence\CoreTableRecognizer;
use WPHeart\Intelligence\EntityExporter;
use WPHeart\Intelligence\OrphanDetector;
use WPHeart\Intelligence\PluginRegistry;
use WPHeart\Intelligence\PluginTableDetector;
use WPHeart\Intelligence\Sweeper;
use WPHeart\Intelligence\ThemeDetector;
use WPHeart\Intelligence\WpContextService;
use WPHeart\Intelligence\AIEngine;
use WPHeart\Query\Explainer;
use WPHeart\Query\QueryExecutor;
use WPHeart\Query\QueryPolicy;
use WPHeart\Query\QueryValidator;
use WPHeart\Rest\RestBootstrap;
use WPHeart\Schema\ColumnInspector;
use WPHeart\Schema\ConstraintInspector;
use WPHeart\Schema\IndexInspector;
use WPHeart\Schema\RelationshipEngine;
use WPHeart\Snapshot\SnapshotService;
use WPHeart\Snapshot\SnapshotStore;
use WPHeart\WordPress\CronInspector;
use WPHeart\WordPress\OptionsInspector;
use WPHeart\WordPress\SiteHealth;
use WPHeart\Search\SearchEngine;
use WPHeart\Security\Capabilities;

/**
 * Plugin bootstrap: wires the container and hooks (WH-001, WH-003).
 */
class Plugin {
	/** @var Plugin|null */
	private static $instance = null;
	/** @var Container */
	private $container;

	/**
	 * @return Plugin
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Resettable for tests.
	 */
	public static function reset() {
		self::$instance = null;
	}

	/**
	 * Wire services and hooks.
	 */
	private function boot() {
		$config = Config::load();
		$c      = new Container();

		$c->singleton(
			'config',
			static function () use ( $config ) {
				return $config;
			}
		);
		$c->singleton(
			'db.adapter',
			static function () {
				global $wpdb;
				return new WpdbAdapter( $wpdb );
			}
		);
		$c->singleton(
			'db.identifiers',
			static function () {
				return new IdentifierValidator();
			}
		);
		$c->singleton(
			'db.privileges',
			static function ( $c ) {
				return new CapabilityInspector( $c->make( 'db.adapter' ) );
			}
		);
		$c->singleton(
			'cache',
			static function ( $c ) {
				return new CacheService( $c->make( 'config' ) );
			}
		);
		$c->singleton(
			'wp.context',
			static function () {
				return new WpContextService();
			}
		);
		$c->singleton(
			'intel.core',
			static function ( $c ) {
				return new CoreTableRecognizer( $c->make( 'wp.context' ) );
			}
		);
		$c->singleton(
			'intel.plugins',
			static function () {
				return new PluginRegistry();
			}
		);
		$c->singleton(
			'intel.plugin_tables',
			static function ( $c ) {
				return new PluginTableDetector( $c->make( 'intel.plugins' ), $c->make( 'wp.context' ) );
			}
		);
		$c->singleton(
			'intel.themes',
			static function ( $c ) {
				return new ThemeDetector( $c->make( 'wp.context' ) );
			}
		);
		$c->singleton(
			'intel.confidence',
			static function () {
				return new ConfidenceScorer();
			}
		);
		$c->singleton(
			'intel.sweeper',
			static function ( $c ) {
				return new Sweeper( $c );
			}
		);
		$c->singleton(
			'intel.exporter',
			static function ( $c ) {
				return new EntityExporter( $c );
			}
		);
		$c->singleton(
			'intel.orphans',
			static function ( $c ) {
				return new OrphanDetector( $c->make( 'intel.plugins' ), $c->make( 'wp.context' ) );
			}
		);
		$c->singleton(
			'classifier',
			static function ( $c ) {
				return new ClassificationEngine(
					$c->make( 'intel.core' ),
					$c->make( 'intel.plugin_tables' ),
					$c->make( 'intel.themes' ),
					$c->make( 'intel.orphans' ),
					$c->make( 'intel.confidence' )
				);
			}
		);
		$c->singleton(
			'discovery',
			static function ( $con ) {
				return new TableDiscovery( $con->make( 'db.adapter' ), $con->make( 'db.identifiers' ), $con->make( 'cache' ) );
			}
		);
		$c->singleton(
			'database.service',
			static function ( $con ) {
				return new DatabaseService( $con->make( 'db.adapter' ), $con->make( 'discovery' ), $con->make( 'db.privileges' ) );
			}
		);
		$c->singleton(
			'schema.columns',
			static function ( $con ) {
				return new ColumnInspector( $con->make( 'db.adapter' ), $con->make( 'db.identifiers' ) );
			}
		);
		$c->singleton(
			'schema.indexes',
			static function ( $con ) {
				return new IndexInspector( $con->make( 'db.adapter' ), $con->make( 'db.identifiers' ) );
			}
		);
		$c->singleton(
			'schema.constraints',
			static function ( $con ) {
				return new ConstraintInspector( $con->make( 'db.adapter' ), $con->make( 'db.identifiers' ) );
			}
		);
		$c->singleton(
			'schema.relationships',
			static function ( $con ) {
				return new RelationshipEngine( $con->make( 'wp.context' ) );
			}
		);
		$c->singleton(
			'health',
			static function ( $con ) {
				return HealthEngine::with_defaults(
					$con->make( 'schema.indexes' ),
					$con->make( 'intel.core' ),
					$con->make( 'wp.context' )
				);
			}
		);
		$c->singleton(
			'options',
			static function ( $con ) {
				return new OptionsInspector( $con->make( 'db.adapter' ) );
			}
		);
		$c->singleton(
			'cron.inspector',
			static function ( $con ) {
				return new CronInspector( $con->make( 'db.adapter' ), $con->make( 'wp.context' ) );
			}
		);
		$c->singleton(
			'snapshots.store',
			static function () {
				return new SnapshotStore();
			}
		);
		$c->singleton(
			'snapshots.service',
			static function ( $con ) {
				return new SnapshotService(
					$con->make( 'discovery' ),
					$con->make( 'schema.columns' ),
					$con->make( 'schema.indexes' ),
					$con->make( 'schema.constraints' ),
					$con->make( 'classifier' ),
					$con->make( 'snapshots.store' )
				);
			}
		);
		$c->singleton(
			'query.policy',
			static function ( $con ) {
				return new QueryPolicy( $con->make( 'config' ) );
			}
		);
		$c->singleton(
			'query.validator',
			static function ( $con ) {
				return new QueryValidator( $con->make( 'query.policy' ), $con->make( 'config' ) );
			}
		);
		$c->singleton(
			'query.executor',
			static function ( $con ) {
				return new QueryExecutor( $con->make( 'db.adapter' ), $con->make( 'query.validator' ), $con->make( 'config' ) );
			}
		);
		$c->singleton(
			'query.explainer',
			static function ( $con ) {
				return new Explainer( $con->make( 'db.adapter' ), $con->make( 'query.validator' ) );
			}
		);
		$c->singleton(
			'search',
			static function ( $con ) {
				return new SearchEngine( $con->make( 'db.adapter' ), $con->make( 'db.identifiers' ), $con->make( 'schema.columns' ), $con->make( 'config' ) );
			}
		);
		$c->singleton(
			'audit.store',
			static function ( $con ) {
				return new AuditStore( $con->make( 'config' ) );
			}
		);
		$c->singleton(
			'audit',
			static function ( $con ) {
				return new AuditLogger( $con->make( 'audit.store' ) );
			}
		);
		$c->singleton(
			'ai.engine',
			static function () {
				return new AIEngine();
			}
		);

		$this->container = $c;

		add_action( 'admin_menu', array( 'WPHeart\\Plugin\\AdminMenu', 'register' ) );
		add_action( 'admin_enqueue_scripts', array( 'WPHeart\\Plugin\\Assets', 'enqueue' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest' ) );
		add_filter( 'site_status_tests', array( $this, 'site_health_tests' ) );
		add_action( 'wp_heart_daily_cleanup', array( $this->container->make( 'cache' ), 'cleanup_expired' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
			\WP_CLI::add_command( 'heart', 'WPHeart\\Cli\\HeartCommand' );
		}
	}

	/**
	 * Register REST routes.
	 */
	public function register_rest() {
		$rest = new RestBootstrap( $this->container );
		$rest->register();
	}

	/**
	 * Append WP-HEART tests to the Site Health suite.
	 *
	 * @param array $tests Existing tests.
	 * @return array
	 */
	public function site_health_tests( $tests ) {
		$health = new SiteHealth( $this->container->make( 'options' ) );
		return $health->tests( is_array( $tests ) ? $tests : array() );
	}

	/**
	 * @param string $id Service id.
	 * @return mixed
	 */
	public static function service( $id ) {
		$instance = self::$instance ? self::$instance : self::init();
		return $instance->container->make( $id );
	}

	/**
	 * @return string[]
	 */
	public static function capabilities() {
		return Capabilities::all();
	}
}
