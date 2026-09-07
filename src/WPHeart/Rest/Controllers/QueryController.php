<?php
/**
 * Query endpoints (WH-106). Server-side read-only policy, no exceptions.
 *
 * @package WP_Heart
 */

namespace WPHeart\Rest\Controllers;

use WPHeart\Container\Container;
use WPHeart\Rest\Presenter;
use WPHeart\Security\Capabilities;
use WPHeart\Security\ErrorSanitizer;
use WPHeart\Security\Permission;

/**
 * Query endpoints (WH-106). Server-side read-only policy, no exceptions.
 */
class QueryController {
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
			'/query',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'query' ),
				'permission_callback' => Permission::rest( Capabilities::RUN_QUERIES ),
				'args'                => array(
					'sql' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/query/explain',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'explain' ),
				'permission_callback' => Permission::rest( Capabilities::RUN_QUERIES ),
				'args'                => array(
					'sql' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/query/ai',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'query_ai' ),
				'permission_callback' => Permission::rest( Capabilities::RUN_QUERIES ),
				'args'                => array(
					'prompt' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function query( $request ) {
		try {
			$result = $this->c->make( 'query.executor' )->execute( $request->get_param( 'sql' ) );
			if ( ! $result['success'] ) {
				return ErrorSanitizer::rest_error( 'wp_heart_' . $result['code'], $result['message'], 400 );
			}
			return Presenter::ok(
				array(
					'rows'    => $result['rows'],
					'columns' => $result['columns'],
				),
				array(
					'row_count'  => $result['row_count'],
					'truncated'  => $result['truncated'],
					'elapsed_ms' => $result['elapsed_ms'],
					'read_only'  => true,
				)
			);
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_query_failed', $e->getMessage(), 500 );
		}
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function explain( $request ) {
		try {
			$result = $this->c->make( 'query.explainer' )->explain( $request->get_param( 'sql' ) );
			if ( ! $result['success'] ) {
				return ErrorSanitizer::rest_error( 'wp_heart_explain_rejected', $result['message'], 400 );
			}
			return Presenter::ok( array( 'plan' => $result['rows'] ), array( 'read_only' => true ) );
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_explain_failed', $e->getMessage(), 500 );
		}
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function query_ai( $request ) {
		try {
			$prompt = $request->get_param( 'prompt' );
			$sql    = $this->c->make( 'ai.engine' )->generate_query( $prompt );
			
			// Strictly pass the generated SQL through the existing executor,
			// ensuring it is validated by QueryValidator as read-only.
			$result = $this->c->make( 'query.executor' )->execute( $sql );
			
			if ( ! $result['success'] ) {
				return ErrorSanitizer::rest_error( 'wp_heart_' . $result['code'], $result['message'], 400 );
			}
			return Presenter::ok(
				array(
					'rows'    => $result['rows'],
					'columns' => $result['columns'],
					'sql'     => $sql, // Include generated SQL for transparency
				),
				array(
					'row_count'  => $result['row_count'],
					'truncated'  => $result['truncated'],
					'elapsed_ms' => $result['elapsed_ms'],
					'read_only'  => true,
				)
			);
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_ai_query_failed', $e->getMessage(), 500 );
		}
	}
}
