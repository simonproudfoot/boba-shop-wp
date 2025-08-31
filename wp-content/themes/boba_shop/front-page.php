<?php get_header(); ?>
<?php include(locate_template('template-parts/page/frontpage-hero.php')); ?>
<main>

    <section>
        <div class="max-w-8xl relative mx-auto px-4 md:px-12">
            <?php 
            $filter_style = 'yellow';
            include(locate_template('template-parts/components/shop-filters.php')); 
            ?>
            <?php
            // Fetch all products to be filtered and sorted by Vue
            $args = array(
                'post_type' => 'shop',
                'posts_per_page' => -1,
            );
            $query = new WP_Query($args);
            if (!$query->have_posts()) {
                error_log('No posts found for post_type "shop". Check if the custom post type is registered correctly.');
            }
            if ($query->have_posts()) :
                // Create an array to store products data for Vue
                $products = array();
                while ($query->have_posts()) : $query->the_post();
                    $price = get_post_meta(get_the_ID(), 'price', true);
                    $url = get_the_permalink();
                    $stock_level = get_post_meta(get_the_ID(), 'stock', true) ? get_post_meta(get_the_ID(), 'stock', true) : 0;
                    $sold_out = get_post_meta(get_the_ID(), 'sold_out', true) ? true : false;
                    // Get product category
                    $categories = get_the_terms(get_the_ID(), 'product_category');
                    $category_slug = '';
                    $category_name = '';
                    if ($categories && !is_wp_error($categories)) {
                        $category_name = $categories[0]->name;
                        $category_slug = $categories[0]->slug;
                    }
                    // Add product data to array
                    $products[] = array(
                        'id' => get_the_ID(),
                        'title' => get_the_title(),
                        'image' => get_the_post_thumbnail_url(get_the_ID(), 'full'),
                        'price' => (float)$price,
                        'stockLevel' => (int)$stock_level,
                        'sold_out' => $sold_out,
                        'category' => $category_name,
                        'category_slug' => $category_slug,
                        'date' => get_the_date('Y-m-d H:i:s'),
                        'url' => get_the_permalink(),
                    );
                endwhile;
                wp_reset_postdata();
                // Output the product data as a JSON object for Vue
            ?>
                <script>
                    var shopProducts = <?php echo json_encode($products); ?>;
                </script>
                <!-- Vue template for filtered and sorted products -->
                <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4  gap-7 gap-y-10 md:gap-6 md:gap-y-12" v-if="filteredProducts.length > 0">
                    <template v-for="(product, i) in filteredProducts" :key="product.id">

                        <productcard
                            :url="product.url"
                            :index="i"
                            :product-id="product.id"
                            :stock-level="product.stockLevel"
                            :title="product.title"
                            :image="product.image"
                            :price="product.price"
                            :category="product.category"
                            :sold_out="product.sold_out"
                            scytheimg="<?php echo esc_url(get_theme_file_uri('assets/img/scythe.png')); ?>"
                            handopenimg="<?php echo esc_url(get_theme_file_uri('assets/img/hand-open.png')); ?>"
                            handclosingimg="<?php echo esc_url(get_theme_file_uri('assets/img/hand-closed.png')); ?>" />
                    </template>
                </div>
                <div v-else class="col-span-full py-8 text-center">
                    <p>No products match your selected filters. Please try selecting different categories.</p>
                </div>
            <?php
            else :
                echo '<p class="text-center col-span-full py-8">No products found. Please add some products to display here.</p>';
            endif;
            ?>
        </div>
    </section>
</main>
<?php get_footer(); ?>