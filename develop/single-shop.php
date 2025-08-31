<?php get_header();

// Get product data
$post_id = get_the_ID();
$title = get_the_title();
$price = get_field('price', $post_id);
$stock = get_field('stock', $post_id) ? get_field('stock', $post_id) : 0;
$sold_out = get_field('sold_out', $post_id) ? true : false;
$is_new = get_field('is_new', $post_id) ? true : false;
$image = get_the_post_thumbnail_url($post_id, 'full');
$description = get_the_content();

// Get color/size variants
$color_variations = get_field('color_variations', $post_id);
$variants_data = array();
if ($color_variations) {
    foreach ($color_variations as $variant) {
        // If this color has sizes_stock, create a variant for each size
        if (!empty($variant['sizes_stock']) && is_array($variant['sizes_stock'])) {
            foreach ($variant['sizes_stock'] as $size_variant) {
                $variants_data[] = array(
                    'color' => $variant['color'],
                    'color_name' => $variant['color_name'],
                    'image' => $variant['variation_image']['url'],
                    'size' => isset($size_variant['size']) ? $size_variant['size'] : '',
                    'stock' => isset($size_variant['stock']) ? (int)$size_variant['stock'] : 0
                );
            }
        } else {
            // No sizes, just color
            $variants_data[] = array(
                'color' => $variant['color'],
                'color_name' => $variant['color_name'],
                'image' => $variant['variation_image']['url'],
                'size' => '',
                'stock' => isset($variant['stock']) ? (int)$variant['stock'] : 0
            );
        }
    }
}

// Get sizes
$sizes = get_field('sizes', $post_id);

// Get product category
$categories = get_the_terms($post_id, 'product_category');
$category = '';
if ($categories && !is_wp_error($categories)) {
    $category = $categories[0]->name;
}

$has_variants = !empty($variants_data);
?>

<section class="mt-16 md:mt-24">
    <div class="container-pad !mb-0 pt-0 flex flex-col lg:flex-row md:gap-x-[56px] xl:gap-x-[256px]">
        <!-- Product details with Vue component -->
        <div class="w-full">
            <?php if ($is_new) : ?>
                <div class="inline-block bg-green-500 text-white px-2 py-1 text-sm mb-4 rounded">New</div>
            <?php endif; ?>

            <productpage
                handopenimg="<?php echo esc_url(get_theme_file_uri('assets/img/hand-open.png')); ?>"
                handclosingimg="<?php echo esc_url(get_theme_file_uri('assets/img/hand-closed.png')); ?>"
                scytheimg="<?php echo esc_url(get_theme_file_uri('assets/img/scythe.png')); ?>"
                image="<?php echo esc_url($image); ?>"
                category="<?php echo esc_attr($category); ?>"
                price="<?php echo esc_attr($has_variants ? '' : $price); ?>"
                title="<?php echo esc_attr($title); ?>"
                description="<?php echo htmlspecialchars($description); ?>"
                :stock-level="<?php echo esc_attr($has_variants ? 0 : $stock); ?>"
                :sold_out="<?php echo $sold_out ? 'true' : 'false'; ?>"
                product-id="<?php echo esc_attr($post_id); ?>"
                :color-variations='<?php echo json_encode($variants_data); ?>'
                :sizes='<?php echo json_encode($sizes ? $sizes : []); ?>'
                :has-variants="<?php echo $has_variants ? 'true' : 'false'; ?>">
            </productpage>
        </div>
    </div>
</section>

<!-- Related Products Section -->
<section class="mt-16 mb-24 px-4">
    <div class="w-full mx-auto max-w-7xl">
        <h2 class="text-3xl font-heading font-bold mb-8 uppercase">You might also like</h2>

        <?php
        // Get related products by category
        $related_args = array(
            'post_type' => 'shop',
            'posts_per_page' => 3,
            'post__not_in' => array($post_id),
        );

        $related_query = new WP_Query($related_args);

        if ($related_query->have_posts()) : ?>
            <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4 gap-7 gap-y-10 md:gap-6">
                <?php
                $index = 0;
                while ($related_query->have_posts()) : $related_query->the_post();
                    $rel_id = get_the_ID();
                    $rel_price = get_field('price', $rel_id);
                    $rel_stock = get_field('stock', $rel_id) ? get_field('stock', $rel_id) : 0;
                    $rel_sold_out = get_field('sold_out', $rel_id) ? true : false;
                    $rel_is_new = get_field('is_new', $rel_id) ? true : false;
                    $post_url = get_permalink();

                    // Get product category
                    $rel_categories = get_the_terms($rel_id, 'product_category');
                    $rel_category = '';
                    if ($rel_categories && !is_wp_error($rel_categories)) {
                        $rel_category = $rel_categories[0]->name;
                    }

                    // Get color/size variants for related product
                    $rel_color_variations = get_field('color_variations', $rel_id);
                    $rel_variants_data = array();
                    if ($rel_color_variations) {
                        foreach ($rel_color_variations as $variant) {
                            if (!empty($variant['sizes_stock']) && is_array($variant['sizes_stock'])) {
                                foreach ($variant['sizes_stock'] as $size_variant) {
                                    $rel_variants_data[] = array(
                                        'color' => $variant['color'],
                                        'color_name' => $variant['color_name'],
                                        'image' => $variant['variation_image']['url'],
                                        'size' => isset($size_variant['size']) ? $size_variant['size'] : '',
                                        'stock' => isset($size_variant['stock']) ? (int)$size_variant['stock'] : 0
                                    );
                                }
                            } else {
                                $rel_variants_data[] = array(
                                    'color' => $variant['color'],
                                    'color_name' => $variant['color_name'],
                                    'image' => $variant['variation_image']['url'],
                                    'size' => '',
                                    'stock' => isset($variant['stock']) ? (int)$variant['stock'] : 0
                                );
                            }
                        }
                    }
                    $rel_has_variants = !empty($rel_variants_data);
                ?>
                    <div class="relative">
                        <productcard
                            url="<?php echo esc_url($post_url); ?>"
                            index="<?php echo $index++; ?>"
                            product-id="<?php echo esc_attr($rel_id); ?>"
                            :stock-level="<?php echo esc_attr($rel_has_variants ? 0 : $rel_stock); ?>"
                            :sold_out="<?php echo $rel_sold_out ? 'true' : 'false'; ?>"
                            title="<?php echo esc_attr(get_the_title()); ?>"
                            image="<?php echo esc_url(get_the_post_thumbnail_url($rel_id, 'full')); ?>"
                            price="<?php echo esc_attr($rel_price); ?>"
                            category="<?php echo esc_attr($rel_category); ?>"
                            :color-variations='<?php echo json_encode($rel_variants_data); ?>'
                            :has-variants="<?php echo $rel_has_variants ? 'true' : 'false'; ?>"
                            scytheimg="<?php echo esc_url(get_theme_file_uri('assets/img/scythe.png')); ?>"
                            handopenimg="<?php echo esc_url(get_theme_file_uri('assets/img/hand-open.png')); ?>"
                            handclosingimg="<?php echo esc_url(get_theme_file_uri('assets/img/hand-closed.png')); ?>">
                        </productcard>

                        <?php if ($rel_is_new) : ?>
                            <div class="absolute top-4 right-4 bg-green-500 text-white px-2 py-1 text-sm rounded z-10">New</div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else : ?>
            <p>No related products found.</p>
        <?php endif;
        wp_reset_postdata();
        ?>
    </div>
</section>

<?php get_footer(); ?>