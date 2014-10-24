<?php
/**
 * Eventbrite functions.
 *
 * @package Eventbrite_API
 */

/**
 * Get an array of Eventbrite events, in the format expected by Eventbrite_Event
 *
 * @param
 * @uses
 * @return
 */
function eventbrite_get_events( $params = array(), $force = false ) {
	return eventbrite()->get_user_owned_events( $params, $force );
}

/**
 * Retrieves event data given an event ID.
 *
 * @param
 * @uses
 * @return
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
 * @param
 * @uses
 * @return
 */
function is_eventbrite_event( $post = null ) {
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
 * @uses get_query_var()
 * @uses esc_html_e()
 * @uses paginate_links()
 * @uses apply_filters()
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
 * @uses get_theme_support()
 * @return object Eventbrite theme support arguments.
 */
function eventbrite_get_support_args() {
	$support = get_theme_support( 'eventbrite' );
	return (object) $support[0];
}
