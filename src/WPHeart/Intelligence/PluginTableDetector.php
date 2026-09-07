<?php
/**
 * Plugin table detection with evidence (WH-051). Pattern-only matches stay
 * low confidence; ownership is never claimed from a name alone.
 *
 * @package WP_Heart
 */

namespace WPHeart\Intelligence;

use WPHeart\Domain\Evidence;

/**
 * Plugin table detection with evidence (WH-051). Pattern-only matches stay.
 */
class PluginTableDetector {
	/** @var PluginRegistry */
	private $registry;
	/** @var WpContextService */
	private $context;

	/** Known table-prefix signatures: prefix => owning plugin slug. */
	const KNOWN_SIGNATURES = array(
		'wc_'              => 'woocommerce',
		'woocommerce_'     => 'woocommerce',
		'actionscheduler_' => 'woocommerce',
		'wpml_'            => 'sitepress-multilingual-cms',
		'icl_'             => 'sitepress-multilingual-cms',
		'rank_math_'       => 'seo-by-rank-math',
		'rm_'              => null, // ambiguous on purpose: never auto-claimed.
		'elementor_'       => 'elementor',
		'wf_'              => 'wordfence',
		'wordfence_'       => 'wordfence',
		'itsec_'           => 'better-wp-security',
		'wpf_'             => null,
		'um_'              => null,
		'bb_'              => null,
	);

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
	 * @return array|null {slug, name, active, evidence: Evidence[], strength}
	 */
	public function detect( $table, $plugins = null ) {
		if ( null === $plugins ) {
			$plugins = $this->registry->list();
		}
		$remainder = $this->context->strip_prefix( $table );
		$haystack  = null !== $remainder ? strtolower( $remainder ) : strtolower( $table );
		$by_slug   = array();
		foreach ( $plugins as $plugin ) {
			$by_slug[ $plugin['slug'] ] = $plugin;
		}

		// 1. Known schema signatures (strongest non-registered signal).
		// A signature whose owning plugin is NOT installed is not ownership:
		// return null so the orphan detector can evaluate it as a candidate.
		foreach ( self::KNOWN_SIGNATURES as $sig => $owner_slug ) {
			if ( null === $owner_slug || 0 !== strpos( $haystack, $sig ) ) {
				continue;
			}
			$plugin = isset( $by_slug[ $owner_slug ] ) ? $by_slug[ $owner_slug ] : null;
			if ( ! $plugin ) {
				continue;
			}
			$active   = (bool) $plugin['active'] || (bool) $plugin['network_active'];
			$strength = $active ? 80 : 60;
			$evidence = array(
				new Evidence( Evidence::KNOWN_SCHEMA_SIGNATURE, sprintf( 'Table %s carries the known %s signature.', $table, $sig ), $strength ),
				new Evidence(
					Evidence::PLUGIN_METADATA,
					sprintf( 'Owning plugin %s is %s.', $owner_slug, $active ? 'active' : 'installed but inactive' ),
					$active ? 70 : 50
				),
			);
			return array(
				'slug'     => $owner_slug,
				'name'     => $plugin['name'],
				'active'   => $active,
				'evidence' => $evidence,
				'strength' => $strength,
			);
		}

		// 2. Slug-prefix patterns.
		$best = null;
		foreach ( $plugins as $plugin ) {
			foreach ( $this->slug_variants( $plugin['slug'] ) as $variant ) {
				if ( $haystack === $variant || 0 === strpos( $haystack, $variant . '_' ) ) {
					$active   = (bool) $plugin['active'] || (bool) $plugin['network_active'];
					$strength = $active ? 60 : 40;
					$score    = $strength + strlen( $variant );
					if ( null === $best || $score > $best['score'] ) {
						$best = array(
							'slug'     => $plugin['slug'],
							'name'     => $plugin['name'],
							'active'   => $active,
							'evidence' => array(
								new Evidence( Evidence::TABLE_NAME_PATTERN, sprintf( 'Table %s matches the %s naming pattern.', $table, $variant ), $strength ),
								new Evidence( Evidence::PLUGIN_METADATA, sprintf( 'Plugin %s is %s.', $plugin['slug'], $active ? 'active' : 'installed but inactive' ), $active ? 55 : 35 ),
							),
							'strength' => $strength,
							'score'    => $score,
						);
					}
				}
			}
		}
		if ( $best ) {
			unset( $best['score'] );
		}
		return $best;
	}

	/**
	 * @param string $slug Plugin slug.
	 * @return string[]
	 */
	public function slug_variants( $slug ) {
		$slug     = strtolower( (string) $slug );
		$variants = array( $slug, str_replace( '-', '_', $slug ) );
		foreach ( array( '-', '_' ) as $sep ) {
			$parts = explode( $sep, $slug );
			if ( count( $parts ) > 1 ) {
				$variants[] = $parts[0];
			}
		}
		return array_values( array_unique( $variants ) );
	}
}
