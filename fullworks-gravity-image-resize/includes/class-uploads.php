<?php
/**
 * Resizing images as Gravity Forms submissions arrive.
 *
 * @package FullworksGravityImageResize
 */

namespace FullworksGravityImageResize;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Hooks the submission and downscales anything oversized.
 *
 * The gform_after_submission hook runs once the entry exists and the files are
 * in their final location, so resizing in place leaves the stored URL correct.
 * Doing it earlier -- at gform_pre_submission, say -- would race the file move.
 */
class Uploads {

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'gform_after_submission', array( $this, 'handle_submission' ), 10, 2 );
	}

	/**
	 * Resize every image uploaded through this entry.
	 *
	 * @param array<string, mixed> $entry The entry.
	 * @param array<string, mixed> $form  The form.
	 */
	public function handle_submission( $entry, $form ) {
		if ( ! Settings::resize_on_upload() ) {
			return;
		}
		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return;
		}

		$max = Settings::max_dimension();

		foreach ( $form['fields'] as $field ) {
			if ( ! self::is_file_field( $field ) ) {
				continue;
			}

			$id    = isset( $field->id ) ? (string) $field->id : '';
			$value = ( '' !== $id && isset( $entry[ $id ] ) ) ? $entry[ $id ] : '';
			if ( '' === $value ) {
				continue;
			}

			foreach ( self::urls_from_value( $value ) as $url ) {
				$path = self::url_to_path( $url );
				if ( '' === $path ) {
					continue;
				}

				$result = Resizer::resize( $path, $max );

				/**
				 * Fires after an uploaded image has been considered for resizing.
				 *
				 * @param string               $path   Absolute path to the file.
				 * @param array<string, mixed> $result Outcome from Resizer::resize().
				 * @param array<string, mixed> $entry  The entry.
				 */
				do_action( 'fwgir_resized_upload', $path, $result, $entry );
			}
		}
	}

	/**
	 * Is this a Gravity Forms file-upload field?
	 *
	 * Post Image fields are deliberately excluded: those DO become media library
	 * attachments, so WordPress's own image sizes and any media optimiser already
	 * apply to them.
	 *
	 * @param mixed $field Field object.
	 * @return bool
	 */
	public static function is_file_field( $field ) {
		if ( is_object( $field ) && method_exists( $field, 'get_input_type' ) ) {
			return 'fileupload' === $field->get_input_type();
		}

		return is_object( $field ) && isset( $field->type ) && 'fileupload' === $field->type;
	}

	/**
	 * Pull the file URLs out of a stored field value.
	 *
	 * A single-file field stores a bare URL. A multi-file field stores a JSON
	 * array of them.
	 *
	 * @param mixed $value Stored entry value.
	 * @return array<int, string>
	 */
	public static function urls_from_value( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return array();
		}

		$value   = trim( $value );
		$decoded = json_decode( $value, true );
		if ( is_array( $decoded ) ) {
			return array_values(
				array_filter(
					array_map( 'strval', $decoded ),
					static function ( $url ) {
						return '' !== trim( $url );
					}
				)
			);
		}

		return array( $value );
	}

	/**
	 * Map an uploads URL to an absolute path.
	 *
	 * Returns '' for anything outside the uploads directory, so a mangled or
	 * hostile value cannot point the resizer at an arbitrary file.
	 *
	 * @param string $url Stored file URL.
	 * @return string Absolute path, or '' when it cannot be resolved safely.
	 */
	public static function url_to_path( $url ) {
		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
			return '';
		}

		// Compare without scheme so http/https and protocol-relative all match.
		$strip   = static function ( $in ) {
			return preg_replace( '#^https?:#', '', (string) $in );
		};
		$url_ns  = $strip( $url );
		$base_ns = $strip( $uploads['baseurl'] );

		if ( 0 !== strpos( $url_ns, $base_ns ) ) {
			return '';
		}

		$relative = ltrim( substr( $url_ns, strlen( $base_ns ) ), '/' );
		$relative = explode( '?', $relative )[0];
		if ( '' === $relative || false !== strpos( $relative, '..' ) ) {
			return '';
		}

		$path = trailingslashit( $uploads['basedir'] ) . $relative;
		$real = realpath( $path );
		$root = realpath( $uploads['basedir'] );
		if ( false === $real || false === $root || 0 !== strpos( $real, $root ) ) {
			return '';
		}

		return $real;
	}
}
