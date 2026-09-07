<?php
/**
 * Pagination helper: single place where bounds are enforced.
 *
 * @package WP_Heart
 */

namespace WPHeart\Domain;

/**
 * Pagination helper: single place where bounds are enforced.
 */
class Pagination {
	/** @var int */
	private $page;
	/** @var int */
	private $per_page;
	/** @var int|null */
	private $total;
	/** @var string */
	private $total_mode;

	/**
	 * @param mixed    $page Page number (1-based).
	 * @param mixed    $per_page Items per page.
	 * @param int      $max_per_page Hard ceiling.
	 * @param int      $default_per_page Default when invalid.
	 * @param int|null $total Total when known.
	 * @param string   $total_mode EXACT|ESTIMATED|UNKNOWN.
	 */
	public function __construct( $page, $per_page, $max_per_page = 100, $default_per_page = 20, $total = null, $total_mode = 'UNKNOWN' ) {
		$this->page       = max( 1, (int) $page > 0 ? (int) $page : 1 );
		$per              = (int) $per_page > 0 ? (int) $per_page : (int) $default_per_page;
		$this->per_page   = max( 1, min( (int) $max_per_page, $per ) );
		$this->total      = null === $total ? null : max( 0, (int) $total );
		$allowed          = array( 'EXACT', 'ESTIMATED', 'UNKNOWN' );
		$this->total_mode = in_array( $total_mode, $allowed, true ) ? $total_mode : 'UNKNOWN';
	}

	/**
	 * @return int
	 */
	public function offset() {
		return ( $this->page - 1 ) * $this->per_page;
	}

	/**
	 * @return int
	 */
	public function limit() {
		return $this->per_page;
	}

	/**
	 * @return int
	 */
	public function page() {
		return $this->page;
	}

	/**
	 * @return int
	 */
	public function per_page() {
		return $this->per_page;
	}

	/**
	 * @param array $items Current page items.
	 * @return array Envelope with pagination meta.
	 */
	public function envelope( array $items ) {
		$has_more = null === $this->total ? count( $items ) >= $this->per_page : ( $this->offset() + count( $items ) ) < $this->total;
		return array(
			'items'      => $items,
			'pagination' => array(
				'page'       => $this->page,
				'per_page'   => $this->per_page,
				'total'      => $this->total,
				'total_mode' => $this->total_mode,
				'has_more'   => $has_more,
			),
		);
	}
}
