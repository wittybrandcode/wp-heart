<?php
/**
 * File-based snapshot store. Snapshots live outside the database
 * (uploads dir, guarded) so observing never burdens the observed.
 *
 * @package WP_Heart
 */

namespace WPHeart\Snapshot;

use WPHeart\Domain\Snapshot;
use WPHeart\Logging\Logger;

/**
 * File-based snapshot store.
 */
class SnapshotStore {
	const OPTION     = 'wp_heart_snapshots';
	const DIR_NAME   = 'wp-heart-snapshots';
	const MAX_BYTES  = 10485760; // 10 MB per snapshot file.
	const MAX_IMPORT = 5242880; // 5 MB import cap.

	/** @var string|null */
	private $base_dir;

	/**
	 * @param string|null $base_dir Storage dir override (tests). Defaults to the uploads dir.
	 */
	public function __construct( $base_dir = null ) {
		$this->base_dir = $base_dir;
	}

	/**
	 * @return string|null Storage directory or null when unavailable.
	 */
	public function dir() {
		if ( null !== $this->base_dir ) {
			$this->ensure_guarded( $this->base_dir );
			return $this->base_dir;
		}
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return null;
		}
		$uploads = wp_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			return null;
		}
		$dir = trailingslashit( $uploads['basedir'] ) . self::DIR_NAME;
		$this->ensure_guarded( $dir );
		return $dir;
	}

	/**
	 * @param string $dir Directory.
	 */
	private function ensure_guarded( $dir ) {
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$index = trailingslashit( $dir ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents -- own guarded dir; WP_Filesystem needs interactive credentials unsuitable for REST/CLI.
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents -- own guarded dir; WP_Filesystem needs interactive credentials unsuitable for REST/CLI.
			file_put_contents( $htaccess, "Require all denied\n" );
		}
	}

	/**
	 * @param string $id Snapshot id.
	 * @param string $dir Storage directory.
	 * @return string File path.
	 */
	private function path_for( $id, $dir ) {
		$safe = preg_replace( '/[^A-Za-z0-9_-]/', '_', (string) $id );
		return trailingslashit( $dir ) . 'wp-heart-snapshot-' . substr( $safe, 0, 80 ) . '.json';
	}

	/**
	 * @param Snapshot $snapshot Snapshot.
	 * @return bool
	 */
	public function save( Snapshot $snapshot ) {
		$dir = $this->dir();
		if ( null === $dir ) {
			return false;
		}
		$json = wp_json_encode( $snapshot->to_array() );
		if ( ! is_string( $json ) || strlen( $json ) > self::MAX_BYTES ) {
			return false;
		}
		// Atomic write: temp file + rename, never a half-written snapshot.
		$tmp = trailingslashit( $dir ) . uniqid( '.tmp-', true ) . '.json';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents -- atomic write+rename has no WP_Filesystem equivalent usable in REST/CLI.
		if ( false === file_put_contents( $tmp, $json, LOCK_EX ) ) {
			return false;
		}
		$ok = rename( $tmp, $this->path_for( $snapshot->id(), $dir ) );
		if ( ! $ok ) {
			unlink( $tmp );
			return false;
		}
		$this->register( $snapshot );
		return true;
	}

	/**
	 * @param string $id Snapshot id.
	 * @return Snapshot|null
	 */
	public function load( $id ) {
		$dir = $this->dir();
		if ( null === $dir ) {
			return null;
		}
		$path = $this->path_for( $id, $dir );
		if ( ! is_readable( $path ) || filesize( $path ) > self::MAX_BYTES ) {
			return null;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local snapshot file, never a URL.
		$doc = json_decode( (string) file_get_contents( $path ), true );
		if ( ! Snapshot::is_valid_document( $doc ) ) {
			Logger::log( 'system', 'snapshot failed validation on load', array( 'id' => substr( (string) $id, 0, 40 ) ) );
			return null;
		}
		$entry = $this->registry_entry( $id );
		if ( $entry && isset( $entry['hash'] ) && $entry['hash'] !== $this->hash_doc( $doc ) ) {
			Logger::log( 'system', 'snapshot hash mismatch', array( 'id' => substr( (string) $id, 0, 40 ) ) );
			return null;
		}
		return new Snapshot( $doc );
	}

	/**
	 * @param string $id Snapshot id.
	 * @return bool
	 */
	public function delete( $id ) {
		$dir = $this->dir();
		if ( null === $dir ) {
			return false;
		}
		$path = $this->path_for( $id, $dir );
		if ( file_exists( $path ) ) {
			unlink( $path );
		}
		$this->unregister( $id );
		return true;
	}

	/**
	 * @return array[] Registry rows (id, label, created_at, tables), newest first.
	 */
	public function list() {
		$all = function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : array();
		if ( ! is_array( $all ) ) {
			return array();
		}
		usort(
			$all,
			static function ( $a, $b ) {
				return strcmp( isset( $b['created_at'] ) ? $b['created_at'] : '', isset( $a['created_at'] ) ? $a['created_at'] : '' );
			}
		);
		return array_values( $all );
	}

	/**
	 * @param string $id Snapshot id.
	 * @return array|null
	 */
	private function registry_entry( $id ) {
		foreach ( $this->list() as $entry ) {
			if ( isset( $entry['id'] ) && $entry['id'] === $id ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * @param Snapshot $snapshot Snapshot.
	 */
	private function register( Snapshot $snapshot ) {
		$data  = $snapshot->to_array();
		$all   = $this->list();
		$found = false;
		$row   = array(
			'id'         => $snapshot->id(),
			'label'      => isset( $data['label'] ) ? substr( (string) $data['label'], 0, 191 ) : '',
			'created_at' => isset( $data['created_at'] ) ? (string) $data['created_at'] : '',
			'tables'     => isset( $data['tables'] ) ? count( $data['tables'] ) : 0,
			'hash'       => $this->hash_doc( $data ),
		);
		foreach ( $all as $i => $entry ) {
			if ( isset( $entry['id'] ) && $entry['id'] === $snapshot->id() ) {
				$all[ $i ] = $row;
				$found     = true;
			}
		}
		if ( ! $found ) {
			$all[] = $row;
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION, array_values( $all ), false );
		}
	}

	/**
	 * @param string $id Snapshot id.
	 */
	private function unregister( $id ) {
		$all = array();
		foreach ( $this->list() as $entry ) {
			if ( ! isset( $entry['id'] ) || $entry['id'] !== $id ) {
				$all[] = $entry;
			}
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION, array_values( $all ), false );
		}
	}

	/**
	 * Canonical hash for tamper evidence.
	 *
	 * @param array $doc Document.
	 * @return string
	 */
	private function hash_doc( array $doc ) {
		return md5( (string) wp_json_encode( $doc ) );
	}
}
