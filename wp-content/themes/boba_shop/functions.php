<?php

/**
 * Global vars
 */
define('THEMEURL', get_stylesheet_directory_uri());
define('THEMEPATH', get_stylesheet_directory());


/**
 * Theme version to version scripts and styles
 */
define('THEME_VERSION', wp_get_theme()->get('Version'));




// API'S
include(THEMEPATH . '/functions/api.php');


function allow_svg_uploads($data, $file, $filename, $mimes)
{
    $filetype = wp_check_filetype($filename, $mimes);

    $ext = $filetype['ext'];
    $type = $filetype['type'];
    $proper_filename = $data['proper_filename'];

    return compact('ext', 'type', 'proper_filename');
}
add_filter('wp_check_filetype_and_ext', 'allow_svg_uploads', 10, 4);


add_action('admin_menu', function () {
    remove_menu_page('edit.php'); // Removes the 'Posts' menu
});


/**
 * Theme Setup
 */
function theme_setup()
{
    /**
     * Featured Image Support
     */
    add_theme_support('post-thumbnails');
    add_image_size('thumb-150', 150);
    add_image_size('thumb-270', 270);
    add_image_size('thumb-640', 640);
    add_image_size('thumb-768', 768);
    add_image_size('thumb-1024', 1024);
    add_image_size('thumb-1280', 1280);
    add_image_size('thumb-1500', 1500);
    add_image_size('thumb-1920', 1920);

    // LARGE
    add_image_size('thumb-2200', 2200, 0, false);
    add_image_size('thumb-3000', 3000, 0, false);
    add_image_size('thumb-4000', 4000, 0, false);


    // Tall variant
    add_image_size('thumbnail-tall', 1312, 2495, true);

    // Square variant
    add_image_size('thumbnail-square', 800, 800, true);
    add_image_size('thumbnail-square-medium', 1100, 1100, true);
    add_image_size('thumbnail-square-large', 1500, 1500, true);

    add_image_size('card', 500, 500, true);


    // Wide variant
    add_image_size('thumbnail-wide', 1312, 640, true);

    // gallery sizes
    add_image_size('gallery-landscape', 1920, 1080);
    add_image_size('gallery_tall', 1080, 1920);
    add_image_size('gallery_tall_small', 640, 1312);


    /**
     * Nav Menus
     */
    register_nav_menus(array(
        'main' => __('Main'),
        'main-menu' => __('Main Menu'),
        'menu' => __('Menu'),
        'footer-social' => __('Footer Social Links'),
        'footer-terms' => __('Footer Terms & Conditions')
    ));
}
add_action('after_setup_theme', 'theme_setup');
@ini_set('upload_max_size', '64M');
@ini_set('post_max_size', '64M');
@ini_set('max_execution_time', '300');
add_editor_style('editor-style.css');

/**
 * Enqueue scripts and styles
 */
function sites_scripts()
{

    // Enqueue Vue.js stylesheet
    wp_enqueue_style('style', get_template_directory_uri() . '/style.css', '', THEME_VERSION);

    // Add preload to the stylesheet
    add_action('wp_head', function () {
?>
        <link rel="preload"
            href="<?php echo get_template_directory_uri(); ?>/style.css?ver=<?php echo THEME_VERSION; ?>"
            as="style"
            onload="this.onload=null;this.rel='stylesheet'"
            crossorigin="anonymous">
        <noscript>
            <link rel="stylesheet"
                href="<?php echo get_template_directory_uri(); ?>/style.css?ver=<?php echo THEME_VERSION; ?>">
        </noscript>
    <?php
    }, 1);


    // Enqueue Vue.js
    wp_enqueue_script('vuejs', get_template_directory_uri() . '/scripts/lib/vue.min.js', null, null, true);

    // Enqueue Stripe.js from CDN
    wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', null, null, true);

    wp_enqueue_script('card', get_template_directory_uri() . '/scripts/site/product-card.min.js', null, null, true);
    wp_enqueue_script('page', get_template_directory_uri() . '/scripts/site/product-page.min.js', null, null, true);
    wp_enqueue_script('cart', get_template_directory_uri() . '/scripts/site/cart.min.js', null, null, true);
    wp_enqueue_script('checkout', get_template_directory_uri() . '/scripts/site/checkout.min.js', array('stripe-js'), null, true);


    // Enqueue Vue.js custom script
    wp_enqueue_script('vue-js', get_template_directory_uri() . '/scripts/site/vue.min.js', array('vuejs', 'card', 'page', 'cart', 'checkout'), THEME_VERSION, true);


    // Get cart count from session
    $cart_count = 0;
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['cart'])) {
        $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
    }

    $shop_categories = array();
    $categories = get_terms(array(
        'taxonomy' => 'product_category',
        'hide_empty' => true,
    ));

    if (!empty($categories) && !is_wp_error($categories)) {
        foreach ($categories as $category) {
            $shop_categories[] = array(
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'count' => $category->count,
            );
        }
    }

    // Localize the script with cart data, categories, and other variables
    wp_localize_script('vue-js', 'myVueObj', array(
        "site_url" => site_url(),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        "rest_url" => get_rest_url(),

        "cartCount" => $cart_count,
        "shopCategories" => $shop_categories,
    ));

    /**
     * Move jquery to footer
     */
    wp_scripts()->add_data('jquery', 'group', 1);
    wp_scripts()->add_data('jquery-core', 'group', 1);
    wp_scripts()->add_data('jquery-migrate', 'group', 1);
    /**
     * Dequeue Gutenburg Assets
     */
    wp_dequeue_style('wp-block-library'); // WordPress core
    wp_dequeue_style('wp-block-library-theme'); // WordPress core
    wp_dequeue_style('wc-block-style'); // WooCommerce
    wp_dequeue_style('storefront-gutenberg-blocks'); // Storefront theme
}
add_action('wp_enqueue_scripts', 'sites_scripts', 100);

function wps_deregister_styles()
{
    wp_dequeue_style('global-styles');
}
add_action('wp_enqueue_scripts', 'wps_deregister_styles', 100);

// REMOVE WP EMOJI
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

remove_action('admin_print_scripts', 'print_emoji_detection_script');
remove_action('admin_print_styles', 'print_emoji_styles');



function body_start()
{
    ?>

<?php
}
add_action('body_start', 'body_start');

// Disable Gutenberg
add_filter('use_block_editor_for_post', '__return_false');

// Remove admin bar
//add_filter('show_admin_bar', '__return_false');

/**
 * Allow svgs
 */
function cc_mime_types($mimes)
{
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
}

add_filter('upload_mimes', 'cc_mime_types');


// excerps
function excerpt($content, $chars)
{

    $suffix = '';


    if (strlen($content) > $chars) {
        $chars_qty = $chars - 3;
        $suffix = '...';
    } else {
        $chars_qty = $chars;
    }

    $cleaned = strip_tags($content);

    return substr($content, 0, $chars_qty) . $suffix;
}





/**
 * Comments
 */

/**
 * Disable all comment related functions
 */
add_action('admin_init', function () {
    // Redirect any user trying to access comments page
    global $pagenow;

    if ($pagenow === 'edit-comments.php') {
        wp_safe_redirect(admin_url());
        exit;
    }

    // Remove comments metabox from dashboard
    remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');

    // Disable support for comments and trackbacks in post types
    foreach (get_post_types() as $post_type) {
        if (post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
            remove_post_type_support($post_type, 'trackbacks');
        }
    }
});

// Close comments on the front-end
add_filter('comments_open', '__return_false', 20, 2);
add_filter('pings_open', '__return_false', 20, 2);

// Hide existing comments
add_filter('comments_array', '__return_empty_array', 10, 2);

// Remove comments page in menu
add_action('admin_menu', function () {
    remove_menu_page('edit-comments.php');
});

// Remove comments links from admin bar
add_action('init', function () {
    if (is_admin_bar_showing()) {
        remove_action('admin_bar_menu', 'wp_admin_bar_comments_menu', 60);
    }
});


### END DISABLE COMMENTS ###
function header_stuff()
{
    // add header fonts, scripts, etc
    // pre load fonts
?>

    <link rel="preload" href="<?php echo THEMEURL; ?>/assets/fonts/" as="font" type="font/woff" crossorigin="anonymous">
    <link rel="preload" href="<?php echo THEMEURL; ?>/assets/fonts/" as="font" type="font/woff" crossorigin="anonymous">
    <link href="<?php echo THEMEURL; ?>/assets/fonts/fonts.css" rel="stylesheet" media="print" onload="this.media='all'">

<?php
}
add_action('wp_head', 'header_stuff');




/**
 * Lazy Loader Helper
 */
function lazy_img($image_id, $size = 'full', $class = '', $loading = 'lazy', $sizes = '')
{

    $img_src = wp_get_attachment_image_src($image_id, $size);
    $img_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
    $img_title = get_post_meta($image_id, '_wp_attachment_image_title', true);

    $img_srcset = '';

    if (is_array($sizes)) :

        foreach ($sizes as $s) :
            $iss = wp_get_attachment_image_src($image_id, $s[0]);
            $img_srcset .= $iss[0] . ' ' . $s[1] . 'w, ';
        endforeach;
    else :
        $img_srcset = wp_get_attachment_image_srcset($image_id, $size);
    endif;

    $width = ($img_src[1] > 1) ? $img_src[1] : '';
    $height = ($img_src[2] > 1) ? $img_src[2] : '';
    echo '<img width="' . $width . '" height="' . $height . '" class="lazyload opacity-0 transition-opacity ' . $class . '" title="" alt="' . $img_alt . '" data-src="' . $img_src[0] . '" data-srcset="' . $img_srcset . '" loading="' . $loading . '" src="' . THEMEURL . '/assets/img/pixel.png"/>';
}

function img($image_id, $size = 'full', $class = '', $loading = 'eager', $sizes = '')
{

    $img_src = wp_get_attachment_image_src($image_id, $size);
    $img_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
    $img_title = get_post_meta($image_id, '_wp_attachment_image_title', true);

    $img_srcset = '';

    if (is_array($sizes)) :

        foreach ($sizes as $s) :
            $iss = wp_get_attachment_image_src($image_id, $s[0]);
            $img_srcset .= $iss[0] . ' ' . $s[1] . 'w, ';
        endforeach;
    else :
        $img_srcset = wp_get_attachment_image_srcset($image_id, $size);
    endif;

    $width = ($img_src[1] > 1) ? $img_src[1] : '';
    $height = ($img_src[2] > 1) ? $img_src[2] : '';
    echo '<img width="' . $width . '" height="' . $height . '" class="' . $class . '" title="" alt="' . $img_alt . '" src="' . $img_src[0] . '" srcset="' . $img_srcset . '" loading="eager"/>';
}

// --- Begin Cart AJAX Handlers ---

// Ensure session is started
function theme_start_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}
add_action('init', 'theme_start_session', 1);

function theme_add_to_cart() {
    $product_id = intval($_POST['product_id']);
    $variant_id = isset($_POST['variant_id']) ? sanitize_text_field($_POST['variant_id']) : '';
    $color = isset($_POST['variation_color']) ? sanitize_text_field($_POST['variation_color']) : '';
    $color_name = isset($_POST['variation_color_name']) ? sanitize_text_field($_POST['variation_color_name']) : '';
    $size = isset($_POST['variation_size']) ? sanitize_text_field($_POST['variation_size']) : '';
    $key = $product_id;
    if ($variant_id) {
        $key .= '|' . $variant_id;
    }
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['quantity'] += 1;
    } else {
        $_SESSION['cart'][$key] = [
            'product_id' => $product_id,
            'variant_id' => $variant_id,
            'color' => $color,
            'color_name' => $color_name,
            'size' => $size,
            'quantity' => 1
        ];
    }
    wp_send_json_success($_SESSION['cart']);
}
add_action('wp_ajax_add_to_cart', 'theme_add_to_cart');
add_action('wp_ajax_nopriv_add_to_cart', 'theme_add_to_cart');

function theme_update_cart_quantity() {
    $product_id = intval($_POST['product_id']);
    $variant_id = isset($_POST['variant_id']) ? sanitize_text_field($_POST['variant_id']) : '';
    $key = $product_id;
    if ($variant_id) {
        $key .= '|' . $variant_id;
    }
    $quantity = intval($_POST['quantity']);
    if ($quantity < 1) $quantity = 1; // Minimum quantity
    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['quantity'] = $quantity;
    }
    wp_send_json_success($_SESSION['cart']);
}
add_action('wp_ajax_update_cart_quantity', 'theme_update_cart_quantity');
add_action('wp_ajax_nopriv_update_cart_quantity', 'theme_update_cart_quantity');

function theme_remove_from_cart() {
    $product_id = sanitize_text_field($_POST['product_id']);
    $variant_id = isset($_POST['variant_id']) ? sanitize_text_field($_POST['variant_id']) : '';
    
    // Handle special case for clearing entire cart
    if ($product_id === 'all') {
        $_SESSION['cart'] = [];
        wp_send_json_success($_SESSION['cart']);
        return;
    }
    
    $key = $product_id;
    if ($variant_id) {
        $key .= '|' . $variant_id;
    }
    if (isset($_SESSION['cart'][$key])) {
        unset($_SESSION['cart'][$key]);
    }
    wp_send_json_success($_SESSION['cart']);
}
add_action('wp_ajax_remove_from_cart', 'theme_remove_from_cart');
add_action('wp_ajax_nopriv_remove_from_cart', 'theme_remove_from_cart');

function theme_get_cart_count() {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    wp_send_json_success($_SESSION['cart']);
}
add_action('wp_ajax_get_cart_count', 'theme_get_cart_count');
add_action('wp_ajax_nopriv_get_cart_count', 'theme_get_cart_count');

// --- End Cart AJAX Handlers ---

// --- Begin Shop Custom Post Type Registration ---
function theme_register_shop_post_type() {
    // Get archive setting (optional, can be hardcoded to true if not using option)
    $has_archive = true;
    register_post_type('shop', [
        'labels' => [
            'name' => 'Shop',
            'singular_name' => 'Product'
        ],
        'public' => true,
        'has_archive' => $has_archive,
        'supports' => ['title', 'thumbnail', 'editor'], // Added 'editor' support for description
        'taxonomies' => ['product_category'], // Use our custom taxonomy
        'menu_icon' => 'dashicons-cart'
    ]);
}
add_action('init', 'theme_register_shop_post_type');
// --- End Shop Custom Post Type Registration ---

// --- Begin Shop Product Category Taxonomy Registration ---
function theme_register_shop_taxonomy() {
    $labels = array(
        'name'              => _x('Product Categories', 'taxonomy general name'),
        'singular_name'     => _x('Product Category', 'taxonomy singular name'),
        'search_items'      => __('Search Product Categories'),
        'all_items'         => __('All Product Categories'),
        'parent_item'       => __('Parent Product Category'),
        'parent_item_colon' => __('Parent Product Category:'),
        'edit_item'         => __('Edit Product Category'),
        'update_item'       => __('Update Product Category'),
        'add_new_item'      => __('Add New Product Category'),
        'new_item_name'     => __('New Product Category Name'),
        'menu_name'         => __('Product Categories'),
    );

    register_taxonomy('product_category', 'shop', array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'product-category'),
    ));
}
add_action('init', 'theme_register_shop_taxonomy', 5);
// --- End Shop Product Category Taxonomy Registration ---

// --- Begin Stripe Integration ---

// Add ACF options page for shop settings
if( function_exists('acf_add_options_page') ) {
    
    acf_add_options_page(array(
        'page_title' 	=> 'Shop Settings',
        'menu_title'	=> 'Shop Settings',
        'menu_slug' 	=> 'shop-settings',
        'capability'	=> 'edit_posts',
        'redirect'		=> false
    ));
}

// Add ACF fields for Shop Settings
function theme_add_shop_settings_fields() {
    if( function_exists('acf_add_local_field_group') ) {
        acf_add_local_field_group(array(
            'key' => 'group_shop_settings',
            'title' => 'Shop Settings',
            'fields' => array(
                array(
                    'key' => 'field_stripe_secret_key',
                    'label' => 'Stripe Secret Key',
                    'name' => 'stripe_secret_key',
                    'type' => 'text',
                    'instructions' => 'Enter your Stripe secret key (starts with sk_)',
                    'required' => 1,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                ),
                array(
                    'key' => 'field_stripe_publishable_key',
                    'label' => 'Stripe Publishable Key',
                    'name' => 'stripe_publishable_key',
                    'type' => 'text',
                    'instructions' => 'Enter your Stripe publishable key (starts with pk_)',
                    'required' => 1,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'options_page',
                        'operator' => '==',
                        'value' => 'shop-settings',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => '',
        ));
    }
}
add_action('acf/init', 'theme_add_shop_settings_fields');

// Also try to add fields on admin_init as a fallback
add_action('admin_init', 'theme_add_shop_settings_fields_fallback');
function theme_add_shop_settings_fields_fallback() {
    // Only run if we're on the shop-settings page and fields don't exist
    if (isset($_GET['page']) && $_GET['page'] === 'shop-settings') {
        if (!get_field('stripe_secret_key', 'option') && !get_field('stripe_publishable_key', 'option')) {
            theme_add_shop_settings_fields();
        }
    }
}

// Manual setup function - can be called to ensure fields are created
function theme_setup_shop_settings_manually() {
    if (current_user_can('manage_options')) {
        // First, ensure the field group is created
        theme_add_shop_settings_fields();
        
        // Then, set some placeholder values if they don't exist
        if (!get_field('stripe_secret_key', 'option')) {
            update_field('stripe_secret_key', 'sk_test_placeholder', 'option');
        }
        if (!get_field('stripe_publishable_key', 'option')) {
            update_field('stripe_publishable_key', 'pk_test_placeholder', 'option');
        }
        
        return true;
    }
    return false;
}

// Add admin notice to help with setup
add_action('admin_notices', 'theme_shop_settings_admin_notice');
function theme_shop_settings_admin_notice() {
    if (isset($_GET['page']) && $_GET['page'] === 'shop-settings') {
        if (!get_field('stripe_secret_key', 'option') || !get_field('stripe_publishable_key', 'option')) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>Shop Settings Setup Required:</strong> Please enter your Stripe API keys below. If the fields are not visible, try refreshing the page or contact support.</p>';
            echo '</div>';
        }
    }
}

// Handle checkout redirect
function theme_handle_checkout() {
    if (isset($_GET['checkout'])) {
        include get_template_directory() . '/page-checkout.php';
        exit;
    }
}
add_action('template_redirect', 'theme_handle_checkout');

// Add cart icon to menu
function theme_add_cart_icon($items, $args) {
    $cart_count = 0;
    if (isset($_SESSION['cart'])) {
        $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
    }
    $cart_url = home_url('/?checkout=1');

    // Add cart count to JavaScript for Vue - simple one-liner
    echo '<script>window.cartCount = ' . $cart_count . ';</script>';

    $items .= '<li class="menu-item"><a href="' . $cart_url . '">🛒 Cart (' . $cart_count . ')</a></li>';
    return $items;
}
add_filter('wp_nav_menu_items', 'theme_add_cart_icon', 10, 2);

// --- End Stripe Integration ---

// Include order management and checkout functions
include(THEMEPATH . '/functions/orders.php');
include(THEMEPATH . '/functions/ajax-handlers.php');
include(THEMEPATH . '/functions/stripe-webhook.php');
include(THEMEPATH . '/functions/admin-orders.php');