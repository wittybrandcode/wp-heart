<?php
/**
 * Snapshot + diff tests: capture shape, determinism, tamper evidence,
 * and Added/Removed/Changed semantics.
 *
 * @package WP_Heart_Tests
 */

use WPHeart\Domain\Snapshot;
use WPHeart\Snapshot\SchemaDiff;

wh_group( 'Snapshot' );

$GLOBALS['wh_transients'] = array();
$c       = wh_test_container();
$service = $c->make( 'snapshots.service' );
$store   = $c->make( 'snapshots.store' );

// Hygiene: never let files from an interrupted run pollute assertions.
foreach ( glob( sys_get_temp_dir() . '/wh-snap-tests/wp-heart-snapshot-*.json' ) ?: array() as $stale ) {
	unlink( $stale );
}

$snap = $service->capture( 'unit fixture' );
wh_check( $snap instanceof Snapshot && '' !== $snap->id(), 'capture returns identified snapshot' );
$doc = $snap->to_array();
wh_check( 1 === $doc['version'] && 'unit fixture' === $doc['label'], 'snapshot envelope' );
wh_check( 8 === count( $doc['tables'] ), 'all fixture tables captured' );

$leak = false;
foreach ( $doc['tables'] as $table ) {
	foreach ( array( 'rows', 'row_count', 'option_value', 'results' ) as $banned ) {
		if ( array_key_exists( $banned, $table ) ) {
			$leak = true;
		}
	}
	if ( ! isset( $table['name'], $table['columns'], $table['indexes'], $table['classification'] ) ) {
		$leak = true;
	}
}
wh_check( ! $leak, 'metadata only, never row content' );

// Store round trip + registry.
$loaded = $store->load( $snap->id() );
wh_check( $loaded instanceof Snapshot && $loaded->to_array()['label'] === $doc['label'], 'store round trip' );
$list = $store->list();
$found = false;
foreach ( $list as $entry ) {
	if ( $entry['id'] === $snap->id() && $entry['tables'] === count( $doc['tables'] ) && isset( $entry['hash'] ) ) {
		$found = true;
	}
}
wh_check( $found, 'registry entry with hash' );

// Determinism: two captures of the same reality produce an empty diff.
$snap2 = $service->capture( 'second' );
$diff  = SchemaDiff::compare( $snap->to_array(), $snap2->to_array() );
wh_check( 0 === $diff['summary']['tables_added'] && 0 === $diff['summary']['tables_removed'] && 0 === $diff['summary']['tables_changed'], 'identical realities diff empty' );

// Tamper evidence: modify this snapshot's own file, load must refuse it.
$target = glob( sys_get_temp_dir() . '/wh-snap-tests/wp-heart-snapshot-' . $snap->id() . '.json' );
if ( $target ) {
	file_put_contents( $target[0], str_replace( 'unit fixture', 'unit fixture TAMPERED', (string) file_get_contents( $target[0] ) ) );
	wh_check( null === $store->load( $snap->id() ), 'tampered file refused' );
	wh_check( $store->load( $snap2->id() ) instanceof Snapshot, 'untampered snapshot still loads' );
} else {
	wh_check( false, 'snapshot file exists for tamper test' );
}

// Crafted diff semantics.
$mk_table = static function ( $name, $cols, $extra = array() ) {
	return array_merge(
		array(
			'name'           => $name,
			'metadata'       => array( 'engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci' ),
			'columns'        => $cols,
			'indexes'        => array(),
			'constraints'    => array(),
			'classification' => array( 'type' => 'UNKNOWN', 'owner' => null, 'confidence' => 'UNKNOWN' ),
		),
		$extra
	);
};
$col = static function ( $name, $type = 'bigint(20)', $nullable = false ) {
	return array( 'name' => $name, 'data_type' => $type, 'nullable' => $nullable, 'default' => null, 'extra' => '' );
};
$doc_a = array(
	'version' => 1,
	'id'      => 'a',
	'label'   => 'A',
	'tables'  => array(
		$mk_table( 't_keep', array( $col( 'id' ), $col( 'title', 'text', true ) ) ),
		$mk_table( 't_gone', array( $col( 'id' ) ) ),
		$mk_table(
			't_change',
			array( $col( 'id' ), $col( 'nick', 'varchar(50)', true ) ),
			array( 'indexes' => array( array( 'name' => 'PRIMARY', 'unique' => true, 'columns' => array( 'id' ) ) ) )
		),
	),
);
$doc_b = array(
	'version' => 1,
	'id'      => 'b',
	'label'   => 'B',
	'tables'  => array(
		$mk_table( 't_keep', array( $col( 'id' ), $col( 'title', 'text', true ) ) ),
		$mk_table( 't_new', array( $col( 'id' ) ) ),
		$mk_table(
			't_change',
			array( $col( 'id' ), $col( 'nick', 'varchar(100)', true ), $col( 'age', 'int(11)', true ) ),
			array(
				'indexes'        => array( array( 'name' => 'PRIMARY', 'unique' => true, 'columns' => array( 'id' ) ) ),
				'classification' => array( 'type' => 'PLUGIN', 'owner' => 'demo', 'confidence' => 'MEDIUM' ),
			)
		),
	),
);
$diff = SchemaDiff::compare( $doc_a, $doc_b );
wh_check( array( 't_new' ) === $diff['tables']['added'], 'added tables' );
wh_check( array( 't_gone' ) === $diff['tables']['removed'], 'removed tables' );
wh_check( isset( $diff['tables']['changed']['t_change'] ) && ! isset( $diff['tables']['changed']['t_keep'] ), 'only changed tables listed' );
$tc = $diff['tables']['changed']['t_change'];
wh_check( array( 'age' ) === $tc['columns']['added'] && empty( $tc['columns']['removed'] ), 'added column detected' );
wh_check( isset( $tc['columns']['changed']['nick']['data_type'] ), 'changed column type detected' );
wh_check( 'PLUGIN' === $tc['classification']['type']['to'], 'classification change detected' );
wh_check( 1 === $diff['summary']['tables_added'] && 1 === $diff['summary']['tables_removed'] && 1 === $diff['summary']['tables_changed'], 'summary counts' );

// Document validation.
wh_check( ! Snapshot::is_valid_document( 'nope' ), 'garbage rejected' );
wh_check( ! Snapshot::is_valid_document( array( 'version' => 1 ) ), 'tables required' );
wh_check( ! Snapshot::is_valid_document( array( 'version' => 1, 'tables' => array( array( 'x' => 1 ) ) ) ), 'table names required' );
wh_check( Snapshot::is_valid_document( $doc_a ), 'well-formed accepted' );

// Delete cleans file + registry.
wh_check( $store->delete( $snap->id() ) && $store->delete( $snap2->id() ), 'delete ok' );
$ids = array();
foreach ( $store->list() as $entry ) {
	$ids[] = $entry['id'];
}
wh_check( ! in_array( $snap->id(), $ids, true ), 'registry cleaned' );
