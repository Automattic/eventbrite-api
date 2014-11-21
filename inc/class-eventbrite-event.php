<?php
/**
 * Eventbrite Event class.
 * Modeled on WP_Post, allowing developers to work with familiar terms and loop conventions.
 *
 * @package Eventbrite_API
 */

class Eventbrite_Event {

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
	public $post_title; // name->text

	/**
	 * The event's content.
	 *
	 * @var string
	 */
	public $post_content; // description->html

	/**
	 * Date on which the event was created.
	 *
	 * @var string
	 */
	public $post_date; // created

	/**
	 * The event's Eventbrite.com URL.
	 *
	 * @var string
	 */
	public $url; // url

	/**
	 * The event's logo URL.
	 *
	 * @var string
	 */
	public $logo_url; // logo_url

	/**
	 * The event's UTC start time.
	 *
	 * @var string
	 */
	public $start; // start->utc

	/**
	 * The event's UTC end time.
	 *
	 * @var string
	 */
	public $end;  // start->utc

	/**
	 * The event organizer's name.
	 *
	 * @var string
	 */
	public $post_author; // organizer->name

	/**
	 * The event organizer's ID.
	 *
	 * @var int
	 */
	public $organizer_id; // organizer->id

	/**
	 * The event's venue.
	 *
	 * @var string
	 */
	public $venue;  // venue->name

	/**
	 * The venue's ID.
	 *
	 * @var int
	 */
	public $venue_id; // venue->id

	/**
	 * Retrieve Eventbrite_Event instance.
	 *
	 * @static
	 * @access public
	 *
	 * @param int $event_id Event ID on eventbrite.com (commonly ten digits).
	 * @return Eventbrite_Event|bool Eventbrite_Event object, false otherwise.
	 */
	public static function get_instance( $event_id ) {
		// We can bail if no event ID was passed.
		if ( ! $event_id ) {
			return false;
		}

		// Get the raw event.
		$event = eventbrite_get_event( $event_id );

		// Return false if the ID was invalid or we got an error from the API call.
		if ( ! $event || ! empty( $event->error ) ) {
			return false;
		}

		// We've got an event, let's dress it up.
		return new Eventbrite_Event( $event->events[0] );
	}

	/**
	 * Constructor.
	 *
	 * @access public
	 *
	 * @param object $event An event object from the API results.
	 */
	public function __construct( $event ) {
		foreach ( get_object_vars( $event ) as $key => $value )
			$this->$key = $value;
	}
}
