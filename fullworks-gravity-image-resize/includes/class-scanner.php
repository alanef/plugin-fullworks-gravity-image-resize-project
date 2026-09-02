<?php
/**
 * Finding what is on disk and what is still referenced.
 *
 * @package FullworksGravityImageResize
 */

namespace FullworksGravityImageResize;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Walks uploads/gravity_forms and reconciles it against the entries.
 */
class Scanner {

	/**
	 * Files Gravity Forms puts in its upload tree that are not submissions.
	 *
	 * @var array<int, string>
	 */
	const IGNORED_NAMES = array( '.htaccess', 'index.html', 'index.php', 'web.config' );

	/**
	 * Absolute path to the Gravity Forms upload root.
	 *
	 * @return string Empty when uploads are unavailable.
	 */
	public static function upload_root() {
		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			return '';
		}

		return trailingslashit( $uploads['basedir'] ) . 'gravity_forms';
	}

	/**
	 * Every candidate file beneath the upload root.
	 *
	 * The logs/ directory is Gravity Forms' own and is skipped: it is not
	 * submission data and deleting from it would be someone else's business.
	 *
	 * @return array<int, string> Absolute paths.
	 */
	public static function all_files() {
		$root = self::upload_root();
		if ( '' === $root || ! is_dir( $root ) ) {
			return array();
		}

		$found    = array();
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}
			$path = $file->getPathname();
			if ( in_array( $file->getFilename(), self::IGNORED_NAMES, true ) ) {
				continue;
			}
			if ( false !== strpos( $path, DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR ) ) {
				continue;
			}
			$found[] = $path;
		}

		sort( $found );

		return $found;
	}

	/**
	 * Absolute paths still referenced by an entry or an unfinished draft.
	 *
	 * Drafts matter: a part-completed submission has files on disk that no entry
	 * points at yet. Treating those as orphans would delete a customer's upload
	 * out from under them mid-form.
	 *
	 * @return array<string, true> Keyed by path for O(1) lookup.
	 */
	public static function referenced_paths() {
		global $wpdb;

		$referenced = array();
		$tables     = array(
			$wpdb->prefix . 'gf_entry_meta'        => 'meta_value',
			$wpdb->prefix . 'gf_draft_submissions' => 'submission',
			$wpdb->prefix . 'rg_lead_detail'       => 'value',
		);

		foreach ( $tables as $table => $column ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names cannot be bound; reconciling the filesystem is inherently uncached.
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $exists !== $table ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- see above.
			$rows = $wpdb->get_col( "SELECT `{$column}` FROM `{$table}` WHERE `{$column}` LIKE '%/gravity_forms/%'" );
			foreach ( (array) $rows as $row ) {
				foreach ( self::urls_in( (string) $row ) as $url ) {
					$path = Uploads::url_to_path( $url );
					if ( '' !== $path ) {
						$referenced[ $path ] = true;
					}
				}
			}
		}

		return $referenced;
	}

	/**
	 * Extract every gravity_forms upload URL appearing in a blob of text.
	 *
	 * Values arrive as bare URLs, JSON arrays, or a serialised draft containing
	 * both -- so this matches rather than parses, which is the robust choice when
	 * the container format is not guaranteed.
	 *
	 * @param string $text Arbitrary stored value.
	 * @return array<int, string>
	 */
	public static function urls_in( $text ) {
		if ( '' === $text ) {
			return array();
		}

		// Unescape JSON's escaped slashes so the match works on either form.
		$text = str_replace( '\\/', '/', $text );

		$matches = array();
		preg_match_all( '#https?://[^\s"\'\\\\<>]+/gravity_forms/[^\s"\'\\\\<>]+#i', $text, $matches );

		return isset( $matches[0] ) ? array_values( array_unique( $matches[0] ) ) : array();
	}

	/**
	 * Files on disk that no entry or draft references.
	 *
	 * @return array<int, string> Absolute paths.
	 */
	public static function orphans() {
		$referenced = self::referenced_paths();

		return array_values(
			array_filter(
				self::all_files(),
				static function ( $path ) use ( $referenced ) {
					return ! isset( $referenced[ $path ] );
				}
			)
		);
	}

	/**
	 * Referenced image files whose longest edge exceeds the limit.
	 *
	 * Orphans are excluded deliberately: resizing a file nothing points at is
	 * wasted work, and `orphans --delete` is the better answer for those.
	 *
	 * @param int $max Longest permitted edge.
	 * @return array<int, string> Absolute paths.
	 */
	public static function oversized( $max ) {
		$referenced = self::referenced_paths();

		return array_values(
			array_filter(
				self::all_files(),
				static function ( $path ) use ( $referenced, $max ) {
					return isset( $referenced[ $path ] ) && Resizer::needs_resize( $path, $max );
				}
			)
		);
	}

	/**
	 * Total bytes for a list of paths.
	 *
	 * @param array<int, string> $paths Absolute paths.
	 * @return int
	 */
	public static function total_bytes( array $paths ) {
		$bytes = 0;
		foreach ( $paths as $path ) {
			if ( is_file( $path ) ) {
				$bytes += (int) filesize( $path );
			}
		}

		return $bytes;
	}
}
