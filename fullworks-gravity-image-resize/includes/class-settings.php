<?php
/**
 * Settings storage and admin screen.
 *
 * @package FullworksGravityImageResize
 */

namespace FullworksGravityImageResize;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * One option, one screen.
 */
class Settings {

	/**
	 * Option name holding the settings array.
	 *
	 * @var string
	 */
	const OPTION = 'fwgir_settings';

	/**
	 * Smallest and largest max-dimension we will accept.
	 *
	 * Below 200 the image stops being useful as evidence; above 10000 nothing is
	 * being downscaled in practice and the setting is doing no work.
	 *
	 * @var int
	 */
	const MIN_DIMENSION = 200;
	const MAX_DIMENSION = 10000;

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Longest permitted edge, in pixels.
	 *
	 * @return int
	 */
	public static function max_dimension() {
		$settings = get_option( self::OPTION, array() );
		$max      = isset( $settings['max_dimension'] ) ? (int) $settings['max_dimension'] : Resizer::DEFAULT_MAX_DIMENSION;

		/**
		 * Filter the longest permitted edge for Gravity Forms uploads.
		 *
		 * @param int $max Longest edge in pixels.
		 */
		return (int) apply_filters( 'fwgir_max_dimension', self::clamp( $max ) );
	}

	/**
	 * Is resizing on upload switched on?
	 *
	 * @return bool
	 */
	public static function resize_on_upload() {
		$settings = get_option( self::OPTION, array() );
		$on       = isset( $settings['resize_on_upload'] ) ? (bool) $settings['resize_on_upload'] : true;

		return (bool) apply_filters( 'fwgir_resize_on_upload', $on );
	}

	/**
	 * Force a dimension into the supported range.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function clamp( $value ) {
		$value = (int) $value;
		if ( $value < self::MIN_DIMENSION ) {
			return self::MIN_DIMENSION;
		}
		if ( $value > self::MAX_DIMENSION ) {
			return self::MAX_DIMENSION;
		}

		return $value;
	}

	/**
	 * Sanitize the whole settings array.
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();

		return array(
			'max_dimension'    => self::clamp( isset( $input['max_dimension'] ) ? $input['max_dimension'] : Resizer::DEFAULT_MAX_DIMENSION ),
			'resize_on_upload' => ! empty( $input['resize_on_upload'] ) ? 1 : 0,
		);
	}

	/**
	 * Add the settings page.
	 */
	public function add_page() {
		add_options_page(
			__( 'Gravity Image Resize', 'fullworks-gravity-image-resize' ),
			__( 'Gravity Image Resize', 'fullworks-gravity-image-resize' ),
			'manage_options',
			'fwgir',
			array( $this, 'render' )
		);
	}

	/**
	 * Register the setting and its fields.
	 */
	public function register_settings() {
		register_setting(
			'fwgir',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(
					'max_dimension'    => Resizer::DEFAULT_MAX_DIMENSION,
					'resize_on_upload' => 1,
				),
			)
		);
	}

	/**
	 * Render the settings screen.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$max = self::max_dimension();
		$on  = self::resize_on_upload();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Gravity Image Resize', 'fullworks-gravity-image-resize' ); ?></h1>
			<p>
				<?php esc_html_e( 'Gravity Forms stores file-upload fields exactly as they arrive: a modern phone photo can be 20MB or more. Media-library optimisers never see these files, because Gravity Forms writes them directly rather than through the WordPress upload pipeline.', 'fullworks-gravity-image-resize' ); ?>
			</p>
			<form action="options.php" method="post">
				<?php settings_fields( 'fwgir' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="fwgir-max"><?php esc_html_e( 'Maximum dimension', 'fullworks-gravity-image-resize' ); ?></label>
						</th>
						<td>
							<input name="<?php echo esc_attr( self::OPTION ); ?>[max_dimension]" id="fwgir-max"
								type="number" class="small-text" min="<?php echo esc_attr( (string) self::MIN_DIMENSION ); ?>"
								max="<?php echo esc_attr( (string) self::MAX_DIMENSION ); ?>"
								value="<?php echo esc_attr( (string) $max ); ?>" />
							<span><?php esc_html_e( 'pixels', 'fullworks-gravity-image-resize' ); ?></span>
							<p class="description">
								<?php esc_html_e( 'Images with a longer edge than this are scaled down to fit, keeping their aspect ratio. Nothing is cropped.', 'fullworks-gravity-image-resize' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Resize on upload', 'fullworks-gravity-image-resize' ); ?></th>
						<td>
							<label>
								<input name="<?php echo esc_attr( self::OPTION ); ?>[resize_on_upload]" type="checkbox"
									value="1" <?php checked( $on ); ?> />
								<?php esc_html_e( 'Resize images as forms are submitted', 'fullworks-gravity-image-resize' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Existing files', 'fullworks-gravity-image-resize' ); ?></h2>
			<p>
				<?php esc_html_e( 'Files already uploaded are handled from WP-CLI, so that every destructive step is deliberate and can be previewed first:', 'fullworks-gravity-image-resize' ); ?>
			</p>
			<pre>wp fwgir scan
wp fwgir resize            <?php esc_html_e( '# preview', 'fullworks-gravity-image-resize' ); ?>
wp fwgir resize --execute
wp fwgir orphans
wp fwgir orphans --delete --confirm</pre>
			<p>
				<strong><?php esc_html_e( 'Both resizing and deleting are irreversible. Back up the gravity_forms uploads directory yourself before running either — this plugin does not do it for you.', 'fullworks-gravity-image-resize' ); ?></strong>
			</p>
		</div>
		<?php
	}
}
