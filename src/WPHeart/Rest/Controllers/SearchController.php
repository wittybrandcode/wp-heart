<?php
/**
 * Search endpoint (WH-104). Bounded and attributed.
 *
 * @package WP_Heart
 */

namespace WPHeart\Rest\Controllers;

use WPHeart\Audit\AuditLogger;
use WPHeart\Container\Container;
use WPHeart\Domain\AuditEvent;
use WPHeart\Rest\Presenter;
use WPHeart\Security\Capabilities;
use WPHeart\Security\ErrorSanitizer;
use WPHeart\Security\Permission;

/**
 * Search endpoint (WH-104). Bounded and attributed.
 */
class SearchController {
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
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search' ),
				'permission_callback' => Permission::rest( Capabilities::VIEW_DATA ),
				'args'                => array(
					'q'        => array(
						'type'     => 'string',
						'required' => true,
					),
					'tables'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
					),
				),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function search( $request ) {
		try {
			$tables_param = trim( (string) $request->get_param( 'tables' ) );
			$args         = array(
				'tables'   => $tables_param,
				'page'     => (int) $request->get_param( 'page' ),
				'per_page' => (int) $request->get_param( 'per_page' ),
			);
			if ( '' !== $tables_param ) {
				$args['tables'] = array_map( 'trim', explode( ',', $tables_param ) );
			}
			$result = $this->c->make( 'search' )->search( (string) $request->get_param( 'q' ), $args );
			if ( isset( $result['error'] ) ) {
				return ErrorSanitizer::rest_error( 'wp_heart_' . $result['error'], $result['message'], 400 );
			}

			$audit = $this->c->make( 'audit' );
			if ( $audit instanceof AuditLogger ) {
				$audit->log(
					AuditEvent::SEARCH,
					'global',
					array(
						'results' => count( $result['items'] ),
						'tables'  => $result['tables_searched'],
					)
				);
			}

			$items = $result['items'];
			unset( $result['items'] );
			return Presenter::ok( $items, $result );
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_search_failed', $e->getMessage(), 500 );
		}
	}
}
