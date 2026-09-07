<?php
/**
 * Orphan-candidate detection (WH-062). A candidate is information only —
 * never a recommendation to delete.
 *
 * @package WP_Heart
 */

namespace WPHeart\Intelligence;

use WPHeart\Domain\Evidence;

/**
 * Orphan-candidate detection (WH-062). A candidate is information only —.
 */
class OrphanDetector {
	/** @var PluginRegistry */
	private $registry;
	/** @var WpContextService */
	private $context;

	/**
	 * @param PluginRegistry   $registry Registry.
	 * @param WpContextService $context WP context.
	 */
	public function __construct( PluginRegistry $registry, WpContextService $context ) {
		$this->registry = $registry;
		$this->context  = $context;
	}

	/**
	 * @param string       $table Table name.
	 * @param array[]|null $plugins Registry rows (null = load live).
	 * @return array|null {evidence: Evidence[], strength}
	 */
	public function detect( $table, $plugins = null ) {
		if ( null === $plugins ) {
			$plugins = $this->registry->list();
		}
		$installed = array();
		foreach ( $plugins as $plugin ) {
			$installed[ $plugin['slug'] ] = true;
		}

		$remainder = $this->context->strip_prefix( $table );
		$haystack  = null !== $remainder ? strtolower( $remainder ) : strtolower( $table );

		foreach ( PluginTableDetector::KNOWN_SIGNATURES as $sig => $owner_slug ) {
			if ( null === $owner_slug || 0 !== strpos( $haystack, $sig ) ) {
				continue;
			}
			if ( ! isset( $installed[ $owner_slug ] ) ) {
				return array(
					'evidence'   => array(
						new Evidence( Evidence::HISTORICAL_SIGNATURE, sprintf( 'Table %s carries the %s signature but %s is not installed.', $table, $sig, $owner_slug ), 45 ),
						new Evidence( Evidence::PLUGIN_METADATA, 'No current owner plugin detected.', 30 ),
					),
					'strength'   => 45,
					'owner_hint' => $owner_slug,
				);
			}
			return null;
		}

		return null;
	}
}
