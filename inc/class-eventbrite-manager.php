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
	 * @param string $endpoint Valid Eventbrite v3 API endpoint.
	 * @param array $params Parameters passed to the API during a call.
	 * @param int|string|bool $id A specific event ID used for calls to the event_details endpoint.
	 * @param bool $force Force a fresh API call, ignoring any existing transient.
	 * @return object Request results
	 */
	public function request( $endpoint, $params = array(), $id = false, $force = false ) {
		// Make sure the endpoint and parameters are valid.
		if ( ! $this->validate_endpoint_params( $endpoint, $params ) ) {
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

		// Extend the HTTP timeout to account for Eventbrite API calls taking longer than ~5 seconds.
		add_filter( 'http_request_timeout', array( $this, 'increase_timeout' ) );

		// Make a fresh request.
		$request = Eventbrite_API::call( $endpoint, $params, $id );

		// Remove the timeout extension for any non-Eventbrite calls.
		remove_filter( 'http_request_timeout', array( $this, 'increase_timeout' ) );

		// If we get back a proper response, cache it.
		if ( ! is_wp_error( $request ) ) {
			$transient_name = $this->get_transient_name( $endpoint, $params );
			set_transient( $transient_name, $request, apply_filters( 'eventbrite_cache_expiry', DAY_IN_SECONDS ) );
			$this->register_transient( $transient_name );
		}

		return $request;
	}

	/**
	 * Validate the given parameters against its endpoint. Values are also validated where the API only accepts
	 * specific values.
	 *
	 * @access protected
	 *
	 * @param string $endpoint Endpoint to be called.
	 * @param array $params Parameters to be passed during the API call.
	 * @return bool True if all params were able to be validated, false otherwise.
	 */
	protected function validate_endpoint_params( $endpoint, $params ) {
		// Get valid request params.
		$valid = $this->get_endpoint_params();

		// Validate the endpoint.
		if ( ! array_key_exists( $endpoint, $valid ) ) {
			return false;
		}

		// Check that an array was passed for params.
		if ( ! is_array( $params ) ) {
			return false;
		}

		// Giving no parameters at all for queries is fine.
		if ( empty( $params ) ) {
			return true;
		}

		// The 'page' parameter is valid for any endpoint, as long as it's a positive integer.
		if ( isset( $params['page'] ) && ( 1 > (int) $params['page'] ) ) {
			return false;
		}
		unset( $params['page'] );

		// Compare each passed parameter and value against our valid ones, and fail if a match can't be found.
		foreach ( $params as $key => $value ) {
			// Check the parameter is valid for that endpoint.
			if ( ! array_key_exists( $key, $valid[$endpoint] ) ) {
				return false;
			}

			// If the parameter has a defined set of possible values, make sure the passed value is valid.
			if ( ! empty( $valid[$endpoint][$key] ) && ! in_array( $value, $valid[$endpoint][$key] ) ) {
				return false;
			}
		}

		// Looks good.
		return true;
	}

	/**
	 * Search for public live events.
	 *
	 * @access public
	 *
	 * @param array $params Parameters to be passed during the API call.
	 * @param bool $force Force a fresh API call, ignoring any existing transient.
	 * @return object Eventbrite_Manager
	 */
	public function do_event_search( $params = array(), $force = false ) {
		// Get the raw results.
		$results = $this->request( 'event_search', $params, false, $force );

		// If we have events, there's some work to do.
		if ( ! empty( $results->events ) ) {
			// Add the missing 'listed' property. Because this endpoint only returns public events, the API doesn't include it – but we need it.
			foreach ( $results->events as $event ) {
				$event->listed = true;
			}

			// Map events to the format expected by Eventbrite_Event.
			$results->events = array_map( array( $this, 'map_event_keys' ), $results->events );
		}

		return $results;
	}

	/**
	 * Get user-owned private and public events.
	 *
	 * @access public
	 *
	 * @param array $params Parameters to be passed during the API call.
	 * @param bool $force Force a fresh API call, ignoring any existing transient.
	 * @return object Eventbrite_Manager
	 */
	public function get_user_owned_events( $params = array(), $force = false ) {
		// Query for 'live' events by default (rather than 'all', which includes events in the past).
		if ( ! isset( $params['status'] ) ) {
			$params['status'] = 'live';
		}

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
	 * @param int|string|bool $id Eventbrite event ID (commonly a ten digit integer).
	 * @param bool $force Force a fresh API call, ignoring any existing transient.
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
	 * @param string $endpoint Endpoint being called.
	 * @param array $params Parameters to be passed during the API call.
	 * @return mixed Transient if found, false if not.
	 */
	protected function get_cache( $endpoint, $params ) {
		return get_transient( $this->get_transient_name( $endpoint, $params ) ); 
	}

	/**
	 * Determine a transient's name based on endpoint and parameters.
	 *
	 * @access protected
	 *
	 * @param string $endpoint Endpoint being called.
	 * @param array $params Parameters to be passed during the API call.
	 * @return string
	 */
	protected function get_transient_name( $endpoint, $params ) {
		// Results in 62 characters for the timeout option name (maximum is 64).
		$transient_name = 'eventbrite_' . md5( $endpoint . implode( $params ) );

		return apply_filters( 'eventbrite_transient_name', $transient_name, $endpoint, $params );
	}

	/**
	 * Return an array of valid request parameters by endpoint.
	 *
	 * @access protected
	 *
	 * @return array All valid request parameters for supported endpoints.
	 */
	protected function get_endpoint_params() {
		$params = array(
			// http://developer.eventbrite.com/docs/event-search/
			'event_search' => array(
				'q'                         => array(),
				'since_id'                  => array(),
				'sort_by'                   => array(
					'id',
					'date',
					'name',
					'city',
				),
				'popular'                   => array(
					true,
					false,
				),
				'location.address'          => array(),
				'location.latitude'         => array(),
				'location.longitude'        => array(),
				'location.within'           => array(),
				'venue.city'                => array(),
				'venue.region'              => array(),
				'venue.country'             => array(),
				'organizer.id'              => array(),
				'user.id'                   => array(),
				'tracking_code'             => array(),
				'categories'                => array(),
				'formats'                   => array(),
				'start_date.range_start'    => array(),
				'start_date.range_end'      => array(),
				'start_date.keyword'        => array(
					'today',
					'tomorrow',
					'this_week',
					'this_weekend',
					'next_week',
					'this_month',
				),
				'date_created.range_start'  => array(),
				'date_created.range_end'    => array(),
				'date_created.keyword'      => array(
					'today',
					'tomorrow',
					'this_week',
					'this_weekend',
					'next_week',
					'this_month',
				),
				'date_modified.range_start' => array(),
				'date_modified.range_end'   => array(),
				'date_modified.keyword'     => array(
					'today',
					'tomorrow',
					'this_week',
					'this_weekend',
					'next_week',
					'this_month',
				),
			),
			// http://developer.eventbrite.com/docs/event-details/
			'event_details' => array(
				// Not a true param for this endpoint; the ID gets passed as its own argument in the API call.
				'p' => array(),
			),
			// http://developer.eventbrite.com/docs/user-owned-events/
			'user_owned_events' => array(
				'status'   => array(
					'all',
					'cancelled',
					'draft',
					'ended',
					'live',
					'started',
				),
				'order_by' => array(
					'start_asc',
					'start_desc',
					'created_asc',
					'created_desc',
				),
			),
		);

		return $params;
	}

	/**
	 * Convert the Eventbrite API properties into properties used by Eventbrite_Event.
	 *
	 * @access protected
	 *
	 * @param object $api_event A single event from the API results.
	 * @return object Event with Eventbrite_Event keys.
	 */
	protected function map_event_keys( $api_event ) {
		$event = array();

		$event['ID']            = ( isset( $api_event->id ) )                ? $api_event->id                : '';
		$event['post_title']    = ( isset( $api_event->name->text ) )        ? $api_event->name->text        : '';
		$event['post_content']  = ( isset( $api_event->description->html ) ) ? $api_event->description->html : '';
		$event['post_date']     = ( isset( $api_event->start->local ) )      ? $api_event->start->local      : '';
		$event['post_date_gmt'] = ( isset( $api_event->start->utc ) )        ? $api_event->start->utc        : '';
		$event['url']           = ( isset( $api_event->url ) )               ? $api_event->url               : '';
		$event['logo_url']      = ( isset( $api_event->logo->url ) )         ? $api_event->logo->url         : '';
		$event['start']         = ( isset( $api_event->start ) )             ? $api_event->start             : '';
		$event['end']           = ( isset( $api_event->end ) )               ? $api_event->end               : '';
		$event['organizer']     = ( isset( $api_event->organizer ) )         ? $api_event->organizer         : '';
		$event['venue']         = ( isset( $api_event->venue ) )             ? $api_event->venue             : '';
		$event['public']        = ( isset( $api_event->listed ) )            ? $api_event->listed            : '';
		$event['tickets']       = ( isset( $api_event->ticket_classes ) )    ? $api_event->ticket_classes    : '';
		$event['category']      = ( isset( $api_event->category ) )          ? $api_event->category          : '';
		$event['subcategory']   = ( isset( $api_event->subcategory ) )       ? $api_event->subcategory       : '';
		$event['format']        = ( isset( $api_event->format ) )            ? $api_event->format            : '';

		return (object) $event;
	}

	/**
	 * Add a transient name to the list of registered transients, stored in the 'eventbrite_api_transients' option.
	 *
	 * @access protected
	 *
	 * @param string $transient_name The transient name/key used to store the transient.
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
	 * @param string $service The Keyring service that has lost its connection.
	 * @param string $request The Keyring action that's been called ("delete", not used).
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

	/**
	 * Increase the timeout for Eventbrite API calls from the default 5 seconds to 15.
	 *
	 * @access public
	 */
	public function increase_timeout() {
		return 15;
	}
}

new Eventbrite_Manager;

/**
 * Allow themes and plugins a simple function to access Eventbrite_Manager methods and properties.
 *
 * @return object Eventbrite_Manager
 */
function eventbrite() {
	return Eventbrite_Manager::$instance;
}
