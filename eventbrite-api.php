<?php

/*
Plugin Name: Eventbrite API
Plugin URI: https://github.com/Automattic/eventbrite-api
Description: A WordPress plugin that integrates the Eventbrite API with WordPress and Keyring.
Version: 1.0
Author: Automattic
Author URI: http://automattic.com
License: GPL v2 or newer <https://www.gnu.org/licenses/gpl.txt>
*/

/**
 * Load our API on top of the Keyring Eventbrite service.
 */
function eventbrite_api_load_keyring_service() {
	require_once( 'inc/class-eventbrite-api.php' );
}
add_action( 'keyring_load_services', 'eventbrite_api_load_keyring_service', 11 );

/**
 * Load classes and helper functions.
 *
 * @uses current_theme_supports()
 */
function eventbrite_api_init() {
	// Load our plugin only if the current theme supports it.
	if ( current_theme_supports( 'eventbrite' ) ) {
		require_once( 'inc/class-eventbrite-manager.php' );
		require_once( 'inc/class-eventbrite-query.php' );
		require_once( 'inc/class-eventbrite-event.php' );
		require_once( 'inc/admin.php' );
		require_once( 'inc/functions.php' );
	}
}
add_action( 'init', 'eventbrite_api_init' );
