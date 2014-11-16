<?php
/**
 * Eventbrite_Templates class, for handling Eventbrite template redirection, file includes, and rewrite rules.
 *
 * @package Eventbrite_API
 */

 class Eventbrite_Templates {
	/**
	 * Our constructor.
	 *
	 * @access public
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'body_class', array( $this, 'add_body_classes' ) );
 	}

	/**
	 * Add Eventbrite-specific query vars so they are recognized by WP_Query.
	 *
	 * @access public
	 *
	 * @param array $query_vars
	 * @return array Query vars
	 */
	public function add_query_vars( $query_vars ) {
		$query_vars = array_merge( $query_vars, array( 'eventbrite_id', 'organizer_id', 'venue_id' ) );

		return $query_vars;
	}

	/**
	 * Add rewrite rules for Eventbrite views.
	 *
	 * @access public
	 *
	 * @param  array $wp_rules WordPress rewrite rules.
	 * @uses   eventbrite_get_support_args()
	 * @uses   get_pages()
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

		// If any pages are using the template, add rewrite rules for each of them.
		if ( $pages ) {
			foreach ( $pages as $page ) {
				// Add rules for "author" archives (meaning all events by an organizer).
				$eb_rules_key = sprintf( '(%s)/organizer/[0-9a-z-]+-(\d+)/?$', $page->post_name );
				$eb_rules[$eb_rules_key] = 'index.php?pagename=$matches[1]&organizer_id=$matches[2]';
				$eb_rules_key = sprintf( '(%s)/organizer/[0-9a-z-]+-(\d+)/page/([0-9]{1,})/?$', $page->post_name );
				$eb_rules[$eb_rules_key] = 'index.php?pagename=$matches[1]&organizer_id=$matches[2]&paged=$matches[3]';

				// Add rules for venue archives (meaning all events at a given venue).
				$eb_rules_key = sprintf( '(%s)/venue/[0-9a-z-]+-(\d+)/?$', $page->post_name );
				$eb_rules[$eb_rules_key] = 'index.php?pagename=$matches[1]&venue_id=$matches[2]';
				$eb_rules_key = sprintf( '(%s)/venue/[0-9a-z-]+-(\d+)/page/([0-9]{1,})/?$', $page->post_name );
				$eb_rules[$eb_rules_key] = 'index.php?pagename=$matches[1]&venue_id=$matches[2]&paged=$matches[3]';

				// Add a rule for event single views. Event IDs are 11 digits long (for the foreseeable future).
				$eb_rules_key = sprintf( '(%s)/[0-9a-z-]+-(\d{11})/?$', $page->post_name );
				$eb_rules[$eb_rules_key] = 'index.php?pagename=$matches[1]&eventbrite_id=$matches[2]';
			}
		}

		// Combine all rules and return.
		$rules = array_merge( $eb_rules + $wp_rules );
		return $rules;
	}

	/**
	 * Force our way into the theme's cache of page template listings. Gah.
	 * Based on code by Harri Bell-Thomas: https://github.com/wpexplorer/page-templater
	 *
	 * @access public
	 *
	 * @param  array $params
	 * @uses   eventbrite_get_support_args()
	 * @uses   WP_Theme::get_page_templates()
	 * @uses   wp_cache_delete()
	 * @uses   wp_cache_add()
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
	 * @access public
	 *
	 * @param  string $template
	 * @uses   get_query_var()
	 * @uses   eventbrite_get_support_args()
	 * @uses   esc_url()
	 * @uses   trailingslashit()
	 * @uses   get_stylesheet_directory()
	 * @uses   Eventbrite_Templates::default_theme_activated()
	 * @uses   plugin_dir_path()
	 * @uses   get_template()
	 * @uses   get_post_meta()
	 * @uses   get_the_ID()
	 * @return string Template file name.
	 */
	public function check_templates( $template ) {
		// If we have an 'eventbrite_id' query var, we're dealing with an event single view.
		if ( get_query_var( 'eventbrite_id' ) ) {
			// The theme defined a single event template, let's use that.
			if ( isset( eventbrite_get_support_args()->single ) ) {
				$template = esc_url( trailingslashit( get_stylesheet_directory() ) . eventbrite_get_support_args()->single );
			}

			// A default theme is being used; we've got special templates for those.
			elseif ( $this->default_theme_activated() ) {
				$template = plugin_dir_path( __DIR__ ) . 'tmpl/compat/' . get_template() . '/eventbrite-single.php';
			}

			// No template specified by the theme, and it's not a default theme, so use the one included with the plugin.
			else {
				$template = plugin_dir_path( __DIR__ ) . 'tmpl/eventbrite-single.php';
			}
		}

		// Check if we have a page using the plugin's event listing template. An events template defined in the theme will kick in normally.
		elseif ( 'eventbrite-index.php' == get_post_meta( get_the_ID(), '_wp_page_template', true ) ) {
			// We're using a default theme. We've got special template files for those.
			if ( $this->default_theme_activated() ) {
				$template = plugin_dir_path( __DIR__ ) . 'tmpl/compat/' . get_template() . '/eventbrite-index.php';
			}

			// Nothing in the theme, and it's not a default theme; just use our regular template.
			else {
				$template = plugin_dir_path( __DIR__ ) . 'tmpl/eventbrite-index.php';
			}
		}

		// We have nothing Eventbrite-related, so go with the Template Hierarchy results.
		return $template;
	}

	/**
	 * Enqueue any styles required for default themes.
	 *
	 * @access public
	 *
	 * @uses Eventbrite_Templates::default_theme_activated()
	 * @uses get_template()
	 * @uses plugin_dir_path()
	 * @uses wp_enqueue_style()
	 * @uses plugins_url()
	 */
	public function enqueue_styles() {
		// Bail if we're not using a default theme.
		if ( ! $this->default_theme_activated() ) {
			return;
		}

		// If there's a stylesheet for this default theme, enqueue it.
		$style_rel_path = 'tmpl/compat/' . get_template() . '/eventbrite-style.css';
		if ( file_exists( plugin_dir_path( dirname( __FILE__ ) ) . $style_rel_path ) ) {
			wp_enqueue_style(
				'eventbrite-styles',
				plugins_url( $style_rel_path, dirname( __FILE__ ) )
			);
		}
	}

/**
 * Add body classes when our Eventbrite templates are in use.
 *
 * @access public
 *
 * @param
 * @uses
 * @return
 */
public function add_body_classes( $classes ) {
	// Check if we're loading an Eventbrite single view.
	if ( eventbrite_is_single() ) {
		$classes[] = 'eventbrite-single';
	}

	// Check for an Eventbrite index view, either from our plugin or the theme.
	else {
		// Get any template for the current page being displayed.
		$template = get_post_meta( get_the_ID(), '_wp_page_template', true );

		// Determine possible templates from the plugin and theme.
		$eventbrite_templates = array( 'eventbrite-index.php' );
		if ( ! empty( eventbrite_get_support_args()->index ) ) {
			$eventbrite_templates[] = eventbrite_get_support_args()->index;
		}
		$eventbrite_templates = apply_filters( 'eventbrite_templates', $eventbrite_templates, $template );

		// If there's a match, add the index body class.
		if ( in_array( $template, $eventbrite_templates ) ) {
			$classes[] = 'eventbrite-index';
		}
	}

	return $classes;
}

	/**
	 * Check if a default theme is active.
	 *
	 * @access protected
	 *
	 * @uses   get_template()
	 * @return bool True if a default theme is active, false otherwise.
	 */
	protected function default_theme_activated() {
		// Our supported default themes.
		$default_themes = array(
			'twentythirteen',
			'twentyfourteen',
			'twentyfifteen',
		);

		return in_array( get_template(), $default_themes );
	}
 }

 new Eventbrite_Templates();
