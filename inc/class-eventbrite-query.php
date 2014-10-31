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
	public $api_results;

	/**
	 * Constructor.
	 *
	 * Sets up the Eventbrite query.
	 *
	 * @access public
	 *
	 * @param string $query URL query string.
	 * @uses  get_query_var()
	 * @uses  add_filter()
	 * @uses  Eventbrite_Query::query()
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

		// Put our query in motion.
		$this->query( $query );
	}

	/**
	 * Handle any query args that come from the requested URL.
	 *
	 * @param
	 * @uses
	 * @return
	 */
	public function process_query_args( $query ) {
		// Handle requests for paged events.
		$paged = get_query_var( 'paged' );
		if ( 2 >= $paged ) {
			$query['paged'] = $paged;
		}

		// Filter by organizer ID if an "author archive" (organizer events) was requested.
		$organizer_id = get_query_var( 'organizer_id' );
		if ( ! empty( $organizer_id ) ) {
			$query['organizer_id'] = $organizer_id;
		}

		// Filter by venue ID if a venue archive (all events at a certain venue) was requested.
		$venue_id = get_query_var( 'venue_id' );
		if ( ! empty( $venue_id ) ) {
			$query['venue_id'] = $venue_id;
		}

		return $query;
	}

	/**
	 * Retrieve the posts based on query variables.
	 *
	 * @access public
	 *
	 * @uses   Eventbrite_Query::parse_query()
	 * @uses   Eventbrite_Query::$query_vars
	 * @uses   Eventbrite_Query::api_results()
	 * @uses   Eventbrite_Manager::get_event()
	 * @uses   Eventbrite_Manager::get_user_owned_events()
	 * @uses   Eventbrite_Query::post_api_filters()
	 * @uses   Eventbrite_Query::set_properties()
	 * @uses   Eventbrite_Query::$posts
	 * @return array List of posts.
	 */
	public function get_posts() {
		// Set up query variables.
		$this->parse_query();

		// Determine which endpoint is needed. Do we want just a single event?
		if ( ! empty( $this->query_vars['p'] ) ) {
			$this->api_results = eventbrite()->get_event( $this->query_vars['p'] );
		}

		// If private events are wanted, the user_owned_events endpoint must be used.
		elseif ( isset( $this->query_vars['display_private'] ) && true === $this->query_vars['display_private'] ) {
			$this->api_results = eventbrite()->get_user_owned_events();
		}

		// It's a run-of-the-mill query (only the user's public live events), meaning event_search is best.
		else {
			$this->api_results = eventbrite()->do_event_search( array(
				'user.id' => Eventbrite_API::$instance->get_token()->get_meta( 'user_id' ),
			) );
		}

		// Do any post-API query processing.
		$this->post_api_filters();

		// Set properties based on the results.
		$this->set_properties();

		// Return what we have for posts.
		return $this->posts;
	}

	/**
	 * Set properties based on the fully processed results.
	 *
	 * @access public
	 *
	 * @uses Eventbrite_Query::$api_results
	 * @uses Eventbrite_Query::$posts
	 * @uses Eventbrite_Query::$found_posts
	 * @uses Eventbrite_Query::$query_vars
	 * @uses Eventbrite_Query::$post_count
	 * @uses Eventbrite_Query::$post
	 * @uses Eventbrite_Query::$max_num_pages
	 */
	public function set_properties() {
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
	 * Filter the permalink for events to point to our rewrites. // kwight: what about different permalink formats?
	 *
	 * @access public
	 *
	 * @param  string $url
	 * @global $post
	 * @uses   eventbrite_is_event()
	 * @uses   esc_url()
	 * @uses   home_url()
	 * @uses   sanitize_title()
	 * @uses   get_queried_object()
	 * @uses   WP_Post::$post_title
	 * @uses   absint()
	 * @uses   WP_Post::$ID
	 * @return string Permalink URL
	 */
	public function filter_event_permalink( $url ) { // eg. http://mysite.com/events/july-test-drive-11829569561
		if ( eventbrite_is_event() ) {
			global $post;
			$url = sprintf( '%1$s/%2$s/%3$s-%4$s/',
				esc_url( home_url() ),                             // protocol://domain
				sanitize_title( get_queried_object()->post_name ), // page-with-eventbrite-template
				sanitize_title( $post->post_title ),               // event-title
				absint( $post->ID )                                // event ID
			);
		}

		return $url;
	}

	/**
	 * Turn a given event into a proper Eventbrite_Event object.
	 *
	 * @access public
	 *
	 * @param  null|object $event
	 * @uses   Eventbrite_Event
	 * @uses   Eventbrite_Event::get_instance()
	 * @return object Eventbrite_Event object.
	 */
	public function create_eventbrite_event( $event = null ) {
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
	 * @access public
	 *
	 * @uses   Eventbrite_Query::$query_vars
	 * @uses   Eventbrite_Query::$api_results
	 * @uses   absint()
	 */
	public function post_api_filters() {
		// Filter out specified IDs: 'post__not_in'
		if ( isset( $this->query_vars['post__not_in'] ) ) {
			$this->api_results->events = array_filter( $this->api_results->events, array( $this, 'filter_by_post_not_in' ) );
		}

		// Filter by organizer: 'organizer'
		if ( isset( $this->query_vars['organizer_id'] ) ) {
			$this->api_results->events = array_filter( $this->api_results->events, array( $this, 'filter_by_organizer' ) );
		}

		// Filter by venue: 'venue'
		if ( isset( $this->query_vars['venue_id'] ) ) {
			$this->api_results->events = array_filter( $this->api_results->events, array( $this, 'filter_by_venue' ) );
		}

		// Limit the number of results: 'limit'
		if ( isset( $this->query_vars['limit'] ) ) {
			$this->api_results->events = array_slice( $this->api_results->events, 0, absint( $this->query_vars['limit'] ) );
		}
	}

	/**
	 * Determine by ID if an event is to be filtered out.
	 *
	 * @access public
	 *
	 * @param  object Event
	 * @uses   Eventbrite_Query::$query_vars
	 * @return bool True with no ID match, false if the ID is in the array of events to be removed.
	 */
	public function filter_by_post_not_in( $event ) {
		// Allow events not found in the array.
		return ! in_array( $event->ID, $this->query_vars['post__not_in'] );
	}

	/**
	 * Determine if an event is managed by a certain organizer.
	 *
	 * @access public
	 *
	 * @param  object Event
	 * @uses   Eventbrite_Query::$query_vars
	 * @return bool True if properties match, false otherwise.
	 */
	public function filter_by_organizer( $event ) {
		return ( isset( $event->organizer->id ) ) ? $event->organizer->id == $this->query_vars['organizer_id'] : false;
	}

	/**
	 * Determine if an event is occurring at a given venue.
	 *
	 * @access public
	 *
	 * @param  object Event
	 * @uses   Eventbrite_Query::$query_vars
	 * @return bool True if properties match, false otherwise.
	 */
	public function filter_by_venue( $event ) {
		return ( isset( $event->venue->id ) ) ? $event->venue->id == $this->query_vars['venue_id'] : false;
	}

	/**
	 * Filter post metadata so that has_post_thumbnail() returns true for events with a logo URL.
	 *
	 * @access public
	 *
	 * @param  null    $check
	 * @param  integer $object_id Event ID.
	 * @param  string  $meta_key Name of meta key being checked.
	 * @uses   eventbrite_is_event()
	 * @uses   Eventbrite_Event::get_instance()
	 * @uses   Eventbrite_Event::$logo_url
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
	 * @param  string $html
	 * @param  int $post_id
	 * @uses   eventbrite_is_event()
	 * @uses   eventbrite_get_event()
	 * @uses   Eventbrite_Event::$logo_url
	 * @uses   esc_url()
	 * @uses   get_the_permalink()
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
	 * @global $post
	 * @uses   eventbrite_is_event()
	 * @uses   Eventbrite_Event::$logo_url
	 * @return array Filtered post classes
	 */
	public function filter_post_classes( $classes ) {
		if ( eventbrite_is_event() ) {
			$classes[] = 'eventbrite-event';

			global $post;
			if ( isset( $post->logo_url ) ) {
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
	 * @param  string $name Author name
	 * @global $post
	 * @uses   eventbrite_is_event()
	 * @uses   Eventbrite_Event::$organizer_name
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
	 * @param  string $name Author name
	 * @global $post
	 * @uses   eventbrite_is_event()
	 * @uses   Eventbrite_Event::$organizer_name
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
