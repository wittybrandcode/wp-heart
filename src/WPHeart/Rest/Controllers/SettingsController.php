<?php
/**
 * Settings + multisite context endpoints (WH-220, WH-230).
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
 * Settings + multisite context endpoints (WH-220, WH-230).
 */
class SettingsController {
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
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'show' ),
					'permission_callback' => Permission::rest( Capabilities::VIEW_DATABASE ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => Permission::rest( Capabilities::MANAGE_SETTINGS ),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/context',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'context' ),
				'permission_callback' => Permission::rest( Capabilities::VIEW_DATABASE ),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( $request ) {
		try {
			unset( $request );
			$config = $this->c->make( 'config' );
			return Presenter::ok(
				array(
					'cache_ttl'        => (int) $config->get( 'cache_ttl' ),
					'default_per_page' => (int) $config->get( 'default_per_page' ),
					'max_per_page'     => (int) $config->get( 'max_per_page' ),
					'max_query_rows'   => (int) $config->get( 'max_query_rows' ),
					'capabilities'     => Capabilities::labels(),
					'version'          => (string) $config->get( 'version' ),
				),
				array()
			);
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_settings_failed', $e->getMessage(), 500 );
		}
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update( $request ) {
		try {
			$stored = function_exists( 'get_option' ) ? get_option( 'wp_heart_settings', array() ) : array();
			if ( ! is_array( $stored ) ) {
				$stored = array();
			}
			$rules = array(
				'cache_ttl'        => array( 60, 3600 ),
				'default_per_page' => array( 5, 100 ),
				'max_per_page'     => array( 10, 200 ),
				'max_query_rows'   => array( 50, 2000 ),
			);
			foreach ( $rules as $key => $bounds ) {
				$value = $request->get_param( $key );
				if ( null === $value || '' === $value ) {
					continue;
				}
				if ( ! is_numeric( $value ) ) {
					return ErrorSanitizer::rest_error( 'wp_heart_bad_setting', sprintf( 'Invalid value for %s.', $key ), 400 );
				}
				$stored[ $key ] = max( $bounds[0], min( $bounds[1], (int) $value ) );
			}
			if ( function_exists( 'update_option' ) ) {
				update_option( 'wp_heart_settings', $stored, false );
			}

			$audit = $this->c->make( 'audit' );
			if ( $audit instanceof AuditLogger ) {
				$audit->log( AuditEvent::SETTINGS_CHANGE, 'settings', array( 'keys' => array_keys( $stored ) ) );
			}

			return Presenter::ok(
				array(
					'updated'  => true,
					'settings' => $stored,
				),
				array()
			);
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_settings_failed', $e->getMessage(), 500 );
		}
	}

	/**
	 * Site/network context for multisite correctness (WH-230).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function context( $request ) {
		try {
			unset( $request );
			$context = $this->c->make( 'wp.context' );
			global $wpdb;
			return Presenter::ok(
				array(
					'is_multisite' => $context->is_multisite(),
					'blog_id'      => $context->blog_id(),
					'prefix'       => $context->prefix(),
					'base_prefix'  => $context->base_prefix(),
					'network_id'   => function_exists( 'get_current_network_id' ) ? (int) get_current_network_id() : 1,
					'charset'      => isset( $wpdb->charset ) ? (string) $wpdb->charset : null,
				),
				array()
			);
		} catch ( \Exception $e ) {
			return ErrorSanitizer::rest_error( 'wp_heart_context_failed', $e->getMessage(), 500 );
		}
	}
}
