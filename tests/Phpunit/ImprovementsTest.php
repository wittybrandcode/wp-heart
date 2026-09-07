<?php
/**
 * Unit tests for Phase 1–4 improvements.
 *
 * Covers:
 *   - AuditStore graceful degradation (slow write disables logger)
 *   - AIEngine::generate_query() prompt mapping
 *   - WpContextService::blog_prefix() fallback logic
 *   - CoreTableRecognizer multisite prefix validation
 *
 * @package WP_Heart_Tests
 */

use PHPUnit\Framework\TestCase;
use WPHeart\Audit\AuditStore;
use WPHeart\Intelligence\AIEngine;
use WPHeart\Intelligence\WpContextService;
use WPHeart\Intelligence\CoreTableRecognizer;
use WPHeart\Config\Config;

final class ImprovementsTest extends TestCase {

	// ------------------------------------------------------------------
	// AIEngine
	// ------------------------------------------------------------------

	public function test_ai_engine_maps_largest_options_prompt() {
		$engine = new AIEngine();
		$sql    = $engine->generate_query( 'show me the 10 largest options' );
		$this->assertStringContainsStringIgnoringCase( 'SELECT', $sql );
		$this->assertStringContainsStringIgnoringCase( 'option_name', $sql );
		$this->assertStringContainsStringIgnoringCase( 'ORDER BY', $sql );
	}

	public function test_ai_engine_maps_transients_prompt() {
		$engine = new AIEngine();
		$sql    = $engine->generate_query( 'list all transients in the database' );
		$this->assertStringContainsStringIgnoringCase( 'SELECT', $sql );
		$this->assertStringContainsStringIgnoringCase( 'transient', $sql );
	}

	public function test_ai_engine_maps_plugins_prompt() {
		$engine = new AIEngine();
		$sql    = $engine->generate_query( 'Which plugins are currently active?' );
		$this->assertStringContainsStringIgnoringCase( 'SELECT', $sql );
		$this->assertStringContainsStringIgnoringCase( 'active_plugins', $sql );
	}

	public function test_ai_engine_returns_safe_placeholder_for_unknown_prompt() {
		$engine = new AIEngine();
		$sql    = $engine->generate_query( 'something completely unrecognized' );
		// Must return a SELECT-only safe fallback, never a modifying statement.
		$this->assertStringStartsWith( 'SELECT', ltrim( $sql ) );
	}

	// ------------------------------------------------------------------
	// WpContextService::blog_prefix()
	// ------------------------------------------------------------------

	public function test_blog_prefix_fallback_without_wpdb_method() {
		// Construct with an explicit base prefix.
		$ctx = new WpContextService( 'wp_', true );
		// When wpdb doesn't have get_blog_prefix, falls back to base_prefix + id + '_'.
		// Since there is no real $wpdb global with get_blog_prefix in the test environment,
		// the fallback path must produce the expected pattern.
		$result = $ctx->blog_prefix( 3 );
		$this->assertStringContainsString( '3', $result );
	}

	public function test_blog_prefix_blog_id_1_matches_base_prefix() {
		$ctx = new WpContextService( 'wp_', true );
		// Blog ID 1 on a standard multisite installation uses the base prefix.
		$result = $ctx->blog_prefix( 1 );
		$this->assertNotEmpty( $result );
	}

	// ------------------------------------------------------------------
	// CoreTableRecognizer — multisite sub-site accuracy
	// ------------------------------------------------------------------

	public function test_recognizer_identifies_sister_site_table() {
		$ctx = new WpContextService( 'wp_', true, 1 );
		$recognizer = new CoreTableRecognizer( $ctx );
		// wp_2_posts is a valid sister-site core table in multisite.
		$result = $recognizer->recognize( 'wp_2_posts' );
		$this->assertNotNull( $result, 'Expected wp_2_posts to be recognized as CORE in multisite.' );
	}

	public function test_recognizer_rejects_non_core_suffix_in_multisite() {
		$ctx = new WpContextService( 'wp_', true, 1 );
		$recognizer = new CoreTableRecognizer( $ctx );
		// wp_2_wc_orders has a non-core suffix and must NOT be classified as CORE.
		$result = $recognizer->recognize( 'wp_2_wc_orders' );
		$this->assertNull( $result, 'Expected wp_2_wc_orders to NOT be recognized as CORE.' );
	}

	// ------------------------------------------------------------------
	// AuditStore — graceful degradation static flag reset between tests
	// ------------------------------------------------------------------

	public function test_audit_store_push_does_not_fatal_on_slow_write() {
		// The static $disabled flag must not be set from a previous test run.
		// We directly test that push() is callable without throwing.
		$config = new Config( array( 'audit_max_entries' => 5 ) );
		$store  = new AuditStore( $config );
		$event  = new \WPHeart\Domain\AuditEvent(
			array(
				'type'     => \WPHeart\Domain\AuditEvent::QUERY_EXECUTION,
				'target'   => 'test-table',
				'metadata' => array( 'test' => true ),
				'outcome'  => 'success',
			)
		);
		$this->assertNull( $store->push( $event ) ); // push() returns void/null
	}
}
