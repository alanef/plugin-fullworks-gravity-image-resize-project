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

		$uploads = wp_get_upload_dir();
		$basedir = empty( $uploads['basedir'] ) ? '' : realpath( $uploads['basedir'] );
		if ( ! $basedir ) {
			return $referenced;
		}

		foreach ( $tables as $table => $column ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- table names cannot be bound; reconciling the filesystem is inherently uncached.
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $exists !== $table ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- see above.
			$rows = $wpdb->get_col( "SELECT `{$column}` FROM `{$table}` WHERE `{$column}` LIKE '%/gravity_forms/%'" );
			foreach ( (array) $rows as $row ) {
				foreach ( self::references_in( (string) $row ) as $relative ) {
					$path = realpath( trailingslashit( $basedir ) . $relative );
					if ( false !== $path && 0 === strpos( $path, $basedir ) ) {
						$referenced[ $path ] = true;
					}
				}
			}
		}

		return $referenced;
	}

	/**
	 * Extract every gravity_forms reference appearing in a stored value.
	 *
	 * The formats in the wild, all seen on one real site:
	 *
	 * 1. A bare URL.
	 * 2. A JSON array of URLs (multi-file fields).
	 * 3. A serialised array where the DIRECTORY and the FILENAME are separate
	 *    keys, and the path is an absolute one from the previous host:
	 *
	 *        a:3:{s:4:"path";s:103:"/home/site/public_html/.../gravity_forms/4-abc/2022/02/";
	 *             s:3:"url";s:98:"http://example.test/.../gravity_forms/4-abc/2022/02/";
	 *             s:9:"file_name";s:41:"CBE114EE-BDCF-40CB-AD10-2580E32E8315.jpeg";}
	 *
	 * Form 3 is why this parses rather than pattern-matches: a regex over the
	 * whole blob yields the directory, which exists, so the reference resolves to
	 * a folder and every actual file still looks orphaned.
	 *
	 * Everything is reduced to a path relative to the uploads directory, so a
	 * change of domain, scheme or document root cannot break matching.
	 *
	 * @param string $text Arbitrary stored value.
	 * @return array<int, string> e.g. "gravity_forms/4-abc/2022/02/photo.jpeg".
	 */
	public static function references_in( $text ) {
		if ( '' === $text ) {
			return array();
		}

		$out = array();

		// Never instantiate objects from stored data.
		$data = false;
		if ( is_serialized( $text ) ) {
			// A corrupt or truncated row must not emit a warning or abort a scan of
			// thousands of files, so failure is suppressed and handled as "not an
			// array" below. allowed_classes=false prevents object instantiation.
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize,WordPress.PHP.NoSilencedErrors.Discouraged
			$data = @unserialize( $text, array( 'allowed_classes' => false ) );
		}
		if ( is_array( $data ) ) {
			self::collect_from_array( $data, $out );
		}

		// JSON array of URLs, or a bare URL/path.
		$decoded = json_decode( $text, true );
		$strings = is_array( $decoded ) ? $decoded : array( $text );
		foreach ( $strings as $candidate ) {
			if ( is_string( $candidate ) ) {
				self::collect_from_string( $candidate, $out );
			}
		}

		return array_keys( $out );
	}

	/**
	 * Walk a decoded array looking for directory + file_name pairs.
	 *
	 * @param array<mixed>        $data Decoded value.
	 * @param array<string, true> $out  Accumulator, keyed by relative path.
	 */
	private static function collect_from_array( array $data, array &$out ) {
		$dir  = '';
		$name = '';
		foreach ( array( 'path', 'url' ) as $key ) {
			if ( ! empty( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
				$dir = $data[ $key ];
				break;
			}
		}
		if ( ! empty( $data['file_name'] ) && is_string( $data['file_name'] ) ) {
			$name = $data['file_name'];
		}

		if ( '' !== $dir && '' !== $name ) {
			self::collect_from_string( trailingslashit( $dir ) . $name, $out );
		}
		// A directory without a file_name is deliberately NOT recorded. Recording
		// it would resolve to a folder that exists, making the reference look
		// satisfied while every actual file in it still counted as an orphan.

		foreach ( $data as $value ) {
			if ( is_array( $value ) ) {
				self::collect_from_array( $value, $out );
			} elseif ( is_string( $value ) ) {
				self::collect_from_string( $value, $out );
			}
		}
	}

	/**
	 * Reduce a URL or path to its uploads-relative form and record it.
	 *
	 * @param string              $text Candidate URL or path.
	 * @param array<string, true> $out  Accumulator.
	 */
	private static function collect_from_string( $text, array &$out ) {
		$text    = str_replace( '\\/', '/', $text );
		$matches = array();
		preg_match_all( '#gravity_forms/[^\s"\'\\\\<>;,)]+#i', $text, $matches );

		foreach ( $matches[0] as $match ) {
			$match = rtrim( $match, '.' );
			if ( false !== strpos( $match, '..' ) ) {
				continue;
			}
			// A bare directory is not a file, and treating it as one is how the
			// first version resolved references to folders while every file
			// still looked orphaned.
			if ( '/' === substr( $match, -1 ) ) {
				continue;
			}
			$out[ rtrim( $match, '/' ) ] = true;
		}
	}

	/**
	 * Sanity-check orphan detection before anything acts on it.
	 *
	 * If a site holds entries but not one file reference resolves, the likely
	 * explanation is that detection has failed -- a storage format we do not
	 * recognise, a moved uploads directory -- not that every single file is
	 * genuinely unreferenced. Both look identical from the outside, and one of
	 * them means deleting every customer upload.
	 *
	 * Seen for real: a site migrated from cPanel stored absolute paths from the
	 * old host inside a serialised array, so URL matching found nothing and all
	 * 763 files were reported as orphans.
	 *
	 * @return array{entries:int,referenced:int,files:int,sane:bool}
	 */
	public static function reference_health() {
		global $wpdb;

		$entries = 0;
		foreach ( array( $wpdb->prefix . 'gf_entry', $wpdb->prefix . 'rg_lead' ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$entries += (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
			}
		}

		$referenced = count( self::referenced_paths() );
		$files      = count( self::all_files() );

		return array(
			'entries'    => $entries,
			'referenced' => $referenced,
			'files'      => $files,
			// No entries at all is a legitimately empty site. Entries but no
			// resolved references is a detection failure.
			'sane'       => ( 0 === $entries || $referenced > 0 ),
		);
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
