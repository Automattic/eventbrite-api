<?php
/**
 * Eventbrite functions.
 *
 * @package Eventbrite_API
 */

if ( ! function_exists( 'eventbrite_get_events' ) ) :
/**
 * Get an array of Eventbrite events, in the format expected by Eventbrite_Event
 *
 * @param  array $params
 * @param  bool $force Force an API call, don't use cache
 * @uses   Eventbrite_Manager::get_user_owned_events()
 * @return object API results
 */
function eventbrite_get_events( $params = array(), $force = false ) {
	return eventbrite()->get_user_owned_events( $params, $force );
}
endif;

if ( ! function_exists( 'eventbrite_get_event' ) ) :
/**
 * Retrieves event data given an event ID.
 *
 * @param  int $id
 * @param  bool $force Force an API call, don't use cache
 * @uses   Eventbrite_Manager::get_event()
 * @return object Event
 */
function eventbrite_get_event( $id = false, $force = false ) {
	// Bail if no ID is passed in.
	if ( empty( $id ) ) {
		return null;
	}

	// Retrieve and return our event.
	return eventbrite()->get_event( $id, $force );
}
endif;

if ( ! function_exists( 'eventbrite_search' ) ) :
/**
 * Search Eventbrite public events by any user.
 * Note that not limiting the scope of the search somehow will likely result in timeout errors.
 *
 * @param  array $params
 * @param  bool $force Force an API call, don't use cache
 * @uses   Eventbrite_Manager::get_user_owned_events()
 * @return object API results
 */
function eventbrite_search( $params = array(), $force = false ) {
	return eventbrite()->do_event_search( $params, $force );
}
endif;

if ( ! function_exists( 'eventbrite_is_event' ) ) :
/**
 * Determine if a given object, given ID, or the current post is an Eventbrite event.
 *
 * @param  mixed $post
 * @global $post
 * @uses   eventbrite_get_event()
 * @uses   absint()
 * @return bool True if it's an Eventbrite event, false otherwise.
 */
function eventbrite_is_event( $post = null ) {
	// If no post is given, assume the current post.
	if ( ! $post ) {
		global $post;
	}

	// Check if the post is an Eventbrite_Event object.
	if ( is_a( $post, 'Eventbrite_Event' ) ) {
		return true;
	}

	// Maybe we're working with an event ID.
	if ( is_integer( $post ) && 10 < strlen( strval( $id ) ) ) {
		$event = eventbrite_get_event( absint( $post ) );
		return ( empty( $event->error ) ) ? true : false;
	}

	// No dice.
	return false;
}
endif;

if ( ! function_exists( 'eventbrite_paging_nav' ) ) :
/**
 * Paging navigation on event listings, using paginate_links().
 *
 * @param object $events
 * @uses  get_query_var()
 * @uses  esc_html_e()
 * @uses  paginate_links()
 * @uses  apply_filters()
 */
function eventbrite_paging_nav( $events ) {
	// Bail if we only have one page and don't need pagination.
	if ( $events->max_num_pages < 2 ) {
		return;
	}

	// Set arguments for paginate_links().
	$args = array(
		'current'   => get_query_var( 'paged' ) ? intval( get_query_var( 'paged' ) ) : 1,
		'next_text' => __( 'Next &rarr;', 'eventbrite' ),
		'prev_text' => __( '&larr; Previous', 'eventbrite' ),
		'total'     => $events->max_num_pages,
	);

	// If we have 10 or less pages, just show them all.
	if ( 10 >= $events->max_num_pages ) {
		$args['show_all'] = true;
	}

	// Output our markup. ?>
	<nav class="navigation paging-navigation pagination" role="navigation">
		<h1 class="screen-reader-text"><?php esc_html_e( 'Events navigation', 'eventbrite' ); ?></h1>
		<div class="nav-links">
			<?php echo paginate_links( apply_filters( 'eventbrite_paginate_links_args', $args, $events ) ); ?>
		</div><!-- .pagination -->
	</nav><!-- .navigation -->
<?php }
endif;

if ( ! function_exists( 'eventbrite_get_support_args' ) ) :
/**
 * Get the arguments being passed to add_theme_support().
 *
 * @uses   get_theme_support()
 * @return mixed Eventbrite theme support arguments, or false if no theme support.
 */
function eventbrite_get_support_args() {
	$support = get_theme_support( 'eventbrite' );
	return ( isset( $support[0] ) ) ? (object) $support[0] : false;
}
endif;

if ( ! function_exists( 'eventbrite_is_single' ) ) :
/**
 * Determine if a query is for an event single view.
 *
 * @param  mixed $query
 * @uses   Eventbrite_Query::$is_single
 * @uses   get_query_var()
 * @return bool True if an event single view, false otherwise.
 */
function eventbrite_is_single( $query = null ) {
	// If an Eventbrite_Query object is passed in, check the is_single property.
	if ( is_a( $query, 'Eventbrite_Query' ) ) {
		return $query->is_single;
	}

	// If the eventbrite_id query var has something, then it's an event single view.
	elseif ( get_query_var( 'eventbrite_id' ) ) {
		return true;
	}

	// Whatever it is, if anything, it's not an event single view.
	else {
		return false;
	}
}
endif;

if ( ! function_exists( 'eventbrite_event_meta' ) ) :
/**
 * Output event information such as date, time, venue, and organizer
 *
 * @uses   eventbrite_event_time()
 * @uses   eventbrite_event_venue()
 * @uses   esc_url()
 * @uses   eventbrite_venue_get_archive_link()
 * @uses   esc_html()
 * @uses   eventbrite_event_organizer()
 * @uses   get_author_posts_url()
 * @uses   get_the_author_meta()
 * @uses   get_the_author()
 * @uses   eventbrite_is_single()
 * @uses   get_the_permalink()
 * @uses   esc_html__()
 * @uses   apply_filters()
 * @return string Event meta
 */
function eventbrite_event_meta() {
	// Determine our separator.
	$separator = apply_filters( 'eventbrite_meta_separator', ' &middot; ' );

	// Start our HTML output with the event time.
	$time = '<span class="event-time">' . eventbrite_event_time() . '</span>';

	// Add a venue name if available.
	$venue = '';
	if ( ! empty( eventbrite_event_venue()->name ) ) {
		$venue = sprintf( '%s<span class="event-venue"><a class="url fn n" href="%s">%s</a></span>',
			esc_html( $separator ),
			esc_url( eventbrite_venue_get_archive_link() ),
			esc_html( eventbrite_event_venue()->name )
		);
	}

	// Add the organizer's name if available.
	$organizer = '';
	if ( ! empty( eventbrite_event_organizer()->name ) ) {
		// Assemble the "author archive" link and name. Author-related functions are filtered to use the event's organizer.
		$organizer = sprintf( '%s<span class="event-organizer">%s</span>',
			esc_html( $separator ),
			sprintf( _x( 'Organized by %s', 'Event organizer', 'eventbrite_api' ),
				'<a class="url fn n" href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . esc_html( get_the_author() ) . '</a>'
			)
		);
	}

	// Add a contextual link to event details.
	if ( eventbrite_is_single() ) {
		// Link to event info on eventbrite.com.
		$url = add_query_arg( array( 'ref' => 'wporglink' ), eventbrite_event_eb_url() );
	} else {
		// Link to the event single view.
		$url = get_the_permalink();
	}

	$details = sprintf( '%s<span class="event-details"><a href="%s">%s</a></span>',
		esc_html( $separator ),
		esc_url( $url ),
		esc_html__( 'More details', 'eventbrite_api' )
	);

	// Assemble our HTML. Yugly.
	$html = sprintf( _x( '%1$s%2$s%3$s%4$s', '%1$s: time, %2$s: venue, %3$s: organizer, %4$s: event details (only on index views)', 'eventbrite-api' ),
		$time,
		$venue,
		$organizer,
		$details
	);

	echo apply_filters( 'eventbrite_event_meta', $html, $time, $venue, $organizer, $details );
}
endif;

if ( ! function_exists( 'eventbrite_event_time' ) ) :
/**
 * Return an event's time.
 *
 * @uses   eventbrite_is_multiday_event()
 * @uses   mysql2date()
 * @uses   eventbrite_event_end()
 * @uses   esc_html()
 * @uses   eventbrite_event_start()
 * @return string Event time.
 */
function eventbrite_event_time() {
	// Determine if the end time needs the date included (in the case of multi-day events).
	$end_time = ( eventbrite_is_multiday_event() )
		? mysql2date( 'F j Y, g:i A', eventbrite_event_end()->local )
		: mysql2date( 'g:i A', eventbrite_event_end()->local );

	// Assemble the full event time string.
	$event_time = sprintf(
		_x( '%1$s - %2$s', 'Event date and time. %1$s = start time, %2$s = end time', 'eventbrite_api' ),
		esc_html( mysql2date( 'F j Y, g:i A', eventbrite_event_start()->local ) ),
		esc_html( $end_time )
	);

	return $event_time;
}
endif;

if ( ! function_exists( 'eventbrite_is_multiday_event' ) ) :
/**
 * Determine if an event spans multiple calendar days.
 *
 * @uses   mysql2date()
 * @uses   eventbrite_event_start()
 * @uses   eventbrite_event_end()
 * @return bool True if start and end date are the same, false otherwise.
 */
function eventbrite_is_multiday_event() {
	// Set date variables for comparison.
	$start_date = mysql2date( 'Ymd', eventbrite_event_start()->utc );
	$end_date = mysql2date( 'Ymd', eventbrite_event_end()->utc );

	// Return true if they're different, false otherwise.
	return ( $start_date !== $end_date ) ? true : false;
}
endif;

if ( ! function_exists( 'eventbrite_event_eb_url' ) ) :
/**
 * Give the URL to an event's public viewing page on eventbrite.com.
 *
 * @global $post
 * @uses   apply_filters()
 * @return string URL on eventbrite.com
 */
function eventbrite_event_eb_url() {
	global $post;
	return apply_filters( 'eventbrite_event_eb_url', $post->url );
}
endif;

if ( ! function_exists( 'eventbrite_event_venue' ) ) :
/**
 * Give access to the current event's venue properties: address, resource_uri, id, name, latitude, longitude
 *
 * @global $post
 * @uses   apply_filters()
 * @return object Venue info
 */
function eventbrite_event_venue() {
	global $post;
	return apply_filters( 'eventbrite_event_venue', $post->venue );
}
endif;

if ( ! function_exists( 'eventbrite_event_organizer' ) ) :
/**
 * Give access to the current event's organizer properties: description, logo, resouce_uri, id, name, url, num_past_events, num_future_events
 *
 * @global $post
 * @uses   apply_filters()
 * @return object Organizer info
 */
function eventbrite_event_organizer() {
	global $post;
	return apply_filters( 'eventbrite_event_organizer', $post->organizer );
}
endif;

if ( ! function_exists( 'eventbrite_event_start' ) ) :
/**
 * Give access to the current event's start time: timezone, local, utc
 *
 * @global $post
 * @uses   apply_filters()
 * @return object Start time properties
 */
function eventbrite_event_start() {
	global $post;
	return apply_filters( 'eventbrite_event_start', $post->start );
}
endif;

if ( ! function_exists( 'eventbrite_event_end' ) ) :
/**
 * Give access to the current event's end time: timezone, local, utc
 *
 * @global $post
 * @uses   apply_filters()
 * @return object End time properties
 */
function eventbrite_event_end() {
	global $post;
	return apply_filters( 'eventbrite_event_end', $post->end );
}
endif;

if ( ! function_exists( 'eventbrite_venue_get_archive_link' ) ) :
/**
 * Output a permalink to a venue's "archive" page.
 *
 * @uses   get_permalink()
 * @uses   get_queried_object_id()
 * @uses   eventbrite_event_venue()
 * @uses   sanitize_title()
 * @uses   absint()
 * @return string URL
 */
function eventbrite_venue_get_archive_link() {
	// Get the permalink of the current template page.
	$url = get_permalink( get_queried_object_id() );

	// If the event has a venue set, append it to the URL. http://(page permalink)/venue/(venue name)-(venue ID)/
	if ( ! empty( eventbrite_event_venue()->name ) ) {
		$url .= 'venue/' . sanitize_title( eventbrite_event_venue()->name ) . '-' . absint( eventbrite_event_venue()->id );
	}

	return $url;
}
endif;

if ( ! function_exists( 'eventbrite_edit_post_link' ) ) :
/**
 * Output a link to edit the current event on eventbrite.com.
 *
 * @param  string $text
 * @param  string $before
 * @param  string $after
 * @uses   add_query_arg()
 * @uses   get_the_ID()
 * @uses   esc_url()
 * @uses   esc_html()
 */
function eventbrite_edit_post_link( $text = null, $before = '', $after = '' ) {
	// Assemble the edit URL.
	$url = add_query_arg( array(
			'eid' => get_the_ID(),
			'ref' => 'wporgedit',
		), 'https://eventbrite.com/edit' );

	// Output the formatted link.
	printf( '%s<a href="%s">%s</a>%s',
		$before,
		esc_url( $url ),
		esc_html( $text ),
		$after
	);
}
endif;

if ( ! function_exists( 'eventbrite_ticket_form_widget' ) ) :
/**
 * Insert the Eventbrite ticket form widget.
 *
 * @uses  get_the_ID()
 * @uses  add_query_arg()
 * @uses  esc_url()
 * @uses  esc_attr()
 * @uses  apply_filters()
 */
function eventbrite_ticket_form_widget() {
	// Build the src attribute URL.
	$src = add_query_arg( array(
			'eid' => get_the_ID(),
			'ref' => 'etckt',
	), '//eventbrite.com/tickets-external' );

	// Assemble our ticket info HTML.
	$ticket_html = sprintf( '<div class="eventbrite-widget"><iframe src="%1$s" height="%2$s" width="100%%" frameborder="0" vspace="0" hspace="0" marginheight="5" marginwidth="5" scrolling="auto" allowtransparency="true"></iframe></div>',
		esc_url( $src ),
		esc_attr( eventbrite_get_ticket_form_widget_height() )
	);

	// Output the markup.
	echo $ticket_html;
}
endif;

if ( ! function_exists( 'eventbrite_get_ticket_form_widget_height' ) ) :
/**
 * Calculate the height of the ticket form widget iframe. Not perfect, but avoids having to do it with JS.
 *
 * @global  $post
 * @uses    mysql2date()
 * @uses    apply_filters()
 * @return  int Height of iframe
 */
function eventbrite_get_ticket_form_widget_height() {
	// Set the minimum height (essentially iframe chrome).
	$height = 56;

	// Get tickets for the current event.
	global $post;
	$tickets = $post->tickets;

	// Move along if the event has no ticket information.
	if ( ! $tickets ) {
		return $height + 45;
	}

	// Add height for various ticket table elements.
	$height += 123;

	// Check each ticket.
	foreach ( $tickets as $ticket ) {
		// Add height for each visible ticket type.
		if ( ! $ticket->hidden ) {
			$height += 45;
		}

		// Check if any visible sales are still open.
		if ( ( time() < mysql2date( 'U', $ticket->sales_end ) ) && ! $ticket->hidden ) {
			$sales_open = true;
		}
	}

	// Remove call-to-action spacing if no tickets are still on sale.
	if ( ! isset( $sales_open ) ) {
		$height -= 74;
	}

	return (int) apply_filters( 'eventbrite_ticket_form_widget_height', $height, $post );
}
endif;

/**
 * Check if everything we need is working: Keyring is installed, activated, and has a valid user connection to Eventbrite.
 *
 * @uses   Eventbrite_API::$instance->get_token()
 * @return bool True if a valid user token exists, false otherwise.
 */
function eventbrite_has_active_connection() {
	return ( Eventbrite_Requirements::has_active_connection() ) ? true : false;
}
