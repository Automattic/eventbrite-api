<?php
/**
 * Eventbrite Query class.
 * Modeled on WP_Query, allowing developers to work with familiar terms and loop conventions.
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
	 * @return WP_Query
	 */
	public function __construct( $query = '' ) {
		// Set pagination if required.
		$paged = get_query_var( 'paged' );

		if ( 0 != $paged ) {
			$query['paged'] = $paged;
		}

		// Assign hooks.
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_filter( 'post_link', array( $this, 'filter_event_permalink' ) );
		add_filter( 'post_thumbnail_html', array( $this, 'filter_event_logo' ), 9, 2 );
		add_filter( 'post_class', array( $this, 'filter_post_classes' ) );

		// Put our query in motion.
		$this->query( $query );
	}

	/**
	 * Retrieve the posts based on query variables.
	 *
	 * @access public
	 * @return array List of posts.
	 */
	public function get_posts() {
		// Set up query variables.
		$this->parse_query();

		// Get the events.
		$this->api_results = eventbrite_get_events( $this->query_vars );

		// Set properties based on the results.
		$this->set_properties();

		// Return what we have for posts.
		return $this->posts;
	}

	/**
	 * Set properties based on the API call results.
	 */
	public function set_properties() {
		if ( empty( $this->api_results->events ) ) {
			$this->post_count = 0;
			$this->posts = array();
		} else {
			// Determine posts according to pagination. Math hurts.
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

		$this->found_posts   = $this->api_results->pagination->object_count;
		$this->max_num_pages = ceil( $this->found_posts / 10 ); // kwight: support posts_per_page
	}

	/**
	 * Filter the permalink for events to point to our rewrites. // kwight: what about different permalink formats?
	 *
	 * @param
	 * @uses
	 * @return
	 */
	public function filter_event_permalink( $url ) { // eg. http://mysite.com/events/july-test-drive-11829569561
		if ( is_eventbrite_event() ) {
			global $post;
			$url = sprintf( '%1$s/%2$s/%3$s',
				esc_url( home_url() ),                             // protocol://domain
				sanitize_title( get_queried_object()->post_name ), // page-with-eventbrite-template
				//sanitize_title( $post->post_title ),               // event-title
				absint( $post->ID )                                // event ID
			);
		}

		return $url;
	}

	/**
	 * Replace featured images with the Eventbrite event logo.
	 *
	 * @param
	 * @uses
	 * @return
	 */
	function filter_event_logo( $html, $post_id ) {
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
	 * @param
	 * @uses
	 * @return
	 */
	function filter_post_classes( $classes ) {
		if ( is_eventbrite_event() ) {
			$classes[] = 'eventbrite-event';

			global $post;
			if ( isset( $post->logo_url ) ) {
				$classes[] = 'has-post-thumbnail';
			}
		}

		return $classes;
	}

	function add_query_vars( $query_vars ) {
		$query_vars[] = 'eb_event_id';

		return $query_vars;
	}
}
