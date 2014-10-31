<?php
/**
 * @package _s
 */
?>

<article id="event-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<?php the_post_thumbnail(); ?>

		<?php if ( eventbrite_is_single() ) : ?>

			<h1 class="entry-title"><?php the_title(); ?></h1>

		<?php else : ?>

			<?php the_title( sprintf( '<h1 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h1>' ); ?>

		<?php endif; ?>

		<div class="entry-meta">
			<?php eventbrite_event_info(); ?>
		</div><!-- .entry-meta -->
	</header><!-- .entry-header -->

	<div class="entry-content">
		<?php the_content(); ?>
	</div><!-- .entry-content -->

	<footer class="entry-footer">
		<?php eventbrite_entry_footer(); ?>
	</footer><!-- .entry-footer -->
</article><!-- #post-## -->
