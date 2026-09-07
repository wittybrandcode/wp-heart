<?php
/**
 * REST bootstrap: versioned namespace + controller wiring (WH-100).
 *
 * @package WP_Heart
 */

namespace WPHeart\Rest;

use WPHeart\Container\Container;
use WPHeart\Rest\Controllers\AuditController;
use WPHeart\Rest\Controllers\CronController;
use WPHeart\Rest\Controllers\DatabaseController;
use WPHeart\Rest\Controllers\DataController;
use WPHeart\Rest\Controllers\HealthController;
use WPHeart\Rest\Controllers\MapController;
use WPHeart\Rest\Controllers\QueryController;
use WPHeart\Rest\Controllers\SearchController;
use WPHeart\Rest\Controllers\SettingsController;
use WPHeart\Rest\Controllers\SnapshotsController;
use WPHeart\Rest\Controllers\TablesController;

/**
 * REST bootstrap: versioned namespace + controller wiring (WH-100).
 */
class RestBootstrap {
	/** @var Container */
	private $c;

	/**
	 * @param Container $c Container.
	 */
	public function __construct( Container $c ) {
		$this->c = $c;
	}

	/**
	 * Register all routes under wp-heart/v1.
	 */
	public function register() {
		$config = $this->c->make( 'config' );
		$ns     = (string) $config->get( 'rest_namespace', 'wp-heart/v1' );

		$controllers = array(
			new DatabaseController( $this->c ),
			new TablesController( $this->c ),
			new DataController( $this->c ),
			new SearchController( $this->c ),
			new HealthController( $this->c ),
			new CronController( $this->c ),
			new QueryController( $this->c ),
			new AuditController( $this->c ),
			new SettingsController( $this->c ),
			new SnapshotsController( $this->c ),
			new MapController( $this->c ),
		);
		foreach ( $controllers as $controller ) {
			$controller->register( $ns );
		}
	}
}
