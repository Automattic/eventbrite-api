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
			$this->posts = array_slice( $this->api_results->events, 0, 10 ); // kwight: support posts_per_page
			$this->posts = array_map( 'eventbrite_get_event', $this->posts );
			$this->post_count = count( $this->posts );
			$this->post = reset( $this->posts );
		}

		$this->found_posts   = $this->api_results->pagination->object_count;
		$this->max_num_pages = ( 10 >= $this->found_posts ) ? 1 : $this->found_posts / 10; // kwight: support posts_per_page
	}
}
