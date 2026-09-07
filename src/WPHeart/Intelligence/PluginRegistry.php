<?php
/**
 * Plugin registry: installed/active plugin representation (WH-050).
 *
 * @package WP_Heart
 */

namespace WPHeart\Intelligence;

/**
 * Plugin registry: installed/active plugin representation (WH-050).
 */
class PluginRegistry {
	/**
	 * @return array[] Each: {file, slug, name, version, active, network_active}
	 */
	public function list() {
		$all           = $this->all_plugins();
		$active_option = function_exists( 'get_option' ) ? (array) get_option( 'active_plugins', array() ) : array();
		$network       = function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_site_option' )
			? (array) get_site_option( 'active_sitewide_plugins', array() )
			: array();

		$out = array();
		foreach ( $all as $file => $data ) {
			$slug  = $this->slug_from_file( $file );
			$out[] = array(
				'file'           => $file,
				'slug'           => $slug,
				'name'           => isset( $data['Name'] ) ? (string) $data['Name'] : $slug,
				'version'        => isset( $data['Version'] ) ? (string) $data['Version'] : null,
				'active'         => in_array( $file, $active_option, true ),
				'network_active' => array_key_exists( $file, $network ),
			);
		}
		return $out;
	}

	/**
	 * @param array[] $plugins Registry rows.
	 * @param string  $slug Plugin slug.
	 * @return array|null
	 */
	public static function find( array $plugins, $slug ) {
		foreach ( $plugins as $plugin ) {
			if ( isset( $plugin['slug'] ) && $plugin['slug'] === $slug ) {
				return $plugin;
			}
		}
		return null;
	}

	/**
	 * @return array[]
	 */
	private function all_plugins() {
		if ( function_exists( 'get_plugins' ) ) {
			$plugins = get_plugins();
			return is_array( $plugins ) ? $plugins : array();
		}
		// Fallback: scan the plugins directory without loading admin includes.
		if ( ! defined( 'WP_PLUGIN_DIR' ) || ! is_readable( WP_PLUGIN_DIR ) ) {
			return array();
		}
		$found = array();
		foreach ( glob( WP_PLUGIN_DIR . '/*', GLOB_ONLYDIR ) as $dir ) {
			$slug = basename( $dir );
			foreach ( glob( $dir . '/*.php' ) as $file ) {
				$found[ $slug . '/' . basename( $file ) ] = array(
					'Name'    => $slug,
					'Version' => null,
				);
				break;
			}
		}
		return $found;
	}

	/**
	 * @param string $file Plugin file (slug/file.php or file.php).
	 * @return string
	 */
	public function slug_from_file( $file ) {
		$file = (string) $file;
		if ( false !== strpos( $file, '/' ) ) {
			$parts = explode( '/', $file );
			return $parts[0];
		}
		return preg_replace( '/\.php$/', '', basename( $file ) );
	}
}
