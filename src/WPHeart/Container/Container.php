<?php
/**
 * Lightweight dependency container (WH-003).
 *
 * @package WP_Heart
 */

namespace WPHeart\Container;

/**
 * Lightweight dependency container (WH-003).
 */
class Container {
	/** @var callable[] */
	private $bindings = array();
	/** @var array */
	private $instances = array();

	/**
	 * @param string   $id Service id.
	 * @param callable $factory Factory receiving the container.
	 */
	public function bind( $id, callable $factory ) {
		$this->bindings[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	/**
	 * @param string   $id Service id.
	 * @param callable $factory Factory receiving the container.
	 */
	public function singleton( $id, callable $factory ) {
		$this->bind(
			$id,
			function ( $c ) use ( $id, $factory ) {
				if ( ! array_key_exists( $id, $this->instances ) ) {
					$this->instances[ $id ] = $factory( $c );
				}
				return $this->instances[ $id ];
			}
		);
	}

	/**
	 * @param string $id Service id.
	 * @return bool
	 */
	public function has( $id ) {
		return isset( $this->bindings[ $id ] );
	}

	/**
	 * @param string $id Service id.
	 * @return mixed
	 * @throws \InvalidArgumentException When the service is unknown.
	 */
	public function make( $id ) {
		if ( ! $this->has( $id ) ) {
			throw new \InvalidArgumentException( 'Unknown service: ' . $id );
		}
		$factory = $this->bindings[ $id ];
		return $factory( $this );
	}
}
