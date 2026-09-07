<?php
/**
 * Theme/context attribution (WH-050 companion). Conservative by design.
 *
 * @package WP_Heart
 */

namespace WPHeart\Intelligence;

use WPHeart\Domain\Evidence;

/**
 * Theme/context attribution (WH-050 companion). Conservative by design.
 */
class ThemeDetector {
	/** @var WpContextService */
	private $context;

	/**
	 * @param WpContextService $context WP context.
	 */
	public function __construct( WpContextService $context ) {
		$this->context = $context;
	}

	/**
	 * @param string $table Table name.
	 * @return array|null {slug, name, evidence, strength}
	 */
	public function detect( $table ) {
		$themes = $this->active_themes();
		if ( empty( $themes ) ) {
			return null;
		}
		$remainder = $this->context->strip_prefix( $table );
		$haystack  = null !== $remainder ? strtolower( $remainder ) : strtolower( $table );
		foreach ( $themes as $theme ) {
			$variant = str_replace( '-', '_', strtolower( $theme['slug'] ) );
			if ( $haystack === $variant || 0 === strpos( $haystack, $variant . '_' ) ) {
				return array(
					'slug'     => $theme['slug'],
					'name'     => $theme['name'],
					'evidence' => array(
						new Evidence( Evidence::THEME_METADATA, sprintf( 'Table %s matches the active theme %s pattern.', $table, $theme['slug'] ), 50 ),
					),
					'strength' => 50,
				);
			}
		}
		return null;
	}

	/**
	 * @return array[] Active stylesheet (+ parent) themes.
	 */
	private function active_themes() {
		$out = array();
		if ( function_exists( 'wp_get_theme' ) ) {
			$theme = wp_get_theme();
			if ( $theme && $theme->exists() ) {
				$out[]  = array(
					'slug' => (string) $theme->get_stylesheet(),
					'name' => (string) $theme->get( 'Name' ),
				);
				$parent = $theme->parent();
				if ( $parent && $parent->exists() ) {
					$out[] = array(
						'slug' => (string) $parent->get_stylesheet(),
						'name' => (string) $parent->get( 'Name' ),
					);
				}
			}
			return $out;
		}
		foreach ( array( 'stylesheet', 'template' ) as $option ) {
			$value = function_exists( 'get_option' ) ? get_option( $option ) : '';
			if ( is_string( $value ) && '' !== $value ) {
				$out[] = array(
					'slug' => $value,
					'name' => $value,
				);
			}
		}
		return $out;
	}
}
