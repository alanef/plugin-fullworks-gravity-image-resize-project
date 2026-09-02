<?php
/**
 * Resizing real files through WP_Image_Editor.
 *
 * @package FullworksGravityImageResize
 */

use FullworksGravityImageResize\Resizer;

/**
 * These use genuine image files rather than mocks: the whole point of the plugin
 * is what WP_Image_Editor does to bytes on disk.
 */
class ResizeTest extends WP_UnitTestCase {

	/**
	 * Remove anything the factory made.
	 */
	public function tearDown(): void {
		FWGIR_Image_Factory::cleanup();
		parent::tearDown();
	}

	/**
	 * An oversized image is scaled so its longest edge meets the limit, keeping
	 * aspect ratio and losing nothing to cropping.
	 */
	public function test_resizes_oversized_image() {
		$path = FWGIR_Image_Factory::create( 4000, 3000, 'jpg' );
		$this->assertTrue( Resizer::needs_resize( $path, 2000 ) );

		$result = Resizer::resize( $path, 2000 );

		$this->assertSame( '', $result['error'] );
		$this->assertTrue( $result['resized'] );

		$dims = Resizer::dimensions( $path );
		$this->assertSame( 2000, $dims['width'] );
		$this->assertSame( 1500, $dims['height'], 'Aspect ratio must be preserved.' );
		$this->assertLessThan( $result['bytes_before'], $result['bytes_after'] );
	}

	/**
	 * A portrait image is bounded by its height.
	 */
	public function test_resizes_portrait_by_longest_edge() {
		$path = FWGIR_Image_Factory::create( 1500, 3000, 'jpg' );
		Resizer::resize( $path, 1000 );

		$dims = Resizer::dimensions( $path );
		$this->assertSame( 1000, $dims['height'] );
		$this->assertSame( 500, $dims['width'] );
	}

	/**
	 * An image already within the limit is left byte-identical. Re-running the
	 * command must not quietly recompress every file each time.
	 */
	public function test_leaves_small_image_untouched() {
		$path   = FWGIR_Image_Factory::create( 800, 600, 'jpg' );
		$before = md5_file( $path );

		$result = Resizer::resize( $path, 2000 );

		$this->assertFalse( $result['resized'] );
		$this->assertSame( '', $result['error'] );
		$this->assertSame( $before, md5_file( $path ), 'A small image must not be rewritten.' );
	}

	/**
	 * Resizing is idempotent: a second pass changes nothing.
	 */
	public function test_resize_is_idempotent() {
		$path = FWGIR_Image_Factory::create( 3000, 2000, 'jpg' );
		Resizer::resize( $path, 1200 );
		$after_first = md5_file( $path );

		$second = Resizer::resize( $path, 1200 );

		$this->assertFalse( $second['resized'] );
		$this->assertSame( $after_first, md5_file( $path ) );
	}

	/**
	 * PNG keeps its format: saving as JPEG would leave a stale .png URL pointing
	 * at the wrong bytes.
	 */
	public function test_png_stays_png() {
		$path = FWGIR_Image_Factory::create( 3000, 3000, 'png' );
		Resizer::resize( $path, 1000 );

		$info = getimagesize( $path );
		$this->assertSame( 'image/png', $info['mime'] );
		$this->assertSame( 1000, $info[0] );
	}

	/**
	 * Non-images are ignored rather than corrupted. PDFs are common in these
	 * upload fields and must survive untouched.
	 */
	public function test_ignores_non_image() {
		$path   = FWGIR_Image_Factory::create_non_image( 'pdf' );
		$before = md5_file( $path );

		$this->assertFalse( Resizer::is_resizable( $path ) );
		$result = Resizer::resize( $path, 100 );

		$this->assertFalse( $result['resized'] );
		$this->assertSame( $before, md5_file( $path ) );
	}

	/**
	 * A file that does not exist is reported, not fatal.
	 */
	public function test_missing_file() {
		$result = Resizer::resize( '/no/such/file.jpg', 2000 );
		$this->assertFalse( $result['resized'] );
		$this->assertFalse( Resizer::is_resizable( '/no/such/file.jpg' ) );
	}

	/**
	 * A nonsensical limit is refused rather than producing a 0px image.
	 */
	public function test_rejects_bad_max() {
		$path   = FWGIR_Image_Factory::create( 3000, 3000, 'jpg' );
		$result = Resizer::resize( $path, 0 );

		$this->assertFalse( $result['resized'] );
		$this->assertNotSame( '', $result['error'] );
	}

	/**
	 * The saving is real and substantial, which is the entire justification for
	 * the plugin.
	 */
	public function test_saving_is_material() {
		$path   = FWGIR_Image_Factory::create( 4000, 3000, 'jpg' );
		$result = Resizer::resize( $path, 2000 );

		$this->assertTrue( $result['resized'] );
		$this->assertLessThan( $result['bytes_before'] * 0.6, $result['bytes_after'] );
	}
}
