<?php
/**
 * Diagnostic contract (WH-070). Each diagnostic is independent and modular.
 *
 * Context shape:
 *   tables: TableInfo[] (metadata level)
 *   table_names: string[]
 *   classifications: Classification[] keyed by table
 *   indexes: IndexInfo[][] keyed by table (when loaded)
 *   summary: array (database summary facts)
 *   expected_core: string[] (full names)
 *
 * @package WP_Heart
 */

namespace WPHeart\Diagnostics;

/**
 * Diagnostic contract (WH-070). Each diagnostic is independent and modular.
 */
interface DiagnosticInterface {
	/**
	 * @return string Stable id, e.g. wpheart_no_pk.
	 */
	public function id();

	/**
	 * @return string Human-readable name.
	 */
	public function name();

	/**
	 * @return string Description of what is checked.
	 */
	public function description();

	/**
	 * @param array $context Diagnostic context.
	 * @return \WPHeart\Domain\HealthIssue[]
	 */
	public function check( array $context );
}
