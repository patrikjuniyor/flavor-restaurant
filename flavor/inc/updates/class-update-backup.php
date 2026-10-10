<?php
/**
 * Copy-based backups of the theme folder, and restore with a rollback on failure.
 *
 * A backup is taken before every update, so there is always a known-good
 * version to return to. Restore never deletes the current theme until the
 * replacement is fully in place, and puts the old one back if the swap fails.
 *
 * @package Flavor
 */

namespace Flavor\Updates;

defined( 'ABSPATH' ) || exit;

/**
 * Class Backup
 */
final class Backup {

	/** How many backups to keep. Older ones are pruned after each update. */
	public const KEEP = 3;

	/** @var string Directory that holds the backups. */
	private string $base;

	/**
	 * @param string $base_dir Directory that will hold the backups.
	 */
	public function __construct( string $base_dir ) {
		$this->base = rtrim( $base_dir, '/\\' );
	}

	/**
	 * Copy a theme folder into a new backup.
	 *
	 * @param string $source  Theme folder to copy.
	 * @param string $version Version being backed up, e.g. "1.6.1".
	 * @return string Path of the new backup.
	 * @throws Update_Error When the copy cannot be made completely.
	 */
	public function create( string $source, string $version ): string {
		if ( ! is_dir( $source ) ) {
			throw new Update_Error( 'source', 'پوشهٔ قالب فعلی پیدا نشد؛ پشتیبان ساخته نشد.' );
		}
		if ( ! is_dir( $this->base ) && ! self::make_dir( $this->base ) ) {
			throw new Update_Error( 'write', 'پوشهٔ پشتیبان ساخته نشد.' );
		}
		$id   = self::safe_version( $version ) . '-' . gmdate( 'YmdHis' ) . '-' . bin2hex( random_bytes( 3 ) );
		$dest = $this->base . '/' . $id;
		try {
			self::copy_tree( $source, $dest );
		} catch ( \RuntimeException $e ) {
			self::remove_tree( $dest );
			throw new Update_Error( 'copy', 'کپی پشتیبان کامل نشد؛ نصب متوقف شد.' );
		}
		return $dest;
	}

	/**
	 * Existing backups, newest first.
	 *
	 * @return array<int, array{id: string, path: string, version: string, time: int}>
	 */
	public function list(): array {
		if ( ! is_dir( $this->base ) ) {
			return array();
		}
		$items = array();
		foreach ( (array) scandir( $this->base ) as $entry ) {
			$path = $this->base . '/' . $entry;
			if ( '.' === $entry || '..' === $entry || ! is_dir( $path ) || is_link( $path ) ) {
				continue;
			}
			$items[] = array(
				'id'      => $entry,
				'path'    => $path,
				'version' => explode( '-', $entry, 2 )[0],
				'time'    => (int) filemtime( $path ),
			);
		}
		usort(
			$items,
			static function ( array $a, array $b ): int {
				return $b['time'] <=> $a['time'] ?: strcmp( $b['id'], $a['id'] );
			}
		);
		return $items;
	}

	/**
	 * Remove all but the newest $keep backups.
	 *
	 * @param int $keep How many to keep.
	 * @return void
	 */
	public function prune( int $keep = self::KEEP ): void {
		foreach ( array_slice( $this->list(), max( 0, $keep ) ) as $old ) {
			self::remove_tree( $old['path'] );
		}
	}

	/**
	 * Replace $target with the content of backup $id.
	 *
	 * Order: stage a copy, move the current folder aside, move the stage into
	 * place, then delete the old folder. If the second move fails the old folder
	 * is moved back, so the site never ends up without a theme.
	 *
	 * @param string $id     Backup id from list().
	 * @param string $target Theme folder to replace.
	 * @return void
	 * @throws Update_Error On any failure, with the original folder restored.
	 */
	public function restore( string $id, string $target ): void {
		$source = $this->resolve( $id );
		$stage  = $target . '.new-' . bin2hex( random_bytes( 4 ) );
		$old    = $target . '.old-' . bin2hex( random_bytes( 4 ) );

		try {
			self::copy_tree( $source, $stage );
		} catch ( \RuntimeException $e ) {
			self::remove_tree( $stage );
			throw new Update_Error( 'copy', 'بازگردانی آغاز نشد؛ قالب فعلی دست‌نخورده ماند.' );
		}

		if ( ! rename( $target, $old ) ) {
			self::remove_tree( $stage );
			throw new Update_Error( 'swap', 'قالب فعلی جابه‌جا نشد؛ بازگردانی انجام نشد.' );
		}
		if ( ! rename( $stage, $target ) ) {
			rename( $old, $target ); // Put the working theme back.
			self::remove_tree( $stage );
			throw new Update_Error( 'swap', 'بازگردانی ناموفق بود؛ قالب فعلی حفظ شد.' );
		}
		self::remove_tree( $old );
	}

	/**
	 * Resolve a backup id to a path, refusing anything outside the backup folder.
	 *
	 * @param string $id Backup id.
	 * @return string Absolute path.
	 * @throws Update_Error When the id is malformed or not a backup.
	 */
	public function resolve( string $id ): string {
		if ( 1 !== preg_match( '/^[A-Za-z0-9._-]+$/', $id ) || '.' === $id[0] ) {
			throw new Update_Error( 'id', 'شناسهٔ پشتیبان نامعتبر است.' );
		}
		$path = $this->base . '/' . $id;
		$real = realpath( $path );
		$root = realpath( $this->base );
		if ( false === $real || false === $root || 0 !== strpos( $real, $root . DIRECTORY_SEPARATOR ) || ! is_dir( $real ) ) {
			throw new Update_Error( 'id', 'پشتیبان مورد نظر پیدا نشد.' );
		}
		return $real;
	}

	/**
	 * Version part of a folder name, restricted to digits and dots.
	 *
	 * @param string $version Raw version.
	 * @return string
	 */
	private static function safe_version( string $version ): string {
		$clean = preg_replace( '/[^0-9.]/', '', $version );
		return '' === $clean ? '0' : $clean;
	}

	/**
	 * Create a directory tree, returning false instead of warning.
	 *
	 * @param string $dir Directory.
	 * @return bool
	 */
	private static function make_dir( string $dir ): bool {
		return is_dir( $dir ) || @mkdir( $dir, 0755, true ) || is_dir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	/**
	 * Recursively copy a directory. Symlinks are skipped, never followed.
	 *
	 * @param string $src Source directory.
	 * @param string $dst Destination directory (must not exist).
	 * @return void
	 * @throws \RuntimeException On any failed copy.
	 */
	private static function copy_tree( string $src, string $dst ): void {
		if ( ! @mkdir( $dst, 0755, true ) && ! is_dir( $dst ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			throw new \RuntimeException( 'mkdir' );
		}
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $src, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ( $iterator as $item ) {
			$relative = substr( $item->getPathname(), strlen( $src ) + 1 );
			$target   = $dst . DIRECTORY_SEPARATOR . $relative;
			if ( $item->isLink() ) {
				continue;
			}
			if ( $item->isDir() ) {
				if ( ! @mkdir( $target, 0755, true ) && ! is_dir( $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
					throw new \RuntimeException( 'mkdir' );
				}
				continue;
			}
			if ( ! @copy( $item->getPathname(), $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				throw new \RuntimeException( 'copy' );
			}
		}
	}

	/**
	 * Delete a directory tree without following symlinks.
	 *
	 * @param string $dir Directory to remove.
	 * @return void
	 */
	private static function remove_tree( string $dir ): void {
		if ( is_link( $dir ) ) {
			@unlink( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return;
		}
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			if ( $item->isDir() && ! $item->isLink() ) {
				@rmdir( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			} else {
				@unlink( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
}
