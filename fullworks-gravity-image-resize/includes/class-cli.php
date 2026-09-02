<?php
/**
 * WP-CLI commands for the existing backlog of files.
 *
 * @package FullworksGravityImageResize
 */

namespace FullworksGravityImageResize;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * `wp fwgir <command>`
 *
 * Everything destructive previews by default and needs an explicit flag to act,
 * because both resizing and deleting are irreversible and operate on customers'
 * uploaded files.
 */
class CLI {

	/**
	 * Report what is on disk and what would change.
	 *
	 * ## OPTIONS
	 *
	 * [--max=<pixels>]
	 * : Longest permitted edge. Defaults to the configured setting.
	 *
	 * ## EXAMPLES
	 *
	 *     wp fwgir scan
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function scan( $args, $assoc_args ) {
		$max = isset( $assoc_args['max'] ) ? Settings::clamp( $assoc_args['max'] ) : Settings::max_dimension();

		$all       = Scanner::all_files();
		$orphans   = Scanner::orphans();
		$oversized = Scanner::oversized( $max );

		\WP_CLI::log( sprintf( 'Upload root:  %s', Scanner::upload_root() ) );
		\WP_CLI::log( sprintf( 'Max dimension: %dpx', $max ) );
		\WP_CLI::log( '' );
		\WP_CLI::log( sprintf( 'Files on disk:      %6d   %s', count( $all ), size_format( Scanner::total_bytes( $all ) ) ) );
		\WP_CLI::log( sprintf( 'Orphaned:           %6d   %s', count( $orphans ), size_format( Scanner::total_bytes( $orphans ) ) ) );
		\WP_CLI::log( sprintf( 'Oversized (kept):   %6d   %s', count( $oversized ), size_format( Scanner::total_bytes( $oversized ) ) ) );
		\WP_CLI::log( '' );
		\WP_CLI::log( 'Orphans are files no entry or draft references. Oversized counts only files that ARE referenced.' );

		$health = Scanner::reference_health();
		\WP_CLI::log( '' );
		\WP_CLI::log( sprintf( 'Entries: %d   file references resolved: %d', $health['entries'], $health['referenced'] ) );
		if ( ! $health['sane'] ) {
			\WP_CLI::warning( 'This site has entries but NO file reference resolved. Orphan detection is unreliable here -- treat the orphan count as meaningless and do not delete.' );
		}
	}

	/**
	 * Downscale existing images that are still referenced by an entry.
	 *
	 * Destructive and irreversible. Back up the gravity_forms uploads directory
	 * before running with --execute; this command does not back anything up.
	 *
	 * ## OPTIONS
	 *
	 * [--max=<pixels>]
	 * : Longest permitted edge. Defaults to the configured setting.
	 *
	 * [--execute]
	 * : Actually resize. Without this the command only reports.
	 *
	 * [--limit=<n>]
	 * : Stop after this many files. Useful for a cautious first run.
	 *
	 * ## EXAMPLES
	 *
	 *     wp fwgir resize
	 *     wp fwgir resize --limit=10 --execute
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function resize( $args, $assoc_args ) {
		$max     = isset( $assoc_args['max'] ) ? Settings::clamp( $assoc_args['max'] ) : Settings::max_dimension();
		$execute = isset( $assoc_args['execute'] );
		$limit   = isset( $assoc_args['limit'] ) ? max( 0, (int) $assoc_args['limit'] ) : 0;

		$files = Scanner::oversized( $max );
		if ( $limit > 0 ) {
			$files = array_slice( $files, 0, $limit );
		}

		if ( empty( $files ) ) {
			\WP_CLI::success( 'Nothing to resize.' );
			return;
		}

		if ( ! $execute ) {
			\WP_CLI::log( sprintf( 'DRY RUN — %d file(s), %s, would be downscaled to %dpx.', count( $files ), size_format( Scanner::total_bytes( $files ) ), $max ) );
			foreach ( array_slice( $files, 0, 10 ) as $path ) {
				$d = Resizer::dimensions( $path );
				\WP_CLI::log( sprintf( '  %s  %dx%d  %s', basename( $path ), $d ? $d['width'] : 0, $d ? $d['height'] : 0, size_format( (int) filesize( $path ) ) ) );
			}
			if ( count( $files ) > 10 ) {
				\WP_CLI::log( sprintf( '  ... and %d more', count( $files ) - 10 ) );
			}
			\WP_CLI::log( '' );
			\WP_CLI::warning( 'Resizing is irreversible. Back up the gravity_forms directory, then re-run with --execute.' );
			return;
		}

		$before   = 0;
		$after    = 0;
		$done     = 0;
		$failed   = 0;
		$progress = \WP_CLI\Utils\make_progress_bar( 'Resizing', count( $files ) );

		foreach ( $files as $path ) {
			$result  = Resizer::resize( $path, $max );
			$before += $result['bytes_before'];
			$after  += $result['bytes_after'];
			if ( '' !== $result['error'] ) {
				++$failed;
				\WP_CLI::warning( sprintf( '%s: %s', basename( $path ), $result['error'] ) );
			} elseif ( $result['resized'] ) {
				++$done;
			}
			$progress->tick();
		}
		$progress->finish();

		\WP_CLI::success(
			sprintf(
				'Resized %d file(s), %d failed. %s -> %s (saved %s).',
				$done,
				$failed,
				size_format( $before ),
				size_format( $after ),
				size_format( max( 0, $before - $after ) )
			)
		);
	}

	/**
	 * List, or delete, files that no entry or draft references.
	 *
	 * Deletion is permanent. Back up the gravity_forms uploads directory first;
	 * this command does not back anything up.
	 *
	 * ## OPTIONS
	 *
	 * [--delete]
	 * : Delete the orphans rather than listing them.
	 *
	 * [--confirm]
	 * : Required alongside --delete. Two flags, so deletion cannot be a typo.
	 *
	 * ## EXAMPLES
	 *
	 *     wp fwgir orphans
	 *     wp fwgir orphans --delete --confirm
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function orphans( $args, $assoc_args ) {
		$delete  = isset( $assoc_args['delete'] );
		$confirm = isset( $assoc_args['confirm'] );

		$files = Scanner::orphans();
		if ( empty( $files ) ) {
			\WP_CLI::success( 'No orphaned files.' );
			return;
		}

		$bytes = Scanner::total_bytes( $files );

		if ( ! $delete ) {
			\WP_CLI::log( sprintf( '%d orphaned file(s), %s:', count( $files ), size_format( $bytes ) ) );
			foreach ( array_slice( $files, 0, 20 ) as $path ) {
				\WP_CLI::log( sprintf( '  %s  %s', size_format( (int) filesize( $path ) ), $path ) );
			}
			if ( count( $files ) > 20 ) {
				\WP_CLI::log( sprintf( '  ... and %d more', count( $files ) - 20 ) );
			}
			\WP_CLI::log( '' );
			\WP_CLI::log( 'Add --delete --confirm to remove them. Back up first: this is permanent.' );
			return;
		}

		if ( ! $confirm ) {
			\WP_CLI::error( 'Refusing to delete without --confirm. Back up the gravity_forms directory first — deletion is permanent.' );
		}

		$removed = 0;
		$failed  = 0;
		foreach ( $files as $path ) {
			if ( wp_delete_file( $path ) || ! file_exists( $path ) ) {
				++$removed;
			} else {
				++$failed;
				\WP_CLI::warning( sprintf( 'Could not delete %s', $path ) );
			}
		}

		\WP_CLI::success( sprintf( 'Deleted %d orphan(s), %s reclaimed. %d failed.', $removed, size_format( $bytes ), $failed ) );
	}
}
