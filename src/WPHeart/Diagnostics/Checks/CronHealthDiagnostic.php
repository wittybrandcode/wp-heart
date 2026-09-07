<?php
/**
 * Scheduled-task health diagnostic. Overdue or failed background work is
 * reported as WARNING with hook names — never with callback arguments.
 *
 * @package WP_Heart
 */

namespace WPHeart\Diagnostics\Checks;

use WPHeart\Diagnostics\DiagnosticInterface;
use WPHeart\Domain\Evidence;
use WPHeart\Domain\HealthIssue;
use WPHeart\Support\Severity;

/**
 * Scheduled-task health diagnostic.
 */
class CronHealthDiagnostic implements DiagnosticInterface {
	const MAX_HOOKS = 10;

	/**
	 * @return string
	 */
	public function id() {
		return 'wpheart_cron';
	}

	/**
	 * @return string
	 */
	public function name() {
		return __( 'Overdue or failed scheduled tasks', 'wp-heart' );
	}

	/**
	 * @return string
	 */
	public function description() {
		return __( 'Detects wp-cron events past their grace period and failed Action Scheduler actions.', 'wp-heart' );
	}

	/**
	 * @param array $context Context (needs cron summary).
	 * @return HealthIssue[]
	 */
	public function check( array $context ) {
		if ( ! isset( $context['cron'] ) || ! is_array( $context['cron'] ) ) {
			return array();
		}
		$cron    = $context['cron'];
		$overdue = isset( $cron['wp_overdue'] ) && is_array( $cron['wp_overdue'] ) ? $cron['wp_overdue'] : array();
		$failed  = isset( $cron['as_failed'] ) && is_array( $cron['as_failed'] ) ? $cron['as_failed'] : array();
		$as_over = isset( $cron['as_overdue'] ) ? (int) $cron['as_overdue'] : 0;
		if ( empty( $overdue ) && empty( $failed ) && $as_over <= 0 ) {
			return array();
		}

		$hooks    = array();
		$affected = array( 'wp_options' );
		foreach ( array_slice( $overdue, 0, self::MAX_HOOKS ) as $event ) {
			$event   = (array) $event;
			$hooks[] = 'wp-cron overdue: ' . ( isset( $event['hook'] ) ? $event['hook'] : '?' );
		}
		foreach ( array_slice( $failed, 0, self::MAX_HOOKS ) as $action ) {
			$action  = (array) $action;
			$hooks[] = 'action-scheduler failed: ' . ( isset( $action['hook'] ) ? $action['hook'] : '?' );
		}
		if ( ! empty( $failed ) || $as_over > 0 ) {
			foreach ( (array) ( isset( $cron['tables'] ) ? $cron['tables'] : array() ) as $as_table ) {
				$affected[] = (string) $as_table;
			}
		}
		$affected = array_values( array_unique( $affected ) );

		$summary = array();
		if ( count( $overdue ) > 0 ) {
			$summary[] = sprintf( '%d overdue wp-cron event(s)', count( $overdue ) );
		}
		if ( count( $failed ) > 0 ) {
			$summary[] = sprintf( '%d failed Action Scheduler action(s)', count( $failed ) );
		}
		if ( $as_over > 0 ) {
			$summary[] = sprintf( '%d overdue pending Action Scheduler action(s)', $as_over );
		}

		return array(
			new HealthIssue(
				array(
					'id'             => 'wpheart_cron',
					'diagnostic'     => $this->id(),
					'severity'       => Severity::WARNING,
					'affected'       => $affected,
					'evidence'       => array(
						new Evidence( Evidence::OTHER, 'Scheduled-task state: ' . implode( '; ', $summary ) . '. Hooks: ' . implode( ', ', array_slice( $hooks, 0, self::MAX_HOOKS ) ), 80 ),
					),
					'explanation'    => __( 'Background work is overdue or failing. On low-traffic sites wp-cron only runs on visits; repeated Action Scheduler failures point at a broken callback or exhausted resources.', 'wp-heart' ),
					'recommendation' => __( 'Check site traffic patterns and the failing hooks error logs; consider a real cron trigger for wp-cron on quiet sites.', 'wp-heart' ),
				)
			),
		);
	}
}
