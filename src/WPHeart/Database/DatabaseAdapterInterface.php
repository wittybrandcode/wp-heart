<?php
/**
 * Database adapter contract. All DB access flows through here (WH-010).
 * Domain code must never touch $wpdb directly.
 *
 * @package WP_Heart
 */

namespace WPHeart\Database;

/**
 * Database adapter contract. All DB access flows through here (WH-010).
 */
interface DatabaseAdapterInterface {
	/**
	 * @return string Database name (never credentials).
	 */
	public function db_name();

	/**
	 * @return array {engine, version, charset, collation} with UNKNOWN fallbacks.
	 */
	public function server_info();

	/**
	 * Enumerate every table visible to the current connection (no prefix filter).
	 *
	 * @return string[]
	 */
	public function list_table_names();

	/**
	 * @param string $table Validated table name.
	 * @return array|null SHOW TABLE STATUS row or null.
	 */
	public function table_status( $table );

	/**
	 * @param string $table Validated table name.
	 * @return array[] SHOW FULL COLUMNS rows.
	 */
	public function table_columns( $table );

	/**
	 * @param string $table Validated table name.
	 * @return array[] SHOW INDEX rows.
	 */
	public function table_indexes( $table );

	/**
	 * Foreign-key metadata for one table (empty array when unavailable).
	 *
	 * @param string $table Validated table name.
	 * @return array[]
	 */
	public function foreign_keys( $table );

	/**
	 * Run an already-validated read-only query.
	 *
	 * @param string $sql Safe read SQL.
	 * @return array {rows, error, elapsed_ms}
	 */
	public function run_read( $sql );

	/**
	 * Prepared SELECT helper for internal bounded queries.
	 *
	 * @param string $sql SQL with placeholders.
	 * @param array  $params Parameters.
	 * @return array[] Rows.
	 */
	public function fetch_all( $sql, array $params = array() );

	/**
	 * Fetch one row by an exact column match (validated identifiers, prepared value).
	 *
	 * @param string $table Validated table name.
	 * @param string $column Validated column name.
	 * @param string $value Raw value (prepared, never interpolated).
	 * @return array|null Row or null.
	 */
	public function fetch_row( $table, $column, $value );

	/**
	 * Autoloaded-options totals. Handles both the legacy (yes/no) and the
	 * modern (on/off/auto) autoload schemes.
	 *
	 * @return array {count: int, bytes: int}
	 */
	public function options_autoload_totals();

	/**
	 * Largest autoloaded options, biggest first.
	 *
	 * @param int $limit Row cap, clamped to 1–50 by the caller contract.
	 * @return array[] Each {name: string, bytes: int}.
	 */
	public function top_autoload_options( $limit );

	/**
	 * @return array {values: int, expired: int} Transient value rows and expired timeout rows.
	 */
	public function transient_stats();

	/**
	 * @return string Last sanitized error message (empty when none).
	 */
	public function last_error();
}
