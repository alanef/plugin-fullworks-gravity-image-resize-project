<?php
/**
 * Remove the plugin's own option. Uploaded files are never touched.
 *
 * @package FullworksGravityImageResize
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

// Only our setting. Customers' uploads are their data, not ours to clean up.
delete_option( 'fwgir_settings' );
