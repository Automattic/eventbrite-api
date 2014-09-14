<?php
/**
 * Eventbrite Query class.
 * Modeled on WP_Query, allowing developers to work with familiar terms and loop conventions.
 *
 * @package Eventbrite_API
 */
class Eventbrite_Query extends WP_Query {

	/**
	 * Retrieve the posts based on query variables.
	 *
	 * @access public
	 * @return array List of posts.
	 */
	public function get_posts() {
		$this->posts = eventbrite_request_events();

		$this->set_found_posts();

		// Ensure that any posts added/modified via one of the filters above are
		// of the type WP_Post and are filtered.
		if ( $this->posts ) {
			$this->post_count = count( $this->posts );

			$this->posts = array_map( 'eventbrite_get_event', $this->posts );

			$this->post = reset( $this->posts );
		} else {
			$this->post_count = 0;
			$this->posts = array();
		}

		return $this->posts;
	}

	/**
	 * Set up the amount of found posts and the number of pages (if limit clause was used)
	 * for the current query.
	 *
	 * @access private
	 */
	private function set_found_posts() {
		$this->found_posts = count( $this->posts );
	}

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
		// if ( ! empty( $query ) ) {
		// 	$this->query( $query );
		// }
		$this->query( $query );

	}
}
