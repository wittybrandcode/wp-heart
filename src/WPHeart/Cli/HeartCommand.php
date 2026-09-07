<?php
/**
 * WP-CLI commands: `wp heart <overview|tables|health|search|cron|autoload>`.
 * Thin wrappers over the same controllers/services as REST — read-only,
 * bounded, and safe to run on production data.
 *
 * @package WP_Heart
 */

namespace WPHeart\Cli;

use WPHeart\Container\Container;
use WPHeart\Plugin\Plugin;
use WPHeart\Rest\Controllers\CronController;
use WPHeart\Rest\Controllers\DatabaseController;
use WPHeart\Rest\Controllers\HealthController;
use WPHeart\Rest\Controllers\SearchController;
use WPHeart\Rest\Controllers\TablesController;

/**
 * WP-CLI commands for the database observatory.
 */
class HeartCommand {
	/** @var Container|null */
	private $container;

	/**
	 * @param Container|null $container Container override (tests).
	 */
	public function __construct( $container = null ) {
		$this->container = $container;
		
		// Ensure explicit $_SERVER superglobals to avoid misleading 
		// dead_db / ms_not_installed errors on multisite CLI iterations.
		if ( ! isset( $_SERVER['SERVER_NAME'] ) ) {
			$_SERVER['SERVER_NAME'] = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost';
		}
		if ( ! isset( $_SERVER['HTTP_HOST'] ) ) {
			$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'];
		}
	}

	/**
	 * @param string $id Service id.
	 * @return mixed
	 */
	private function svc( $id ) {
		if ( $this->container ) {
			return $this->container->make( $id );
		}
		return Plugin::service( $id );
	}

	/**
	 * @return Container
	 */
	private function container() {
		if ( $this->container ) {
			return $this->container;
		}
		return $this->build_container();
	}

	/**
	 * Rebuild a container from live services when none was injected.
	 * Only used for controller construction; services resolve lazily.
	 *
	 * @return Container
	 */
	private function build_container() {
		$c = new Container();
		foreach ( array( 'config', 'db.adapter', 'db.identifiers', 'cache', 'wp.context', 'intel.core', 'intel.plugins', 'intel.plugin_tables', 'intel.themes', 'intel.confidence', 'intel.orphans', 'classifier', 'discovery', 'database.service', 'db.privileges', 'schema.columns', 'schema.indexes', 'schema.constraints', 'schema.relationships', 'health', 'options', 'cron.inspector', 'search', 'audit.store', 'audit', 'query.policy', 'query.validator', 'query.executor' ) as $id ) {
			$service_id = $id;
			$c->singleton(
				$id,
				static function () use ( $service_id ) {
					return Plugin::service( $service_id );
				}
			);
		}
		return $c;
	}

	/**
	 * Show the database overview.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. table, json, or count. Default: table.
	 *
	 * @param array $args Positional args.
	 * @param array $assoc Associative args.
	 */
	public function overview( $args, $assoc ) {
		$controller = new DatabaseController( $this->container() );
		$response   = $controller->overview( $this->request() );
		$this->emit_envelope( $response, $assoc, 'overview' );
	}

	/**
	 * List tables with optional filters.
	 *
	 * ## OPTIONS
	 *
	 * [--classification=<type>]
	 * : Filter by classification.
	 *
	 * [--owner=<slug>]
	 * : Filter by owner plugin slug.
	 *
	 * [--engine=<engine>]
	 * : Filter by storage engine.
	 *
	 * [--search=<term>]
	 * : Filter by table name fragment.
	 *
	 * [--format=<format>]
	 * : Output format. table, json, or count. Default: table.
	 *
	 * @param array $args Positional args.
	 * @param array $assoc Associative args.
	 */
	public function tables( $args, $assoc ) {
		$params     = array(
			'page'           => 1,
			'per_page'       => 100,
			'search'         => isset( $assoc['search'] ) ? $assoc['search'] : '',
			'classification' => isset( $assoc['classification'] ) ? $assoc['classification'] : '',
			'owner'          => isset( $assoc['owner'] ) ? $assoc['owner'] : '',
			'engine'         => isset( $assoc['engine'] ) ? $assoc['engine'] : '',
			'orderby'        => 'name',
			'order'          => 'ASC',
		);
		$controller = new TablesController( $this->container() );
		$response   = $controller->index( $this->request( $params ) );
		$this->emit_items(
			$response,
			$assoc,
			array( 'name', 'classification', 'rows' ),
			static function ( $row ) {
				$cls = isset( $row['classification'] ) ? $row['classification'] : array();
				return array(
					$row['name'],
					( isset( $cls['type'] ) ? $cls['type'] : '?' ) . '/' . ( isset( $cls['owner'] ) && $cls['owner'] ? $cls['owner'] : '-' ),
					isset( $row['metadata']['estimated_rows'] ) ? $row['metadata']['estimated_rows'] : '?',
				);
			}
		);
	}

	/**
	 * Show health diagnostics.
	 *
	 * ## OPTIONS
	 *
	 * [--severity=<level>]
	 * : Filter by severity.
	 *
	 * [--format=<format>]
	 * : Output format. table, json, or count. Default: table.
	 *
	 * @param array $args Positional args.
	 * @param array $assoc Associative args.
	 */
	public function health( $args, $assoc ) {
		$controller = new HealthController( $this->container() );
		$response   = $controller->issues(
			$this->request( array( 'severity' => isset( $assoc['severity'] ) ? $assoc['severity'] : '' ) )
		);
		$this->emit_items(
			$response,
			$assoc,
			array( 'severity', 'id' ),
			static function ( $issue ) {
				return array( $issue['severity'], $issue['id'] );
			}
		);
	}

	/**
	 * Search the database (bounded, schema-aware).
	 *
	 * ## OPTIONS
	 *
	 * <q>
	 * : Search term (2-100 characters).
	 *
	 * [--tables=<list>]
	 * : Comma-separated table scope.
	 *
	 * [--format=<format>]
	 * : Output format. table, json, or count. Default: table.
	 *
	 * @param array $args Positional args.
	 * @param array $assoc Associative args.
	 */
	public function search( $args, $assoc ) {
		$params     = array(
			'q'        => isset( $args[0] ) ? $args[0] : '',
			'tables'   => isset( $assoc['tables'] ) ? $assoc['tables'] : '',
			'page'     => 1,
			'per_page' => 20,
		);
		$controller = new SearchController( $this->container() );
		$response   = $controller->search( $this->request( $params ) );
		$this->emit_items(
			$response,
			$assoc,
			array( 'table', 'column', 'preview' ),
			static function ( $match ) {
				$preview = (string) $match['preview'];
				if ( function_exists( 'mb_substr' ) ) {
					$preview = mb_substr( $preview, 0, 60 );
				} else {
					$preview = substr( $preview, 0, 60 );
				}
				return array( $match['table'], $match['column'], $preview );
			}
		);
	}

	/**
	 * Show scheduled-task status (wp-cron + Action Scheduler).
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. table, json, or count. Default: table.
	 *
	 * @param array $args Positional args.
	 * @param array $assoc Associative args.
	 */
	public function cron( $args, $assoc ) {
		$controller = new CronController( $this->container() );
		$response   = $controller->status( $this->request() );
		$data       = $this->unwrap( $response );
		$format     = isset( $assoc['format'] ) ? $assoc['format'] : 'table';
		if ( 'json' === $format ) {
			self::cli_log( wp_json_encode( $data ) );
			return;
		}
		$wp_cron = $data['wp_cron'];
		$as      = $data['action_scheduler'];
		self::cli_log( sprintf( 'wp-cron: %d events, %d overdue', $wp_cron['total'], $wp_cron['overdue'] ) );
		self::cli_log( sprintf( 'action-scheduler: %s', $as['available'] ? 'available' : 'not detected' ) );
		if ( $as['available'] ) {
			foreach ( $as['by_status'] as $status => $count ) {
				self::cli_log( sprintf( '  %s: %d', $status, $count ) );
			}
		}
	}

	/**
	 * Show the autoload footprint.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. table, json, or count. Default: table.
	 *
	 * @param array $args Positional args.
	 * @param array $assoc Associative args.
	 */
	public function autoload( $args, $assoc ) {
		$summary = $this->svc( 'options' )->autoload_summary( 10 );
		$format  = isset( $assoc['format'] ) ? $assoc['format'] : 'table';
		if ( 'json' === $format ) {
			self::cli_log( wp_json_encode( $summary ) );
			return;
		}
		if ( 'count' === $format ) {
			self::cli_log( (string) $summary['bytes'] );
			return;
		}
		self::cli_log( sprintf( '%d bytes across %d autoloaded options', $summary['bytes'], $summary['count'] ) );
		$rows = array();
		foreach ( $summary['top'] as $row ) {
			$rows[] = array( $row['name'], $row['bytes'] );
		}
		self::cli_log( CliTable::render( array( 'option', 'bytes' ), $rows ) );
	}

	/**
	 * Capture a metadata snapshot of the current database.
	 *
	 * ## OPTIONS
	 *
	 * [--label=<label>]
	 * : Human label for the snapshot.
	 *
	 * @param array $args Positional args.
	 * @param array $assoc Associative args.
	 */
	public function snapshot_create( $args, $assoc ) {
		$snapshot = $this->svc( 'snapshots.service' )->capture( isset( $assoc['label'] ) ? $assoc['label'] : '' );
		if ( ! $snapshot ) {
			self::cli_error( 'Snapshot storage is unavailable.' );
		}
		self::cli_log( sprintf( 'snapshot %s captured (%d tables)', $snapshot->id(), count( $snapshot->to_array()['tables'] ) ) );
	}

	/**
	 * List stored snapshots.
	 *
	 * @param array $args Positional args.
	 * @param array $assoc Associative args.
	 */
	public function snapshots( $args, $assoc ) {
		$list = $this->svc( 'snapshots.store' )->list();
		$rows = array();
		foreach ( $list as $entry ) {
			$rows[] = array( $entry['id'], isset( $entry['label'] ) ? $entry['label'] : '', isset( $entry['tables'] ) ? $entry['tables'] : 0 );
		}
		self::cli_log( CliTable::render( array( 'id', 'label', 'tables' ), $rows ) );
	}

	/**
	 * Delete a snapshot.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Snapshot id.
	 *
	 * @param array $args Positional args.
	 * @param array $assoc Associative args.
	 */
	public function snapshot_delete( $args, $assoc ) {
		$id = isset( $args[0] ) ? (string) $args[0] : '';
		if ( '' === $id ) {
			self::cli_error( 'Snapshot id is required.' );
		}
		$this->svc( 'snapshots.store' )->delete( $id );
		self::cli_log( 'deleted ' . $id );
	}

	/**
	 * Compare two snapshots (Added / Removed / Changed).
	 *
	 * ## OPTIONS
	 *
	 * <a>
	 * : Older snapshot id.
	 *
	 * <b>
	 * : Newer snapshot id.
	 *
	 * @param array $args Positional args.
	 * @param array $assoc Associative args.
	 */
	public function snapshot_diff( $args, $assoc ) {
		if ( ! isset( $args[0], $args[1] ) ) {
			self::cli_error( 'Two snapshot ids are required.' );
		}
		$store = $this->svc( 'snapshots.store' );
		$a     = $store->load( (string) $args[0] );
		$b     = $store->load( (string) $args[1] );
		if ( ! $a || ! $b ) {
			self::cli_error( 'Snapshot not found.' );
		}
		$diff = \WPHeart\Snapshot\SchemaDiff::compare( $a->to_array(), $b->to_array() );
		$s    = $diff['summary'];
		self::cli_log( sprintf( 'added=%d removed=%d changed=%d', $s['tables_added'], $s['tables_removed'], $s['tables_changed'] ) );
	}

	/**
	 * Build a request object (real WP_REST_Request at runtime, stub in tests).
	 *
	 * @param array $params Parameters.
	 * @return mixed
	 */
	private function request( array $params = array() ) {
		$request = new \WP_REST_Request();
		$request->set_query_params( $params );
		return $request;
	}

	/**
	 * Unwrap a controller result or raise a CLI error.
	 *
	 * @param mixed $response Response or WP_Error.
	 * @return array Payload data.
	 */
	private function unwrap( $response ) {
		if ( $response instanceof \WP_Error ) {
			self::cli_error( $response->get_error_message() );
		}
		$data = $response->get_data();
		return isset( $data['data'] ) ? $data['data'] : array();
	}

	/**
	 * @param mixed  $response Response or WP_Error.
	 * @param array  $assoc CLI args.
	 * @param string $label Count label.
	 */
	private function emit_envelope( $response, $assoc, $label ) {
		$data   = $this->unwrap( $response );
		$format = isset( $assoc['format'] ) ? $assoc['format'] : 'table';
		if ( 'json' === $format ) {
			self::cli_log( wp_json_encode( $data ) );
			return;
		}
		if ( 'count' === $format ) {
			self::cli_log( isset( $data['table_count'] ) ? (string) $data['table_count'] : $label . ': ok' );
			return;
		}
		self::cli_log( wp_json_encode( $data, JSON_PRETTY_PRINT ) );
	}

	/**
	 * @param mixed    $response Response or WP_Error.
	 * @param array    $assoc CLI args.
	 * @param string[] $headers Headers.
	 * @param callable $map Row mapper.
	 */
	private function emit_items( $response, $assoc, array $headers, $map ) {
		$items  = $this->unwrap( $response );
		$format = isset( $assoc['format'] ) ? $assoc['format'] : 'table';
		if ( 'json' === $format ) {
			self::cli_log( wp_json_encode( $items ) );
			return;
		}
		if ( 'count' === $format ) {
			self::cli_log( (string) count( $items ) );
			return;
		}
		$rows = array();
		foreach ( $items as $item ) {
			$rows[] = call_user_func( $map, $item );
		}
		self::cli_log( CliTable::render( $headers, $rows ) );
	}

	/**
	 * @param string $message Message.
	 */
	private static function cli_log( $message ) {
		if ( class_exists( 'WP_CLI' ) ) {
			\WP_CLI::log( $message );
		} else {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI sink, not HTML; content is already sanitized upstream.
			echo $message . "\n";
		}
	}

	/**
	 * @param string $message Message.
	 * @throws \RuntimeException Always, after delegating to WP_CLI when present.
	 */
	private static function cli_error( $message ) {
		if ( class_exists( 'WP_CLI' ) ) {
			\WP_CLI::error( $message );
		}
		throw new \RuntimeException( $message );
	}
}
