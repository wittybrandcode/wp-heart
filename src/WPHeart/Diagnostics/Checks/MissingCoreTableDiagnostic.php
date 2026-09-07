<?php
/**
 * Missing-core-table diagnostic. Boot-critical absences are CRITICAL
 * (Decision D-006); other core absences are ERROR.
 *
 * @package WP_Heart
 */

namespace WPHeart\Diagnostics\Checks;

use WPHeart\Diagnostics\DiagnosticInterface;
use WPHeart\Domain\Evidence;
use WPHeart\Domain\HealthIssue;
use WPHeart\Intelligence\CoreTableRecognizer;
use WPHeart\Intelligence\WpContextService;
use WPHeart\Support\Severity;

/**
 * Missing-core-table diagnostic. Boot-critical absences are CRITICAL.
 */
class MissingCoreTableDiagnostic implements DiagnosticInterface {
	/** @var CoreTableRecognizer|null */
	private $core;
	/** @var WpContextService|null */
	private $context;

	/**
	 * @param CoreTableRecognizer|null $core Core recognizer.
	 * @param WpContextService|null    $context WP context.
	 */
	public function __construct( $core = null, $context = null ) {
		$this->core    = $core;
		$this->context = $context;
	}

	/**
	 * @return string
	 */
	public function id() {
		return 'wpheart_missing_core';
	}

	/**
	 * @return string
	 */
	public function name() {
		return __( 'Missing expected WordPress tables', 'wp-heart' );
	}

	/**
	 * @return string
	 */
	public function description() {
		return __( 'Detects expected WordPress core tables that are absent from the database.', 'wp-heart' );
	}

	/**
	 * @param array $context Context.
	 * @return HealthIssue[]
	 */
	public function check( array $context ) {
		$names = isset( $context['table_names'] ) && is_array( $context['table_names'] ) ? $context['table_names'] : array();
		if ( isset( $context['expected_core'] ) && is_array( $context['expected_core'] ) ) {
			$expected = $context['expected_core'];
		} elseif ( $this->core ) {
			$expected = $this->core->expected_tables();
		} else {
			return array();
		}

		$prefix   = $this->context ? $this->context->prefix() : '';
		$critical = array( $prefix . 'options', $prefix . 'users', $prefix . 'usermeta', $prefix . 'posts' );
		$issues   = array();

		foreach ( $expected as $table ) {
			if ( in_array( $table, $names, true ) ) {
				continue;
			}
			$is_critical = in_array( $table, $critical, true );
			$issues[]    = new HealthIssue(
				array(
					'id'             => 'wpheart_missing_core:' . $table,
					'diagnostic'     => $this->id(),
					'severity'       => $is_critical ? Severity::CRITICAL : Severity::ERROR,
					'affected'       => array( $table ),
					'evidence'       => array(
						new Evidence( Evidence::WORDPRESS_CORE, sprintf( 'Expected core table %s was not found during discovery.', $table ), 90 ),
					),
					'explanation'    => sprintf( 'Expected WordPress table %s is missing. WordPress cannot function normally without its core tables.', $table ),
					'recommendation' => __( 'Restore from a backup or reinstall the affected component. Investigate before creating tables manually.', 'wp-heart' ),
				)
			);
		}
		return $issues;
	}
}
