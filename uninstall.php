<?php
/**
 * Uninstall handler. Removes ONLY WP-HEART's own internal data.
 * Never touches user content or third-party tables (Release 1.0 is read-only).
 *
 * @package WP_Heart
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = array(
	'wp_heart_version',
	'wp_heart_settings',
	'wp_heart_audit_log',
	'wp_heart_db_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
	delete_site_option( $option );
}

// Remove custom capabilities from every role (both single-site and network).
global $wp_roles;
if ( isset( $wp_roles ) && is_object( $wp_roles ) ) {
	$caps = array(
		'wp_heart_view_database',
		'wp_heart_view_data',
		'wp_heart_run_queries',
		'wp_heart_view_audit',
		'wp_heart_manage_settings',
	);
	foreach ( $wp_roles->roles as $role_name => $role_info ) {
		$role_obj = get_role( $role_name );
		if ( ! $role_obj ) {
			continue;
		}
		foreach ( $caps as $cap ) {
			$role_obj->remove_cap( $cap );
		}
	}
}

// Best-effort transient cleanup (transients may also simply expire).
global $wpdb;
if ( isset( $wpdb ) ) {
	$like = $wpdb->esc_like( '_transient_wp_heart_' ) . '%';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup of own keys only.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, '_' . $like ) );
	if ( is_multisite() ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup of own keys only.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s", $like, '_' . $like ) );
	}
}
