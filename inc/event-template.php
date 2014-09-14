<?php
/**
 * Eventbrite Event Template Functions.
 *
 * @package Eventbrite_API
 */

/**
 * Display the ID of the current item in the WordPress Loop.
 *
 * @since 0.71
 */
function eventbrite_the_ID() {
	echo eventbrite_get_the_ID();
}

/**
 * Retrieve the ID of the current item in the WordPress Loop.
 *
 * @since 2.1.0
 * @uses $post
 *
 * @return int|bool The ID of the current item in the WordPress Loop. False if $post is not set.
 */
function eventbrite_get_the_ID() {
	$post = eventbrite_get_event();
	return ! empty( $post ) ? $post->ID : false;
}

/**
 * Display or retrieve the current post title with optional content.
 *
 * @since 0.71
 *
 * @param string $before Optional. Content to prepend to the title.
 * @param string $after Optional. Content to append to the title.
 * @param bool $echo Optional, default to true.Whether to display or return.
 * @return null|string Null on no title. String if $echo parameter is false.
 */
function eventbrite_the_title($before = '', $after = '', $echo = true) {
	$title = eventbrite_get_the_title();

	if ( strlen($title) == 0 )
		return;

	$title = $before . $title . $after;

	if ( $echo )
		echo $title;
	else
		return $title;
}

 /**
 * Retrieve post title.
 *
 * If the post is protected and the visitor is not an admin, then "Protected"
 * will be displayed before the post title. If the post is private, then
 * "Private" will be located before the post title.
 *
 * @param int|WP_Post $post Optional. Post ID or WP_Post object. Default is global $post.
 * @return string
 */
function eventbrite_get_the_title( $post = 0 ) {
	$post = eventbrite_get_event();

	$title = isset( $post->post_title ) ? $post->post_title : '';
	$id = isset( $post->ID ) ? $post->ID : 0;

	/**
	 * Filter the post title.
	 *
	 * @since 0.71
	 *
	 * @param string $title The post title.
	 * @param int    $id    The post ID.
	 */
	return apply_filters( 'the_title', $title, $id );
}

/**
 * Display the post content.
 *
 * @since 0.71
 *
 * @param string $more_link_text Optional. Content for when there is more text.
 * @param bool $strip_teaser Optional. Strip teaser content before the more text. Default is false.
 */
function eventbrite_the_content() {
	$content = eventbrite_get_the_content();

	/**
	 * Filter the post content.
	 *
	 * @since 0.71
	 *
	 * @param string $content Content of the current post.
	 */
	$content = apply_filters( 'the_content', $content );
	$content = str_replace( ']]>', ']]&gt;', $content );
	echo $content;
}

/**
 * Retrieve the post content.
 *
 * @since 0.71
 *
 * @param string $more_link_text Optional. Content for when there is more text.
 * @param bool $stripteaser Optional. Strip teaser content before the more text. Default is false.
 * @return string
 */
function eventbrite_get_the_content() {
	$post = eventbrite_get_event();

	return $post->post_content;
}
