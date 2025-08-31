<?php
// Create API endpoints

add_action('rest_api_init', function () {
    register_rest_field(['post', 'work'], 'featured_image', [
        'get_callback' => function ($post) {
            $post_id = $post['id'];
            if (!has_post_thumbnail($post_id)) {
                return '';
            }
            $img_id = get_post_thumbnail_id($post_id);
            $full = wp_get_attachment_image_src($img_id, 'full');
            $square_small = wp_get_attachment_image_src($img_id, 'thumbnail-square');
            $square_medium = wp_get_attachment_image_src($img_id, 'thumbnail-square-medium');
            $square_large = wp_get_attachment_image_src($img_id, 'thumbnail-square-large');
            return [
                'full' => $full ? $full[0] : '',
                'sizes' => [
                    'thumbnail-square' => [
                        'url' => $square_small ? $square_small[0] : '',
                        'width' => $square_small ? $square_small[1] : 0
                    ],
                    'thumbnail-square-medium' => [
                        'url' => $square_medium ? $square_medium[0] : '',
                        'width' => $square_medium ? $square_medium[1] : 0
                    ],
                    'thumbnail-square-large' => [
                        'url' => $square_large ? $square_large[0] : '',
                        'width' => $square_large ? $square_large[1] : 0
                    ]
                ]
            ];
        },
        'schema' => [
            'description' => 'Featured image URLs and sizes for different resolutions',
            'type' => ['object', 'string'],
            'properties' => [
                'full' => ['type' => 'string'],
                'sizes' => [
                    'type' => 'object',
                    'properties' => [
                        'thumbnail-square' => [
                            'type' => 'object',
                            'properties' => [
                                'url' => ['type' => 'string'],
                                'width' => ['type' => 'integer']
                            ]
                        ],
                        'thumbnail-square-medium' => [
                            'type' => 'object',
                            'properties' => [
                                'url' => ['type' => 'string'],
                                'width' => ['type' => 'integer']
                            ]
                        ],
                        'thumbnail-square-large' => [
                            'type' => 'object',
                            'properties' => [
                                'url' => ['type' => 'string'],
                                'width' => ['type' => 'integer']
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ]);
});


function register_categories_api_routes()
{
    register_rest_route('work-api/v1', '/categories', array(
        'methods' => 'GET',
        'callback' => 'get_post_type_categories',
        'permission_callback' => '__return_true'
    ));
}
add_action('rest_api_init', 'register_categories_api_routes');
function get_post_type_categories($request)
{
    $post_type = $request->get_param('post_type');
    $categories = array();

    $taxonomies = get_object_taxonomies($post_type, 'objects');

    foreach ($taxonomies as $taxonomy) {
        $terms = get_terms([
            'taxonomy' => $taxonomy->name,
            'hide_empty' => true,
        ]);

        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $main_cat = get_field('main_cat', $term);
                $categories[] = [
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'taxonomy' => $taxonomy->name,
                    'main_cat' => $main_cat
                ];
            }
        }
    }

    return array(
        'categories' => $categories
    );
}




function register_work_api_routes()
{
    register_rest_route('work-api/v1', '/posts', array(
        'methods' => 'GET',
        'callback' => 'get_work_data',
        'permission_callback' => '__return_true'
    ));
}
add_action('rest_api_init', 'register_work_api_routes');
function get_work_data($request)
{
    $post_type = $request->get_param('post_type');
    $categories = $request->get_param('categories');

    $args = array(
        'post_type' => $post_type,
        'posts_per_page' => -1,
    );

    // Add category filter if categories are selected
    if (!empty($categories)) {
        $category_slugs = explode(',', $categories);
        // Use 'category' for regular posts, and {post_type}_category for custom post types
        $taxonomy = $post_type === 'post' ? 'category' : $post_type . '_category';

        $args['tax_query'] = array(
            array(
                'taxonomy' => $taxonomy,
                'field' => 'slug',
                'terms' => $category_slugs,
                'operator' => 'IN'
            )
        );
    }

    $query = new WP_Query($args);
    $posts = array();
    $categories = array();

    if ($query->have_posts()) {
        $post_count = 0;
        while ($query->have_posts()) {
            $query->the_post();
            $post_count++;
            // Use the correct taxonomy name based on post type
            $taxonomy = $post_type === 'post' ? 'category' : $post_type . '_category';
            $post_terms = get_the_terms(get_the_ID(), $taxonomy);

            // Get main category
            $main_cat_id = get_field('main_cat', get_the_ID());
            $main_cat_name = '';
            if ($main_cat_id && !empty($main_cat_id)) {
                $main_cat_term = get_term($main_cat_id, $taxonomy);
                $main_cat_name = ($main_cat_term && !is_wp_error($main_cat_term)) ? $main_cat_term->name : '';
            }

            // If no main_cat, fall back to first category
            if (empty($main_cat_name) && $post_terms && !is_wp_error($post_terms)) {
                $main_cat_name = $post_terms[0]->name;
            }

            $normalized_terms = array();
            if ($post_terms && !is_wp_error($post_terms)) {
                foreach ($post_terms as $term) {
                    $categories[$term->slug] = $term->name;
                    $normalized_terms[] = array(
                        'slug' => $term->slug,
                        'name' => $term->name,
                    );
                }
            }

            $featured_image = array(
                'full' => get_the_post_thumbnail_url(get_the_ID(), 'thumb-3000'),
                'thumb_1280' => get_the_post_thumbnail_url(get_the_ID(), 'thumb-1280'),
                'thumb_2200' => get_the_post_thumbnail_url(get_the_ID(), 'thumb-2200')
            );

            $posts[] = array(
                'id' => get_the_ID(),
                'title' => get_the_title(),
                'permalink' => get_permalink(),
                'categories' => $normalized_terms,
                'main_category' => $main_cat_name,
                'featured_image' => $featured_image,
                'excerpt' => get_the_excerpt(),
                'is_large' => $post_count === 1
            );
        }
        wp_reset_postdata();
    }

    return array(
        'posts' => $posts,
        'categories' => $categories
    );
}
