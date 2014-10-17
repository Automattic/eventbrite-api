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
	 * Sets up the WordPress query, if parameter is not empty.
	 *
	 * @access public
	 *
	 * @param string $query URL query string.
	 * @uses get_query_var()
	 * @uses add_filter()
	 * @uses Eventbrite_Query::query()
	 */
	public function __construct( $query = '' ) {
		// Set pagination if required.
		$paged = get_query_var( 'paged' );

		if ( 0 != $paged ) {
			$query['paged'] = $paged;
		}

		// Assign hooks.
		add_filter( 'get_post_metadata', array( $this, 'filter_post_metadata' ), 10, 3 );
		add_filter( 'post_thumbnail_html', array( $this, 'filter_event_logo' ), 9, 2 );
		add_filter( 'post_class', array( $this, 'filter_post_classes' ) );

		// Put our query in motion.
		$this->query( $query );
	}

	/**
	 * Retrieve the posts based on query variables.
	 *
	 * @access public
	 *
	 * @uses Eventbrite_Query::parse_query()
	 * @uses Eventbrite_Query::api_results()
	 * @uses eventbrite_get_events()
	 * @uses Eventbrite_Query::$query_vars
	 * @uses Eventbrite_Query::post_api_filters()
	 * @uses Eventbrite_Query::set_properties()
	 * @uses Eventbrite_Query::$posts
	 * @return array List of posts.
	 */
	public function get_posts() {
		// Set up query variables.
		$this->parse_query();

		// Get the event or events from the API (or cache).
		if ( ! empty( $this->query_vars['p'] ) ) {
			$this->api_results = eventbrite()->get_event( $this->query_vars['p'] );
		} else {
			$this->api_results = eventbrite()->get_user_owned_events( $this->query_vars );
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

			// Process the posts. kwight: what exactly do I need this for?
			$this->posts = array_map( 'eventbrite_get_event', $this->posts );

			// The post count will always equal the number of posts while we only support a fixed number of 10 posts returned.
			$this->post_count = count( $this->posts );

			// Set the first post.
			$this->post = reset( $this->posts );
		}

		$this->max_num_pages = ceil( $this->found_posts / 10 ); // kwight: support posts_per_page
	}

	/**
	 * Process any remaining internal query parameters. These are parameters that are specific to Eventbrite_Query, not the API calls.
	 *
	 * @access public
	 *
	 * @uses Eventbrite_Query::$query_vars
	 * @uses Eventbrite_Query::$api_results
	 * @uses absint()
	 */
	public function post_api_filters() {
		// Limit the number of results: 'limit'
		if ( isset( $this->query_vars['limit'] ) ) {
			$this->api_results->events = array_slice( $this->api_results->events, 0, absint( $this->query_vars['limit'] ) );
		}

	}

	/**
	 * Filter post metadata so that has_post_thumbnail() returns true for events with a logo URL.
	 *
	 * @param null    $check
	 * @param integer $object_id Event ID.
	 * @param string  $meta_key Name of meta key being checked.
	 * @uses is_eventbrite_event()
	 * @uses Eventbrite_Post::get_instance()
	 * @uses Eventbrite_Post::$logo_url
	 * @return string URL of event logo passed from the API.
	 */
	public function filter_post_metadata( $check, $object_id, $meta_key ) {
		// If we aren't dealing with an Eventbrite event or wanting the thumbnail ID, then it's business as usual.
		if ( ! is_eventbrite_event() || '_thumbnail_id' !== $meta_key ) {
			return null;
		}

		// Get the event in question.
		$event = Eventbrite_Post::get_instance( $object_id );

		// Return whatever we have for the logo URL, which is used for event Featured Images.
		return $event->logo_url;
	}

	/**
	 * Replace featured images with the Eventbrite event logo.
	 *
	 * @access public
	 *
	 * @param string $html
	 * @param int $post_id
	 * @uses is_eventbrite_event()
	 * @uses eventbrite_get_event()
	 * @uses Eventbrite_Post::$logo_url
	 * @uses esc_url()
	 * @uses get_the_permalink()
	 * @return string HTML <img> tag for the Eventbrite logo linked to the event single view.
	 */
	public function filter_event_logo( $html, $post_id ) {
		// Are we dealing with an Eventbrite event?
		if ( is_eventbrite_event() ) {
			$html = '';

			$event = eventbrite_get_event( $post_id );

			if ( isset( $event->logo_url ) ) {
				$html = '<img src="' . $event->logo_url . '" />';
				$html = sprintf( '<a class="post-thumbnail" href="%1$s"><img src="%2$s" class="wp-post-image"></a>',
					esc_url( get_the_permalink() ),
					esc_url( $event->logo_url )
				);
			}
		}

		return $html;
	}

	/**
	 * Adjust classes for Event <article>s.
	 *
	 * @access public
	 *
	 * @param array $classes Unfiltered post classes
	 * @uses is_eventbrite_event()
	 * @uses $post
	 * @uses Eventbrite_Post::$logo_url
	 * @return array Filtered post classes
	 */
	public function filter_post_classes( $classes ) {
		if ( is_eventbrite_event() ) {
			$classes[] = 'eventbrite-event';

			global $post;
			if ( isset( $post->logo_url ) ) {
				$classes[] = 'has-post-thumbnail';
			}
		}

		return $classes;
	}
}
