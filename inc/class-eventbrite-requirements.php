<?php
/**
 * Eventbrite Requirements class, which prompts to install and activate Keyring if it's not currently active.
 *
 * @package Eventbrite_API
 */

class Eventbrite_Requirements {
	/**
	 * Class instance.
	 *
	 * @var object
	 */
	public static $instance;

	/**
	 * The class constructor.
	 *
	 * @access public
	 */
	public function __construct() {
		// Assign our instance.
		self::$instance = $this;

		// Add hooks.
		add_action( 'admin_notices', array( $this, 'display_admin_notice' ) );
		add_filter( 'keyring_eventbrite_request_token_params', array( $this, 'add_connection_referrer' ) );
	}

	/**
	 * Check if we have a valid user connection to Eventbrite.
	 *
	 * @return bool True if a valid user token exists, false otherwise.
	 */
	public static function has_active_connection() {
		// Definitely no connection if Keyring isn't activated.
		if ( ! class_exists( 'Keyring_SingleStore' ) ) {
			return false;
		}

		// Let's check for Eventbrite connections.
		$tokens = Keyring_SingleStore::init()->get_tokens( array( 'service'=>'eventbrite' ) );
		if ( ! empty( $tokens[0] ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Display admin notices to help users solve requirements for getting events.
	 *
	 * @access public
	 */
	public function display_admin_notice() {
		// Don't display notices to users that can't do anything about it.
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		// Notices are only displayed on the dashboard, plugins, tools, and settings admin pages.
		$page = get_current_screen()->base;
		$display_on_pages = array(
			'dashboard',
			'plugins',
			'tools',
			'options-general',
		);
		if ( ! in_array( $page, $display_on_pages ) ) {
			return;
		}

		// We're all fired up. No need for any admin notices.
		if ( self::has_active_connection() ) {
			return;
		}

		// Keyring is not already installed.
		if ( ! file_exists( plugin_dir_path( dirname( __DIR__ ) ) . 'keyring' ) ) {
			$notice = sprintf( __( 'Eventbrite needs the <a href="%1$s">Keyring plugin</a> to work. <a href="%2$s" class="thickbox">Install Keyring</a>.', 'eventbrite-api' ),
				'https://wordpress.org/plugins/keyring/',
				esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=keyring&TB_iframe=true&width=600&height=550' ) )
			);
		}

		// Keyring is installed, just not activated.
		elseif ( ! is_plugin_active( 'keyring/keyring.php' ) ) {
			$notice = sprintf( __( 'Eventbrite needs the <a href="%1$s">Keyring plugin</a> activated, with a connection to eventbrite.com. <a href="%2$s">Activate Keyring</a>.', 'eventbrite-api' ),
				'https://wordpress.org/plugins/keyring/',
				esc_url( wp_nonce_url( network_admin_url( 'plugins.php?action=activate&plugin=keyring/keyring.php&plugin_status=all&paged=1&s' ), 'activate-plugin_keyring/keyring.php' ) )
			);
		}

		// We don't have an active Keyring connection to Eventbrite.
		elseif ( ! self::has_active_connection() ) {
			$notice = sprintf( __( 'The Eventbrite API plugin needs a working connection to eventbrite.com. We recommend first <a href="%1$s">logging in</a> to your eventbrite.com account. <a href="%2$s">Connect to Eventbrite</a>.', 'eventbrite-api' ),
				'https://www.eventbrite.com/login/',
				esc_url( get_admin_url( null, 'tools.php?page=keyring&action=services' ) )
			);
		}

		// Output notice HTML.
		printf( '<div id="message" class="updated"><p>%s</p></div>', $notice );
	}

	/**
	 * Append a referrer to the OAuth request made to Eventbrite, giving them an idea of WordPress adoption.
	 *
	 * @param array $params Parameters to be passed on an OAuth request.
	 * @return array OAuth request parameters with the referral added.
	 */
	public function add_connection_referrer( $params ) {
		if ( ! isset( $params['ref'] ) ) {
			$params['ref'] = 'wpoauth';
		}

		return $params;
	}
}

new Eventbrite_Requirements;
