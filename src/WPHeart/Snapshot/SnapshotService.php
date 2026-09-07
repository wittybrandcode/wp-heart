<?php
/**
 * Snapshot builder: freezes observed database reality on explicit request.
 * Metadata only. Never row content.
 *
 * @package WP_Heart
 */

namespace WPHeart\Snapshot;

use WPHeart\Classification\ClassificationEngine;
use WPHeart\Discovery\TableDiscovery;
use WPHeart\Domain\Snapshot;
use WPHeart\Schema\ColumnInspector;
use WPHeart\Schema\ConstraintInspector;
use WPHeart\Schema\IndexInspector;

/**
 * Snapshot builder.
 */
class SnapshotService {
	/** @var TableDiscovery */
	private $discovery;
	/** @var ColumnInspector */
	private $columns;
	/** @var IndexInspector */
	private $indexes;
	/** @var ConstraintInspector */
	private $constraints;
	/** @var ClassificationEngine */
	private $classifier;
	/** @var SnapshotStore */
	private $store;

	/**
	 * @param TableDiscovery       $discovery Discovery.
	 * @param ColumnInspector      $columns Columns.
	 * @param IndexInspector       $indexes Indexes.
	 * @param ConstraintInspector  $constraints Constraints.
	 * @param ClassificationEngine $classifier Classifier.
	 * @param SnapshotStore        $store Store.
	 */
	public function __construct( TableDiscovery $discovery, ColumnInspector $columns, IndexInspector $indexes, ConstraintInspector $constraints, ClassificationEngine $classifier, SnapshotStore $store ) {
		$this->discovery   = $discovery;
		$this->columns     = $columns;
		$this->indexes     = $indexes;
		$this->constraints = $constraints;
		$this->classifier  = $classifier;
		$this->store       = $store;
	}

	/**
	 * Capture a snapshot of the current database reality.
	 *
	 * @param string $label Human label.
	 * @return Snapshot|null Null when storage is unavailable.
	 */
	public function capture( $label ) {
		$label = substr( trim( (string) $label ), 0, 191 );
		if ( '' === $label ) {
			$label = function_exists( '__' ) ? __( 'Untitled snapshot', 'wp-heart' ) : 'Untitled snapshot';
		}
		$found = $this->discovery->discover( true );
		$names = array();
		foreach ( $found['tables'] as $table ) {
			$names[] = $table->name();
		}
		$map    = $this->classifier->classify_all( $names );
		$tables = array();
		foreach ( $found['tables'] as $table ) {
			$name     = $table->name();
			$cons     = $this->constraints->inspect( $name );
			$cls      = isset( $map[ $name ] ) ? $map[ $name ] : \WPHeart\Domain\Classification::unknown( $name );
			$tables[] = array(
				'name'           => $name,
				'metadata'       => $table->to_array()['metadata'],
				'columns'        => array_map(
					static function ( $c ) {
						return $c->to_array();
					},
					$this->columns->inspect( $name )
				),
				'indexes'        => array_map(
					static function ( $i ) {
						return $i->to_array();
					},
					$this->indexes->inspect( $name )
				),
				'constraints'    => array_map(
					static function ( $c ) {
						return $c->to_array();
					},
					$cons['constraints']
				),
				'classification' => $cls->to_array(),
			);
		}
		usort(
			$tables,
			static function ( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);

		$snapshot = new Snapshot(
			array(
				'version'    => 1,
				'id'         => gmdate( 'Ymd-His' ) . '-' . substr( md5( uniqid( '', true ) ), 0, 8 ),
				'label'      => $label,
				'created_at' => gmdate( 'Y-m-d\TH:i:s+00:00' ),
				'tables'     => $tables,
			)
		);
		return $this->store->save( $snapshot ) ? $snapshot : null;
	}
}
