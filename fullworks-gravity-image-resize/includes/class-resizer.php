<?php
/**
 * Downscaling a single image file in place.
 *
 * @package FullworksGravityImageResize
 */

namespace FullworksGravityImageResize;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Resizes an image file in place, keeping its name, format and location.
 *
 * Gravity Forms writes upload-field files with move_uploaded_file() straight into
 * uploads/gravity_forms/. They never pass through wp_handle_upload(), never become
 * attachments, and never meet the image-size pipeline -- which is why media-library
 * optimisers such as Imsanity, Smush and ShortPixel do not touch them, and why a
 * 22MB phone photo is stored exactly as it arrived.
 *
 * Everything here is static and takes an explicit path so it can be exercised in
 * tests against a temporary file, with no forms, entries or uploads directory.
 */
class Resizer {

	/**
	 * Longest edge, in pixels, that a stored image may keep.
	 *
	 * 2000px is comfortably more than any theme displays while cutting a typical
	 * phone photo by an order of magnitude.
	 *
	 * @var int
	 */
	const DEFAULT_MAX_DIMENSION = 2000;

	/**
	 * Image types worth downscaling.
	 *
	 * PNG is deliberately included but is often a screenshot rather than a photo;
	 * it is still resized, just rarely a large win. Anything not listed -- PDFs,
	 * documents, SVG -- is left untouched.
	 *
	 * @var array<int, string>
	 */
	const RESIZABLE_MIMES = array( 'image/jpeg', 'image/png', 'image/webp' );

	/**
	 * Can this file be resized at all?
	 *
	 * @param string $path Absolute path to the file.
	 * @return bool
	 */
	public static function is_resizable( $path ) {
		if ( ! is_string( $path ) || '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
			return false;
		}

		$size = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a non-image returns false, which is the answer we want.
		if ( false === $size || empty( $size['mime'] ) ) {
			return false;
		}

		return in_array( $size['mime'], self::RESIZABLE_MIMES, true );
	}

	/**
	 * Current dimensions of an image.
	 *
	 * @param string $path Absolute path to the file.
	 * @return array{width:int,height:int}|null Null when the file is not an image.
	 */
	public static function dimensions( $path ) {
		$size = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- see is_resizable().
		if ( false === $size ) {
			return null;
		}

		return array(
			'width'  => (int) $size[0],
			'height' => (int) $size[1],
		);
	}

	/**
	 * Would resizing this file actually change it?
	 *
	 * @param string $path Absolute path to the file.
	 * @param int    $max  Longest permitted edge in pixels.
	 * @return bool
	 */
	public static function needs_resize( $path, $max ) {
		if ( ! self::is_resizable( $path ) ) {
			return false;
		}

		$dims = self::dimensions( $path );
		if ( null === $dims ) {
			return false;
		}

		return ( $dims['width'] > $max || $dims['height'] > $max );
	}

	/**
	 * Downscale an image in place.
	 *
	 * Writes back to the same path with the same mime type, so the URL already
	 * recorded against the Gravity Forms entry keeps working. This is lossy and
	 * irreversible: the original bytes are gone once it returns.
	 *
	 * @param string $path Absolute path to the file.
	 * @param int    $max  Longest permitted edge in pixels.
	 * @return array{resized:bool,bytes_before:int,bytes_after:int,error:string} Error is '' on success.
	 */
	public static function resize( $path, $max ) {
		$result = array(
			'resized'      => false,
			'bytes_before' => 0,
			'bytes_after'  => 0,
			'error'        => '',
		);

		$max = (int) $max;
		if ( $max < 1 ) {
			$result['error'] = 'Maximum dimension must be a positive integer.';
			return $result;
		}

		if ( ! self::needs_resize( $path, $max ) ) {
			// Not an image, or already small enough. Not an error: nothing to do.
			$result['bytes_before'] = is_file( $path ) ? (int) filesize( $path ) : 0;
			$result['bytes_after']  = $result['bytes_before'];
			return $result;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- WP_Image_Editor::save() writes with direct filesystem calls itself, so checking via WP_Filesystem would test a different thing from what is about to happen.
		if ( ! is_writable( $path ) ) {
			$result['error'] = 'File is not writable.';
			return $result;
		}

		$result['bytes_before'] = (int) filesize( $path );

		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			$result['error'] = $editor->get_error_message();
			return $result;
		}

		// false = fit inside the box rather than crop, so nothing is lost from
		// the edges of the photo.
		$resized = $editor->resize( $max, $max, false );
		if ( is_wp_error( $resized ) ) {
			$result['error'] = $resized->get_error_message();
			return $result;
		}

		// Save back over the same path, and pass the original mime so the editor
		// cannot decide to write a .jpg beside our .png and leave both.
		$dims  = self::dimensions( $path );
		$mime  = '';
		$probe = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- validated above.
		if ( is_array( $probe ) && ! empty( $probe['mime'] ) ) {
			$mime = $probe['mime'];
		}
		unset( $dims );

		$saved = $editor->save( $path, $mime );
		if ( is_wp_error( $saved ) ) {
			$result['error'] = $saved->get_error_message();
			return $result;
		}

		clearstatcache( true, $path );
		$result['resized']     = true;
		$result['bytes_after'] = (int) filesize( $path );

		return $result;
	}
}
