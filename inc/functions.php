<?php
/**
 * Eventbrite functions.
 *
 * @package Eventbrite_API
 */

/**
 * Get an array of Eventbrite events, in the format expected by Eventbrite_Post
 *
 * @param
 * @uses
 * @return
 */
function eventbrite_get_events( $params = array(), $force = false ) {
	return eventbrite()->get_user_owned_events( $params, $force );
}

/**
 * Retrieves post data given a post ID or post object.
 *
 * See {@link sanitize_post()} for optional $filter values. Also, the parameter
 * $post, must be given as a variable, since it is passed by reference.
 *
 * @since 1.5.1$keys
 *
 * @param int|WP_Post $post   Optional. Post ID or post object. Defaults to global $post.
 * @param string      $output Optional, default is Object. Accepts OBJECT, ARRAY_A, or ARRAY_N.
 *                            Default OBJECT.
 * @param string      $filter Optional. Type of filter to apply. Accepts 'raw', 'edit', 'db',
 *                            or 'display'. Default 'raw'.
 * @return WP_Post|null WP_Post on success or null on failure.
 */
function eventbrite_get_event( $post = null ) {
	if ( empty( $post ) ) {
		return null;
	}

	if ( is_a( $post, 'Eventbrite_Post' ) ) {
		$_post = $post;
	} elseif ( is_object( $post ) ) {
		// if ( empty( $post->filter ) ) {
		// 	$_post = sanitize_post( $post, 'raw' );
		// 	$_post = new WP_Post( $_post );
		// } elseif ( 'raw' == $post->filter ) {
		// 	$_post = new WP_Post( $post );
		// } else {
			$_post = Eventbrite_Post::get_instance( $post->ID );
		//}
	} else {
		$_post = Eventbrite_Post::get_instance( $post );
	}

	if ( ! $_post ) {
		return null;
	}

	// $_post = $_post->filter( $filter );

	// if ( $output == ARRAY_A )
	// 	return $_post->to_array();
	// elseif ( $output == ARRAY_N )
	// 	return array_values( $_post->to_array() );

	return $_post;
}

/**
 * Determine if a given object, given ID, or the current post is an Eventbrite event.
 *
 * @param
 * @uses
 * @return
 */
function is_eventbrite_event( $post = null ) {
	if ( ! $post ) {
		global $post;
	}

	if ( is_a( $post, 'Eventbrite_Post' ) ) {
		return true;
	}

	if ( eventbrite_get_event( $post ) ) {
		return true;
	}

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
