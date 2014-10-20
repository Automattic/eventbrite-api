<?php

/**
 * 
 *
 * @param
 * @uses
 * @return
 */
function eventbrite_pagination_rewrite( $wp_rules ) {
	// Get all pages that are using the Eventbrite page template.
	$support = get_theme_support('eventbrite');
	$template = ( isset( $support[0]['template'] ) ) ? $support[0]['template'] : 'eventbrite.php';
	$pages = get_pages(array(
		'meta_key' => '_wp_page_template',
		'meta_value' => $template,
	));

	// If any pages are using the template, add a rewrite rule for each of them.
	if ( $pages ) {
		foreach ( $pages as $page ) {
			$eb_rules_key = sprintf( '%s/page/?([0-9]{1,})/?$', $page->post_name );
			$eb_rules[$eb_rules_key] = sprintf( 'index.php?pagename=%s&paged=$matches[1]', $page->post_name );
		}
	}

	// Combine all rules and return.
	$rules = array_merge( $eb_rules + $wp_rules );
	return $rules;
}
//add_filter( 'rewrite_rules_array', 'eventbrite_pagination_rewrite' );