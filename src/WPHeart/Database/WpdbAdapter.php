<?php
/**
 * $wpdb-backed adapter. Read-only by contract (WH-010, WH-012).
 *
 * @package WP_Heart
 */

namespace WPHeart\Database;

use WPHeart\Logging\Logger;

/**
 * $wpdb-backed adapter. Read-only by contract (WH-010, WH-012).
 */
class WpdbAdapter implements DatabaseAdapterInterface {
	/** @var object $wpdb instance (untyped for test fakes). */
	private $wpdb;

	/**
	 * @param object $wpdb WordPress database object.
	 */
	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * @return string
	 */
	public function db_name() {
		if ( defined( 'DB_NAME' ) ) {
			return (string) DB_NAME;
		}
		$name = $this->wpdb->get_var( 'SELECT DATABASE()' );
		$this->clear_error();
		return is_string( $name ) ? $name : '';
	}

	/**
	 * @return array
	 */
	public function server_info() {
		$info = array(
			'engine'    => 'UNKNOWN',
			'version'   => 'UNKNOWN',
			'charset'   => isset( $this->wpdb->charset ) ? (string) $this->wpdb->charset : 'UNKNOWN',
			'collation' => 'UNKNOWN',
		);

		$version = method_exists( $this->wpdb, 'db_version' ) ? $this->wpdb->db_version() : $this->wpdb->get_var( 'SELECT VERSION()' );
		$this->clear_error();
		if ( is_string( $version ) && '' !== $version ) {
			$info['version'] = $version;
			$info['engine']  = false !== stripos( $version, 'mariadb' ) ? 'MariaDB' : 'MySQL';
		}

		$collation = method_exists( $this->wpdb, 'get_charset_collate' ) ? $this->wpdb->get_charset_collate() : '';
		if ( is_string( $collation ) && '' !== $collation ) {
			$info['collation'] = $collation;
		} else {
			// constant() keeps this defensive fallback analyzable without
			// constant-folding the DB_COLLATE check away.
			$const_collate = defined( 'DB_COLLATE' ) ? (string) constant( 'DB_COLLATE' ) : '';
			if ( '' !== $const_collate ) {
				$info['collation'] = $const_collate;
			}
		}

		return $info;
	}

	/**
	 * @return string[]
	 */
	public function list_table_names() {
		$db  = $this->db_name();
		$sql = 'SHOW FULL TABLES FROM `' . str_replace( '`', '``', $db ) . "` WHERE Table_type IN ('BASE TABLE','VIEW')";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- metadata discovery, cached by caller; database name is backtick-escaped (identifiers cannot be placeholders).
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			Logger::log( Logger::CHANNEL_DISCOVERY, 'list_table_names failed', array( 'error' => $this->last_error() ) );
			$this->clear_error();
			return array();
		}
		$this->clear_error();
		$tables = array();
		foreach ( $rows as $row ) {
			$values = array_values( (array) $row );
			if ( isset( $values[0] ) && is_string( $values[0] ) && '' !== $values[0] ) {
				$tables[] = $values[0];
			}
		}
		sort( $tables );
		return $tables;
	}

	/**
	 * @param string $table Validated table name.
	 * @return array|null
	 */
	public function table_status( $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- single-table metadata, cached by caller; table name passed as prepared %s value.
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $table ), ARRAY_A );
		$this->clear_error();
		if ( is_array( $rows ) && isset( $rows[0] ) && is_array( $rows[0] ) ) {
			return $rows[0];
		}
		return null;
	}

	/**
	 * @param string $table Validated table name.
	 * @return array[]
	 */
	public function table_columns( $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- schema metadata, cached by caller; validated table name is backtick-quoted (identifiers cannot be placeholders).
		$rows = $this->wpdb->get_results( 'SHOW FULL COLUMNS FROM `' . str_replace( '`', '``', $table ) . '`', ARRAY_A );
		$this->clear_error();
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param string $table Validated table name.
	 * @return array[]
	 */
	public function table_indexes( $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- schema metadata, cached by caller; validated table name is backtick-quoted (identifiers cannot be placeholders).
		$rows = $this->wpdb->get_results( 'SHOW INDEX FROM `' . str_replace( '`', '``', $table ) . '`', ARRAY_A );
		$this->clear_error();
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param string $table Validated table name.
	 * @return array[]
	 */
	public function foreign_keys( $table ) {
		$sql = 'SELECT k.CONSTRAINT_NAME AS name, k.COLUMN_NAME AS col, k.REFERENCED_TABLE_NAME AS ref_table,'
			. ' k.REFERENCED_COLUMN_NAME AS ref_col, r.UPDATE_RULE AS update_rule, r.DELETE_RULE AS delete_rule'
			. ' FROM information_schema.KEY_COLUMN_USAGE k'
			. ' LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS r'
			. ' ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME'
			. ' AND r.TABLE_NAME = k.TABLE_NAME'
			. ' WHERE k.TABLE_SCHEMA = %s AND k.TABLE_NAME = %s AND k.REFERENCED_TABLE_NAME IS NOT NULL'
			. ' ORDER BY k.ORDINAL_POSITION';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- constraint metadata, cached by caller; schema/table passed as prepared %s values.
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $this->db_name(), $table ), ARRAY_A );
		$this->clear_error();
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param string $sql Safe read SQL.
	 * @return array
	 */
	public function run_read( $sql ) {
		$start = microtime( true );
		// Reset first: $wpdb->last_error is sticky and a failed internal
		// write (e.g. transient on a read-only connection) must never
		// poison the error state of an unrelated successful read.
		if ( isset( $this->wpdb->last_error ) ) {
			$this->wpdb->last_error = '';
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- policy-validated read-only user query; dynamic values inside it were prepared by the caller path.
		$rows     = $this->wpdb->get_results( $sql, ARRAY_A );
		$elapsed  = ( microtime( true ) - $start ) * 1000.0;
		$error    = $this->last_error();
		$this->clear_error();
		$rows_out = is_array( $rows ) ? $rows : array();
		return array(
			'rows'       => $rows_out,
			'error'      => $error,
			'elapsed_ms' => round( $elapsed, 2 ),
		);
	}

	/**
	 * @param string $sql SQL with placeholders.
	 * @param array  $params Parameters.
	 * @return array[]
	 */
	public function fetch_all( $sql, array $params = array() ) {
		// Always routed through prepare(): static SQL passes through
		// unchanged, placeholder SQL is bound. Never raw interpolation.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- single choke point: internal callers pass static SQL or identifier-quoted SQL with %s placeholders; all values bound here.
		$prepared = $this->wpdb->prepare( $sql, $params );
		if ( false === $prepared || '' === $prepared ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- internal bounded queries.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above via $wpdb->prepare.
		$rows = $this->wpdb->get_results( $prepared, ARRAY_A );
		$this->clear_error();
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param string $table Validated table name.
	 * @param string $column Validated column name.
	 * @param string $value Raw value (prepared, never interpolated).
	 * @return array|null
	 */
	public function fetch_row( $table, $column, $value ) {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- validated identifiers are backtick-quoted and the value is a prepared %s parameter.
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				'SELECT * FROM `' . str_replace( '`', '``', $table ) . '` WHERE `' . str_replace( '`', '``', $column ) . '` = %s LIMIT 1',
				(string) $value
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$this->clear_error();
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Autoload flag values that mean "loaded on every request", across
	 * WordPress generations (legacy yes/no, modern on/off/auto).
	 */
	const AUTOLOAD_ON = array( 'yes', 'on', 'auto', 'auto-on' );

	/**
	 * @return string Quoted options table (core-provided identifier).
	 */
	private function options_table() {
		return '`' . str_replace( '`', '``', $this->wpdb->options ) . '`';
	}

	/**
	 * @return array
	 */
	public function options_autoload_totals() {
		$rows = $this->fetch_all(
			'SELECT COUNT(*) AS n, COALESCE(SUM(LENGTH(option_value)), 0) AS bytes FROM ' . $this->options_table() . ' WHERE autoload IN (%s, %s, %s, %s)',
			self::AUTOLOAD_ON
		);
		if ( ! isset( $rows[0] ) ) {
			return array(
				'count' => 0,
				'bytes' => 0,
			);
		}
		return array(
			'count' => (int) $rows[0]['n'],
			'bytes' => (int) $rows[0]['bytes'],
		);
	}

	/**
	 * @param int $limit Row cap.
	 * @return array[]
	 */
	public function top_autoload_options( $limit ) {
		$limit = max( 1, min( 50, (int) $limit ) );
		$rows  = $this->fetch_all(
			'SELECT option_name AS name, LENGTH(option_value) AS bytes FROM ' . $this->options_table() . ' WHERE autoload IN (%s, %s, %s, %s) ORDER BY bytes DESC LIMIT %d',
			array_merge( self::AUTOLOAD_ON, array( $limit ) )
		);
		$out   = array();
		foreach ( $rows as $row ) {
			$row   = (array) $row;
			$out[] = array(
				'name'  => isset( $row['name'] ) ? (string) $row['name'] : '',
				'bytes' => isset( $row['bytes'] ) ? (int) $row['bytes'] : 0,
			);
		}
		return $out;
	}

	/**
	 * @return array
	 */
	public function transient_stats() {
		$values = $this->fetch_all(
			'SELECT COUNT(*) AS n FROM ' . $this->options_table() . ' WHERE option_name LIKE %s ESCAPE \'\\\\\' AND option_name NOT LIKE %s ESCAPE \'\\\\\'',
			array( '\\_transient\\_%', '\\_transient\\_timeout\\_%' )
		);
		// LIKE patterns are literals here; esc_like parity is unnecessary.
		$expired = $this->fetch_all(
			'SELECT COUNT(*) AS n FROM ' . $this->options_table() . ' WHERE option_name LIKE %s ESCAPE \'\\\\\' AND option_value < UNIX_TIMESTAMP()',
			array( '\\_transient\\_timeout\\_%' )
		);
		return array(
			'values'  => isset( $values[0]['n'] ) ? (int) $values[0]['n'] : 0,
			'expired' => isset( $expired[0]['n'] ) ? (int) $expired[0]['n'] : 0,
		);
	}

	/**
	 * @return string Last error.
	 */
	public function last_error() {
		$error = isset( $this->wpdb->last_error ) ? (string) $this->wpdb->last_error : '';
		return $error;
	}

	/**
	 * Clears the last error to prevent sticky errors leaking to other WP operations.
	 */
	private function clear_error() {
		if ( isset( $this->wpdb->last_error ) && '' !== $this->wpdb->last_error ) {
			$this->wpdb->last_error = '';
		}
	}
}
