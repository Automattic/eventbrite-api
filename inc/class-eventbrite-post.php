<?php
/**
 * Eventbrite Event class.
 * Modeled on WP_Post, allowing developers to work with familiar terms and loop conventions.
 *
 * @package Eventbrite_API
 */
class Eventbrite_Post {

	/**
	 * Event ID.
	 *
	 * @var int
	 */
	public $ID; // id

	/**
	 * The event's title.
	 *
	 * @var string
	 */
	public $post_title = ''; // name->text

	/**
	 * The event's content.
	 *
	 * @var string
	 */
	public $post_content = ''; // description->html

	/**
	 * The event's Eventbrite.com URL.
	 *
	 * @var string
	 */
	public $url = ''; // url

	/**
	 * The event's logo URL.
	 *
	 * @var string
	 */
	public $logo_url = ''; // logo_url

	/**
	 * The event's status.
	 *
	 * @var string
	 */
	public $post_status = false; // listed: public or private

	/**
	 * The event's UTC start time.
	 *
	 * @var string
	 */
	public $start = '0000-00-00 00:00:00'; // start->utc

	/**
	 * The event's UTC end time.
	 *
	 * @var string
	 */
	public $end = '0000-00-00 00:00:00';  // start->utc

	/**
	 * The event's organizer.
	 *
	 * @var string
	 */
	public $organizer = ''; // organizer->description->text

	/**
	 * The event's venue.
	 *
	 * @var string
	 */
	public $venue = '';  // venue->name

	/**
	 * Retrieve Eventbrite_Post instance.
	 *
	 * @static
	 * @access public
	 *
	 * @param int $post_id Post ID.
	 * @return WP_Post|bool Post object, false otherwise.
	 */
	public static function get_instance( $post_id ) {
		//global $wpdb;
		$api_events = eventbrite_request_events();
		$post_id = (int) $post_id;
		
		if ( ! $api_events || ! $post_id ) {
			return false;
		}

		//$_post = wp_cache_get( $post_id, 'posts' );

		// if ( ! $_post ) {
		// 	$_post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID = %d LIMIT 1", $post_id ) );

		$_post = false;

			//error_log( print_r( $api_events, true ) );
		foreach ( $api_events as $event ) {
			if ( $post_id == $event->ID ) {
				$_post = $event;
			} 
		}

		if ( ! $_post ) {
			return false;
		}
		// 	$_post = sanitize_post( $_post, 'raw' );
		// 	wp_cache_add( $_post->ID, $_post, 'posts' );
		// } elseif ( empty( $_post->filter ) ) {
		// 	$_post = sanitize_post( $_post, 'raw' );
		// }

		return new Eventbrite_Post( $_post );
	}

	/**
	 * Constructor.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function __construct( $post ) {
		foreach ( get_object_vars( $post ) as $key => $value )
			$this->$key = $value;
	}
}
