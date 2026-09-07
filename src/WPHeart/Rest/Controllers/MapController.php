<?php
/**
 * Database map endpoint (WH-151). Inferred edges are labeled and capped.
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
use WPHeart\Support\RelationshipOrigin;

/**
 * Database map endpoint (WH-151). Inferred edges are labeled and capped.
 */
class MapController {
	const MAX_EDGES = 2000;

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
			'/map',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'graph' ),
				'permission_callback' => Permission::rest( Capabilities::VIEW_DATABASE ),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function graph( $request ) {
		try {
			unset( $request );
			$cache  = $this->c->make( 'cache' );
			$cached = $cache->get( 'map:v1' );
			if ( $cached['hit'] && isset( $cached['value']['nodes'] ) ) {
				$graph              = $cached['value'];
				$graph['freshness'] = 'CACHED';
				return Presenter::ok( $graph, array( 'freshness' => 'CACHED' ) );
			}

			$assembler = new TableAssembler( $this->c );
			$listed    = $assembler->list( false );
			$nodes     = array();
			$edges     = array();
			$truncated = false;

			foreach ( $listed['tables'] as $table ) {
				$full = $assembler->get( $table->name() );
				if ( $full instanceof \WP_Error ) {
					continue;
				}
				$data    = $full->to_array();
				$nodes[] = array(
					'id'             => $data['name'],
					'classification' => isset( $data['classification']['type'] ) ? $data['classification']['type'] : 'UNKNOWN',
					'rows'           => isset( $data['metadata']['estimated_rows'] ) ? $data['metadata']['estimated_rows'] : null,
					'rows_mode'      => isset( $data['metadata']['rows_mode'] ) ? $data['metadata']['rows_mode'] : 'UNKNOWN',
				);
				foreach ( $data['relationships'] as $rel ) {
					if ( in_array( $rel['origin'], array( RelationshipOrigin::NONE, RelationshipOrigin::UNKNOWN ), true ) ) {
						continue;
					}
					if ( null === $rel['target_table'] ) {
						continue;
					}
					if ( count( $edges ) >= self::MAX_EDGES ) {
						$truncated = true;
						break 2;
					}
					$edges[] = $rel;
				}
			}

			$graph = array(
				'nodes'     => $nodes,
				'edges'     => $edges,
				'truncated' => $truncated,
				'freshness' => 'EXACT',
			);
			$cache->set( 'map:v1', $graph );
			return Presenter::ok(
				$graph,
				array(
					'freshness' => 'EXACT',
					'edge_cap'  => self::MAX_EDGES,
				)
			);
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_map_failed', $e->getMessage(), 500 );
		}
	}
}
