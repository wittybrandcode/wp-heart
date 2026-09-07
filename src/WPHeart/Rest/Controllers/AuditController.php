<?php
/**
 * Audit endpoint (WH-107). Separate capability by design.
 *
 * @package WP_Heart
 */

namespace WPHeart\Rest\Controllers;

use WPHeart\Container\Container;
use WPHeart\Domain\AuditEvent;
use WPHeart\Rest\Presenter;
use WPHeart\Security\Capabilities;
use WPHeart\Security\ErrorSanitizer;
use WPHeart\Security\Permission;

/**
 * Audit endpoint (WH-107). Separate capability by design.
 */
class AuditController {
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
			'/audit',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'index' ),
				'permission_callback' => Permission::rest( Capabilities::VIEW_AUDIT ),
				'args'                => array(
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'type'     => array(
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
	public function index( $request ) {
		try {
			$type  = strtoupper( trim( (string) $request->get_param( 'type' ) ) );
			$known = array(
				AuditEvent::DATABASE_SCAN,
				AuditEvent::TABLE_VIEW,
				AuditEvent::ROW_VIEW,
				AuditEvent::SEARCH,
				AuditEvent::QUERY_EXECUTION,
				AuditEvent::SETTINGS_CHANGE,
				AuditEvent::CACHE_REFRESH,
				AuditEvent::SNAPSHOT_CREATE,
				AuditEvent::SNAPSHOT_DELETE,
				AuditEvent::SNAPSHOT_IMPORT,
			);
			if ( '' !== $type && ! in_array( $type, $known, true ) ) {
				return ErrorSanitizer::rest_error( 'wp_heart_bad_audit_type', __( 'Invalid audit event type.', 'wp-heart' ), 400 );
			}
			$envelope = $this->c->make( 'audit.store' )->query(
				array(
					'type'     => $type,
					'page'     => (int) $request->get_param( 'page' ),
					'per_page' => (int) $request->get_param( 'per_page' ),
				)
			);
			return Presenter::ok( $envelope['items'], array( 'pagination' => $envelope['pagination'] ) );
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_audit_failed', $e->getMessage(), 500 );
		}
	}
}
