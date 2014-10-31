<?php
/**
 * The Template for displaying all single Eventbrite events.
 */

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
			<?php
				// Get our event based on the ID passed by query variable.
				$event = new Eventbrite_Query( array( 'p' => get_query_var( 'eventbrite_id' ) ) );

				if ( $event->have_posts() ) :
					while ( $event->have_posts() ) : $event->the_post();

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
					eventbrite_post_nav( $event );

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
