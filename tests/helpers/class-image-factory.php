<?php
/**
 * Builds throwaway image files for tests.
 *
 * @package FullworksGravityImageResize
 */

/**
 * Creates real image files on disk, because the code under test reads real
 * headers with getimagesize() and hands real paths to WP_Image_Editor. A mock
 * would only test the mock.
 */
class FWGIR_Image_Factory {

	/**
	 * Directories created, for cleanup.
	 *
	 * @var array<int, string>
	 */
	private static $dirs = array();

	/**
	 * Write an image of the given size.
	 *
	 * @param int    $width  Width in pixels.
	 * @param int    $height Height in pixels.
	 * @param string $ext    One of jpg, png, webp.
	 * @param string $dir    Optional target directory.
	 * @return string Absolute path.
	 */
	public static function create( $width, $height, $ext = 'jpg', $dir = '' ) {
		if ( '' === $dir ) {
			$dir = self::temp_dir();
		}
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0777, true );
		}

		$path  = trailingslashit( $dir ) . uniqid( 'fwgir-', true ) . '.' . $ext;
		$image = imagecreatetruecolor( $width, $height );

		// Noise rather than flat colour: a solid image compresses to almost
		// nothing, which would make byte-size assertions meaningless.
		for ( $i = 0; $i < 400; $i++ ) {
			$colour = imagecolorallocate( $image, wp_rand( 0, 255 ), wp_rand( 0, 255 ), wp_rand( 0, 255 ) );
			imagefilledrectangle( $image, wp_rand( 0, $width ), wp_rand( 0, $height ), wp_rand( 0, $width ), wp_rand( 0, $height ), $colour );
		}

		switch ( $ext ) {
			case 'png':
				imagepng( $image, $path );
				break;
			case 'webp':
				imagewebp( $image, $path );
				break;
			default:
				imagejpeg( $image, $path, 92 );
		}
		imagedestroy( $image );

		return $path;
	}

	/**
	 * Write a file that is not an image at all.
	 *
	 * @param string $ext Extension to use.
	 * @param string $dir Optional target directory.
	 * @return string Absolute path.
	 */
	public static function create_non_image( $ext = 'pdf', $dir = '' ) {
		if ( '' === $dir ) {
			$dir = self::temp_dir();
		}
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0777, true );
		}
		$path = trailingslashit( $dir ) . uniqid( 'fwgir-', true ) . '.' . $ext;
		file_put_contents( $path, "%PDF-1.4\nnot really a pdf\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.

		return $path;
	}

	/**
	 * A fresh temp directory, remembered for cleanup.
	 *
	 * @return string
	 */
	public static function temp_dir() {
		$dir = trailingslashit( sys_get_temp_dir() ) . uniqid( 'fwgir-test-', true );
		mkdir( $dir, 0777, true );
		self::$dirs[] = $dir;

		return $dir;
	}

	/**
	 * Remove everything created.
	 */
	public static function cleanup() {
		foreach ( self::$dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			$items = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::CHILD_FIRST
			);
			foreach ( $items as $item ) {
				if ( $item->isDir() ) {
					rmdir( $item->getPathname() );
				} else {
					unlink( $item->getPathname() );
				}
			}
			rmdir( $dir );
		}
		self::$dirs = array();
	}
}
