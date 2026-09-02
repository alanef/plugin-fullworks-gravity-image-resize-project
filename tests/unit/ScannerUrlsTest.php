<?php
/**
 * Pulling upload URLs out of arbitrary stored blobs.
 *
 * @package FullworksGravityImageResize
 */

use FullworksGravityImageResize\Scanner;

/**
 * Entry values, JSON arrays and serialised drafts all hold URLs differently, so
 * this matches rather than parses.
 */
class ScannerUrlsTest extends WP_UnitTestCase {

	/**
	 * A bare URL is found, reduced to its uploads-relative part.
	 */
	public function test_finds_bare_url() {
		$found = Scanner::references_in( 'https://example.com/wp-content/uploads/gravity_forms/1-a/2026/01/x.jpg' );
		$this->assertSame( array( 'gravity_forms/1-a/2026/01/x.jpg' ), $found );
	}

	/**
	 * The real-world format, and the reason this parses instead of pattern
	 * matching: Gravity Forms stores the DIRECTORY and the FILENAME as separate
	 * keys, with an absolute path from whichever host the site used to live on.
	 *
	 * A regex over the whole blob yields the directory. The directory exists, so
	 * the reference "resolves" to a folder while every actual file still looks
	 * orphaned -- which is how the first version of this plugin offered 763
	 * customer uploads for deletion.
	 */
	public function test_finds_serialised_directory_plus_file_name() {
		// Built with serialize() so the declared lengths are correct by
		// construction; a hand-written fixture with wrong s:NNN: lengths tests
		// the error path rather than the format.
		$blob = serialize( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- constructing a realistic fixture.
			array(
				'path'      => '/home/lawncrew/public_html/wp-content/uploads/gravity_forms/4-abc/2022/02/',
				'url'       => 'http://localhost:8971/wp-content/uploads/gravity_forms/4-abc/2022/02/',
				'file_name' => 'CBE114EE-BDCF-40CB-AD10-2580E32E8315.jpeg',
			)
		);

		$this->assertSame(
			array( 'gravity_forms/4-abc/2022/02/CBE114EE-BDCF-40CB-AD10-2580E32E8315.jpeg' ),
			Scanner::references_in( $blob )
		);
	}

	/**
	 * A bare directory must never be recorded as a file reference.
	 */
	public function test_directory_alone_is_not_a_reference() {
		$blob = serialize( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- constructing a realistic fixture.
			array( 'path' => '/home/x/public_html/wp-content/uploads/gravity_forms/4-abc/2022/02/' )
		);
		$this->assertSame( array(), Scanner::references_in( $blob ) );
	}

	/**
	 * A stored URL from the pre-migration domain still resolves, because only
	 * the part from gravity_forms/ onwards is used.
	 */
	public function test_domain_change_does_not_break_matching() {
		$found = Scanner::references_in( 'https://old-domain.example/wp-content/uploads/gravity_forms/1-a/x.jpg' );
		$this->assertSame( array( 'gravity_forms/1-a/x.jpg' ), $found );
	}

	/**
	 * Traversal is never returned as a reference.
	 */
	public function test_rejects_traversal() {
		$this->assertSame( array(), Scanner::references_in( 'gravity_forms/../../wp-config.php' ) );
	}

	/**
	 * JSON escapes its slashes; both forms must match.
	 */
	public function test_finds_urls_in_escaped_json() {
		$blob  = '{"3":"https:\/\/example.com\/wp-content\/uploads\/gravity_forms\/1-a\/2026\/01\/x.jpg"}';
		$found = Scanner::references_in( $blob );
		$this->assertSame( array( 'gravity_forms/1-a/2026/01/x.jpg' ), $found );
	}

	/**
	 * Several URLs in one blob are all returned, without duplicates.
	 */
	public function test_finds_multiple_and_dedupes() {
		$blob = 'a https://e.com/wp-content/uploads/gravity_forms/1/a.jpg b '
			. 'https://e.com/wp-content/uploads/gravity_forms/1/b.jpg c '
			. 'https://e.com/wp-content/uploads/gravity_forms/1/a.jpg';
		$this->assertCount( 2, Scanner::references_in( $blob ) );
	}

	/**
	 * A corrupt serialised row is skipped rather than aborting the scan.
	 */
	public function test_corrupt_serialised_value_is_survivable() {
		$this->assertSame( array(), Scanner::references_in( 'a:3:{s:4:"path";s:999:"truncated' ) );
	}

	/**
	 * Unrelated content yields nothing.
	 */
	public function test_ignores_other_urls() {
		$this->assertSame( array(), Scanner::references_in( 'https://example.com/wp-content/uploads/2026/01/other.jpg' ) );
		$this->assertSame( array(), Scanner::references_in( 'no urls here' ) );
		$this->assertSame( array(), Scanner::references_in( '' ) );
	}
}
