<?php
/**
 * Sweep endpoint for safe data operations.
 *
 * @package WP_Heart
 */

namespace WPHeart\Rest\Controllers;

use WPHeart\Container\Container;
use WPHeart\Rest\Presenter;
use WPHeart\Security\Capabilities;
use WPHeart\Security\Permission;

/**
 * Sweep endpoint for safe data operations.
 */
class SweepController {
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
			'/sweep/(?P<target>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'sweep' ),
				'permission_callback' => Permission::rest( Capabilities::MANAGE_DATABASE ),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function sweep( $request ) {
		$target  = $request->get_param( 'target' );
		$sweeper = $this->c->make( 'intel.sweeper' );
		$result  = $sweeper->sweep( $target );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Clear health cache immediately so next view shows updated stats.
		$this->c->make( 'cache' )->delete( 'health:v1' );

		return Presenter::ok( $result );
	}
}
