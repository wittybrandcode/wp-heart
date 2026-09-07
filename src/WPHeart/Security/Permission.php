<?php
/**
 * Authorization boundary (WH-081–WH-083). Server-side, capability-first.
 *
 * @package WP_Heart
 */

namespace WPHeart\Security;

/**
 * Authorization boundary (WH-081–WH-083). Server-side, capability-first.
 */
class Permission {
	/**
	 * Capability check with administrator fallback.
	 *
	 * @param string $cap Capability.
	 * @return bool
	 */
	public static function can( $cap ) {
		if ( function_exists( 'current_user_can' ) && ( current_user_can( $cap ) || current_user_can( 'manage_options' ) ) ) {
			return true;
		}
		return false;
	}

	/**
	 * REST permission callback factory. Authorization runs before any
	 * expensive work (Design §79).
	 *
	 * @param string $cap Capability.
	 * @return callable
	 */
	public static function rest( $cap ) {
		return static function () use ( $cap ) {
			if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) {
				return new \WP_Error( 'wp_heart_unauthorized', __( 'Authentication is required.', 'wp-heart' ), array( 'status' => 401 ) );
			}
			if ( ! self::can( $cap ) ) {
				return new \WP_Error( 'wp_heart_forbidden', __( 'You do not have permission to perform this action.', 'wp-heart' ), array( 'status' => 403 ) );
			}
			return true;
		};
	}
}
