<?php
/**
 * Reconciling files on disk against entries.
 *
 * @package FullworksGravityImageResize
 */

use FullworksGravityImageResize\Scanner;

/**
 * Orphan detection decides what `orphans --delete` destroys, so the test that
 * matters most is that a referenced file is never reported as an orphan.
 */
class ScannerTest extends WP_UnitTestCase {

	/**
	 * Files created under uploads, for cleanup.
	 *
	 * @var array<int, string>
	 */
	private $created = array();

	/**
	 * Remove created files.
	 */
	public function tearDown(): void {
		foreach ( $this->created as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
		$this->created = array();
		FWGIR_Image_Factory::cleanup();
		parent::tearDown();
	}

	/**
	 * Put an image inside the real gravity_forms upload tree.
	 *
	 * @param int $w Width.
	 * @param int $h Height.
	 * @return string Absolute path.
	 */
	private function make_upload( $w = 3000, $h = 2000 ) {
		$uploads = wp_get_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'gravity_forms/1-test/2026/01';
		wp_mkdir_p( $dir );
		$path            = FWGIR_Image_Factory::create( $w, $h, 'jpg', $dir );
		$this->created[] = $path;

		return $path;
	}

	/**
	 * The scanner sees files in the upload tree.
	 */
	public function test_all_files_finds_uploads() {
		$path = $this->make_upload();
		$this->assertContains( $path, Scanner::all_files() );
	}

	/**
	 * Gravity Forms' own protective files are not submission data and must never
	 * be offered for deletion.
	 */
	public function test_ignores_guard_files() {
		$uploads = wp_get_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'gravity_forms';
		wp_mkdir_p( $dir );
		$htaccess = $dir . '/.htaccess';
		file_put_contents( $htaccess, "deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fixture.
		$this->created[] = $htaccess;

		$this->assertNotContains( $htaccess, Scanner::all_files() );
	}

	/**
	 * With no entries at all, every file is an orphan — and nothing throws.
	 */
	public function test_unreferenced_file_is_an_orphan() {
		$path = $this->make_upload();
		$this->assertContains( $path, Scanner::orphans() );
	}

	/**
	 * The critical case: a file referenced by an entry must never be listed as
	 * an orphan, because that is what deletion acts on.
	 */
	public function test_referenced_file_is_not_an_orphan() {
		global $wpdb;
		$path = $this->make_upload();

		$table = $wpdb->prefix . 'gf_entry_meta';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$table}` (id BIGINT AUTO_INCREMENT PRIMARY KEY, meta_value LONGTEXT)" );

		$uploads = wp_get_upload_dir();
		$url     = str_replace( $uploads['basedir'], $uploads['baseurl'], $path );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert( $table, array( 'meta_value' => $url ) );

		$this->assertNotContains( $path, Scanner::orphans(), 'A referenced file must never be treated as an orphan.' );
		$this->assertArrayHasKey( $path, Scanner::referenced_paths() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
	}

	/**
	 * oversized() reports only referenced files: resizing an orphan is wasted
	 * work when deleting it is the better answer.
	 */
	public function test_oversized_excludes_orphans() {
		$path = $this->make_upload( 4000, 3000 );
		$this->assertNotContains( $path, Scanner::oversized( 2000 ), 'An orphan should not be queued for resizing.' );
	}

	/**
	 * The guard that stops a detection failure becoming a mass deletion: entries
	 * present but nothing resolved must report as not sane.
	 */
	public function test_reference_health_flags_detection_failure() {
		global $wpdb;
		$this->make_upload();

		$table = $wpdb->prefix . 'gf_entry';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$table}` (id BIGINT AUTO_INCREMENT PRIMARY KEY, form_id BIGINT)" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert( $table, array( 'form_id' => 1 ) );

		$health = Scanner::reference_health();
		$this->assertGreaterThan( 0, $health['entries'] );
		$this->assertSame( 0, $health['referenced'] );
		$this->assertFalse( $health['sane'], 'Entries with no resolved references must be flagged as unsafe.' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
	}

	/**
	 * A site with no entries at all is legitimately empty, not broken.
	 */
	public function test_reference_health_empty_site_is_sane() {
		$health = Scanner::reference_health();
		$this->assertSame( 0, $health['entries'] );
		$this->assertTrue( $health['sane'] );
	}

	/**
	 * Byte totals are reported for reporting purposes.
	 */
	public function test_total_bytes() {
		$path = $this->make_upload( 1200, 900 );
		$this->assertSame( (int) filesize( $path ), Scanner::total_bytes( array( $path ) ) );
		$this->assertSame( 0, Scanner::total_bytes( array( '/no/such/file' ) ) );
	}
}
