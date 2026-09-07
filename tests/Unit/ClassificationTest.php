<?php
/**
 * Classification engine tests (WH-060–WH-062) incl. false-ownership guards.
 *
 * @package WP_Heart_Tests
 */

use WPHeart\Classification\ClassificationEngine;
use WPHeart\Intelligence\ConfidenceScorer;
use WPHeart\Intelligence\CoreTableRecognizer;
use WPHeart\Intelligence\OrphanDetector;
use WPHeart\Intelligence\PluginRegistry;
use WPHeart\Intelligence\PluginTableDetector;
use WPHeart\Intelligence\ThemeDetector;
use WPHeart\Intelligence\WpContextService;
use WPHeart\Support\ClassificationType;
use WPHeart\Support\Confidence;

wh_group( 'Classification' );

// Isolate from other test files that set $GLOBALS['wpdb'], then simulate a
// $wpdb extended by plugins (registration alone must not imply CORE).
$GLOBALS['wpdb'] = new FakeWpdb();

$GLOBALS['wh_options']['stylesheet'] = 'mytheme';
$GLOBALS['wh_options']['template']   = 'mytheme';

$context   = new WpContextService( 'wp_', false, 1 );
$registry  = new PluginRegistry();
$engine    = new ClassificationEngine(
	new CoreTableRecognizer( $context ),
	new PluginTableDetector( $registry, $context ),
	new ThemeDetector( $context ),
	new OrphanDetector( $registry, $context ),
	new ConfidenceScorer()
);
$plugins = FakeAdapter::default_plugins();

$c = $engine->classify( 'wp_posts', $plugins );
wh_check( ClassificationType::CORE === $c->type() && Confidence::HIGH === $c->confidence(), 'core recognized with high confidence' );
wh_check( ! empty( $c->evidence() ), 'core carries evidence' );

$c = $engine->classify( 'wp_wc_orders', $plugins );
wh_check( ClassificationType::PLUGIN === $c->type() && 'woocommerce' === $c->owner(), 'woocommerce signature attributed' );
wh_check( ClassificationType::CORE !== $c->type(), 'plugin-registered table never CORE via $wpdb->tables' );

$c = $engine->classify( 'wp_old_gw_log', $plugins );
wh_check( ClassificationType::PLUGIN === $c->type() && 'old-gateway' === $c->owner(), 'inactive plugin still attributed (stale)' );
wh_check( Confidence::rank( $c->confidence() ) <= Confidence::rank( Confidence::MEDIUM ), 'stale plugin never HIGH' );

$c = $engine->classify( 'wp_wordfence_hits', $plugins );
wh_check( ClassificationType::ORPHAN_CANDIDATE === $c->type(), 'absent owner becomes orphan candidate' );

$c = $engine->classify( 'wp_xyz_mystery', $plugins );
wh_check( ClassificationType::UNKNOWN === $c->type(), 'unrecognized table stays UNKNOWN' );
wh_check( ! empty( $c->evidence() ), 'unknown carries evidence too' );

$c = $engine->classify( 'wp_mytheme_data', $plugins );
wh_check( ClassificationType::THEME_CUSTOM === $c->type(), 'theme pattern attributed' );

// False-ownership regression: ambiguous short prefixes must never be claimed.
$detector = new PluginTableDetector( $registry, $context );
wh_check( null === $detector->detect( 'wp_rm_stats', $plugins ), 'ambiguous rm_ prefix unclaimed' );
wh_check( null === $detector->detect( 'wp_bb_press', $plugins ), 'ambiguous bb_ prefix unclaimed' );

// Custom prefix context: same logic under a different prefix.
$custom_ctx    = new WpContextService( 'shop_', false, 1 );
$custom_engine = new ClassificationEngine(
	new CoreTableRecognizer( $custom_ctx ),
	new PluginTableDetector( $registry, $custom_ctx ),
	new ThemeDetector( $custom_ctx ),
	new OrphanDetector( $registry, $custom_ctx ),
	new ConfidenceScorer()
);
$c = $custom_engine->classify( 'shop_posts', $plugins );
wh_check( ClassificationType::CORE === $c->type(), 'core recognized under custom prefix' );
$c = $custom_engine->classify( 'wp_posts', $plugins );
wh_check( ClassificationType::UNKNOWN === $c->type(), 'foreign-prefix table not misclassified as core' );

// Confidence normalization.
$scorer = new ConfidenceScorer();
wh_check( Confidence::UNKNOWN === $scorer->score( array() ), 'no evidence means unknown' );
