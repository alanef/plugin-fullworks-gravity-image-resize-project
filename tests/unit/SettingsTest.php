<?php
/**
 * Settings validation.
 *
 * @package FullworksGravityImageResize
 */

use FullworksGravityImageResize\Settings;
use FullworksGravityImageResize\Resizer;

/**
 * The setting is user input, so the guard rails matter more than the happy path.
 */
class SettingsTest extends WP_UnitTestCase {

	/**
	 * Values inside the permitted range survive untouched.
	 */
	public function test_clamp_leaves_sane_values_alone() {
		$this->assertSame( 2000, Settings::clamp( 2000 ) );
		$this->assertSame( 800, Settings::clamp( 800 ) );
		$this->assertSame( Settings::MIN_DIMENSION, Settings::clamp( Settings::MIN_DIMENSION ) );
		$this->assertSame( Settings::MAX_DIMENSION, Settings::clamp( Settings::MAX_DIMENSION ) );
	}

	/**
	 * Anything outside the range is pulled back to the nearest bound rather than
	 * rejected, so a mistyped setting degrades instead of breaking uploads.
	 */
	public function test_clamp_bounds_extremes() {
		$this->assertSame( Settings::MIN_DIMENSION, Settings::clamp( 0 ) );
		$this->assertSame( Settings::MIN_DIMENSION, Settings::clamp( -5000 ) );
		$this->assertSame( Settings::MAX_DIMENSION, Settings::clamp( 999999 ) );
	}

	/**
	 * Junk must not become 0, which would disable resizing silently.
	 */
	public function test_clamp_handles_junk() {
		$this->assertSame( Settings::MIN_DIMENSION, Settings::clamp( 'nonsense' ) );
		$this->assertSame( Settings::MIN_DIMENSION, Settings::clamp( null ) );
		$this->assertSame( Settings::MIN_DIMENSION, Settings::clamp( array() ) );
	}

	/**
	 * Sanitize always returns both keys with usable types.
	 */
	public function test_sanitize_shape() {
		$out = Settings::sanitize( array( 'max_dimension' => '1500', 'resize_on_upload' => '1' ) );
		$this->assertSame( 1500, $out['max_dimension'] );
		$this->assertSame( 1, $out['resize_on_upload'] );

		$out = Settings::sanitize( 'not an array' );
		$this->assertSame( Resizer::DEFAULT_MAX_DIMENSION, $out['max_dimension'] );
		$this->assertSame( 0, $out['resize_on_upload'] );
	}

	/**
	 * An unset option yields the documented default, not zero.
	 */
	public function test_default_when_unset() {
		delete_option( Settings::OPTION );
		$this->assertSame( Resizer::DEFAULT_MAX_DIMENSION, Settings::max_dimension() );
		$this->assertTrue( Settings::resize_on_upload() );
	}

	/**
	 * The filter can override the stored setting.
	 */
	public function test_filter_overrides() {
		update_option( Settings::OPTION, array( 'max_dimension' => 2000 ) );
		add_filter( 'fwgir_max_dimension', static function () {
			return 640;
		} );
		$this->assertSame( 640, Settings::max_dimension() );
		remove_all_filters( 'fwgir_max_dimension' );
	}
}
