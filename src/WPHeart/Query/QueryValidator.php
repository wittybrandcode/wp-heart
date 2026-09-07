<?php
/**
 * Query validation: structure, size, policy, permission context (WH-091).
 *
 * @package WP_Heart
 */

namespace WPHeart\Query;

use WPHeart\Config\Config;

/**
 * Query validation: structure, size, policy, permission context (WH-091).
 */
class QueryValidator {
	/** @var QueryPolicy */
	private $policy;
	/** @var Config */
	private $config;

	/**
	 * @param QueryPolicy $policy Policy.
	 * @param Config      $config Config.
	 */
	public function __construct( QueryPolicy $policy, Config $config ) {
		$this->policy = $policy;
		$this->config = $config;
	}

	/**
	 * @param mixed $sql Raw SQL input.
	 * @return array {valid, code, message, sql}
	 */
	public function validate( $sql ) {
		if ( ! is_string( $sql ) || '' === trim( $sql ) ) {
			return $this->fail( 'empty_query', __( 'The query is empty.', 'wp-heart' ) );
		}
		if ( strlen( $sql ) > (int) $this->config->get( 'max_query_length', 20000 ) ) {
			return $this->fail( 'query_too_long', __( 'The query exceeds the maximum allowed length.', 'wp-heart' ) );
		}
		$result = $this->policy->evaluate( $sql );
		if ( ! $result['allowed'] ) {
			return $this->fail( 'query_rejected', $this->message_for( $result['reason'] ) );
		}
		return array(
			'valid'   => true,
			'code'    => 'ok',
			'message' => '',
			'sql'     => $result['sql'],
		);
	}

	/**
	 * @param string $reason Deny reason.
	 * @return string Safe user-facing message (no SQL internals).
	 */
	private function message_for( $reason ) {
		if ( 0 === strpos( $reason, 'forbidden_keyword_' ) ) {
			return __( 'This query performs an operation that is not allowed in the read-only console.', 'wp-heart' );
		}
		$map = array(
			'empty_query'                     => __( 'The query is empty.', 'wp-heart' ),
			'query_too_long'                  => __( 'The query exceeds the maximum allowed length.', 'wp-heart' ),
			'comments_not_allowed'            => __( 'SQL comments are not allowed in the query console.', 'wp-heart' ),
			'multiple_statements_not_allowed' => __( 'Only a single statement may be executed at a time.', 'wp-heart' ),
			'statement_not_allowed'           => __( 'Only read operations (SELECT, SHOW, DESCRIBE, EXPLAIN) are allowed.', 'wp-heart' ),
			'locking_reads_not_allowed'       => __( 'Locking reads are not allowed.', 'wp-heart' ),
		);
		return isset( $map[ $reason ] ) ? $map[ $reason ] : __( 'This query is not allowed in the read-only console.', 'wp-heart' );
	}

	/**
	 * @param string $code Code.
	 * @param string $message Message.
	 * @return array
	 */
	private function fail( $code, $message ) {
		return array(
			'valid'   => false,
			'code'    => $code,
			'message' => $message,
			'sql'     => '',
		);
	}
}
