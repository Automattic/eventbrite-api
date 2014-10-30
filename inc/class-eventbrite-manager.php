<?php
/**
 * Eventbrite Manager class for handling calls to the Eventbrite API.
 *
 * @package Eventbrite_API
 */

class Eventbrite_Manager {
	/**
	 * Class instance used by themes and plugins.
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
		add_action( 'keyring_connection_deleted', array( $this, 'flush_transients' ), 10, 2 );
	}

	/**
	 * Make a call to the Eventbrite v3 REST API, or return an existing transient.
	 *
	 * @access public
	 *
	 * @param string $endpoint
	 * @param array $params
	 * @param int|string|bool $id
	 * @param bool $force
	 * @uses Eventbrite_Manager->validate_endpoint()
	 * @uses Eventbrite_Manager->validate_request_params()
	 * @uses absint()
	 * @uses Eventbrite_Manager->get_cache()
	 * @uses Eventbrite_API::call()
	 * @uses set_transient()
	 * @uses Eventbrite_Manager->get_transient_name()
	 * @return object Request results
	 */
	public function request( $endpoint, $params = array(), $id = false, $force = false ) {
		// Ensure it's a supported endpoint.
		if ( ! $this->validate_endpoint( $endpoint ) ) {
			return false;
		}

		// Make sure the parameters are valid for the endpoint.
		if ( ! $this->validate_request_params( $params, $endpoint ) ) {
			return false;
		}

		// If an ID has been passed, validate and sanitize it.
		if ( ! empty( $id ) && is_numeric( $id ) && ( 0 < absint( $id ) ) ) {
			$id = absint( $id );
		} else {
			$id = false;
		}

		// Return a cached result if we have one.
		if ( ! $force ) {
			$cached = $this->get_cache( $endpoint, $params );
			if ( ! empty( $cached ) ) {
				return $cached;
			}
		}

		// Make a fresh request and cache it.
		$request = Eventbrite_API::call( $endpoint, $params, $id );
		$transient_name = $this->get_transient_name( $endpoint, $params );
		set_transient( $transient_name, $request, WEEK_IN_SECONDS );

		// Register the transient in case we need to flush.
		$this->register_transient( $transient_name );

		return $request;
	}

	/**
	 * Verify the endpoint passed is valid.
	 *
	 * @access public
	 *
	 * @param string $endpoint
	 * @uses Eventbrite_Manager::get_endpoints()
	 * @return bool True if the endpoint is valid, false otherwise.
	 */
	public function validate_endpoint( $endpoint ) {
		return in_array( $endpoint, $this->get_endpoints() );
	}

	/**
	 * Validate the given parameters against its endpoint.
	 *
	 * @access public
	 *
	 * @param array $params
	 * @param string $endpoint
	 * @uses Eventbrite_Manager::$instance
	 * @return object Eventbrite_Manager
	 */
	public function validate_request_params( $params, $endpoint ) {
		// Check that an array was passed.
		if ( ! is_array( $params ) ) {
			return false;
		}

		// kwight: sort this out
		return true;
	}

	/**
	 * Search for public live events.
	 *
	 * @access public
	 *
	 * @param array $params
	 * @param bool $force
	 * @uses Eventbrite_Manager::request
	 * @uses Eventbrite_Manager::map_event_keys
	 * @return object Eventbrite_Manager
	 */
	public function do_event_search( $params = array(), $force = false ) {
		// Get the raw results.
		$results = $this->request( 'event_search', $params, false, $force );

		// If we have events, map them to the format expected by Eventbrite_Event
		if ( ! empty( $results->events ) ) {
			$results->events = array_map( array( $this, 'map_event_keys' ), $results->events );
		}

		return $results;
	}

	/**
	 * Get user-owned private and public events.
	 *
	 * @access public
	 *
	 * @param array $params
	 * @param bool $force
	 * @uses Eventbrite_Manager::request
	 * @uses Eventbrite_Manager::map_event_keys
	 * @return object Eventbrite_Manager
	 */
	public function get_user_owned_events( $params = array(), $force = false ) {
		// Query for 'live' events by default (rather than 'all', which includes events in the past).
		$params['status'] = 'live';

		// Get the raw results.
		$results = $this->request( 'user_owned_events', $params, false, $force );

		// If we have events, map them to the format expected by Eventbrite_Event
		if ( ! empty( $results->events ) ) {
			$results->events = array_map( array( $this, 'map_event_keys' ), $results->events );
		}

		return $results;
	}

	/**
	 * Get a single event by ID.
	 *
	 * @access public
	 *
	 * @param int|string|bool $id
	 * @param bool $force
	 * @uses Eventbrite_Manager::request()
	 * @uses absint()
	 * @uses Eventbrite_Manager::map_event_keys()
	 * @return object Eventbrite_Manager
	 */
	public function get_event( $id = false, $force = false ) {
		// Ensure ID is an integer of at least 10 digits.
		if ( ! is_numeric( $id ) || 10 > strlen( $id ) ) {
			return false;
		}

		// Get the raw results. Although query parameters aren't needed for the API call, they're necessary for identifying transients.
		$results = $this->request( 'event_details', array( 'p' => absint( $id ) ), absint( $id ), $force );

		// If we have our event, map it to the format expected by Eventbrite_Event, and create pagination info.
		if ( empty( $results->error ) ) {
			$results = (object) array(
				'events' => array(
					$this->map_event_keys( $results ),
				),
				'pagination' => (object) array(
					'object_count' => 1,
					'page_number'  => 1,
					'page_size'    => 1,
					'page_count'   => 1,
				),
			);
		}

		return $results;
	}

	/**
	 * Get the transient for a certain endpoint and combination of parameters.
	 * get_transient() returns false if not found.
	 *
	 * @access protected
	 *
	 * @param string $endpoint
	 * @param array $params
	 * @uses get_transient()
	 * @uses Eventbrite_Manager::get_transient_name()
	 * @return mixed Transient if found, false if not
	 */
	protected function get_cache( $endpoint, $params ) {
		return get_transient( $this->get_transient_name( $endpoint, $params ) ); 
	}

	/**
	 * Determine a transient's name based on endpoint and parameters.
	 *
	 * @access protected
	 *
	 * @param string $endpoint
	 * @param array $params
	 * @return string
	 */
	protected function get_transient_name( $endpoint, $params ) {
		// Results in 62 characters for the timeout option name (maximum is 64).
		return 'eventbrite_' . md5( $endpoint . implode( $params ) );
	}

	/**
	 * Return an array of valid Eventbrite API endpoints.
	 *
	 * @access public
	 *
	 * @return array All supported endpoints.
	 */
	public function get_endpoints() {
		return apply_filters( 'eventbrite_supported_endpoints', array(
			'event_search',
			// 'event_categories',
			'event_details',
			// 'event_attendees',
			// 'event_attendees_detail',
			// 'event_orders',
			// 'event_discounts',
			// 'event_access_codes',
			// 'event_transfers',
			// 'event_teams',
			// 'event_teams_details',
			// 'event_teams_attendees',
			// 'user_details',
			// 'user_orders',
			'user_owned_events',
			// 'user_owned_events_orders',
			// 'user_owned_events_attendees',
			// 'user_venues',
			// 'user_organizers',
			// 'order_details',
			// 'contact_lists',
			// 'contact_list_details',
		) );
	}

	/**
	 * Convert the Eventbrite API properties into properties used by Eventbrite_Event.
	 *
	 * @access public
	 *
	 * @param object $api_event
	 * @return object Event with Eventbrite_Event keys.
	 */
	public function map_event_keys( $api_event ) {
		$event = array();

		$event['ID']           = ( isset( $api_event->id ) )                ? $api_event->id                : '';
		$event['post_title']   = ( isset( $api_event->name->text ) )        ? $api_event->name->text        : '';
		$event['post_content'] = ( isset( $api_event->description->html ) ) ? $api_event->description->html : '';
		$event['post_date']    = ( isset( $api_event->created ) )           ? $api_event->created           : '';
		$event['url']          = ( isset( $api_event->url ) )               ? $api_event->url               : '';
		$event['logo_url']     = ( isset( $api_event->logo_url ) )          ? $api_event->logo_url          : '';
		$event['start']        = ( isset( $api_event->start->utc ) )        ? $api_event->start->utc        : '';
		$event['end']          = ( isset( $api_event->end->utc ) )          ? $api_event->end->utc          : '';
		$event['post_author']  = ( isset( $api_event->organizer->name ) )   ? $api_event->organizer->name   : '';
		$event['organizer_id'] = ( isset( $api_event->organizer->id ) )     ? $api_event->organizer->id     : '';
		$event['venue']        = ( isset( $api_event->venue->name ) )       ? $api_event->venue->name       : '';
		$event['venue_id']     = ( isset( $api_event->venue->id ) )         ? $api_event->venue->id         : '';
		$event['public']       = ( isset( $api_event->listed ) )            ? $api_event->listed            : '';

		return (object) $event;
	}

	/**
	 * Add a transient name to the list of registered transients, stored in the 'eventbrite_api_transients' option.
	 *
	 * @access protected
	 *
	 * @uses   get_option()
	 * @uses   update_option()
	 */
	protected function register_transient( $transient_name ) {
		// Get any existing list of transients.
		$transients = get_option( 'eventbrite_api_transients', array() );

		// Add the new transient if it doesn't already exist.
		if ( ! in_array( $transient_name, $transients ) ) {
			$transients[] = $transient_name;
		}

		// Save the updated list of transients.
		update_option( 'eventbrite_api_transients', $transients );
	}

	/**
	 * Flush all transients.
	 *
	 * @access public
	 *
	 * @uses   get_option()
	 * @uses   delete_transient()
	 */
	public function flush_transients( $service, $request ) {
		// Bail if it wasn't an Eventbrite connection that got deleted.
		if ( 'eventbrite' != $service ) {
			return;
		}

		// Get the list of registered transients.
		$transients = get_option( 'eventbrite_api_transients', array() );

		// Bail if we have no transients.
		if ( ! $transients ) {
			return;
		}

		// Loop through all registered transients, deleting each one.
		foreach ($transients as $transient ) {
			delete_transient( $transient );
		}

		// Reset the list of registered transients.
		delete_option( 'eventbrite_api_transients' );
	}
}

new Eventbrite_Manager;

/**
 * Allow themes and plugins a simple function to access Eventbrite_Manager methods and properties.
 *
 * @global Eventbrite_Manager::instance()
 * @return object Eventbrite_Manager
 */
function eventbrite() {
	return Eventbrite_Manager::$instance;
}
