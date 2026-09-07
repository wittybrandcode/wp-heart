<?php
/**
 * WordPress core table recognition (WH-041). Database reality wins:
 * a missing "core" table is reported, never synthesized.
 *
 * @package WP_Heart
 */

namespace WPHeart\Intelligence;

use WPHeart\Domain\Classification;
use WPHeart\Domain\Evidence;
use WPHeart\Support\ClassificationType;
use WPHeart\Support\Confidence;

/**
 * WordPress core table recognition (WH-041). Database reality wins:.
 */
class CoreTableRecognizer {
	/** @var WpContextService */
	private $context;

	/** Core suffixes (single-site). */
	const CORE_SUFFIXES = array(
		'posts',
		'postmeta',
		'comments',
		'commentmeta',
		'terms',
		'termmeta',
		'term_taxonomy',
		'term_relationships',
		'options',
		'links',
		'users',
		'usermeta',
	);

	/** Additional network-global suffixes on multisite. */
	const MS_SUFFIXES = array(
		'blogs',
		'blogmeta',
		'site',
		'sitemeta',
		'signups',
		'registration_log',
		'sitecategories',
	);

	/**
	 * Network tables a fresh multisite install actually creates.
	 * `sitecategories` is legacy/optional and must never raise alarms.
	 */
	const MS_EXPECTED = array(
		'blogs',
		'blogmeta',
		'site',
		'sitemeta',
		'signups',
		'registration_log',
	);

	/**
	 * @param WpContextService $context WP context.
	 */
	public function __construct( WpContextService $context ) {
		$this->context = $context;
	}

	/**
	 * @param string $table Table name.
	 * @return Classification|null CORE classification or null.
	 */
	public function recognize( $table ) {
		$suffixes = self::CORE_SUFFIXES;
		if ( $this->context->is_multisite() ) {
			$suffixes = array_merge( $suffixes, self::MS_SUFFIXES );
		}

		$remainder = $this->strip_site_prefix( $table );
		if ( null !== $remainder && in_array( $remainder, $suffixes, true ) ) {
			return $this->core_result( $table, $remainder );
		}

		// Multisite sister-site tables: {base}{blog_id}_{core} belongs to a
		// specific site's core set. Recognized generically (numeric blog id
		// plus known core suffix) without claiming which site owns data.
		if ( $this->context->is_multisite() ) {
			$base = $this->context->base_prefix();
			if ( '' !== $base && 0 === strpos( $table, $base ) ) {
				$rest = substr( $table, strlen( $base ) );
				if ( preg_match( '/^(\d+)_([A-Za-z0-9_]+)$/', $rest, $m ) && in_array( $m[2], $suffixes, true ) ) {
					$site_id         = (int) $m[1];
					$expected_prefix = $this->context->blog_prefix( $site_id );
					
					// Ensure the table exactly matches the resolved dynamic prefix for the site
					if ( $table === $expected_prefix . $m[2] ) {
						return new Classification(
							ClassificationType::CORE,
							Confidence::HIGH,
							array(
								new Evidence( Evidence::WORDPRESS_CORE, sprintf( 'Table %s matches the known core structure %s for site %s.', $table, $m[2], $site_id ), 85 ),
								new Evidence( Evidence::MULTISITE_CONTEXT, sprintf( 'Evaluated as a site-%s table in the current multisite network.', $site_id ), 55 ),
							)
						);
					}
				}
			}
		}

		// Not a known core structure: never CORE, even when some plugin
		// registered the name on $wpdb (plugins may extend $wpdb->tables).
		return null;
	}

	/**
	 * @param string $table Table name.
	 * @param string $remainder Core suffix.
	 * @return Classification
	 */
	private function core_result( $table, $remainder ) {
		$registered = $this->context->registered_tables();
		if ( in_array( $table, $registered, true ) ) {
			return new Classification(
				ClassificationType::CORE,
				Confidence::HIGH,
				array(
					new Evidence( Evidence::WORDPRESS_CORE, sprintf( 'Table %s is registered by the WordPress database API and matches the known core structure %s.', $table, $remainder ), 95 ),
				)
			);
		}

		$evidence = array(
			new Evidence( Evidence::WORDPRESS_CORE, sprintf( 'Table %s matches the known core structure %s under the active prefix.', $table, $remainder ), 85 ),
		);
		if ( $this->context->is_multisite() ) {
			$evidence[] = new Evidence( Evidence::MULTISITE_CONTEXT, 'Evaluated in the current multisite/site context.', 40 );
		}
		return new Classification( ClassificationType::CORE, Confidence::HIGH, $evidence );
	}

	/**
	 * Strip the site prefix; fall back to the network base prefix only on
	 * multisite (where global tables legitimately use it). On single-site a
	 * foreign-prefix table is never core.
	 *
	 * @param string $table Table name.
	 * @return string|null Remainder or null.
	 */
	private function strip_site_prefix( $table ) {
		$prefix = $this->context->prefix();
		if ( '' !== $prefix && 0 === strpos( $table, $prefix ) ) {
			return substr( $table, strlen( $prefix ) );
		}
		if ( $this->context->is_multisite() ) {
			$base = $this->context->base_prefix();
			if ( '' !== $base && $base !== $prefix && 0 === strpos( $table, $base ) ) {
				return substr( $table, strlen( $base ) );
			}
		}
		return null;
	}

	/**
	 * Expected core tables for the current context (used by health checks).
	 *
	 * @return string[] Full table names.
	 */
	public function expected_tables() {
		$prefix   = $this->context->prefix();
		$suffixes = self::CORE_SUFFIXES;
		if ( $this->context->is_multisite() ) {
			$suffixes = array_merge( $suffixes, self::MS_EXPECTED );
		}
		$out = array();
		foreach ( $suffixes as $suffix ) {
			$out[] = $prefix . $suffix;
		}
		return $out;
	}
}
