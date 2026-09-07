<?php
/**
 * Identifier safety: validation + quoting for tables/columns/indexes (WH-011).
 * Identifiers can never be passed as prepared-statement values, so they are
 * validated against a strict allowlist pattern and verified against discovery.
 *
 * @package WP_Heart
 */

namespace WPHeart\Database;

/**
 * Identifier safety: validation + quoting for tables/columns/indexes (WH-011).
 */
class IdentifierValidator {
	const MAX_LENGTH = 64;
	const PATTERN    = '/^[A-Za-z0-9_$]+$/';

	/**
	 * @param mixed $name Candidate identifier.
	 * @return bool
	 */
	public function valid_name( $name ) {
		if ( ! is_string( $name ) || '' === $name || strlen( $name ) > self::MAX_LENGTH ) {
			return false;
		}
		return (bool) preg_match( self::PATTERN, $name );
	}

	/**
	 * Validate a table name, optionally against a known-tables allowlist.
	 *
	 * @param mixed         $table Table name.
	 * @param string[]|null $known Known tables (discovery result) or null to skip existence check.
	 * @return string|null Validated name or null.
	 */
	public function validate_table( $table, $known = null ) {
		if ( ! $this->valid_name( $table ) ) {
			return null;
		}
		if ( is_array( $known ) && ! in_array( $table, $known, true ) ) {
			return null;
		}
		return $table;
	}

	/**
	 * @param mixed         $column Column name.
	 * @param string[]|null $known Known columns or null.
	 * @return string|null
	 */
	public function validate_column( $column, $known = null ) {
		return $this->validate_table( $column, $known );
	}

	/**
	 * Quote an already-validated identifier with backticks.
	 *
	 * @param string $identifier Validated identifier.
	 * @return string
	 */
	public function quote( $identifier ) {
		return '`' . str_replace( '`', '``', $identifier ) . '`';
	}

	/**
	 * @param mixed $direction Sort direction candidate.
	 * @return string|null ASC|DESC or null.
	 */
	public function sort_direction( $direction ) {
		$upper = strtoupper( trim( (string) $direction ) );
		if ( 'ASC' === $upper || 'DESC' === $upper ) {
			return $upper;
		}
		return null;
	}
}
