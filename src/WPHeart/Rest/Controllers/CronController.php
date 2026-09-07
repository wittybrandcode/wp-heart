<?php
/**
 * Scheduled-tasks endpoint (cron observability). Metadata only.
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
 * Scheduled-tasks endpoint.
 */
class CronController {
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
			'/cron',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'status' ),
				'permission_callback' => Permission::rest( Capabilities::VIEW_DATABASE ),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function status( $request ) {
		try {
			unset( $request );
			$inspector = $this->c->make( 'cron.inspector' );
			$wp_cron   = $inspector->wp_cron_events( 100 );
			$as        = $inspector->action_scheduler();
			return Presenter::ok(
				array(
					'wp_cron'          => $wp_cron,
					'action_scheduler' => $as,
				),
				array( 'freshness' => 'EXACT' )
			);
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_cron_failed', $e->getMessage(), 500 );
		}
	}
}
