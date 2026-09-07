<?php
/**
 * Evidence value object: every intelligence claim traces back to evidence.
 *
 * @package WP_Heart
 */

namespace WPHeart\Domain;

/**
 * Evidence value object: every intelligence claim traces back to evidence.
 */
class Evidence {
	const WORDPRESS_CORE         = 'WORDPRESS_CORE';
	const PLUGIN_METADATA        = 'PLUGIN_METADATA';
	const PLUGIN_SCHEMA          = 'PLUGIN_SCHEMA';
	const TABLE_NAME_PATTERN     = 'TABLE_NAME_PATTERN';
	const COLUMN_PATTERN         = 'COLUMN_PATTERN';
	const DATABASE_CONSTRAINT    = 'DATABASE_CONSTRAINT';
	const MULTISITE_CONTEXT      = 'MULTISITE_CONTEXT';
	const KNOWN_SCHEMA_SIGNATURE = 'KNOWN_SCHEMA_SIGNATURE';
	const THEME_METADATA         = 'THEME_METADATA';
	const HISTORICAL_SIGNATURE   = 'HISTORICAL_SIGNATURE';
	const OTHER                  = 'OTHER';

	/** @var string */
	private $source;
	/** @var string */
	private $description;
	/** @var int 0-100 strength of this evidence item. */
	private $strength;

	/**
	 * @param string $source Evidence source constant.
	 * @param string $description Human-readable explanation.
	 * @param int    $strength 0-100.
	 */
	public function __construct( $source, $description, $strength = 50 ) {
		$this->source      = $source;
		$this->description = $description;
		$this->strength    = max( 0, min( 100, (int) $strength ) );
	}

	/**
	 * @return array
	 */
	public function to_array() {
		return array(
			'source'      => $this->source,
			'description' => $this->description,
			'strength'    => $this->strength,
		);
	}

	/**
	 * @return int
	 */
	public function strength() {
		return $this->strength;
	}

	/**
	 * @return string
	 */
	public function source() {
		return $this->source;
	}
}
