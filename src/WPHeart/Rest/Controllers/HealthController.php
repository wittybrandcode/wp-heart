<?php
/**
 * Health endpoints (WH-105). Results cached; explicit refresh via /refresh.
 *
 * @package WP_Heart
 */

namespace WPHeart\Rest\Controllers;

use WPHeart\Container\Container;
use WPHeart\Rest\Presenter;
use WPHeart\Rest\TableAssembler;
use WPHeart\Security\Capabilities;
use WPHeart\Security\ErrorSanitizer;
use WPHeart\Security\Permission;
use WPHeart\Support\Severity;

/**
 * Health endpoints (WH-105). Results cached; explicit refresh via /refresh.
 */
class HealthController {
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
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'health' ),
				'permission_callback' => Permission::rest( Capabilities::VIEW_DATABASE ),
			)
		);
		register_rest_route(
			$namespace,
			'/issues',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'issues' ),
				'permission_callback' => Permission::rest( Capabilities::VIEW_DATABASE ),
				'args'                => array(
					'severity' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function health( $request ) {
		$report = $this->report();
		if ( $report instanceof \WP_Error ) {
			return $report;
		}
		$counts = array();
		foreach ( $report['issues'] as $issue ) {
			$sev = $issue['severity'];
			if ( ! isset( $counts[ $sev ] ) ) {
				$counts[ $sev ] = 0;
			}
			++$counts[ $sev ];
		}
		return Presenter::ok(
			array(
				'status'       => isset( $counts['CRITICAL'] ) || isset( $counts['ERROR'] ) ? 'attention' : 'ok',
				'issue_count'  => count( $report['issues'] ),
				'by_severity'  => $counts,
				'diagnostics'  => $report['ran'],
				'generated_at' => $report['generated_at'],
			),
			array( 'freshness' => $report['freshness'] )
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function issues( $request ) {
		$report = $this->report();
		if ( $report instanceof \WP_Error ) {
			return $report;
		}
		$severity = strtoupper( trim( (string) $request->get_param( 'severity' ) ) );
		$issues   = $report['issues'];
		if ( '' !== $severity ) {
			if ( ! in_array( $severity, Severity::all(), true ) ) {
				return ErrorSanitizer::rest_error( 'wp_heart_bad_severity', __( 'Invalid severity filter.', 'wp-heart' ), 400 );
			}
			$issues = array_values(
				array_filter(
					$issues,
					static function ( $i ) use ( $severity ) {
						return $i['severity'] === $severity;
					}
				)
			);
		}
		return Presenter::ok(
			$issues,
			array(
				'freshness'   => $report['freshness'],
				'diagnostics' => $report['ran'],
			)
		);
	}

	/**
	 * @return array|\WP_Error
	 */
	private function report() {
		try {
			$cache  = $this->c->make( 'cache' );
			$cached = $cache->get( 'health:v1' );
			if ( $cached['hit'] && isset( $cached['value']['issues'] ) ) {
				$report              = $cached['value'];
				$report['freshness'] = 'CACHED';
				return $report;
			}

			$assembler = new TableAssembler( $this->c );
			$listed    = $assembler->list( false );
			$tables    = $listed['tables'];

			$index_map   = array();
			$column_map  = array();
			$indexes_svc = $this->c->make( 'schema.indexes' );
			$columns_svc = $this->c->make( 'schema.columns' );
			foreach ( $tables as $table ) {
				$idx = $indexes_svc->inspect( $table->name() );
				$table->set_indexes( $idx );
				$index_map[ $table->name() ]  = $idx;
				$column_map[ $table->name() ] = $columns_svc->inspect( $table->name() );
			}

			$names = array();
			foreach ( $tables as $table ) {
				$names[] = $table->name();
			}

			$cron_raw = $this->c->make( 'cron.inspector' )->wp_cron_events( 200 );
			$as_raw   = $this->c->make( 'cron.inspector' )->action_scheduler();
			$wp_over  = array();
			foreach ( $cron_raw['events'] as $event ) {
				if ( ! empty( $event['overdue'] ) ) {
					$wp_over[] = array( 'hook' => $event['hook'] );
				}
			}

			$run = $this->c->make( 'health' )->run(
				array(
					'tables'          => $tables,
					'table_names'     => $names,
					'classifications' => $assembler->classifications(),
					'indexes'         => $index_map,
					'columns'         => $column_map,
					'autoload'        => $this->c->make( 'options' )->autoload_summary( 10 ),
					'transients'      => $this->c->make( 'options' )->transient_stats(),
					'cron'            => array(
						'wp_overdue' => $wp_over,
						'as_failed'  => $as_raw['failed_recent'],
						'as_overdue' => $as_raw['overdue_pending'],
						'tables'     => $as_raw['tables'],
					),
				)
			);

			$issues = array();
			foreach ( $run['issues'] as $issue ) {
				$issues[] = $issue->to_array();
			}
			$report = array(
				'issues'       => $issues,
				'ran'          => $run['ran'],
				'generated_at' => $run['generated_at'],
				'freshness'    => 'EXACT',
			);
			$cache->set( 'health:v1', $report );
			return $report;
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_health_failed', $e->getMessage(), 500 );
		}
	}
}
