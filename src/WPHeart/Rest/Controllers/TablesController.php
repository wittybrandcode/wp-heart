<?php
/**
 * Tables endpoints: list, detail, schema, indexes, relationships (WH-102).
 *
 * @package WP_Heart
 */

namespace WPHeart\Rest\Controllers;

use WPHeart\Container\Container;
use WPHeart\Domain\Pagination;
use WPHeart\Rest\Presenter;
use WPHeart\Rest\TableAssembler;
use WPHeart\Security\Capabilities;
use WPHeart\Security\ErrorSanitizer;
use WPHeart\Security\Permission;

/**
 * Tables endpoints: list, detail, schema, indexes, relationships (WH-102).
 */
class TablesController {
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
			'/owners',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'owners' ),
				'permission_callback' => Permission::rest( Capabilities::VIEW_DATABASE ),
			)
		);
		register_rest_route(
			$namespace,
			'/tables',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'index' ),
				'permission_callback' => Permission::rest( Capabilities::VIEW_DATABASE ),
				'args'                => array(
					'page'           => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'per_page'       => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'search'         => array(
						'type'    => 'string',
						'default' => '',
					),
					'classification' => array(
						'type'    => 'string',
						'default' => '',
					),
					'engine'         => array(
						'type'    => 'string',
						'default' => '',
					),
					'owner'          => array(
						'type'    => 'string',
						'default' => '',
					),
					'orderby'        => array(
						'type'    => 'string',
						'default' => 'name',
					),
					'order'          => array(
						'type'    => 'string',
						'default' => 'ASC',
					),
				),
			)
		);

		foreach ( array( '', '/schema', '/indexes', '/relationships' ) as $suffix ) {
			register_rest_route(
				$namespace,
				'/tables/(?P<table>[A-Za-z0-9_$]+)' . $suffix,
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'show' ),
					'permission_callback' => Permission::rest( Capabilities::VIEW_DATABASE ),
				)
			);
		}
	}

	/**
	 * List table owners observed in the current database with table counts.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function owners( $request ) {
		try {
			unset( $request );
			$assembler = new TableAssembler( $this->c );
			$listed    = $assembler->list( false );

			$counts = array();
			foreach ( $listed['tables'] as $table ) {
				$cls = $table->classification();
				if ( $cls && null !== $cls->owner() && '' !== $cls->owner() ) {
					$slug = (string) $cls->owner();
					if ( ! isset( $counts[ $slug ] ) ) {
						$counts[ $slug ] = 0;
					}
					++$counts[ $slug ];
				}
			}

			$by_slug = array();
			foreach ( $this->c->make( 'intel.plugins' )->list() as $plugin ) {
				$by_slug[ $plugin['slug'] ] = $plugin;
			}

			$items = array();
			foreach ( $counts as $slug => $count ) {
				$plugin  = isset( $by_slug[ $slug ] ) ? $by_slug[ $slug ] : null;
				$items[] = array(
					'slug'      => $slug,
					'name'      => $plugin ? (string) $plugin['name'] : $slug,
					'installed' => null !== $plugin,
					'active'    => $plugin ? (bool) ( $plugin['active'] || $plugin['network_active'] ) : false,
					'count'     => $count,
				);
			}
			usort(
				$items,
				static function ( $a, $b ) {
					if ( $a['count'] === $b['count'] ) {
						return strcmp( $a['slug'], $b['slug'] );
					}
					return $a['count'] < $b['count'] ? 1 : -1;
				}
			);

			return Presenter::ok(
				$items,
				array(
					'total'     => count( $items ),
					'freshness' => $listed['freshness'],
				)
			);
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_owners_failed', $e->getMessage(), 500 );
		}
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function index( $request ) {
		try {
			$config    = $this->c->make( 'config' );
			$assembler = new TableAssembler( $this->c );
			$listed    = $assembler->list( false );

			$search = strtolower( trim( (string) $request->get_param( 'search' ) ) );
			$filter = strtoupper( trim( (string) $request->get_param( 'classification' ) ) );
			$engine = strtoupper( trim( (string) $request->get_param( 'engine' ) ) );
			if ( '' !== $engine && ! preg_match( '/^[A-Z0-9]+$/', $engine ) ) {
				return ErrorSanitizer::rest_error( 'wp_heart_bad_engine', __( 'Invalid engine filter.', 'wp-heart' ), 400 );
			}
			$owner = strtolower( trim( (string) $request->get_param( 'owner' ) ) );
			if ( '' !== $owner && ! preg_match( '/^[a-z0-9_-]+$/', $owner ) ) {
				return ErrorSanitizer::rest_error( 'wp_heart_bad_owner', __( 'Invalid owner filter.', 'wp-heart' ), 400 );
			}
			$items = array();
			foreach ( $listed['tables'] as $table ) {
				$data = $table->to_array();
				$cls  = $table->classification();
				$row  = array(
					'name'           => $data['name'],
					'metadata'       => $data['metadata'],
					'classification' => $cls ? $cls->to_array() : null,
				);
				if ( '' !== $search && false === strpos( strtolower( $row['name'] ), $search ) ) {
					continue;
				}
				if ( '' !== $filter && ( ! $cls || $cls->type() !== $filter ) ) {
					continue;
				}
				if ( '' !== $engine ) {
					$table_engine = isset( $row['metadata']['engine'] ) ? strtoupper( (string) $row['metadata']['engine'] ) : '';
					if ( $table_engine !== $engine ) {
						continue;
					}
				}
				if ( '' !== $owner ) {
					// Scoped view, never silent hiding: only an explicitly
					// chosen owner narrows the list; clearing restores all.
					$row_owner = $cls && null !== $cls->owner() ? strtolower( (string) $cls->owner() ) : '';
					if ( $row_owner !== $owner ) {
						continue;
					}
				}
				$items[] = $row;
			}

			$orderby = strtolower( (string) $request->get_param( 'orderby' ) );
			$order   = strtoupper( (string) $request->get_param( 'order' ) ) === 'DESC' ? -1 : 1;
			$self    = $this;
			usort(
				$items,
				static function ( $a, $b ) use ( $orderby, $order, $self ) {
					$va = $self->sort_value( $a, $orderby );
					$vb = $self->sort_value( $b, $orderby );
					if ( $va === $vb ) {
						return 0;
					}
					return ( $va < $vb ? -1 : 1 ) * $order;
				}
			);

			$pagination = new Pagination(
				(int) $request->get_param( 'page' ),
				(int) $request->get_param( 'per_page' ),
				(int) $config->get( 'max_per_page', 100 ),
				(int) $config->get( 'default_per_page', 20 ),
				count( $items ),
				'EXACT'
			);
			$envelope   = $pagination->envelope( array_slice( $items, $pagination->offset(), $pagination->limit() ) );

			return Presenter::ok(
				$envelope['items'],
				array(
					'pagination' => $envelope['pagination'],
					'freshness'  => $listed['freshness'],
				)
			);
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_tables_failed', $e->getMessage(), 500 );
		}
	}

	/**
	 * @param array  $row Row.
	 * @param string $orderby Order key.
	 * @return mixed
	 */
	public function sort_value( $row, $orderby ) {
		switch ( $orderby ) {
			case 'size':
				return isset( $row['metadata']['size_bytes'] ) ? (int) $row['metadata']['size_bytes'] : -1;
			case 'rows':
				return isset( $row['metadata']['estimated_rows'] ) ? (int) $row['metadata']['estimated_rows'] : -1;
			case 'classification':
				return isset( $row['classification']['type'] ) ? $row['classification']['type'] : '';
			default:
				return $row['name'];
		}
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( $request ) {
		try {
			$route     = $request->get_route();
			$table     = (string) $request->get_param( 'table' );
			$assembler = new TableAssembler( $this->c );
			$found     = $assembler->get( $table );
			if ( $found instanceof \WP_Error ) {
				return $found;
			}
			$data = $found->to_array();
			if ( false !== strpos( (string) $route, '/schema' ) ) {
				$data = array(
					'name'     => $data['name'],
					'columns'  => $data['columns'],
					'metadata' => $data['metadata'],
				);
			} elseif ( false !== strpos( (string) $route, '/indexes' ) ) {
				$data = array(
					'name'    => $data['name'],
					'indexes' => $data['indexes'],
				);
			} elseif ( false !== strpos( (string) $route, '/relationships' ) ) {
				$data = array(
					'name'          => $data['name'],
					'relationships' => $data['relationships'],
					'constraints'   => $data['constraints'],
				);
			}
			return Presenter::ok( $data, array( 'freshness' => 'EXACT' ) );
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_table_failed', $e->getMessage(), 500 );
		}
	}
}
