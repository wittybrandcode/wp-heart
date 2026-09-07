<?php
/**
 * Asset registration. Admin-only, screen-scoped, versioned by filemtime.
 *
 * @package WP_Heart
 */

namespace WPHeart\Plugin;

/**
 * Asset registration. Admin-only, screen-scoped, versioned by filemtime.
 */
class Assets {
	/**
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, 'wp-heart' ) && 'toplevel_page_wp-heart' !== $hook_suffix ) {
			return;
		}

		$js_path  = WP_HEART_DIR . 'assets/js/wp-heart-admin.js';
		$css_path = WP_HEART_DIR . 'assets/css/wp-heart-admin.css';
		$tok_path = WP_HEART_DIR . 'assets/css/wp-heart-tokens.css';

		wp_register_script(
			'wp-heart-admin',
			WP_HEART_URL . 'assets/js/wp-heart-admin.js',
			array( 'wp-i18n' ),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : WP_HEART_VERSION,
			true
		);

		wp_localize_script(
			'wp-heart-admin',
			'WPHeartData',
			array(
				'restUrl'   => esc_url_raw( rest_url( WP_HEART_REST_NAMESPACE . '/' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'locale'    => get_locale(),
				'isRtl'     => is_rtl(),
				'canQuery'  => current_user_can( 'wp_heart_run_queries' ) || current_user_can( 'manage_options' ),
				'canAudit'  => current_user_can( 'wp_heart_view_audit' ) || current_user_can( 'manage_options' ),
				'canManage' => current_user_can( 'wp_heart_manage_settings' ) || current_user_can( 'manage_options' ),
			)
		);

		wp_enqueue_script( 'wp-heart-admin' );

		wp_register_style(
			'wp-heart-tokens',
			WP_HEART_URL . 'assets/css/wp-heart-tokens.css',
			array(),
			file_exists( $tok_path ) ? (string) filemtime( $tok_path ) : WP_HEART_VERSION
		);
		wp_register_style(
			'wp-heart-admin',
			WP_HEART_URL . 'assets/css/wp-heart-admin.css',
			array( 'wp-heart-tokens' ),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : WP_HEART_VERSION
		);
		wp_style_add_data( 'wp-heart-admin', 'rtl', 'replace' );
		wp_style_add_data( 'wp-heart-tokens', 'rtl', 'replace' );
		wp_enqueue_style( 'wp-heart-admin' );

		wp_set_script_translations( 'wp-heart-admin', 'wp-heart', WP_HEART_DIR . 'languages' );
	}
}
