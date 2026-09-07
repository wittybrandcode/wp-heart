<?php
/**
 * Database-level facts service (WH-021). Aggregates observed metadata only.
 *
 * @package WP_Heart
 */

namespace WPHeart\Discovery;

use WPHeart\Database\CapabilityInspector;
use WPHeart\Database\DatabaseAdapterInterface;
use WPHeart\Domain\DatabaseSummary;
use WPHeart\Domain\TableInfo;
use WPHeart\Support\Accuracy;

/**
 * Database-level facts service (WH-021). Aggregates observed metadata only.
 */
class DatabaseService {
	/** @var DatabaseAdapterInterface */
	private $adapter;
	/** @var TableDiscovery */
	private $discovery;
	/** @var CapabilityInspector */
	private $privileges;

	/**
	 * @param DatabaseAdapterInterface $adapter Adapter.
	 * @param TableDiscovery           $discovery Discovery.
	 * @param CapabilityInspector      $privileges Privilege inspector.
	 */
	public function __construct( DatabaseAdapterInterface $adapter, TableDiscovery $discovery, CapabilityInspector $privileges ) {
		$this->adapter    = $adapter;
		$this->discovery  = $discovery;
		$this->privileges = $privileges;
	}

	/**
	 * @param bool $refresh Bypass cache.
	 * @return DatabaseSummary
	 */
	public function summary( $refresh = false ) {
		$discovered = $this->discovery->discover( $refresh );
		$tables     = $discovered['tables'];
		$server     = $this->adapter->server_info();

		$total_size = 0;
		$sized      = 0;
		foreach ( $tables as $table ) {
			$meta = $table->to_array();
			if ( isset( $meta['metadata']['size_bytes'] ) && null !== $meta['metadata']['size_bytes'] ) {
				$total_size += (int) $meta['metadata']['size_bytes'];
				++$sized;
			}
		}

		// Largest tables (metadata only — no row reads).
		$by_size = $tables;
		usort(
			$by_size,
			static function ( TableInfo $a, TableInfo $b ) {
				$ma = $a->to_array();
				$mb = $b->to_array();
				$sa = isset( $ma['metadata']['size_bytes'] ) ? (int) $ma['metadata']['size_bytes'] : 0;
				$sb = isset( $mb['metadata']['size_bytes'] ) ? (int) $mb['metadata']['size_bytes'] : 0;
				if ( $sa === $sb ) {
					return 0;
				}
				return $sa < $sb ? 1 : -1;
			}
		);
		$largest = array();
		foreach ( array_slice( $by_size, 0, 10 ) as $table ) {
			$meta      = $table->to_array();
			$largest[] = array(
				'name'       => $table->name(),
				'size_bytes' => isset( $meta['metadata']['size_bytes'] ) ? $meta['metadata']['size_bytes'] : null,
				'size_mode'  => isset( $meta['metadata']['size_mode'] ) ? $meta['metadata']['size_mode'] : Accuracy::UNKNOWN,
				'rows'       => isset( $meta['metadata']['estimated_rows'] ) ? $meta['metadata']['estimated_rows'] : null,
				'rows_mode'  => isset( $meta['metadata']['rows_mode'] ) ? $meta['metadata']['rows_mode'] : Accuracy::UNKNOWN,
			);
		}

		return new DatabaseSummary(
			array(
				'name'                => $this->adapter->db_name(),
				'engine'              => $server['engine'],
				'server_version'      => $server['version'],
				'charset'             => $server['charset'],
				'collation'           => $server['collation'],
				'table_count'         => count( $tables ),
				'table_count_mode'    => Accuracy::EXACT,
				'estimated_size'      => $sized > 0 ? $total_size : null,
				'estimated_size_mode' => $sized > 0 ? Accuracy::ESTIMATED : Accuracy::UNAVAILABLE,
				'largest_tables'      => $largest,
				'freshness'           => $discovered['freshness'],
				'cached_at'           => $discovered['cached_at'],
				'privileges'          => $this->privileges->inspect(),
			)
		);
	}
}
