<?php
/**
 * Test fakes: in-memory $wpdb and adapter fixtures.
 *
 * @package WP_Heart_Tests
 */

use WPHeart\Database\DatabaseAdapterInterface;

if ( ! class_exists( 'FakeWpdb' ) ) {
	class FakeWpdb {
		public $prefix = 'wp_';
		public $base_prefix = 'wp_';
		public $charset = 'utf8mb4';
		public $collate = 'utf8mb4_unicode_ci';
		public $last_error = '';
		public $tables_data = array();
		public $suppress_log = array();
		public $suppress_state = false;

		public function suppress_errors( $suppress = true ) {
			$prev                  = $this->suppress_state;
			$this->suppress_state  = (bool) $suppress;
			$this->suppress_log[]  = $this->suppress_state ? 'on' : 'off';
			return $prev;
		}

		public function prepare( $sql, ...$args ) {
			if ( 1 === count( $args ) && is_array( $args[0] ) ) {
				$args = $args[0];
			}
			foreach ( $args as $arg ) {
				$repl = is_int( $arg ) || is_float( $arg ) ? (string) $arg : "'" . addslashes( (string) $arg ) . "'";
				$pos  = strpos( $sql, '%s' );
				if ( false === $pos ) {
					$pos = strpos( $sql, '%d' );
				}
				if ( false === $pos ) {
					break;
				}
				$sql = substr_replace( $sql, $repl, $pos, 2 );
			}
			return $sql;
		}

		public function esc_like( $text ) {
			return addcslashes( $text, '\\%_' );
		}

		public function db_version() {
			return '10.4.32-MariaDB';
		}

		public function get_charset_collate() {
			return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
		}

		public function get_var( $sql ) {
			if ( false !== stripos( $sql, 'SELECT DATABASE()' ) ) {
				return 'wp';
			}
			if ( false !== stripos( $sql, 'SELECT VERSION()' ) ) {
				return '10.4.32-MariaDB';
			}
			return null;
		}

		public function get_results( $sql, $output = ARRAY_A ) {
			return array();
		}

		public function get_row( $sql, $output = ARRAY_A ) {
			return null;
		}

		public function tables( $scope = 'all', $prefix = true, $blog_id = 0 ) {
			$p = $prefix ? $this->prefix : '';
			// Simulates plugins (e.g. WooCommerce) extending $wpdb->tables:
			// registration alone must NOT imply CORE.
			return array( $p . 'posts', $p . 'postmeta', $p . 'options', $p . 'users', $p . 'usermeta', $p . 'wc_orders' );
		}
	}
}

if ( ! class_exists( 'FakeAdapter' ) ) {
	class FakeAdapter implements DatabaseAdapterInterface {
		private $tables = array();
		private $grants;
		private $fail_read = false;
		/** @var array|null Override for options totals (tests). */
		public $options_totals = null;

		public function __construct( array $tables = array(), $grants = null ) {
			$this->tables = $tables;
			$this->grants = $grants;
		}

		public static function col( $field, $type, $null = 'YES', $key = '', $default = null, $extra = '', $collation = null ) {
			return array(
				'Field'      => $field,
				'Type'       => $type,
				'Null'       => $null,
				'Key'        => $key,
				'Default'    => $default,
				'Extra'      => $extra,
				'Collation'  => $collation,
				'Comment'    => '',
				'Privileges' => 'select',
			);
		}

		public static function idx( $name, $column, $non_unique = 1, $type = 'BTREE', $card = 100 ) {
			return array(
				'Key_name'     => $name,
				'Non_unique'   => (string) $non_unique,
				'Column_name'  => $column,
				'Cardinality'  => $card,
				'Index_type'   => $type,
				'Seq_in_index' => 1,
			);
		}

		public static function default_tables() {
			return array(
				'wp_posts'          => array(
					'status'  => array( 'Engine' => 'InnoDB', 'Rows' => 100, 'Data_length' => 16384, 'Index_length' => 8192, 'Collation' => 'utf8mb4_unicode_ci', 'Comment' => '' ),
					'columns' => array(
						self::col( 'ID', 'bigint(20) unsigned', 'NO', 'PRI', null, 'auto_increment' ),
						self::col( 'post_author', 'bigint(20) unsigned', 'NO', 'MUL', '0' ),
						self::col( 'post_title', 'text', 'NO', '', null, '', 'utf8mb4_unicode_ci' ),
					),
					'indexes' => array( self::idx( 'PRIMARY', 'ID', 0 ), self::idx( 'post_author', 'post_author', 1 ) ),
					'fks'     => array(),
					'rows'    => array( array( 'ID' => 1, 'post_author' => 1, 'post_title' => 'Hello' ) ),
				),
				'wp_postmeta'       => array(
					'status'  => array( 'Engine' => 'InnoDB', 'Rows' => 215000, 'Data_length' => 9000000, 'Index_length' => 2000000, 'Collation' => 'utf8mb4_unicode_ci', 'Comment' => '' ),
					'columns' => array(
						self::col( 'meta_id', 'bigint(20) unsigned', 'NO', 'PRI', null, 'auto_increment' ),
						self::col( 'post_id', 'bigint(20) unsigned', 'NO', 'MUL', '0' ),
						self::col( 'meta_key', 'varchar(255)', 'YES', 'MUL', null, '', 'utf8mb4_unicode_ci' ),
						self::col( 'meta_value', 'longtext', 'YES', '', null, '', 'utf8mb4_unicode_ci' ),
					),
					'indexes' => array( self::idx( 'PRIMARY', 'meta_id', 0 ), self::idx( 'post_id', 'post_id', 1 ) ),
					'fks'     => array(),
					'rows'    => array(),
				),
				'legacy_log'        => array(
					'status'  => array( 'Engine' => 'MyISAM', 'Rows' => 5, 'Data_length' => 512, 'Index_length' => 0, 'Collation' => 'latin1_swedish_ci', 'Comment' => '' ),
					'columns' => array( self::col( 'id', 'int(11)', 'NO' ), self::col( 'message', 'text', 'YES' ) ),
					'indexes' => array(),
					'fks'     => array(),
					'rows'    => array(),
				),
				'rel_pair'          => array(
					'status'  => array( 'Engine' => 'InnoDB', 'Rows' => 2, 'Data_length' => 1024, 'Index_length' => 1024, 'Collation' => 'utf8mb4_unicode_ci', 'Comment' => '' ),
					'columns' => array( self::col( 'a_id', 'bigint(20)', 'NO', 'PRI', '0' ), self::col( 'b_id', 'bigint(20)', 'NO', 'PRI', '0' ) ),
					'indexes' => array( self::idx( 'PRIMARY', 'a_id', 0 ), self::idx( 'PRIMARY', 'b_id', 0 ) ),
					'fks'     => array(
						array( 'name' => 'fk_pair_a', 'col' => 'a_id', 'ref_table' => 'wp_posts', 'ref_col' => 'ID', 'update_rule' => 'CASCADE', 'delete_rule' => 'CASCADE' ),
					),
					'rows'    => array(),
				),
				'wp_wc_orders'      => array(
					'status'  => array( 'Engine' => 'InnoDB', 'Rows' => 0, 'Data_length' => 16384, 'Index_length' => 0, 'Collation' => 'utf8mb4_unicode_ci', 'Comment' => '' ),
					'columns' => array( self::col( 'order_id', 'bigint(20)', 'NO', 'PRI', null, 'auto_increment' ) ),
					'indexes' => array( self::idx( 'PRIMARY', 'order_id', 0 ) ),
					'fks'     => array(),
					'rows'    => array(),
				),
				'wp_old_gw_log'     => array(
					'status'  => array( 'Engine' => 'InnoDB', 'Rows' => 3, 'Data_length' => 1024, 'Index_length' => 0, 'Collation' => 'utf8mb4_unicode_ci', 'Comment' => '' ),
					'columns' => array( self::col( 'id', 'int(11)', 'NO', 'PRI', null, 'auto_increment' ) ),
					'indexes' => array( self::idx( 'PRIMARY', 'id', 0 ) ),
					'fks'     => array(),
					'rows'    => array(),
				),
				'wp_wordfence_hits' => array(
					'status'  => array( 'Engine' => 'InnoDB', 'Rows' => 7, 'Data_length' => 2048, 'Index_length' => 0, 'Collation' => 'utf8mb4_unicode_ci', 'Comment' => '' ),
					'columns' => array( self::col( 'id', 'int(11)', 'NO', 'PRI', null, 'auto_increment' ) ),
					'indexes' => array( self::idx( 'PRIMARY', 'id', 0 ) ),
					'fks'     => array(),
					'rows'    => array(),
				),
				'wp_xyz_mystery'    => array(
					'status'  => array( 'Engine' => 'InnoDB', 'Rows' => 1, 'Data_length' => 1024, 'Index_length' => 0, 'Collation' => 'utf8mb4_unicode_ci', 'Comment' => '' ),
					'columns' => array( self::col( 'id', 'int(11)', 'NO', 'PRI', null, 'auto_increment' ) ),
					'indexes' => array( self::idx( 'PRIMARY', 'id', 0 ) ),
					'fks'     => array(),
					'rows'    => array(),
				),
			);
		}

		public static function default_plugins() {
			return array(
				array( 'file' => 'woocommerce/woocommerce.php', 'slug' => 'woocommerce', 'name' => 'WooCommerce', 'version' => '9.0', 'active' => true, 'network_active' => false ),
				array( 'file' => 'old-gateway/old-gateway.php', 'slug' => 'old-gateway', 'name' => 'Old Gateway', 'version' => '1.0', 'active' => false, 'network_active' => false ),
			);
		}

		public function db_name() {
			return 'wp';
		}

		public function server_info() {
			return array( 'engine' => 'MariaDB', 'version' => '10.4.32-MariaDB', 'charset' => 'utf8mb4', 'collation' => 'DEFAULT CHARACTER SET utf8mb4' );
		}

		public function list_table_names() {
			$names = array_keys( $this->tables );
			sort( $names );
			return $names;
		}

		public function table_status( $table ) {
			return isset( $this->tables[ $table ]['status'] ) ? $this->tables[ $table ]['status'] : null;
		}

		public function table_columns( $table ) {
			return isset( $this->tables[ $table ]['columns'] ) ? $this->tables[ $table ]['columns'] : array();
		}

		public function table_indexes( $table ) {
			return isset( $this->tables[ $table ]['indexes'] ) ? $this->tables[ $table ]['indexes'] : array();
		}

		public function foreign_keys( $table ) {
			return isset( $this->tables[ $table ]['fks'] ) ? $this->tables[ $table ]['fks'] : array();
		}

		public function run_read( $sql ) {
			if ( $this->fail_read ) {
				return array( 'rows' => array(), 'error' => 'Table crashed', 'elapsed_ms' => 1.0 );
			}
			if ( 0 === stripos( ltrim( $sql ), 'EXPLAIN' ) ) {
				return array( 'rows' => array( array( 'id' => 1, 'select_type' => 'SIMPLE', 'table' => 'wp_posts' ) ), 'error' => '', 'elapsed_ms' => 0.5 );
			}
			if ( false !== stripos( $sql, 'wp_posts' ) ) {
				return array( 'rows' => array( array( 'ID' => 1, 'post_title' => 'Hello' ) ), 'error' => '', 'elapsed_ms' => 0.5 );
			}
			return array( 'rows' => array(), 'error' => '', 'elapsed_ms' => 0.5 );
		}

		public function fetch_row( $table, $column, $value ) {
			if ( 'wp_posts' === $table && 'ID' === $column && '1' === (string) $value ) {
				return array( 'ID' => '1', 'post_author' => '1', 'post_title' => 'Hello' );
			}
			return null;
		}

		public function options_autoload_totals() {
			return null !== $this->options_totals ? $this->options_totals : array( 'count' => 213, 'bytes' => 88867 );
		}

		public function top_autoload_options( $limit ) {
			$all = array(
				array( 'name' => 'active_plugins', 'bytes' => 12000 ),
				array( 'name' => 'rewrite_rules', 'bytes' => 8000 ),
				array( 'name' => 'cron', 'bytes' => 5000 ),
			);
			return array_slice( $all, 0, max( 1, min( 50, (int) $limit ) ) );
		}

		public function transient_stats() {
			return array( 'values' => 67, 'expired' => 3 );
		}
		public function fetch_all( $sql, array $params = array() ) {
			if ( false !== stripos( $sql, 'SHOW GRANTS' ) ) {
				if ( null === $this->grants ) {
					return array();
				}
				return array( array( $this->grants ) );
			}
			if ( false !== stripos( $sql, 'actionscheduler_actions' ) ) {
				if ( false !== stripos( $sql, 'GROUP BY status' ) ) {
					return array(
						array( 'status' => 'complete', 'n' => 96 ),
						array( 'status' => 'pending', 'n' => 14 ),
						array( 'status' => 'failed', 'n' => 5 ),
					);
				}
				if ( false !== stripos( $sql, "status = 'failed'" ) ) {
					return array(
						array( 'action_id' => 1, 'hook' => 'fetch_patterns', 'status' => 'failed', 'scheduled_date_gmt' => '2026-09-07 17:23:42' ),
						array( 'action_id' => 2, 'hook' => 'migration_hook', 'status' => 'failed', 'scheduled_date_gmt' => '2026-08-31 11:20:03' ),
					);
				}
				if ( false !== stripos( $sql, "status = 'pending'" ) ) {
					return array( array( 'n' => 2 ) );
				}
				return array();
			}
			if ( false !== stripos( $sql, 'CURRENT_USER' ) ) {
				return array( array( 'u' => 'root@localhost' ) );
			}
			if ( false !== stripos( $sql, ' LIKE ' ) ) {
				return array( array( 'ID' => 1, 'post_title' => 'Hello world match' ) );
			}
			return array();
		}

		public function last_error() {
			return '';
		}

		public function set_fail_read( $fail ) {
			$this->fail_read = (bool) $fail;
		}
	}
}
