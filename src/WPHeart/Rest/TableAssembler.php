<?php
/**
 * Shared table assembler: discovery + schema + relationships + classification.
 * Keeps REST controllers thin (no business logic in transport).
 *
 * @package WP_Heart
 */

namespace WPHeart\Rest;

use WPHeart\Container\Container;
use WPHeart\Domain\TableInfo;
use WPHeart\Security\ErrorSanitizer;

/**
 * Shared table assembler: discovery + schema + relationships + classification.
 */
class TableAssembler {
	/** @var Container */
	private $c;
	/** @var TableInfo[] */
	private $memo = array();
	/** @var array|null */
	private $classifications = null;

	/**
	 * @param Container $c Container.
	 */
	public function __construct( Container $c ) {
		$this->c = $c;
	}

	/**
	 * @param string $table Table name.
	 * @return TableInfo|\WP_Error
	 */
	public function get( $table ) {
		if ( isset( $this->memo[ $table ] ) ) {
			return $this->memo[ $table ];
		}
		$discovery = $this->c->make( 'discovery' );
		$found     = $discovery->get_table( $table );
		if ( ! $found ) {
			return ErrorSanitizer::rest_error( 'wp_heart_unknown_table', __( 'Unknown table.', 'wp-heart' ), 404 );
		}

		$columns     = $this->c->make( 'schema.columns' )->inspect( $table );
		$indexes     = $this->c->make( 'schema.indexes' )->inspect( $table );
		$constraints = $this->c->make( 'schema.constraints' )->inspect( $table );

		$found->set_columns( $columns );
		$found->set_indexes( $indexes );
		$found->set_constraints( $constraints['constraints'] );

		$names = $this->table_names();
		$rels  = $this->c->make( 'schema.relationships' )->for_table(
			$table,
			$columns,
			$constraints['constraints'],
			$names,
			'UNAVAILABLE' !== $constraints['availability']
		);
		$found->set_relationships( $rels );
		$found->set_classification( $this->c->make( 'classifier' )->classify( $table ) );

		$this->memo[ $table ] = $found;
		return $found;
	}

	/**
	 * Metadata-level tables with classifications (list views).
	 *
	 * @param bool $refresh Bypass cache.
	 * @return array {tables: TableInfo[], freshness, cached_at}
	 */
	public function list( $refresh = false ) {
		$result = $this->c->make( 'discovery' )->discover( $refresh );
		$map    = $this->classifications();
		foreach ( $result['tables'] as $table ) {
			if ( isset( $map[ $table->name() ] ) ) {
				$table->set_classification( $map[ $table->name() ] );
			}
		}
		return $result;
	}

	/**
	 * @return \WPHeart\Domain\Classification[] Keyed by table.
	 */
	public function classifications() {
		if ( null === $this->classifications ) {
			$this->classifications = $this->c->make( 'classifier' )->classify_all( $this->table_names() );
		}
		return $this->classifications;
	}

	/**
	 * @return string[]
	 */
	public function table_names() {
		$result = $this->c->make( 'discovery' )->discover();
		$names  = array();
		foreach ( $result['tables'] as $table ) {
			$names[] = $table->name();
		}
		return $names;
	}
}
