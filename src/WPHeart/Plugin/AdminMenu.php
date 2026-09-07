<?php
/**
 * Admin menu: the Database Command Center entry points.
 *
 * @package WP_Heart
 */

namespace WPHeart\Plugin;

use WPHeart\Security\Capabilities;

/**
 * Admin menu: the Database Command Center entry points.
 */
class AdminMenu {
	/**
	 * Register menu pages.
	 */
	public static function register() {
		if ( ! current_user_can( Capabilities::VIEW_DATABASE ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_menu_page(
			__( 'WP-HEART Database Observatory', 'wp-heart' ),
			__( 'HEART', 'wp-heart' ),
			Capabilities::VIEW_DATABASE,
			'wp-heart',
			array( __CLASS__, 'render' ),
			'dashicons-database',
			80
		);

		$subs = array(
			'overview'  => __( 'Overview', 'wp-heart' ),
			'tables'    => __( 'Tables', 'wp-heart' ),
			'map'       => __( 'Map', 'wp-heart' ),
			'snapshots' => __( 'Snapshots', 'wp-heart' ),
			'search'    => __( 'Search', 'wp-heart' ),
			'health'    => __( 'Health', 'wp-heart' ),
			'query'     => __( 'Query', 'wp-heart' ),
			'audit'     => __( 'Audit', 'wp-heart' ),
			'settings'  => __( 'Settings', 'wp-heart' ),
		);

		foreach ( $subs as $slug => $title ) {
			add_submenu_page(
				'wp-heart',
				sprintf( '%s — %s', $title, __( 'WP-HEART', 'wp-heart' ) ),
				$title,
				Capabilities::VIEW_DATABASE,
				'wp-heart#' . $slug,
				array( __CLASS__, 'render' )
			);
		}
	}

	/**
	 * Render the app root. All data flows through the REST API.
	 */
	public static function render() {
		if ( ! current_user_can( Capabilities::VIEW_DATABASE ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view the database observatory.', 'wp-heart' ) );
		}
		echo '<div class="wrap wp-heart-wrap">';
		echo '<h1>' . esc_html__( 'WP-HEART — Database Observatory', 'wp-heart' ) . '</h1>';
		echo '<div id="wp-heart-app" role="application" aria-label="' . esc_attr__( 'Database observatory application', 'wp-heart' ) . '">';
		echo '<p class="wp-heart-loading">' . esc_html__( 'Loading observatory…', 'wp-heart' ) . '</p>';
		echo '</div>';
		echo '</div>';
	}
}
