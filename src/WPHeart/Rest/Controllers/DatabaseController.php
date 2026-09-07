<?php
/**
 * Database overview + explicit refresh endpoints (WH-101).
 *
 * @package WP_Heart
 */

namespace WPHeart\Rest\Controllers;

use WPHeart\Audit\AuditLogger;
use WPHeart\Container\Container;
use WPHeart\Domain\AuditEvent;
use WPHeart\Rest\Presenter;
use WPHeart\Rest\TableAssembler;
use WPHeart\Security\Capabilities;
use WPHeart\Security\ErrorSanitizer;
use WPHeart\Security\Permission;

/**
 * Database overview + explicit refresh endpoints (WH-101).
 */
class DatabaseController {
	/** @var Container */
	private $c;

	/**
	 * @param Container $c Container.
	 */
	public function __construct( Container $c ) {
		$this->c = $c;
	}

	/**
	 * @param string $namespace REST namespace.
	 */
	public function register( $namespace ) {
		register_rest_route(
			$namespace,
			'/database',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'overview' ),
				'permission_callback' => Permission::rest( Capabilities::VIEW_DATABASE ),
			)
		);
		register_rest_route(
			$namespace,
			'/refresh',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'refresh' ),
				'permission_callback' => Permission::rest( Capabilities::VIEW_DATABASE ),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function overview( $request ) {
		try {
			$assembler = new TableAssembler( $this->c );
			$summary   = $this->c->make( 'database.service' )->summary( false )->to_array();
			$listed    = $assembler->list( false );

			$dist = array();
			foreach ( $listed['tables'] as $table ) {
				$cls  = $table->classification();
				$type = $cls ? $cls->type() : 'UNKNOWN';
				if ( ! isset( $dist[ $type ] ) ) {
					$dist[ $type ] = 0;
				}
				++$dist[ $type ];
			}
			$summary['classification_distribution'] = $dist;

			$health     = $this->c->make( 'health' )->run(
				array(
					'tables'      => $listed['tables'],
					'table_names' => array_keys( $assembler->classifications() ),
				)
			);
			$sev_counts = array();
			foreach ( $health['issues'] as $issue ) {
				$sev = $issue->severity();
				if ( ! isset( $sev_counts[ $sev ] ) ) {
					$sev_counts[ $sev ] = 0;
				}
				++$sev_counts[ $sev ];
			}
			$summary['health_summary'] = array(
				'issue_count' => count( $health['issues'] ),
				'by_severity' => $sev_counts,
			);

			// Autoload footprint: two bounded aggregates, explicit page load only.
			$autoload            = $this->c->make( 'options' )->autoload_summary( 5 );
			$summary['autoload'] = array(
				'bytes'    => $autoload['bytes'],
				'count'    => $autoload['count'],
				'top'      => $autoload['top'],
				'accuracy' => $autoload['accuracy'],
			);

			$audit = $this->c->make( 'audit' );
			if ( $audit instanceof AuditLogger ) {
				$audit->log( AuditEvent::DATABASE_SCAN, 'database', array( 'tables' => $summary['table_count'] ) );
			}

			return Presenter::ok( $summary, array( 'freshness' => $summary['freshness'] ) );
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_database_failed', $e->getMessage(), 500 );
		}
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function refresh( $request ) {
		try {
			$this->c->make( 'cache' )->flush_all();
			$audit = $this->c->make( 'audit' );
			if ( $audit instanceof AuditLogger ) {
				$audit->log( AuditEvent::CACHE_REFRESH, 'all', array() );
			}
			return Presenter::ok( array( 'refreshed' => true ), array() );
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_refresh_failed', $e->getMessage(), 500 );
		}
	}
}
