<?php
/**
 * Plugin Name:       Fullworks Gravity Image Resize
 * Plugin URI:        https://github.com/alanef/plugin-fullworks-gravity-image-resize-project
 * Description:       Downscales images uploaded through Gravity Forms file-upload fields, which never pass through the WordPress media pipeline and so are missed by every media-library optimiser. Includes WP-CLI tools for the existing backlog.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Alan Fuller
 * Author URI:        https://fullworks.net
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fullworks-gravity-image-resize
 * Domain Path:       /languages
 *
 * @package FullworksGravityImageResize
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Load Composer autoloader.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

define( 'FWGIR_VERSION', '1.0.0' );
define( 'FWGIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'FWGIR_URL', plugin_dir_url( __FILE__ ) );
define( 'FWGIR_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load the classes.
 *
 * The Composer classmap covers a built release; this fallback keeps a plain git
 * checkout working without running `composer install` first.
 */
function fwgir_load_classes() {
	$classes = array( 'resizer', 'settings', 'uploads', 'scanner', 'cli' );
	foreach ( $classes as $class ) {
		$file = FWGIR_PATH . 'includes/class-' . $class . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
fwgir_load_classes();

/**
 * Wire everything up.
 *
 * Runs on plugins_loaded so Gravity Forms has had its chance to declare itself.
 */
function fwgir_init() {
	$settings = new \FullworksGravityImageResize\Settings();
	$settings->register();

	// The upload hook is pointless without Gravity Forms, but the settings screen
	// and CLI still make sense -- someone may be cleaning up after removing it.
	if ( class_exists( 'GFForms' ) ) {
		$uploads = new \FullworksGravityImageResize\Uploads();
		$uploads->register();
	}
}
add_action( 'plugins_loaded', 'fwgir_init' );

/**
 * Register the WP-CLI commands.
 */
function fwgir_register_cli() {
	if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\FullworksGravityImageResize\CLI' ) ) {
		\WP_CLI::add_command( 'fwgir', '\FullworksGravityImageResize\CLI' );
	}
}
add_action( 'cli_init', 'fwgir_register_cli' );

/**
 * Seed defaults on activation.
 */
function fwgir_activate() {
	if ( false === get_option( \FullworksGravityImageResize\Settings::OPTION, false ) ) {
		add_option(
			\FullworksGravityImageResize\Settings::OPTION,
			array(
				'max_dimension'    => \FullworksGravityImageResize\Resizer::DEFAULT_MAX_DIMENSION,
				'resize_on_upload' => 1,
			)
		);
	}
}
register_activation_hook( __FILE__, 'fwgir_activate' );

/**
 * Nothing to tear down on deactivation: no cron, no tables, no files of our own.
 */
function fwgir_deactivate() {
	// Intentionally empty.
}
register_deactivation_hook( __FILE__, 'fwgir_deactivate' );
