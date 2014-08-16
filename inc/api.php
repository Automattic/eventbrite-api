<?php

if ( ! class_exists( 'Keyring_Service_Eventbrite' ) )
	exit;

class Eventbrite_API extends Keyring_Service_Eventbrite {

	static $instance;

	function __construct() {
		parent::__construct();
		self::$instance = $this;

		$token = get_option( 'eventbrite_token' );
		if ( ! empty( $token ) ) {
			$this->set_token( Keyring::init()->get_token_store()->get_token( array( 'type' => 'access', 'id' => $token ) ) );
		}
		$this->define_endpoints();
		add_action( 'keyring_connection_verified', array( $this, 'keyring_connection_verified' ), 10, 3 );
		add_action( 'keyring_connection_deleted', array( $this, 'keyring_connection_deleted' ), 10, 2 );
	}

	public function get_token() {
		$token = get_option( 'eventbrite_token' );
		if ( empty( $token ) )
			return false;

		$this->set_token( Keyring::init()->get_token_store()->get_token( array( 'type' => 'access', 'id' => $token ) ) );
		return $this->token;
	}

	private function define_endpoints() {
		$token = self::$instance->get_token();
		if ( empty( $token ) )
			return;

		$this->set_endpoint( 'user_owned_events', self::API_BASE . 'users/' . $token->get_meta( 'user_id' ) . '/owned_events', 'GET' );
	}

	static public function call( $endpoint, $params = array() ) {
		$token = self::$instance->get_token();
		if ( empty( $token ) )
			return new Keyring_Error( '400', 'No token present for the Eventbrite API.' );

		$endpoint_url = $endpoint . '_url';
		$response = self::$instance->request( self::$instance->{$endpoint_url}, $params );
		return $response;
	}

	function keyring_connection_verified( $service, $id, $request_token ) {
		if ( 'eventbrite' != $service || 'eventbrite' != $request_token->name ) {
			return;
		}

		update_option( 'eventbrite_token', $id );
	}

	function keyring_connection_deleted( $service, $request ) {
		if ( 'eventbrite' != $service ) {
			return;
		}

		delete_option( 'eventbrite_token' );
	}

}

new Eventbrite_API;