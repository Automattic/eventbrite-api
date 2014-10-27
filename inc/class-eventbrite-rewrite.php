<?php
/**
 * Eventbrite_Rewrite class, for handling Eventbrite rewrite rules and template redirection.
 */

 class Eventbrite_Rewrite {
 	/**
	 * Our constructor.
	 *
	 * @uses add_filter()
	 * @uses add_action()
	 */
 	public function __construct() {
 		// Register hooks.
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_filter( 'rewrite_rules_array', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'page_attributes_dropdown_pages_args', array( $this, 'inject_page_template' ) );
		add_filter( 'wp_insert_post_data', array( $this, 'inject_page_template' ) );
		add_action( 'template_include', array( $this, 'check_templates' ) );
 	}

	/**
	 * Add Eventbrite-specific query vars so they are recognized by WP_Query.
	 *
	 * @param array $query_vars
	 * @return array Query vars
	 */
	function add_query_vars( $query_vars ) {
		$query_vars[] = 'eventbrite_id';

		return $query_vars;
	}

	/**
	 * Add rewrite rules for event single views.
	 *
	 * @param array $wp_rules WordPress rewrite rules.
	 * @uses eventbrite_get_support_args()
	 * @uses get_pages()
	 * @return array All rewrite rules (WordPress and Eventbrite rules combined).
	 */
	public function add_rewrite_rules( $wp_rules ) {
		$eb_rules = array();

		// Get all pages that are using the Eventbrite page template.
		$template = ( isset( eventbrite_get_support_args()->index ) ) ? eventbrite_get_support_args()->index : 'eventbrite-index.php';
		$pages = get_pages(array(
			'meta_key' => '_wp_page_template',
			'meta_value' => $template,
		));

		// If any pages are using the template, add rewrite rules for each of them with an event ID for single views.
		if ( $pages ) {
			foreach ( $pages as $page ) {
				$eb_rules_key = sprintf( '(%s)/[0-9a-z-]+(\d{11})/?$', $page->post_name );
				$eb_rules[$eb_rules_key] = 'index.php?pagename=$matches[1]&eventbrite_id=$matches[2]';
			}
		}

		// Combine all rules and return.
		$rules = array_merge( $eb_rules + $wp_rules );
		return $rules;
	}

	/**
	 * Force our way into the theme's cache of page template listings.
	 * Based on code by Harri Bell-Thomas: https://github.com/wpexplorer/page-templater
	 *
	 * @param array $params
	 * @uses eventbrite_get_support_args()
	 * @uses WP_Theme::get_page_templates()
	 * @uses wp_cache_delete()
	 * @uses wp_cache_add()
	 * @return array $params
	 */
	public function inject_page_template( $params ) {
		if ( ! isset( eventbrite_get_support_args()->index ) ) {
			// Create the key used for the themes cache
			$cache_key = 'page_templates-' . md5( trailingslashit( get_theme_root() ) . get_stylesheet() );

			// Retrieve the cache listing. Prepare an empty array if it's empty.
			$templates = wp_get_theme()->get_page_templates();
			if ( empty( $templates ) ) {
			        $templates = array();
			}

			// Remove the original cache.
			wp_cache_delete( $cache_key , 'themes');

			// Add our template to any existing templates.
			$templates['eventbrite-index.php'] = 'Eventbrite Events';

			// Update the cache that includes our template.
			wp_cache_add( $cache_key, $templates, 'themes', 1800 );
		}

		return $params;
	}

	/**
	 * Check if we need to use Eventbrite page templates, either from the theme or our plugin.
	 *
	 * @param string $template
	 * @uses get_query_var()
	 * @uses eventbrite_get_support_args()
	 * @uses esc_url()
	 * @uses trailingslashit()
	 * @uses get_stylesheet_directory()
	 * @uses plugin_dir_path()
	 * @uses get_post_meta()
	 * @uses get_the_ID()
	 * @return string Template file name.
	 */
	public function check_templates( $template ) {
		// If we have an 'eventbrite_id' query var, we're dealing with an event single view.
		if ( get_query_var( 'eventbrite_id' ) ) {
			$template = ( isset( eventbrite_get_support_args()->single ) )
				// The theme defined a single event template, let's use that.
				? esc_url( trailingslashit( get_stylesheet_directory() ) . eventbrite_get_support_args()->single )
				// No template specified by the theme, so use the one included with the plugin.
				: plugin_dir_path( __DIR__ ) . 'tmpl/eventbrite-single.php';
		}

		// Check if we have a page using the plugin's event listing template. An events template defined in the theme will kick in normally.
		elseif ( 'eventbrite-index.php' == get_post_meta( get_the_ID(), '_wp_page_template', true ) ) {
			$template = plugin_dir_path( __DIR__ ) . 'tmpl/eventbrite-index.php';
		}

		// We have nothing Eventbrite-related, so go with the Template Hierarchy.
		return $template;
	}
 }

 new Eventbrite_Rewrite();
