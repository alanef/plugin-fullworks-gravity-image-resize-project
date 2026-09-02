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
	 * A bare URL is found.
	 */
	public function test_finds_bare_url() {
		$found = Scanner::urls_in( 'https://example.com/wp-content/uploads/gravity_forms/1-a/2026/01/x.jpg' );
		$this->assertCount( 1, $found );
	}

	/**
	 * JSON escapes its slashes; both forms must match.
	 */
	public function test_finds_urls_in_escaped_json() {
		$blob  = '{"3":"https:\/\/example.com\/wp-content\/uploads\/gravity_forms\/1-a\/2026\/01\/x.jpg"}';
		$found = Scanner::urls_in( $blob );
		$this->assertCount( 1, $found );
		$this->assertStringContainsString( '/gravity_forms/1-a/2026/01/x.jpg', $found[0] );
	}

	/**
	 * Several URLs in one blob are all returned, without duplicates.
	 */
	public function test_finds_multiple_and_dedupes() {
		$blob = 'a https://e.com/wp-content/uploads/gravity_forms/1/a.jpg b '
			. 'https://e.com/wp-content/uploads/gravity_forms/1/b.jpg c '
			. 'https://e.com/wp-content/uploads/gravity_forms/1/a.jpg';
		$this->assertCount( 2, Scanner::urls_in( $blob ) );
	}

	/**
	 * Unrelated content yields nothing.
	 */
	public function test_ignores_other_urls() {
		$this->assertSame( array(), Scanner::urls_in( 'https://example.com/wp-content/uploads/2026/01/other.jpg' ) );
		$this->assertSame( array(), Scanner::urls_in( 'no urls here' ) );
		$this->assertSame( array(), Scanner::urls_in( '' ) );
	}
}
