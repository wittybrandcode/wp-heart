<?php
/**
 * Cron intelligence: wp-cron schedule plus Action Scheduler awareness.
 * Read-only and bounded; never dumps callback arguments (they may be
 * large or sensitive) — only hooks, timestamps and statuses.
 *
 * @package WP_Heart
 */

namespace WPHeart\WordPress;

use WPHeart\Database\DatabaseAdapterInterface;
use WPHeart\Intelligence\WpContextService;

/**
 * Cron intelligence.
 */
class CronInspector {
	const OVERDUE_GRACE_SECONDS = 300;
	const EVENT_LIMIT           = 200;
	const AS_ROW_LIMIT          = 20;

	/** @var DatabaseAdapterInterface */
	private $adapter;
	/** @var WpContextService|null */
	private $context;
	/** @var callable|null */
	private $cron_fetcher;

	/**
	 * @param DatabaseAdapterInterface $adapter Adapter.
	 * @param WpContextService|null    $context WP context (for AS table names).
	 * @param callable|null            $cron_fetcher Override for _get_cron_array (tests).
	 */
	public function __construct( DatabaseAdapterInterface $adapter, $context = null, $cron_fetcher = null ) {
		$this->adapter      = $adapter;
		$this->context      = $context;
		$this->cron_fetcher = $cron_fetcher;
	}

	/**
	 * Flattened wp-cron schedule, soonest first.
	 *
	 * @param int $limit Max events.
	 * @return array {total, overdue, events: [{hook, scheduled, overdue, schedule, args}]}
	 */
	public function wp_cron_events( $limit = 200 ) {
		$limit = max( 1, min( self::EVENT_LIMIT, (int) $limit ) );
		$raw   = $this->cron_array();
		$now   = time();

		$events = array();
		foreach ( $raw as $timestamp => $hooks ) {
			if ( ! is_array( $hooks ) ) {
				continue;
			}
			foreach ( $hooks as $hook => $keys ) {
				if ( ! is_array( $keys ) ) {
					continue;
				}
				foreach ( $keys as $data ) {
					$data     = is_array( $data ) ? $data : array();
					$events[] = array(
						'hook'      => (string) $hook,
						'scheduled' => (int) $timestamp,
						'overdue'   => (int) $timestamp < $now - self::OVERDUE_GRACE_SECONDS,
						'schedule'  => isset( $data['schedule'] ) ? (string) $data['schedule'] : 'single',
						'args'      => isset( $data['args'] ) && is_array( $data['args'] ) ? count( $data['args'] ) : 0,
					);
				}
			}
		}
		usort(
			$events,
			static function ( $a, $b ) {
				if ( $a['scheduled'] === $b['scheduled'] ) {
					return 0;
				}
				return $a['scheduled'] < $b['scheduled'] ? -1 : 1;
			}
		);

		$overdue = 0;
		foreach ( $events as $event ) {
			if ( $event['overdue'] ) {
				++$overdue;
			}
		}

		return array(
			'total'   => count( $events ),
			'overdue' => $overdue,
			'events'  => array_slice( $events, 0, $limit ),
		);
	}

	/**
	 * Action Scheduler awareness (WooCommerce and friends ship it).
	 *
	 * @return array {available, tables, by_status, overdue_pending, failed_recent}
	 */
	public function action_scheduler() {
		$result = array(
			'available'       => false,
			'tables'          => array(),
			'by_status'       => array(),
			'overdue_pending' => 0,
			'failed_recent'   => array(),
		);

		$prefix = $this->context ? $this->context->prefix() : '';
		if ( '' !== $prefix && ! preg_match( '/^[A-Za-z0-9_]+$/', $prefix ) ) {
			return $result;
		}
		$actions = $prefix . 'actionscheduler_actions';
		if ( ! in_array( $actions, $this->adapter->list_table_names(), true ) ) {
			return $result;
		}

		$result['available'] = true;
		$result['tables'][]  = $actions;
		$quoted              = '`' . str_replace( '`', '``', $actions ) . '`';

		foreach ( $this->adapter->fetch_all( 'SELECT status, COUNT(*) AS n FROM ' . $quoted . ' GROUP BY status' ) as $row ) {
			$row = (array) $row;
			if ( isset( $row['status'] ) ) {
				$result['by_status'][ (string) $row['status'] ] = isset( $row['n'] ) ? (int) $row['n'] : 0;
			}
		}

		$overdue                   = $this->adapter->fetch_all(
			'SELECT COUNT(*) AS n FROM ' . $quoted . " WHERE status = 'pending' AND scheduled_date_gmt < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)"
		);
		$result['overdue_pending'] = isset( $overdue[0]['n'] ) ? (int) $overdue[0]['n'] : 0;

		foreach ( $this->adapter->fetch_all(
			'SELECT action_id, hook, status, scheduled_date_gmt FROM ' . $quoted . " WHERE status = 'failed' ORDER BY scheduled_date_gmt DESC LIMIT 10"
		) as $row ) {
			$row                       = (array) $row;
			$result['failed_recent'][] = array(
				'action_id' => isset( $row['action_id'] ) ? (int) $row['action_id'] : 0,
				'hook'      => isset( $row['hook'] ) ? (string) $row['hook'] : '',
				'status'    => isset( $row['status'] ) ? (string) $row['status'] : '',
				'scheduled' => isset( $row['scheduled_date_gmt'] ) ? (string) $row['scheduled_date_gmt'] : '',
			);
		}

		return $result;
	}

	/**
	 * @return array Raw cron array (possibly empty when unavailable).
	 */
	private function cron_array() {
		if ( is_callable( $this->cron_fetcher ) ) {
			$raw = call_user_func( $this->cron_fetcher );
			return is_array( $raw ) ? $raw : array();
		}
		if ( function_exists( '_get_cron_array' ) ) {
			$raw = _get_cron_array();
			return is_array( $raw ) ? $raw : array();
		}
		return array();
	}
}
