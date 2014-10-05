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
}
