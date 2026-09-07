<?php
/**
 * Relationship engine tests (WH-033): PHYSICAL needs FK evidence,
 * INFERRED is labeled and never a constraint claim.
 *
 * @package WP_Heart_Tests
 */

use WPHeart\Domain\ColumnInfo;
use WPHeart\Domain\ConstraintInfo;
use WPHeart\Intelligence\WpContextService;
use WPHeart\Schema\RelationshipEngine;
use WPHeart\Support\RelationshipOrigin;

wh_group( 'Relationships' );

$engine = new RelationshipEngine( new WpContextService( 'wp_', false, 1 ) );
$known  = array( 'wp_posts', 'wp_postmeta', 'rel_pair', 'wp_users' );

$col = static function ( $name ) {
	return new ColumnInfo( array( 'name' => $name ) );
};

// Physical from constraint evidence.
$rels = $engine->for_table(
	'rel_pair',
	array( $col( 'a_id' ), $col( 'b_id' ) ),
	array( new ConstraintInfo( array( 'name' => 'fk_pair_a', 'table' => 'rel_pair', 'columns' => array( 'a_id' ), 'referenced_table' => 'wp_posts', 'referenced_columns' => array( 'ID' ) ) ) ),
	$known
);
$origins = array();
foreach ( $rels as $r ) {
	$d = $r->to_array();
	if ( 'a_id' === $d['source_column'] ) {
		$origins[] = $d['origin'];
	}
}
wh_check( in_array( RelationshipOrigin::PHYSICAL, $origins, true ), 'fk-backed column is PHYSICAL' );

// Core convention inference.
$rels = $engine->for_table( 'wp_posts', array( $col( 'post_author' ), $col( 'ID' ) ), array(), $known );
$found = null;
foreach ( $rels as $r ) {
	$d = $r->to_array();
	if ( 'post_author' === $d['source_column'] ) {
		$found = $d;
	}
}
wh_check( null !== $found && RelationshipOrigin::INFERRED === $found['origin'], 'post_author inferred, not physical' );
wh_check( null !== $found && 'wp_users' === $found['target_table'], 'post_author targets users table' );
wh_check( null !== $found && ! empty( $found['evidence'] ), 'inference carries evidence' );

// Polymorphic object_id is deliberately unresolved.
$rels = $engine->for_table( 'wp_postmeta', array( $col( 'object_id' ) ), array(), $known );
wh_check( 0 === count( $rels ), 'polymorphic object_id unresolved (no guess)' );

// Generic {entity}_id convention.
$rels = $engine->for_table( 'wp_postmeta', array( $col( 'post_id' ) ), array(), $known );
$found = null;
foreach ( $rels as $r ) {
	$d = $r->to_array();
	if ( 'post_id' === $d['source_column'] ) {
		$found = $d;
	}
}
wh_check( null !== $found && RelationshipOrigin::INFERRED === $found['origin'], 'generic _id inferred at LOW confidence' );
wh_check( null !== $found && 'LOW' === $found['confidence'], 'generic pattern is LOW' );

// Unavailable constraint metadata yields UNKNOWN, not fabrication.
$rels = $engine->for_table( 'wp_posts', array( $col( 'post_author' ) ), array(), $known, false );
$has_unknown = false;
foreach ( $rels as $r ) {
	if ( RelationshipOrigin::UNKNOWN === $r->origin() ) {
		$has_unknown = true;
	}
}
wh_check( $has_unknown, 'constraint outage surfaces UNKNOWN' );
