<?php
/**
 * Identifier safety tests (WH-011).
 *
 * @package WP_Heart_Tests
 */

use WPHeart\Database\IdentifierValidator;

wh_group( 'IdentifierValidator' );

$v = new IdentifierValidator();

wh_check( $v->valid_name( 'wp_posts' ), 'accepts normal table' );
wh_check( $v->valid_name( 'a' ), 'accepts single char' );
wh_check( $v->valid_name( 'T$X_1' ), 'accepts $ and digits' );
wh_check( ! $v->valid_name( '' ), 'rejects empty' );
wh_check( ! $v->valid_name( 'wp-posts' ), 'rejects dash' );
wh_check( ! $v->valid_name( 'wp posts' ), 'rejects space' );
wh_check( ! $v->valid_name( 't`x' ), 'rejects backtick' );
wh_check( ! $v->valid_name( 't;x' ), 'rejects semicolon' );
wh_check( ! $v->valid_name( "t'x" ), 'rejects quote' );
wh_check( ! $v->valid_name( 't"x' ), 'rejects double quote' );
wh_check( ! $v->valid_name( str_repeat( 'a', 65 ) ), 'rejects >64 chars' );
wh_check( $v->valid_name( str_repeat( 'a', 64 ) ), 'accepts 64 chars' );
wh_check( ! $v->valid_name( null ), 'rejects null' );
wh_check( ! $v->valid_name( 123 ), 'rejects non-string' );
wh_check( ! $v->valid_name( 'wp_posts; DROP TABLE x' ), 'rejects stacked payload' );

// Allowlist verification.
wh_check( 'wp_posts' === $v->validate_table( 'wp_posts', array( 'wp_posts' ) ), 'allowlist hit' );
wh_check( null === $v->validate_table( 'wp_other', array( 'wp_posts' ) ), 'allowlist miss rejected' );
wh_check( 'wp_other' === $v->validate_table( 'wp_other', null ), 'no allowlist skips existence check' );

// Quoting.
wh_check( '`wp_posts`' === $v->quote( 'wp_posts' ), 'backtick quoting' );

// Sort direction.
wh_check( 'ASC' === $v->sort_direction( 'asc' ), 'asc normalized' );
wh_check( 'DESC' === $v->sort_direction( 'DESC' ), 'desc kept' );
wh_check( null === $v->sort_direction( 'DESC; DROP' ), 'direction injection rejected' );
wh_check( null === $v->sort_direction( '' ), 'empty direction rejected' );
