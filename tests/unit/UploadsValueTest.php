<?php
/**
 * Reading Gravity Forms stored values, and resolving them to paths.
 *
 * @package FullworksGravityImageResize
 */

use FullworksGravityImageResize\Uploads;

/**
 * url_to_path() is the security boundary: it decides which files the resizer is
 * allowed to touch, so the negative cases matter more than the positive one.
 */
class UploadsValueTest extends WP_UnitTestCase {

	/**
	 * A single-file field stores a bare URL.
	 */
	public function test_single_file_value() {
		$this->assertSame(
			array( 'https://example.com/wp-content/uploads/gravity_forms/1-abc/2026/01/a.jpg' ),
			Uploads::urls_from_value( 'https://example.com/wp-content/uploads/gravity_forms/1-abc/2026/01/a.jpg' )
		);
	}

	/**
	 * A multi-file field stores a JSON array.
	 */
	public function test_multi_file_value() {
		$json = wp_json_encode(
			array(
				'https://example.com/wp-content/uploads/gravity_forms/1-abc/2026/01/a.jpg',
				'https://example.com/wp-content/uploads/gravity_forms/1-abc/2026/01/b.jpg',
			)
		);
		$this->assertCount( 2, Uploads::urls_from_value( $json ) );
	}

	/**
	 * Empty and malformed values yield nothing rather than a bogus entry.
	 */
	public function test_empty_values() {
		$this->assertSame( array(), Uploads::urls_from_value( '' ) );
		$this->assertSame( array(), Uploads::urls_from_value( '   ' ) );
		$this->assertSame( array(), Uploads::urls_from_value( null ) );
		$this->assertSame( array(), Uploads::urls_from_value( wp_json_encode( array() ) ) );
	}

	/**
	 * A URL inside uploads resolves to a real path.
	 */
	public function test_url_to_path_inside_uploads() {
		$uploads = wp_get_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'gravity_forms/1-test';
		wp_mkdir_p( $dir );
		$file = $dir . '/photo.jpg';
		file_put_contents( $file, 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fixture.

		$url = trailingslashit( $uploads['baseurl'] ) . 'gravity_forms/1-test/photo.jpg';
		$this->assertSame( realpath( $file ), Uploads::url_to_path( $url ) );

		unlink( $file );
	}

	/**
	 * http and https resolve identically, since the stored value may predate an
	 * SSL migration.
	 */
	public function test_url_to_path_ignores_scheme() {
		$uploads = wp_get_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'gravity_forms/1-test';
		wp_mkdir_p( $dir );
		$file = $dir . '/scheme.jpg';
		file_put_contents( $file, 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fixture.

		$https = trailingslashit( $uploads['baseurl'] ) . 'gravity_forms/1-test/scheme.jpg';
		$http  = preg_replace( '#^https:#', 'http:', $https );

		$this->assertSame( realpath( $file ), Uploads::url_to_path( $http ) );
		unlink( $file );
	}

	/**
	 * Anything outside the uploads directory is refused, so a tampered entry
	 * value cannot aim the resizer at wp-config.php.
	 */
	public function test_url_to_path_refuses_outside_uploads() {
		$this->assertSame( '', Uploads::url_to_path( 'https://example.com/etc/passwd' ) );
		$this->assertSame( '', Uploads::url_to_path( 'https://evil.test/wp-content/uploads/x.jpg' ) );
		$this->assertSame( '', Uploads::url_to_path( '' ) );
	}

	/**
	 * Traversal is refused even when it starts from a legitimate prefix.
	 */
	public function test_url_to_path_refuses_traversal() {
		$uploads = wp_get_upload_dir();
		$url     = trailingslashit( $uploads['baseurl'] ) . '../../../wp-config.php';
		$this->assertSame( '', Uploads::url_to_path( $url ) );
	}
}
