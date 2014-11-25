<?php
/**
 * Eventbrite Query class.
 * Modeled on and extends WP_Query, allowing developers to work with familiar terms and loop conventions.
 *
 * @package Eventbrite_API
 */

class Eventbrite_Query extends WP_Query {
	/**
	 * Results from the API call. Includes up to 50 objects plus pagination info.
	 *
	 * @var object
	 */
	protected $api_results;

	/**
	 * Constructor.
	 *
	 * Sets up the Eventbrite query.
	 *
	 * @access public
	 *
	 * @param string $query URL query string.
	 */
	public function __construct( $query = '' ) {
		// Process any query args from the URL.
		$query = $this->process_query_args( $query );
		// Assign hooks.
		add_filter( 'post_link', array( $this, 'filter_event_permalink' ) );
		add_filter( 'get_post_metadata', array( $this, 'filter_post_metadata' ), 10, 3 );
		add_filter( 'post_thumbnail_html', array( $this, 'filter_event_logo' ), 9, 2 );
		add_filter( 'post_class', array( $this, 'filter_post_classes' ) );
		add_filter( 'author_link', array( $this, 'filter_author_url' ) );
		add_filter( 'the_author', array( $this, 'filter_author_name' ) );
		remove_filter( 'the_content', 'wpautop' );

		// Put our query in motion.
		$this->query( $query );
	}

	/**
	 * Handle any query args that come from the requested URL.
	 *
	 * @access protected
	 *
	 * @param  mixed $query Query string.
	 * @return array Query arguments
	 */
	protected function process_query_args( $query ) {
		// Handle requests for paged events.
		$paged = get_query_var( 'paged' );
		if ( 2 <= $paged ) {
			$query['paged'] = $paged;
		}

		// Filter by organizer ID if an "author archive" (organizer events) was requested.
		$organizer_id = get_query_var( 'organizer_id' );
		if ( ! empty( $organizer_id ) ) {
			$query['organizer_id'] = (int) $organizer_id;
		}

		// Filter by venue ID if a venue archive (all events at a certain venue) was requested.
		$venue_id = get_query_var( 'venue_id' );
		if ( ! empty( $venue_id ) ) {
			$query['venue_id'] = (int) $venue_id;
		}

		return $query;
	}

	/**
	 * Retrieve the posts based on query variables.
	 *
	 * @access public
	 *
	 * @return array List of posts.
	 */
	public function get_posts() {
		// Set up query variables.
		$this->parse_query();

		// Set any required parameters for the API request based on the query vars.
		$params = $this->set_api_params();

		// Determine which endpoint is needed. Do we want just a single event?
		if ( ! empty( $this->query_vars['p'] ) ) {
			$this->api_results = eventbrite()->get_event( $this->query_vars['p'] );
		}

		// If private events are wanted, the user_owned_events endpoint must be used.
		elseif ( isset( $this->query_vars['display_private'] ) && true === $this->query_vars['display_private'] ) {
			$this->api_results = eventbrite()->get_user_owned_events( $params );
		}

		// It's a run-of-the-mill query (only the user's public live events), meaning event_search is best.
		else {
			$this->api_results = eventbrite()->do_event_search( $params );
		}

		// Do any post-API query processing.
		$this->post_api_filters();

		// Set properties based on the results.
		$this->set_properties();

		// Return what we have for posts.
		return $this->posts;
	}

	/**
	 * Determine parameters for an API call.
	 *
	 * @access protected
	 *
	 * @return array API call parameters
	 */
	protected function set_api_params() {
		$params = array();

		// Add 'page' parameter if we need events above the first 50.
		if ( 5 < $this->query_vars['paged'] ) {
			/**
			 * The API returns pages of 50, and we currently only support a fixed number of 10 events per WordPress page.
			 */
			$params['page'] = ceil( $this->query_vars['paged'] / 5 );
		}

		// We need the Eventbrite user ID if we're getting only public events.
		if ( ! isset( $this->query_vars['display_private'] ) || true !== $this->query_vars['display_private'] ) {
			$params['user.id'] = Eventbrite_API::$instance->get_token()->get_meta( 'user_id' );
			$params['sort_by'] = 'date';
		}

		return $params;
	}

	/**
	 * Set properties based on the fully processed results.
	 *
	 * @access protected
	 */
	protected function set_properties() {
		if ( empty( $this->api_results->events ) ) {
			$this->posts = array();
		} else {
			// Set found_posts based on all posts returned after Eventbrite_Query filtering.
			$this->found_posts = ( isset( $this->query_vars['limit'] ) && ( $this->query_vars['limit'] < $this->api_results->pagination->object_count ) ) ? count( $this->api_results->events ) : $this->api_results->pagination->object_count;

			// Determine posts according to any pagination querying. Math hurts.
			$modulus = ( 2 <= $this->query_vars['paged'] && 0 == $this->query_vars['paged'] % 5 ) ? 5 : $this->query_vars['paged'] % 5;
			$offset = ( 2 <= $modulus && 5 >= $modulus ) ? ( $modulus - 1 ) * 10 : 0;
			$this->posts = array_slice( $this->api_results->events, $offset, 10 ); // kwight: support posts_per_page

			// Turn the posts into Eventbrite_Event objects.
			$this->posts = array_map( array( $this, 'create_eventbrite_event' ), $this->posts );

			// The post count will always equal the number of posts while we only support a fixed number of 10 posts returned. kwight: support posts_per_page
			$this->post_count = count( $this->posts );

			// Set the first post.
			$this->post = reset( $this->posts );
		}

		$this->max_num_pages = ceil( $this->found_posts / 10 ); // kwight: support posts_per_page

		// Adjust some WP_Query parsing.
		if ( ! empty( $this->query_vars['p'] ) ) {
			$this->is_single = true;
		} else {
			$this->is_category = true;
			$this->is_archive = true;
			$this->is_page = false;
		}
		$this->is_home = false;
	}

	/**
	 * Turn a given event into a proper Eventbrite_Event object.
	 *
	 * @access protected
	 *
	 * @param  null|object $event An event object from the API results.
	 * @return object Eventbrite_Event object.
	 */
	protected function create_eventbrite_event( $event = null ) {
		// Bail if nothing is passed in.
		if ( empty( $event ) ) {
			return null;
		}

		if ( is_a( $event, 'Eventbrite_Event' ) ) {
			// We already have an Eventbrite_Event object. Nothing to do here.
			$_event = $event;
		} elseif ( is_object( $event ) ) {
			// Looks like we have an object already, so make it an Eventbrite_Event object.
			$_event = new Eventbrite_Event( $event );
		} else {
			// Just an ID was passed in. Let's go get the event.
			$_event = Eventbrite_Event::get_instance( $event );
		}

		// That was a bust. We've got nothing.
		if ( ! $_event ) {
			return null;
		}

		// Return our Eventbrite_Event object.
		return $_event;
	}

	/**
	 * Process any remaining internal query parameters. These are parameters that are specific to Eventbrite_Query, not the API calls.
	 *
	 * @access protected
	 */
	protected function post_api_filters() {
		// Filter out specified IDs: 'post__not_in'
		if ( isset( $this->query_vars['post__not_in'] ) && is_array( $this->query_vars['post__not_in'] ) ) {
			$this->api_results->events = array_filter( $this->api_results->events, array( $this, 'filter_by_post_not_in' ) );
		}

		// Filter by organizer: 'organizer_id'
		if ( isset( $this->query_vars['organizer_id'] ) && is_integer( $this->query_vars['organizer_id'] ) ) {
			$this->api_results->events = array_filter( $this->api_results->events, array( $this, 'filter_by_organizer' ) );
		}

		// Filter by venue: 'venue_id'
		if ( isset( $this->query_vars['venue_id'] ) && is_integer( $this->query_vars['venue_id'] ) ) {
			$this->api_results->events = array_filter( $this->api_results->events, array( $this, 'filter_by_venue' ) );
		}

		// Limit the number of results: 'limit'
		if ( isset( $this->query_vars['limit'] ) && is_integer( $this->query_vars['limit'] ) ) {
			$this->api_results->events = array_slice( $this->api_results->events, 0, absint( $this->query_vars['limit'] ) );
		}
	}

	/**
	 * Determine by ID if an event is to be filtered out.
	 *
	 * @access protected
	 *
	 * @param  object $event A single event from the API call results.
	 * @return bool True with no ID match, false if the ID is in the array of events to be removed.
	 */
	protected function filter_by_post_not_in( $event ) {
		// Allow events not found in the array.
		return ! in_array( $event->ID, $this->query_vars['post__not_in'] );
	}

	/**
	 * Determine if an event is managed by a certain organizer.
	 *
	 * @access protected
	 *
	 * @param  object $event A single event from the API call results.
	 * @return bool True if properties match, false otherwise.
	 */
	protected function filter_by_organizer( $event ) {
		return ( isset( $event->organizer->id ) ) ? $event->organizer->id == $this->query_vars['organizer_id'] : false;
	}

	/**
	 * Determine if an event is occurring at a given venue.
	 *
	 * @access protected
	 *
	 * @param  object $event A single event from the API call results.
	 * @return bool True if properties match, false otherwise.
	 */
	protected function filter_by_venue( $event ) {
		return ( isset( $event->venue->id ) ) ? $event->venue->id == $this->query_vars['venue_id'] : false;
	}

	/**
	 * Filter the permalink for events to point to our rewrites. // kwight: what about different permalink formats?
	 *
	 * @access public
	 *
	 * @param  string $url The original unfiltered permalink.
	 * @return string Permalink URL
	 */
	public function filter_event_permalink( $url ) { // eg. http://mysite.com/events/july-test-drive-11829569561
		if ( eventbrite_is_event() ) {
			$url = sprintf( '%1$s/%2$s/%3$s-%4$s/',
				esc_url( home_url() ),                             // protocol://domain
				sanitize_title( get_queried_object()->post_name ), // page-with-eventbrite-template
				sanitize_title( get_post()->post_title ),               // event-title
				absint( get_post()->ID )                                // event ID
			);
		}

		return $url;
	}

	/**
	 * Filter post metadata so that has_post_thumbnail() returns true for events with a logo URL.
	 *
	 * @access public
	 *
	 * @param  null    $check The filter's default value.
	 * @param  integer $object_id Event ID.
	 * @param  string  $meta_key Name of meta key being checked.
	 * @return string URL of event logo passed from the API.
	 */
	public function filter_post_metadata( $check, $object_id, $meta_key ) {
		// If we aren't dealing with an Eventbrite event or wanting the thumbnail ID, then it's business as usual.
		if ( ! eventbrite_is_event() || '_thumbnail_id' !== $meta_key ) {
			return null;
		}

		// Get the event in question.
		$event = Eventbrite_Event::get_instance( $object_id );

		// Return whatever we have for the logo URL, which is used for event Featured Images.
		return $event->logo_url;
	}

	/**
	 * Replace featured images with the Eventbrite event logo.
	 *
	 * @access public
	 *
	 * @param  string $html Original unfiltered HTML for a featured image.
	 * @param  int $post_id The current event ID.
	 * @return string HTML <img> tag for the Eventbrite logo linked to the event single view.
	 */
	public function filter_event_logo( $html, $post_id ) {
		// Are we dealing with an Eventbrite event?
		if ( eventbrite_is_event() ) {
			$html = '';

			// Get our event.
			$event = Eventbrite_Event::get_instance( $post_id );

			// Does the event have a logo set?
			if ( isset( $event->logo_url ) ) {
				// No need for a permalink on event single views.
				if ( eventbrite_is_single() ) {
					$html = '<img src="' . esc_url( $event->logo_url ) . '" class="wp-post-image">';
				}

				// Add a permalink to events on the listings template.
				else {
					$html = sprintf( '<a class="post-thumbnail" href="%1$s"><img src="%2$s" class="wp-post-image"></a>',
						esc_url( get_the_permalink() ),
						esc_url( $event->logo_url )
					);
				}
			}
		}

		return $html;
	}

	/**
	 * Adjust classes for Event <article>s.
	 *
	 * @access public
	 *
	 * @param  array $classes Unfiltered post classes
	 * @return array Filtered post classes
	 */
	public function filter_post_classes( $classes ) {
		if ( eventbrite_is_event() ) {
			$classes[] = 'eventbrite-event';

			if ( isset( get_post()->logo_url ) ) {
				$classes[] = 'has-post-thumbnail';
			}
		}

		return $classes;
	}

	/**
	 * Change the author archive URL to that of the organizer.
	 *
	 * @access public
	 *
	 * @param  string $url Author archive URL, based on the current page or post.
	 * @return string Organizer name
	 */
	public function filter_author_url( $url ) {
		// See if we're working with an Eventbrite event.
		if ( eventbrite_is_event() ) {
			// Get the current page the link was clicked on.
			$url = get_permalink( get_queried_object_id() );

			// If the event has an organizer set, append it to the URL. http://(page permalink)/organizer/(organizer name)-(organizer ID)/
			if ( ! empty( eventbrite_event_organizer()->name ) ) {
				$url .= 'organizer/' . sanitize_title( eventbrite_event_organizer()->name ) . '-' . absint( eventbrite_event_organizer()->id );
			}
		}

		return trailingslashit( $url );
	}

	/**
	 * Change the author name to that of the event's organizer.
	 *
	 * @access public
	 *
	 * @param  string $name Author name, based on the current page or post.
	 * @return string Organizer name
	 */
	public function filter_author_name( $name ) {
		if ( eventbrite_is_event() ) {
			if ( ! empty( eventbrite_event_organizer()->name ) ) {
				$name = eventbrite_event_organizer()->name;
			}
		}

		return $name;
	}
}
