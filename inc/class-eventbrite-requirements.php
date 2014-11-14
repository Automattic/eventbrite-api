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
	 *
	 * @uses   Eventbrite_manager::$instance
	 * @uses   add_action()
	 */
	public function __construct() {
		// Assign our instance.
		self::$instance = $this;

		// Add hooks.
		add_action( 'admin_notices', array( $this, 'display_admin_notice' ) );
	}

	/**
	 * Check if we have a valid user connection to Eventbrite.
	 *
	 * @param
	 * @uses   Eventbrite_API::$instance->get_token()
	 * @return bool True if a valid user token exists, false otherwise.
	 */
	public static function has_active_connection() {
		return ( class_exists( 'Eventbrite_API', false ) && Eventbrite_API::$instance->get_token() ) ? true : false;
	}

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
			$notice = sprintf( __( 'Eventbrite needs the %1$s to work. %2$s.', 'eventbrite-api' ),
				sprintf( '<a href="https://wordpress.org/plugins/keyring/">%s</a>',
					esc_html__( 'Keyring plugin', 'eventbrite-api' )
				),
				sprintf( '<a href="%s" class="thickbox">%s</a>',
					esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=keyring&TB_iframe=true&width=600&height=550' ) ),
					esc_html__( 'Install Keyring', 'eventbrite-api' )
				)
			);
		}

		// Keyring is installed, just not activated.
		elseif ( ! is_plugin_active( 'keyring/keyring.php' ) ) {
			$notice = sprintf( __( 'Eventbrite needs the %1$s activated, with a connection to eventbrite.com. %2$s.', 'eventbrite-api' ),
				sprintf( '<a href="https://wordpress.org/plugins/keyring/">%s</a>',
					esc_html__( 'Keyring plugin', 'eventbrite-api' )
				),
				sprintf( '<a href="%s" class="thickbox">%s</a>',
					esc_url( wp_nonce_url( network_admin_url( 'plugins.php?action=activate&plugin=keyring/keyring.php&plugin_status=all&paged=1&s' ), 'activate-plugin_keyring/keyring.php' ) ),
					esc_html__( 'Activate Keyring', 'eventbrite-api' )
				)
			);
		}

		// We don't have an active Keyring connection to Eventbrite.
		elseif ( ! self::has_active_connection() ) {
			$cta_url = get_admin_url( null, 'tools.php?page=keyring' );

			$notice = sprintf( __( 'The Eventbrite API plugin needs a working connection to eventbrite.com. We recommend first %1$s to your eventbrite.com account. %2$s.', 'eventbrite-api' ),
				sprintf( '<a href="https://www.eventbrite.com/login/">%s</a>',
					esc_html__( 'logging in', 'eventbrite-api' )
				),
				sprintf( '<a href="%s">%s</a>',
					esc_url( $cta_url ),
					esc_html__( 'Connect to Eventbrite', 'eventbrite-api' )
				)
			);
		}

		// Output notice HTML.
		printf( '<div id="message" class="updated"><p>%s</p></div>', $notice );
	}
}

new Eventbrite_Requirements;
