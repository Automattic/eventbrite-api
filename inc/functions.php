<?php
/**
 * Eventbrite functions.
 *
 * @package Eventbrite_API
 */

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

/**
 * Retrieves event data given an event ID.
 *
 * @param  int $id
 * @param  bool $force Force an API call, don't use cache
 * @uses   Eventbrite_Manager::get_event()
 * @return object Event
 */
function eventbrite_get_event( $id = false, $force = false ) {
	// Bail if nothing is passed in.
	if ( empty( $id ) ) {
		return null;
	}

	// Retrieve and return our event.
	return eventbrite()->get_event( $id, $force );
}

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
	<nav class="navigation paging-navigation" role="navigation">
		<h1 class="screen-reader-text"><?php esc_html_e( 'Events navigation', 'eventbrite' ); ?></h1>
		<div class="nav-links">
			<?php echo paginate_links( apply_filters( 'eventbrite_paginate_links_args', $args, $events ) ); ?>
		</div><!-- .pagination -->
	</nav><!-- .navigation -->
<?php }

/**
 * Previous/Next post navigation on single views.
 *
 * @param
 * @uses
 * @return
 */
function eventbrite_post_nav( $event ) {
}

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

if ( ! function_exists( 'eventbrite_entry_footer' ) ) :
/**
 * Output entry footer info. Just Edit link for now, but could include Event Type, Topic, and Sub-Topic.
 *
 * @uses edit_post_link()
 */
function eventbrite_entry_footer() {
	// Edit link is filtered to point to the event's edit page on eventbrite.com.
	eventbrite_edit_post_link( __( 'Edit', 'eventbrite_api' ), '<span class="edit-link">', '</span>' );
}
endif;

/**
 * Output a link to edit the current event on eventbrite.com.
 *
 * @param
 * @uses
 * @return
 */
function eventbrite_edit_post_link( $text = null, $before = '', $after = '' ) {
	// Assemble the edit URL.
	$url = add_query_arg( array( 'eid' => get_the_ID() ), 'https://eventbrite.com/edit' );

	// Output the formatted link.
	printf( '%s<a href="%s">%s</a>%s',
		$before,
		esc_url( $url ),
		esc_html( $text ),
		$after
	);
}

/**
 * Insert the Eventbrite ticket form widget into an event single view.
 *
 * @param int $event_id
 * @uses  add_query_arg()
 * @uses  esc_url()
 * @uses  esc_attr()
 * @uses  apply_filters()
 */
function eventbrite_ticket_form_widget( $content ) {
	// Bail if we're not on an event single view.
	if ( ! eventbrite_is_single() ) {
		return $content;
	}

	// Build the src attribute URL.
	$args = array(
			'eid' => get_the_ID(),
			'ref' => 'etckt',
	);
	$src = add_query_arg( $args, '//eventbrite.com/tickets-external' );

	// Assemble our ticket info HTML.
	$ticket_html = sprintf( '<div class="eventbrite-widget"><iframe src="%1$s" height="%2$s" width="100%%" frameborder="0" vspace="0" hspace="0" marginheight="5" marginwidth="5" scrolling="auto" allowtransparency="true"></iframe></div>',
		esc_url( $src ),
		esc_attr( apply_filters( 'eventbrite_ticket_widget_height', 215 ) )
	);

	// Return the combined markup.
	return $content . $ticket_html;
}
add_filter( 'the_content', 'eventbrite_ticket_form_widget' );
