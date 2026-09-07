<?php
/**
 * Snapshot endpoints: capture, list, inspect, compare, export, import.
 * Snapshots are metadata documents; import is strictly validated and
 * re-identified — input ids are never trusted.
 *
 * @package WP_Heart
 */

namespace WPHeart\Rest\Controllers;

use WPHeart\Audit\AuditLogger;
use WPHeart\Container\Container;
use WPHeart\Domain\AuditEvent;
use WPHeart\Domain\Snapshot;
use WPHeart\Rest\Presenter;
use WPHeart\Security\Capabilities;
use WPHeart\Security\ErrorSanitizer;
use WPHeart\Security\Permission;
use WPHeart\Snapshot\SchemaDiff;

/**
 * Snapshot endpoints.
 */
class SnapshotsController {
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
			'/snapshots',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'index' ),
					'permission_callback' => Permission::rest( Capabilities::VIEW_DATABASE ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create' ),
					'permission_callback' => Permission::rest( Capabilities::MANAGE_SETTINGS ),
					'args'                => array(
						'label' => array(
							'type'    => 'string',
							'default' => '',
						),
					),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/snapshots/compare',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'compare' ),
				'permission_callback' => Permission::rest( Capabilities::VIEW_DATABASE ),
				'args'                => array(
					'a' => array(
						'type'     => 'string',
						'required' => true,
					),
					'b' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/snapshots/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'import' ),
				'permission_callback' => Permission::rest( Capabilities::MANAGE_SETTINGS ),
			)
		);
		register_rest_route(
			$namespace,
			'/snapshots/(?P<id>[A-Za-z0-9_-]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'show' ),
					'permission_callback' => Permission::rest( Capabilities::VIEW_DATABASE ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'destroy' ),
					'permission_callback' => Permission::rest( Capabilities::MANAGE_SETTINGS ),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/snapshots/(?P<id>[A-Za-z0-9_-]+)/download',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'download' ),
				'permission_callback' => Permission::rest( Capabilities::VIEW_DATABASE ),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function index( $request ) {
		try {
			unset( $request );
			return Presenter::ok( $this->store()->list(), array() );
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_snapshots_failed', $e->getMessage(), 500 );
		}
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( $request ) {
		try {
			$snapshot = $this->c->make( 'snapshots.service' )->capture( (string) $request->get_param( 'label' ) );
			if ( ! $snapshot ) {
				return ErrorSanitizer::rest_error( 'wp_heart_snapshot_failed', __( 'Snapshot storage is unavailable.', 'wp-heart' ), 500 );
			}
			$this->audit( AuditEvent::SNAPSHOT_CREATE, $snapshot->id(), $snapshot->to_array() );
			return Presenter::ok( $snapshot->to_array(), array(), 201 );
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_snapshot_failed', $e->getMessage(), 500 );
		}
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( $request ) {
		try {
			$snapshot = $this->store()->load( (string) $request->get_param( 'id' ) );
			if ( ! $snapshot ) {
				return ErrorSanitizer::rest_error( 'wp_heart_snapshot_not_found', __( 'Snapshot not found.', 'wp-heart' ), 404 );
			}
			return Presenter::ok( $snapshot->to_array(), array() );
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_snapshot_failed', $e->getMessage(), 500 );
		}
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function destroy( $request ) {
		try {
			$id = (string) $request->get_param( 'id' );
			if ( ! $this->store()->load( $id ) ) {
				return ErrorSanitizer::rest_error( 'wp_heart_snapshot_not_found', __( 'Snapshot not found.', 'wp-heart' ), 404 );
			}
			$this->store()->delete( $id );
			$this->audit( AuditEvent::SNAPSHOT_DELETE, $id, array() );
			return Presenter::ok( array( 'deleted' => true ), array() );
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_snapshot_failed', $e->getMessage(), 500 );
		}
	}

	/**
	 * Export as a JSON document for client-side download.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function download( $request ) {
		$found = $this->show( $request );
		if ( $found instanceof \WP_Error ) {
			return $found;
		}
		$data = $found->get_data();
		return Presenter::ok(
			array(
				'filename' => 'wp-heart-snapshot-' . $data['data']['id'] . '.json',
				'content'  => (string) wp_json_encode( $data['data'] ),
			),
			array()
		);
	}

	/**
	 * Import a snapshot document (validated, re-identified).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function import( $request ) {
		try {
			$doc = $request->get_param( 'snapshot' );
			if ( ! Snapshot::is_valid_document( $doc ) ) {
				return ErrorSanitizer::rest_error( 'wp_heart_bad_snapshot', __( 'Invalid snapshot document.', 'wp-heart' ), 400 );
			}
			$encoded = wp_json_encode( $doc );
			if ( ! is_string( $encoded ) || strlen( $encoded ) > \WPHeart\Snapshot\SnapshotStore::MAX_IMPORT ) {
				return ErrorSanitizer::rest_error( 'wp_heart_snapshot_too_large', __( 'Snapshot document exceeds the import limit.', 'wp-heart' ), 400 );
			}
			$label = trim( (string) $request->get_param( 'label' ) );
			if ( '' === $label ) {
				$label = ( isset( $doc['label'] ) ? (string) $doc['label'] : 'Snapshot' ) . ' (' . __( 'imported', 'wp-heart' ) . ')';
			}
			$snapshot = new Snapshot(
				array(
					'version'    => 1,
					'id'         => gmdate( 'Ymd-His' ) . '-import-' . substr( md5( uniqid( '', true ) ), 0, 6 ),
					'label'      => substr( $label, 0, 191 ),
					'created_at' => gmdate( 'Y-m-d\TH:i:s+00:00' ),
					'tables'     => array_values( $doc['tables'] ),
				)
			);
			if ( ! $this->store()->save( $snapshot ) ) {
				return ErrorSanitizer::rest_error( 'wp_heart_snapshot_failed', __( 'Snapshot storage is unavailable.', 'wp-heart' ), 500 );
			}
			$this->audit( AuditEvent::SNAPSHOT_IMPORT, $snapshot->id(), array() );
			return Presenter::ok( $snapshot->to_array(), array(), 201 );
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_snapshot_failed', $e->getMessage(), 500 );
		}
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function compare( $request ) {
		try {
			$a = $this->store()->load( (string) $request->get_param( 'a' ) );
			$b = $this->store()->load( (string) $request->get_param( 'b' ) );
			if ( ! $a || ! $b ) {
				return ErrorSanitizer::rest_error( 'wp_heart_snapshot_not_found', __( 'Snapshot not found.', 'wp-heart' ), 404 );
			}
			$diff = SchemaDiff::compare( $a->to_array(), $b->to_array() );
			return Presenter::ok(
				$diff,
				array(
					'a' => array(
						'id'    => $a->id(),
						'label' => $a->to_array()['label'],
					),
					'b' => array(
						'id'    => $b->id(),
						'label' => $b->to_array()['label'],
					),
				)
			);
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_snapshot_failed', $e->getMessage(), 500 );
		}
	}

	/**
	 * @return \WPHeart\Snapshot\SnapshotStore
	 */
	private function store() {
		return $this->c->make( 'snapshots.store' );
	}

	/**
	 * @param string $type Event type.
	 * @param string $target Target id.
	 * @param array  $doc Document (label only is audited).
	 */
	private function audit( $type, $target, array $doc ) {
		$audit = $this->c->make( 'audit' );
		if ( $audit instanceof AuditLogger ) {
			$meta = array();
			if ( isset( $doc['label'] ) ) {
				$meta['label'] = substr( (string) $doc['label'], 0, 191 );
			}
			$audit->log( $type, $target, $meta );
		}
	}
}
