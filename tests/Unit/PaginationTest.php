<?php
/**
 * Pagination bounds tests (WH-242 guard).
 *
 * @package WP_Heart_Tests
 */

use WPHeart\Domain\Pagination;

wh_group( 'Pagination' );

$p = new Pagination( 1, 20 );
wh_check( 0 === $p->offset() && 20 === $p->limit(), 'first page offset' );

$p = new Pagination( 3, 20 );
wh_check( 40 === $p->offset(), 'page 3 offset' );

$p = new Pagination( 0, -5 );
wh_check( 1 === $p->page() && 20 === $p->per_page(), 'invalid falls back to defaults' );

$p = new Pagination( 1, 9999, 100, 20 );
wh_check( 100 === $p->per_page(), 'max ceiling enforced' );

$p = new Pagination( 1, 20, 100, 20, 45, 'EXACT' );
$e = $p->envelope( range( 1, 20 ) );
wh_check( true === $e['pagination']['has_more'], 'has_more with remainder' );
wh_check( 'EXACT' === $e['pagination']['total_mode'], 'total mode kept' );

$e = ( new Pagination( 3, 20, 100, 20, 45, 'EXACT' ) )->envelope( range( 1, 5 ) );
wh_check( false === $e['pagination']['has_more'], 'last page has_more false' );

$e = ( new Pagination( 1, 'abc', 100, 20 ) )->envelope( array( 1 ) );
wh_check( 20 === $e['pagination']['per_page'], 'non-numeric per_page falls back' );
