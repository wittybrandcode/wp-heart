<?php
/**
 * Evidence-aggregating classification engine (WH-060–WH-062).
 *
 * Priority: CORE > PLUGIN > THEME_CUSTOM > ORPHAN_CANDIDATE > UNKNOWN.
 * UNKNOWN is a valid terminal state, never an error.
 *
 * @package WP_Heart
 */

namespace WPHeart\Classification;

use WPHeart\Domain\Classification;
use WPHeart\Intelligence\ConfidenceScorer;
use WPHeart\Intelligence\CoreTableRecognizer;
use WPHeart\Intelligence\OrphanDetector;
use WPHeart\Intelligence\PluginTableDetector;
use WPHeart\Intelligence\ThemeDetector;
use WPHeart\Support\ClassificationType;

/**
 * Evidence-aggregating classification engine (WH-060–WH-062).
 */
class ClassificationEngine {
	/** @var CoreTableRecognizer */
	private $core;
	/** @var PluginTableDetector */
	private $plugins;
	/** @var ThemeDetector */
	private $themes;
	/** @var OrphanDetector */
	private $orphans;
	/** @var ConfidenceScorer */
	private $scorer;

	/**
	 * @param CoreTableRecognizer $core Core recognizer.
	 * @param PluginTableDetector $plugins Plugin detector.
	 * @param ThemeDetector       $themes Theme detector.
	 * @param OrphanDetector      $orphans Orphan detector.
	 * @param ConfidenceScorer    $scorer Confidence scorer.
	 */
	public function __construct( CoreTableRecognizer $core, PluginTableDetector $plugins, ThemeDetector $themes, OrphanDetector $orphans, ConfidenceScorer $scorer ) {
		$this->core    = $core;
		$this->plugins = $plugins;
		$this->themes  = $themes;
		$this->orphans = $orphans;
		$this->scorer  = $scorer;
	}

	/**
	 * @param string       $table Table name.
	 * @param array[]|null $plugins Registry rows (null = load live).
	 * @return Classification
	 */
	public function classify( $table, $plugins = null ) {
		$core = $this->core->recognize( $table );
		if ( $core ) {
			return $core;
		}

		$match = $this->plugins->detect( $table, $plugins );
		if ( $match ) {
			return new Classification(
				ClassificationType::PLUGIN,
				$this->scorer->score( $match['evidence'] ),
				$match['evidence'],
				$match['slug']
			);
		}

		$theme = $this->themes->detect( $table );
		if ( $theme ) {
			return new Classification(
				ClassificationType::THEME_CUSTOM,
				$this->scorer->score( $theme['evidence'] ),
				$theme['evidence'],
				$theme['slug']
			);
		}

		$orphan = $this->orphans->detect( $table, $plugins );
		if ( $orphan ) {
			return new Classification(
				ClassificationType::ORPHAN_CANDIDATE,
				$this->scorer->score( $orphan['evidence'] ),
				$orphan['evidence'],
				isset( $orphan['owner_hint'] ) ? $orphan['owner_hint'] : null
			);
		}

		return Classification::unknown( $table );
	}

	/**
	 * @param string[]     $tables Table names.
	 * @param array[]|null $plugins Registry rows.
	 * @return Classification[] Keyed by table name.
	 */
	public function classify_all( array $tables, $plugins = null ) {
		$out = array();
		foreach ( $tables as $table ) {
			$out[ $table ] = $this->classify( $table, $plugins );
		}
		return $out;
	}
}
