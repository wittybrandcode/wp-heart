<?php
/**
 * Database privilege awareness (WH-012). Inspects, never assumes.
 *
 * @package WP_Heart
 */

namespace WPHeart\Database;

/**
 * Database privilege awareness (WH-012). Inspects, never assumes.
 */
class CapabilityInspector {
	/** @var DatabaseAdapterInterface */
	private $adapter;

	/**
	 * @param DatabaseAdapterInterface $adapter Adapter.
	 */
	public function __construct( DatabaseAdapterInterface $adapter ) {
		$this->adapter = $adapter;
	}

	/**
	 * Best-effort privilege snapshot. Never fatal; UNKNOWN when undetectable.
	 *
	 * @return array {status, current_user, grants_available, privileges[]}
	 */
	public function inspect() {
		$result = array(
			'status'           => 'UNKNOWN',
			'current_user'     => 'UNKNOWN',
			'grants_available' => false,
			'privileges'       => array(),
		);

		$rows = $this->adapter->fetch_all( 'SELECT CURRENT_USER() AS u' );
		if ( isset( $rows[0]['u'] ) && is_string( $rows[0]['u'] ) ) {
			$result['current_user'] = $rows[0]['u'];
		}

		$grants = $this->adapter->fetch_all( 'SHOW GRANTS FOR CURRENT_USER' );
		if ( empty( $grants ) ) {
			return $result;
		}

		$result['grants_available'] = true;
		$text                       = '';
		foreach ( $grants as $row ) {
			$text .= ' ' . implode( ' ', array_map( 'strval', array_values( (array) $row ) ) );
		}
		$upper = strtoupper( $text );

		$watched = array( 'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'ALTER', 'DROP', 'INDEX', 'REFERENCES', 'LOCK TABLES' );
		foreach ( $watched as $priv ) {
			if ( false !== strpos( $upper, 'ALL PRIVILEGES' ) || false !== strpos( $upper, $priv ) ) {
				$result['privileges'][] = $priv;
			}
		}
		$result['status'] = in_array( 'SELECT', $result['privileges'], true ) ? 'LIMITED_VISIBLE' : 'RESTRICTED';
		if ( false !== strpos( $upper, 'ALL PRIVILEGES' ) ) {
			$result['status'] = 'FULL';
		}

		return $result;
	}
}
