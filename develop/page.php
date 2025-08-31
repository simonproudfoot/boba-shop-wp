<?php get_header(); ?>


<main>
    <div class="container pt-24 md:pt-52">
        <div class="">
            <div class="mx-auto max-w-3xl">
                <h1 class="text-6xl font-heading mb-12"><?php the_title(); ?></h1>
                <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
                        <div class="post-content prose">
                            <?php the_content(); ?>
                        </div>
                    <?php endwhile;
                else : ?>
                    <p><?php _e('Sorry, no posts matched your criteria.'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>


<?php get_footer(); ?>