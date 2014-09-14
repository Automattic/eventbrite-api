<?php
/**
 * Eventbrite Query class.
 * Modeled on WP_Query, allowing developers to work with familiar terms and loop conventions.
 *
 * @package Eventbrite_API
 */
class Eventbrite_Query {

	/**
	 * Query vars set by the user
	 *
	 * @access public
	 * @var array
	 */
	public $query;

	/**
	 * Query vars, after parsing
	 *
	 * @access public
	 * @var array
	 */
	public $query_vars = array();

	/**
	 * Holds the data for a single object that is queried.
	 *
	 * Holds the contents of a post, page, category, attachment.
	 *
	 * @since 1.5.0
	 * @access public
	 * @var object|array
	 */
	public $queried_object;

	/**
	 * The ID of the queried object.
	 *
	 * @since 1.5.0
	 * @access public
	 * @var int
	 */
	public $queried_object_id;

	/**
	 * Get post database query.
	 *
	 * @access public
	 * @var string
	 */
	public $request;

	/**
	 * List of posts.
	 *
	 * @access public
	 * @var array
	 */
	public $posts;

	/**
	 * Query vars set by the user
	 *
	 * @access public
	 * @var int
	 */
	public $post_count;

	/**
	 * Index of the current event in the loop.
	 *
	 * @access public
	 * @var int
	 */
	public $current_post = -1;

	/**
	 * The current event.
	 *
	 * @access public
	 * @var WP_Post
	 */
	public $post;

	/**
	 * The amount of found posts for the current query.
	 *
	 * If limit clause was not used, equals $post_count.
	 *
	 * @access public
	 * @var int
	 */
	public $found_posts = 0;

	/**
	 * The amount of pages.
	 *
	 * @access public
	 * @var int
	 */
	public $max_num_pages = 0;

	/**
	 * Initiates object properties and sets default values.
	 *
	 * @access public
	 */
	public function init() {
		unset($this->posts);
		unset($this->query);
		$this->query_vars = array();
		unset($this->queried_object);
		unset($this->queried_object_id);
		$this->post_count = 0;
		$this->current_post = -1;
		//$this->in_the_loop = false;
		unset( $this->request );
		unset( $this->post );
		//unset( $this->comments );
		//unset( $this->comment );
		//$this->comment_count = 0;
		//$this->current_comment = -1;
		$this->found_posts = 0;
		$this->max_num_pages = 0;
		//$this->max_num_comment_pages = 0;

		//$this->init_query_flags();
	}

	/**
	 * Reparse the query vars.
	 *
	 * @access public
	 */
	public function parse_query_vars() {
		$this->parse_query();
	}

	public function parse_query( $query =  '' ) {
	}

	/**
	 * Retrieve the posts based on query variables.
	 *
	 * @access public
	 * @return array List of posts.
	 */
	public function get_posts() {
		//global $wpdb;

		//$this->parse_query();

		//$this->posts = $wpdb->get_results( $this->request );
		$this->posts = eventbrite_request_events();

		$this->set_found_posts();

		// Ensure that any posts added/modified via one of the filters above are
		// of the type WP_Post and are filtered.
		if ( $this->posts ) {
			$this->post_count = count( $this->posts );

			$this->posts = array_map( 'eventbrite_get_event', $this->posts );

			// if ( $q['cache_results'] )
			// 	update_post_caches($this->posts, $post_type, $q['update_post_term_cache'], $q['update_post_meta_cache']);

			$this->post = reset( $this->posts );
		} else {
			$this->post_count = 0;
			$this->posts = array();
		}

		return $this->posts;
	}

	/**
	 * Set up the amount of found posts and the number of pages (if limit clause was used)
	 * for the current query.
	 *
	 * @access private
	 */
	private function set_found_posts() {
		$this->found_posts = count( $this->posts );
	}

	/**
	 * Set up the next post and iterate current post index.
	 *
	 * @access public
	 *
	 * @return WP_Post Next post.
	 */
	public function next_post() {

		$this->current_post++;

		$this->post = $this->posts[$this->current_post];
		return $this->post;
	}

	/**
	 * Sets up the current post.
	 *
	 * Retrieves the next post, sets up the post, sets the 'in the loop'
	 * property to true.
	 *
	 * @access public
	 * @uses $post
	 * @uses do_action_ref_array() Calls 'loop_start' if loop has just started
	 */
	public function the_post() {
		global $post;
		//$this->in_the_loop = true;

		//if ( $this->current_post == -1 ) // loop has just started
			/**
			 * Fires once the loop is started.
			 *
			 * @since 2.0.0
			 *
			 * @param WP_Query &$this The WP_Query instance (passed by reference).
			 */
			//do_action_ref_array( 'loop_start', array( &$this ) );

		$post = $this->next_post();
		setup_postdata( $post );
	}

	/**
	 * Whether there are more posts available in the loop.
	 *
	 * Calls action 'loop_end', when the loop is complete.
	 *
	 * @access public
	 * @uses do_action_ref_array() Calls 'loop_end' if loop is ended
	 *
	 * @return bool True if posts are available, false if end of loop.
	 */
	public function have_posts() {
		if ( $this->current_post + 1 < $this->post_count ) {
			return true;
		} elseif ( $this->current_post + 1 == $this->post_count && $this->post_count > 0 ) {
			/**
			 * Fires once the loop has ended.
			 *
			 * @since 2.0.0
			 *
			 * @param WP_Query &$this The WP_Query instance (passed by reference).
			 */
			//do_action_ref_array( 'loop_end', array( &$this ) );
			// Do some cleaning up after the loop
			$this->rewind_posts();
		}

		//$this->in_the_loop = false;
		return false;
	}

	/**
	 * Rewind the posts and reset post index.
	 *
	 * @access public
	 */
	public function rewind_posts() {
		$this->current_post = -1;
		if ( $this->post_count > 0 ) {
			$this->post = $this->posts[0];
		}
	}

	/**
	 * Sets up the WordPress query by parsing query string.
	 *
	 * @access public
	 *
	 * @param string $query URL query string.
	 * @return array List of posts.
	 */
	public function query( $query ) {
		$this->init();
		$this->query = $this->query_vars = wp_parse_args( $query );
		// kwight: empty $this->query
		return $this->get_posts();
	}

	/**
	 * Retrieve queried object.
	 *
	 * If queried object is not set, then the queried object will be set from
	 * the category, tag, taxonomy, posts page, single post, page, or author
	 * query variable. After it is set up, it will be returned.
	 *
	 * @access public
	 *
	 * @return object
	 */
	public function get_queried_object() {
		if ( isset($this->queried_object) )
			return $this->queried_object;

		$this->queried_object = null;
		$this->queried_object_id = 0;

		// if ( $this->is_category || $this->is_tag || $this->is_tax ) {
		// 	if ( $this->is_category ) {
		// 		if ( $this->get( 'cat' ) ) {
		// 			$term = get_term( $this->get( 'cat' ), 'category' );
		// 		} elseif ( $this->get( 'category_name' ) ) {
		// 			$term = get_term_by( 'slug', $this->get( 'category_name' ), 'category' );
		// 		}
		// 	} elseif ( $this->is_tag ) {
		// 		if ( $this->get( 'tag_id' ) ) {
		// 			$term = get_term( $this->get( 'tag_id' ), 'post_tag' );
		// 		} elseif ( $this->get( 'tag' ) ) {
		// 			$term = get_term_by( 'slug', $this->get( 'tag' ), 'post_tag' );
		// 		}
		// 	} else {
		// 		$tax_query_in_and = wp_list_filter( $this->tax_query->queries, array( 'operator' => 'NOT IN' ), 'NOT' );
		// 		$query = reset( $tax_query_in_and );

		// 		if ( $query['terms'] ) {
		// 			if ( 'term_id' == $query['field'] ) {
		// 				$term = get_term( reset( $query['terms'] ), $query['taxonomy'] );
		// 			} else {
		// 				$term = get_term_by( $query['field'], reset( $query['terms'] ), $query['taxonomy'] );
		// 			}
		// 		}
		// 	}

		// 	if ( ! empty( $term ) && ! is_wp_error( $term ) )  {
		// 		$this->queried_object = $term;
		// 		$this->queried_object_id = (int) $term->term_id;

		// 		if ( $this->is_category && 'category' === $this->queried_object->taxonomy )
		// 			_make_cat_compat( $this->queried_object );
		// 	}
		// } elseif ( $this->is_post_type_archive ) {
		// 	$post_type = $this->get( 'post_type' );
		// 	if ( is_array( $post_type ) )
		// 		$post_type = reset( $post_type );
		// 	$this->queried_object = get_post_type_object( $post_type );
		// } elseif ( $this->is_posts_page ) {
		// 	$page_for_posts = get_option('page_for_posts');
		// 	$this->queried_object = get_post( $page_for_posts );
		// 	$this->queried_object_id = (int) $this->queried_object->ID;
		// } elseif ( $this->is_singular && ! empty( $this->post ) ) {
		// 	$this->queried_object = $this->post;
		// 	$this->queried_object_id = (int) $this->post->ID;
		// } elseif ( $this->is_author ) {
		// 	$this->queried_object_id = (int) $this->get('author');
		// 	$this->queried_object = get_userdata( $this->queried_object_id );
		// }

		return $this->queried_object;
	}

	/**
	 * Retrieve ID of the current queried object.
	 *
	 * @access public
	 *
	 * @return int
	 */
	public function get_queried_object_id() {
		$this->get_queried_object();

		if ( isset($this->queried_object_id) ) {
			return $this->queried_object_id;
		}

		return 0;
	}

	/**
	 * Constructor.
	 *
	 * Sets up the WordPress query, if parameter is not empty.
	 *
	 * @access public
	 *
	 * @param string $query URL query string.
	 * @return WP_Query
	 */
	public function __construct( $query = '' ) {
		// if ( ! empty( $query ) ) {
		// 	$this->query( $query );
		// }
		$this->query( $query );

	}
}
