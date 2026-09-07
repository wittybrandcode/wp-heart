<?php
/**
 * Complete database discovery (WH-020–WH-023). No prefix assumption,
 * unknown tables are first-class results.
 *
 * @package WP_Heart
 */

namespace WPHeart\Discovery;

use WPHeart\Cache\CacheService;
use WPHeart\Database\DatabaseAdapterInterface;
use WPHeart\Database\IdentifierValidator;
use WPHeart\Domain\TableInfo;
use WPHeart\Support\Accuracy;

/**
 * Complete database discovery (WH-020–WH-023). No prefix assumption,.
 */
class TableDiscovery {
	/** @var DatabaseAdapterInterface */
	private $adapter;
	/** @var IdentifierValidator */
	private $identifiers;
	/** @var CacheService */
	private $cache;

	/**
	 * @param DatabaseAdapterInterface $adapter Adapter.
	 * @param IdentifierValidator      $identifiers Identifier validator.
	 * @param CacheService             $cache Cache.
	 */
	public function __construct( DatabaseAdapterInterface $adapter, IdentifierValidator $identifiers, CacheService $cache ) {
		$this->adapter     = $adapter;
		$this->identifiers = $identifiers;
		$this->cache       = $cache;
	}

	/**
	 * Discover all accessible tables with metadata.
	 *
	 * @param bool $refresh Bypass cache when true.
	 * @return array {tables: TableInfo[], freshness, cached_at}
	 */
	public function discover( $refresh = false ) {
		$cache_key = 'discovery:v1';
		if ( ! $refresh ) {
			$cached = $this->cache->get( $cache_key );
			if ( $cached['hit'] && isset( $cached['value']['tables'] ) ) {
				$value              = $cached['value'];
				$value['freshness'] = Accuracy::CACHED;
				return $value;
			}
		}

		$names  = $this->adapter->list_table_names();
		$tables = array();
		foreach ( $names as $name ) {
			$tables[] = $this->build_table( $name );
		}

		$result = array(
			'tables'     => $tables,
			'freshness'  => Accuracy::EXACT,
			'cached_at'  => time(),
			'count'      => count( $tables ),
			'count_mode' => Accuracy::EXACT,
		);
		$this->cache->set( $cache_key, $result );

		return $result;
	}

	/**
	 * @param string $name Table name.
	 * @return TableInfo|null Null when unknown/invalid.
	 */
	public function get_table( $name ) {
		$result = $this->discover();
		$known  = array();
		foreach ( $result['tables'] as $table ) {
			$known[] = $table->name();
		}
		$valid = $this->identifiers->validate_table( $name, $known );
		if ( null === $valid ) {
			return null;
		}
		foreach ( $result['tables'] as $table ) {
			if ( $table->name() === $valid ) {
				return $table;
			}
		}
		return null;
	}

	/**
	 * @param string $name Raw table name from SHOW TABLES (trusted source).
	 * @return TableInfo
	 */
	private function build_table( $name ) {
		$status = $this->adapter->table_status( $name );
		$meta   = array(
			'engine'         => isset( $status['Engine'] ) && '' !== $status['Engine'] ? (string) $status['Engine'] : null,
			'version'        => isset( $status['Version'] ) ? $status['Version'] : null,
			'collation'      => isset( $status['Collation'] ) ? $status['Collation'] : null,
			'create_time'    => isset( $status['Create_time'] ) ? $status['Create_time'] : null,
			'data_length'    => isset( $status['Data_length'] ) ? (int) $status['Data_length'] : null,
			'index_length'   => isset( $status['Index_length'] ) ? (int) $status['Index_length'] : null,
			'size_bytes'     => null,
			'size_mode'      => Accuracy::UNKNOWN,
			'estimated_rows' => null,
			'rows_mode'      => Accuracy::UNKNOWN,
			'comment'        => isset( $status['Comment'] ) ? (string) $status['Comment'] : null,
		);

		if ( null !== $meta['data_length'] && null !== $meta['index_length'] ) {
			$meta['size_bytes'] = $meta['data_length'] + $meta['index_length'];
			// SHOW TABLE STATUS figures are engine estimates for InnoDB.
			$meta['size_mode'] = Accuracy::ESTIMATED;
		}
		if ( isset( $status['Rows'] ) && '' !== $status['Rows'] && null !== $status['Rows'] ) {
			$meta['estimated_rows'] = (int) $status['Rows'];
			$meta['rows_mode']      = Accuracy::ESTIMATED;
		}

		return new TableInfo( $name, $meta );
	}
}
