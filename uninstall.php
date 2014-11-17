<?php
/**
 * Tasks to execute on uninstall.
 *
 * @package Eventbrite_API
 */

/**
 * First verify that this file is not just being called directly.
 */
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

/**
 * Remove all transients.
 */
$transients = get_option( 'eventbrite_api_transients', array() );
if ( $transients ) {
	foreach ( $transients as $transient ) {
		delete_transient( $transient );
	}
}

/**
 * Delete plugin options.
 */
delete_option( 'eventbrite_api_transients' );
delete_option( 'eventbrite_api_token' );
