<?php
/**
 * Minimal ASCII table renderer (pure, dependency-free, unit-tested).
 *
 * @package WP_Heart
 */

namespace WPHeart\Cli;

/**
 * Minimal ASCII table renderer.
 */
class CliTable {
	/**
	 * @param string[] $headers Headers.
	 * @param array[]  $rows Rows (scalar cells).
	 * @return string Rendered table.
	 */
	public static function render( array $headers, array $rows ) {
		$widths = array();
		foreach ( $headers as $i => $header ) {
			$widths[ $i ] = self::width( (string) $header );
		}
		foreach ( $rows as $row ) {
			$row = array_values( (array) $row );
			foreach ( $headers as $i => $header ) {
				$cell         = isset( $row[ $i ] ) ? (string) $row[ $i ] : '';
				$widths[ $i ] = max( $widths[ $i ], self::width( $cell ) );
			}
		}

		$sep = '+' . implode(
			'+',
			array_map(
				static function ( $w ) {
					return str_repeat( '-', $w + 2 );
				},
				$widths
			)
		) . "+\n";
		$out = $sep . self::row( $headers, $widths ) . $sep;
		foreach ( $rows as $row ) {
			$out .= self::row( array_values( (array) $row ), $widths );
		}
		return $out . $sep;
	}

	/**
	 * @param array $cells Cells.
	 * @param int[] $widths Widths.
	 * @return string
	 */
	private static function row( array $cells, array $widths ) {
		$out = '|';
		foreach ( $widths as $i => $w ) {
			$cell = isset( $cells[ $i ] ) ? (string) $cells[ $i ] : '';
			$pad  = $w - self::width( $cell );
			$out .= ' ' . $cell . ( $pad > 0 ? str_repeat( ' ', $pad ) : '' ) . ' |';
		}
		return $out . "\n";
	}

	/**
	 * @param string $text Text.
	 * @return int Display width.
	 */
	private static function width( $text ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $text );
		}
		return strlen( $text );
	}
}
