<?php
/**
 * Template Name: Eventbrite Events
 */

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
			<header class="page-header">
				<h1 class="page-title">
					<?php esc_html_e( 'Eventbrite Events', 'eventbrite-api' ); ?>
				</h1>
			</header><!-- .page-header -->

			<?php
				// Set up and call our Eventbrite query.
				$events = new Eventbrite_Query( apply_filters( 'eventbrite_query_args', array(
					// 'display_private' => false, // boolean
					// 'limit' => null,            // integer
					// 'organizer' => null,        // string
					// 'p' => null,                // integer
					// 'post__not_in' => null,     // array of integers
					// 'venue' => null,            // string
				) ) );

				if ( $events->have_posts() ) :
					while ( $events->have_posts() ) : $events->the_post();

						// If the active theme has an Eventbrite content template part, use it.
						if ( eventbrite_has_event_template_part() ) {
							get_template_part( 'content', 'eventbrite' );
						}

						// Looks like we'll need to use our own.
						else {
							include( 'eventbrite-content.php' );
						}

					endwhile;

					// Previous/next post navigation.
					eventbrite_paging_nav( $events );

				else :
					// If no content, include the "No posts found" template.
					get_template_part( 'content', 'none' );

				endif;

				// Return $post to its rightful owner.
				wp_reset_postdata();
			?>

		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
