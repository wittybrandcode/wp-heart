<?php
/**
 * Read-only query policy (WH-090). Server-side enforcement for Release 1.0.
 *
 * Strategy (never regex-alone for semantics, but conservative allow/deny):
 *  1. Strip string literals, then reject comment markers (bypass-proofing).
 *  2. Reject stacked statements (semicolons outside literals).
 *  3. First keyword must be an allowlisted read operation.
 *  4. Scan the de-literalized remainder for forbidden keywords/phrases.
 *  5. Auto-append LIMIT to unbounded SELECT/WITH queries.
 *
 * @package WP_Heart
 */

namespace WPHeart\Query;

use WPHeart\Config\Config;

/**
 * Read-only query policy (WH-090). Server-side enforcement for Release 1.0.
 */
class QueryPolicy {
	const ALLOWED_FIRST = array( 'SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN' );

	/** Forbidden anywhere outside string literals. */
	const FORBIDDEN = array(
		'INSERT',
		'UPDATE',
		'DELETE',
		'REPLACE',
		'DROP',
		'ALTER',
		'CREATE',
		'TRUNCATE',
		'RENAME',
		'GRANT',
		'REVOKE',
		'CALL',
		'EXECUTE',
		'EXEC',
		'HANDLER',
		'LOCK',
		'UNLOCK',
		'KILL',
		'SHUTDOWN',
		'INSTALL',
		'UNINSTALL',
		'DO',
		'INTO',
		'OUTFILE',
		'DUMPFILE',
		'PROCEDURE',
		'BENCHMARK',
		'SLEEP',
		'GET_LOCK',
		'SET',
		'USE',
		'LOAD_FILE',
	);

	/** @var Config */
	private $config;

	/**
	 * @param Config $config Config.
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * @param string $sql Raw SQL.
	 * @return array {allowed: bool, reason: string, sql: string (bounded when allowed)}
	 */
	public function evaluate( $sql ) {
		$sql = trim( (string) $sql );
		if ( '' === $sql ) {
			return $this->deny( 'empty_query' );
		}
		if ( strlen( $sql ) > (int) $this->config->get( 'max_query_length', 20000 ) ) {
			return $this->deny( 'query_too_long' );
		}

		$stripped = $this->strip_literals( $sql );

		// Comment-based bypasses are rejected outright (Decision D-007).
		if ( preg_match( '/(--\s|\/\*|\*\/|#)/', $stripped ) ) {
			return $this->deny( 'comments_not_allowed' );
		}
		if ( false !== strpos( $stripped, ';' ) ) {
			return $this->deny( 'multiple_statements_not_allowed' );
		}

		$first = strtoupper( strtok( ltrim( $stripped ), " \t\r\n(" ) );
		if ( 'WITH' === $first ) {
			// CTEs are allowed only when the whole body is read-only.
			$check = $this->scan_forbidden( $stripped );
			if ( null !== $check ) {
				return $this->deny( $check );
			}
			return $this->allow( $this->ensure_limit( $sql, $stripped ) );
		}

		if ( ! in_array( $first, self::ALLOWED_FIRST, true ) ) {
			return $this->deny( 'statement_not_allowed' );
		}

		if ( in_array( $first, array( 'SELECT', 'EXPLAIN' ), true ) ) {
			$check = $this->scan_forbidden( $stripped );
			if ( null !== $check ) {
				return $this->deny( $check );
			}
		}

		if ( 'SELECT' === $first && preg_match( '/\bFOR\s+UPDATE\b/i', $stripped ) ) {
			return $this->deny( 'locking_reads_not_allowed' );
		}
		if ( 'SELECT' === $first && preg_match( '/\bLOCK\s+IN\s+SHARE\s+MODE\b/i', $stripped ) ) {
			return $this->deny( 'locking_reads_not_allowed' );
		}

		if ( 'SELECT' === $first ) {
			return $this->allow( $this->ensure_limit( $sql, $stripped ) );
		}

		return $this->allow( $sql );
	}

	/**
	 * Replace '...', "..." and `...` literals with placeholders.
	 *
	 * @param string $sql SQL.
	 * @return string
	 */
	public function strip_literals( $sql ) {
		return preg_replace( "/'(?:[^'\\\\]|\\\\.)*'|\"(?:[^\"\\\\]|\\\\.)*\"|`(?:[^`]|``)*`/", "''", $sql );
	}

	/**
	 * @param string $stripped De-literalized SQL.
	 * @return string|null Deny reason or null when clean.
	 */
	private function scan_forbidden( $stripped ) {
		$upper = strtoupper( $stripped );
		foreach ( self::FORBIDDEN as $word ) {
			if ( preg_match( '/\b' . preg_quote( $word, '/' ) . '\b/', $upper ) ) {
				return 'forbidden_keyword_' . strtolower( $word );
			}
		}
		return null;
	}

	/**
	 * @param string $sql Original SQL.
	 * @param string $stripped De-literalized SQL.
	 * @return string Bounded SQL.
	 */
	private function ensure_limit( $sql, $stripped ) {
		if ( preg_match( '/\bLIMIT\s+\d+/i', $stripped ) ) {
			return $sql;
		}
		return rtrim( $sql ) . ' LIMIT ' . (int) $this->config->get( 'max_query_rows', 500 );
	}

	/**
	 * @param string $reason Reason code.
	 * @return array
	 */
	private function deny( $reason ) {
		return array(
			'allowed' => false,
			'reason'  => $reason,
			'sql'     => '',
		);
	}

	/**
	 * @param string $sql Bounded SQL.
	 * @return array
	 */
	private function allow( $sql ) {
		return array(
			'allowed' => true,
			'reason'  => 'ok',
			'sql'     => $sql,
		);
	}
}
