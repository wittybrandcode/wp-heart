<?php
/**
 * Multisite, upgrade, uninstall and bootstrap tests (WH-230, WH-323, WH-324).
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
use WPHeart\Plugin\Activator;
use WPHeart\Plugin\Deactivator;
use WPHeart\Security\Capabilities;
use WPHeart\Support\ClassificationType;

wh_group( 'MultisiteUpgrade' );

// Multisite context with distinct site/base prefixes.
$ms = new WpContextService( 'wp_2_', true, 2 );
wh_check( true === $ms->is_multisite() && 2 === $ms->blog_id(), 'multisite context overrides' );
wh_check( 'wp_2_' === $ms->prefix(), 'site prefix dynamic' );

$recognizer = new CoreTableRecognizer( $ms );
$expected   = $recognizer->expected_tables();
wh_check( in_array( 'wp_2_posts', $expected, true ), 'site core tables expected' );
wh_check( in_array( 'wp_2_blogs', $expected, true ) || in_array( 'wp_2_site', $expected, true ), 'network tables expected per-site' );

// Network-global table under base prefix recognized on multisite.
$GLOBALS['wpdb'] = new FakeWpdb();
$ms_base = new WpContextService( 'wp_2_', true, 2 );
$GLOBALS['wpdb']->base_prefix = 'wp_';
$engine = new ClassificationEngine(
	new CoreTableRecognizer( $ms_base ),
	new PluginTableDetector( new PluginRegistry(), $ms_base ),
	new ThemeDetector( $ms_base ),
	new OrphanDetector( new PluginRegistry(), $ms_base ),
	new ConfidenceScorer()
);
$c = $engine->classify( 'wp_users', FakeAdapter::default_plugins() );
wh_check( ClassificationType::CORE === $c->type(), 'base-prefix users table is CORE on multisite' );
unset( $GLOBALS['wpdb'] );

// Sister-site tables: recognized as CORE on multisite, UNKNOWN on single-site.
function wh_ms_engine( $prefix, $is_ms, $blog ) {
	$ctx = new WpContextService( $prefix, $is_ms, $blog );
	return new ClassificationEngine(
		new CoreTableRecognizer( $ctx ),
		new PluginTableDetector( new PluginRegistry(), $ctx ),
		new ThemeDetector( $ctx ),
		new OrphanDetector( new PluginRegistry(), $ctx ),
		new ConfidenceScorer()
	);
}
$c = wh_ms_engine( 'wp_', true, 1 )->classify( 'wp_2_posts', array() );
wh_check( ClassificationType::CORE === $c->type(), 'sister-site table is CORE on multisite' );
$c = wh_ms_engine( 'wp_', false, 1 )->classify( 'wp_2_posts', array() );
wh_check( ClassificationType::UNKNOWN === $c->type(), 'numeric-prefix table stays UNKNOWN on single-site' );

// Legacy sitecategories: recognized but never expected (no false alarms).
$ms_expected = ( new CoreTableRecognizer( new WpContextService( 'wp_', true, 1 ) ) )->expected_tables();
wh_check( ! in_array( 'wp_sitecategories', $ms_expected, true ), 'legacy sitecategories not expected' );
wh_check( in_array( 'wp_blogs', $ms_expected, true ) && in_array( 'wp_sitemeta', $ms_expected, true ), 'network tables expected' );

// Activation: capabilities granted, defaults seeded, no destructive behavior.
$GLOBALS['wh_options'] = array();
$GLOBALS['wh_roles']   = array();
Activator::activate();
wh_check( '1.6.0' === $GLOBALS['wh_options']['wp_heart_version'], 'version option seeded' );
wh_check( array() === $GLOBALS['wh_options']['wp_heart_settings'], 'settings default to empty (no assumptions)' );
$admin_caps = $GLOBALS['wh_roles']['administrator']->caps;
foreach ( Capabilities::all() as $cap ) {
	wh_check( isset( $admin_caps[ $cap ] ), 'admin granted ' . $cap );
}

// Upgrade: re-activation preserves user settings and bumps version.
$GLOBALS['wh_options']['wp_heart_settings'] = array( 'cache_ttl' => 600 );
$GLOBALS['wh_options']['wp_heart_version']  = '0.9.0';
Activator::activate();
wh_check( array( 'cache_ttl' => 600 ) === $GLOBALS['wh_options']['wp_heart_settings'], 'upgrade preserves settings' );
wh_check( '1.6.0' === $GLOBALS['wh_options']['wp_heart_version'], 'upgrade bumps version' );

// Deactivation is non-destructive.
Deactivator::deactivate();
wh_check( isset( $GLOBALS['wh_options']['wp_heart_settings'] ), 'deactivation keeps settings' );
wh_check( isset( $GLOBALS['wh_options']['wp_heart_version'] ), 'deactivation keeps version' );

// Bootstrap file loads without fatals under stubbed WordPress.
$GLOBALS['wh_options'] = array();
$GLOBALS['wh_roles']   = array();
require WP_HEART_DIR . 'wp-heart.php';
wh_check( defined( 'WP_HEART_VERSION' ) && '1.6.0' === WP_HEART_VERSION, 'bootstrap defines version' );
wh_check( isset( $GLOBALS['wh_activation_hook'], $GLOBALS['wh_deactivation_hook'] ), 'lifecycle hooks registered' );
call_user_func( $GLOBALS['wh_activation_hook'] );
wh_check( '1.6.0' === $GLOBALS['wh_options']['wp_heart_version'], 'activation hook executes cleanly' );
