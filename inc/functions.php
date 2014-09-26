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
	if ( empty( $post ) && isset( $GLOBALS['event'] ) ) {
		$post = $GLOBALS['event'];
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

	if ( ! $_post )
		return null;

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
 * Filter the Edit Post link to point to the Eventbrite event edit page.
 *
 * @param
 * @uses
 * @return
 */
function eventbrite_event_edit_link( $link, $post_id, $text ) {
	// kwight: blame get_edit_post_link()
	return $link;
}
//add_filter( 'edit_post_link', 'eventbrite_event_edit_link', 10, 3 );

/**
 * Replace featured images with the Eventbrite event logo.
 *
 * @param
 * @uses
 * @return
 */
function eventbrite_event_image_url( $html, $post_id ) {
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
add_filter( 'post_thumbnail_html', 'eventbrite_event_image_url', 9, 2 );

/**
 * Adjust classes for Event <article>s.
 *
 * @param
 * @uses
 * @return
 */
function eventbrite_post_classes( $classes ) {
	if ( is_eventbrite_event() ) {
		global $post;
		if ( isset( $post->logo_url ) ) {
			$classes[] = 'has-post-thumbnail';
		}
	}

	return $classes;
}
add_filter( 'post_class', 'eventbrite_post_classes' );
