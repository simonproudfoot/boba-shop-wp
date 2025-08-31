<?php
/**
 * AJAX Handlers for Checkout Process
 * Handles delivery address submission and order creation
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle delivery address submission
 */
function handle_delivery_address_submission() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'delivery_address_nonce')) {
        wp_die('Security check failed');
    }
    
    // Validate required fields
    $required_fields = ['customer_name', 'customer_email', 'address_line1', 'city', 'postal_code'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            wp_send_json_error('Please fill in all required fields.');
            return;
        }
    }
    
    // Validate email
    if (!is_email($_POST['customer_email'])) {
        wp_send_json_error('Please enter a valid email address.');
        return;
    }
    
    // Sanitize input
    $delivery_data = array(
        'customer_name' => sanitize_text_field($_POST['customer_name']),
        'customer_email' => sanitize_email($_POST['customer_email']),
        'address_line1' => sanitize_text_field($_POST['address_line1']),
        'address_line2' => sanitize_text_field($_POST['address_line2']),
        'city' => sanitize_text_field($_POST['city']),
        'state' => sanitize_text_field($_POST['state']),
        'postal_code' => sanitize_text_field($_POST['postal_code']),
        'country' => sanitize_text_field($_POST['country']),
        'delivery_notes' => sanitize_textarea_field($_POST['delivery_notes'])
    );
    
    // Store in session for later use
    if (!session_id()) {
        session_start();
    }
    $_SESSION['delivery_data'] = $delivery_data;
    
    wp_send_json_success('Delivery address saved successfully.');
}

/**
 * Handle order creation
 */
function handle_order_creation() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'create_order_nonce')) {
        wp_die('Security check failed');
    }
    
    // Check if delivery data exists
    if (!session_id()) {
        session_start();
    }
    
    if (empty($_SESSION['delivery_data'])) {
        wp_send_json_error('Delivery address not found. Please submit the delivery form first.');
        return;
    }
    
    $delivery_data = $_SESSION['delivery_data'];
    
    // Get cart data
    $cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
    
    if (empty($cart)) {
        wp_send_json_error('Cart is empty.');
        return;
    }
    
    // Calculate totals
    $subtotal = 0;
    foreach ($cart as $item) {
        $product_id = isset($item['product_id']) ? $item['product_id'] : $item;
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
        
        // Get price (handle variants if needed)
        $price = floatval(get_post_meta($product_id, 'price', true));
        $subtotal += $price * $quantity;
    }
    
    // Calculate shipping
    $shipping_cost = calculate_shipping_cost($subtotal);
    $order_total = $subtotal + $shipping_cost;
    
    // Generate order ID
    $order_id = generate_order_id();
    
    // Format delivery address
    $formatted_address = format_delivery_address($delivery_data);
    
    // Create order data
    $order_data = array(
        'order_id' => $order_id,
        'stripe_session_id' => '', // Will be updated after Stripe session creation
        'customer_email' => $delivery_data['customer_email'],
        'customer_name' => $delivery_data['customer_name'],
        'delivery_address' => $formatted_address,
        'delivery_notes' => $delivery_data['delivery_notes'],
        'order_total' => $order_total,
        'shipping_cost' => $shipping_cost,
        'subtotal' => $subtotal
    );
    
    // Store order data in session for Stripe integration
    $_SESSION['pending_order'] = $order_data;
    
    wp_send_json_success(array(
        'message' => 'Order created successfully.',
        'order_data' => $order_data
    ));
}

/**
 * Get shipping cost for current cart
 */
function get_shipping_cost() {
    // Get cart data
    $cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
    
    if (empty($cart)) {
        wp_send_json_error('Cart is empty.');
        return;
    }
    
    // Calculate subtotal
    $subtotal = 0;
    foreach ($cart as $item) {
        $product_id = isset($item['product_id']) ? $item['product_id'] : $item;
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
        $price = floatval(get_post_meta($product_id, 'price', true));
        $subtotal += $price * $quantity;
    }
    
    // Calculate shipping
    $shipping_cost = calculate_shipping_cost($subtotal);
    $order_total = $subtotal + $shipping_cost;
    
    wp_send_json_success(array(
        'subtotal' => $subtotal,
        'shipping_cost' => $shipping_cost,
        'order_total' => $order_total,
        'free_shipping_threshold' => 40.00
    ));
}

/**
 * Handle Stripe session creation
 */
function handle_create_stripe_session() {
    // Prevent any output before JSON response
    ob_clean();
    
    // Include Stripe PHP library
    $stripe_init_path = get_template_directory() . '/stripe-php/init.php';
    if (file_exists($stripe_init_path)) {
        require_once $stripe_init_path;
        error_log('Stripe library loaded successfully');
    } else {
        error_log('Stripe library init file not found at: ' . $stripe_init_path);
        wp_send_json_error('Stripe library not found. Please ensure the Stripe PHP library is properly installed.');
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'create_stripe_session_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    // Check if order data exists
    if (!session_id()) {
        session_start();
    }
    
    if (empty($_SESSION['pending_order'])) {
        wp_send_json_error('Order data not found. Please complete the delivery form first.');
        return;
    }
    
    $order_data = $_SESSION['pending_order'];
    
    // Debug logging
    error_log('Creating Stripe session for order: ' . print_r($order_data, true));
    
    // Get Stripe secret key
    $stripe_secret_key = get_field('stripe_secret_key', 'option');
    
    // Fallback: Try to get from WordPress options directly
    if (empty($stripe_secret_key)) {
        $stripe_secret_key = get_option('options_stripe_secret_key');
    }
    
    if (empty($stripe_secret_key)) {
        wp_send_json_error('Stripe configuration is missing.');
        return;
    }
    
    // Debug logging
    error_log('Stripe secret key found: ' . substr($stripe_secret_key, 0, 10) . '...');
    
    // Check if Stripe library is available
    if (!class_exists('\Stripe\Stripe')) {
        error_log('Stripe library not found after including init.php');
        wp_send_json_error('Stripe library not available. Please ensure the Stripe PHP library is properly installed.');
        return;
    }
    
    // Configure Stripe
    try {
        \Stripe\Stripe::setApiKey($stripe_secret_key);
        error_log('Stripe configured successfully');
        
        // Fix SSL certificate verification issue
        // Check if test mode is enabled or if we're in local development
        $test_mode = get_field('stripe_test_mode', 'option');
        
        // Fallback: Try to get from WordPress options directly
        if (empty($test_mode)) {
            $test_mode = get_option('options_stripe_test_mode');
        }
        
        $is_local = strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
                    strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
                    strpos($_SERVER['HTTP_HOST'] ?? '', '3001') !== false;
        
        if ($test_mode || $is_local) {
            error_log('Test mode enabled or local development detected, disabling SSL verification to avoid timeout issues');
            \Stripe\Stripe::setVerifySslCerts(false);
        } else {
            // Try to set CA bundle path for SSL verification on production
            $ca_bundle_paths = [
                ABSPATH . WPINC . '/certificates/ca-bundle.crt',
                ABSPATH . 'wp-includes/certificates/ca-bundle.crt',
                '/etc/ssl/certs/ca-certificates.crt',
                '/etc/pki/tls/certs/ca-bundle.crt',
                '/usr/share/ssl/certs/ca-bundle.crt',
                '/usr/local/share/certs/ca-root-nss.crt', // FreeBSD
                '/etc/ssl/cert.pem', // macOS
                '/System/Library/OpenSSL/certs/cert.pem' // macOS system
            ];
            
            $ca_bundle_set = false;
            foreach ($ca_bundle_paths as $ca_path) {
                if (file_exists($ca_path) && is_readable($ca_path)) {
                    try {
                        \Stripe\Stripe::setCABundlePath($ca_path);
                        error_log('CA bundle path set successfully: ' . $ca_path);
                        $ca_bundle_set = true;
                        break;
                    } catch (Exception $e) {
                        error_log('Failed to set CA bundle path: ' . $ca_path . ' - ' . $e->getMessage());
                        continue;
                    }
                }
            }
            
            if (!$ca_bundle_set) {
                error_log('No CA bundle path found, disabling SSL verification as fallback');
                \Stripe\Stripe::setVerifySslCerts(false);
            }
        }
        
    } catch (Exception $e) {
        error_log('Stripe configuration error: ' . $e->getMessage());
        wp_send_json_error('Stripe configuration error: ' . $e->getMessage());
        return;
    }
    
    // Get cart items
    $cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
    $line_items = [];
    
    error_log('Cart contents: ' . print_r($cart, true));
    
    foreach ($cart as $item) {
        $product_id = isset($item['product_id']) ? $item['product_id'] : $item;
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
        
        // Get price (handle variants if needed)
        $price = floatval(get_post_meta($product_id, 'price', true));
        
        error_log("Processing item - Product ID: $product_id, Price: $price, Quantity: $quantity");
        
        $line_items[] = [
            'price_data' => [
                'currency' => 'gbp',
                'product_data' => [
                    'name' => get_the_title($product_id),
                    'metadata' => [
                        'product_id' => $product_id,
                        'variant_id' => isset($item['variant_id']) ? $item['variant_id'] : '',
                        'color' => isset($item['color']) ? $item['color'] : '',
                        'color_name' => isset($item['color_name']) ? $item['color_name'] : '',
                        'size' => isset($item['size']) ? $item['size'] : ''
                    ]
                ],
                'unit_amount' => $price * 100, // Stripe expects pence
            ],
            'quantity' => $quantity,
        ];
    }
    
    error_log('Line items created: ' . print_r($line_items, true));
    
    // Add shipping as a separate line item if applicable
    if ($order_data['shipping_cost'] > 0) {
        $line_items[] = [
            'price_data' => [
                'currency' => 'gbp',
                'product_data' => [
                    'name' => 'Shipping & Delivery',
                    'description' => 'Standard delivery'
                ],
                'unit_amount' => $order_data['shipping_cost'] * 100,
            ],
            'quantity' => 1,
        ];
    }
    
    try {
        error_log('Attempting to create Stripe checkout session...');
        
        // Create Stripe checkout session with timeout configuration
        $checkout_session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => $line_items,
            'mode' => 'payment',
            'success_url' => home_url('/success/?session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => home_url('/?checkout=1'),
            'customer_email' => $order_data['customer_email'],
            'metadata' => [
                'order_id' => $order_data['order_id'],
                'customer_name' => $order_data['customer_name'],
                'delivery_address' => $order_data['delivery_address'],
                'delivery_notes' => $order_data['delivery_notes']
            ]
        ], [
            'timeout' => 30, // 30 second timeout
            'connect_timeout' => 10 // 10 second connection timeout
        ]);
        
        error_log('Stripe checkout session created successfully: ' . $checkout_session->id);
        
        // Update order with Stripe session ID
        $order_data['stripe_session_id'] = $checkout_session->id;
        
        // Prepare cart items for order
        $cart_items = [];
        foreach ($cart as $item) {
            $product_id = isset($item['product_id']) ? $item['product_id'] : $item;
            $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
            $price = floatval(get_post_meta($product_id, 'price', true));
            
            $cart_items[] = [
                'product_id' => $product_id,
                'variant_id' => isset($item['variant_id']) ? $item['variant_id'] : '',
                'product_name' => get_the_title($product_id),
                'product_sku' => get_post_meta($product_id, 'sku', true),
                'quantity' => $quantity,
                'unit_price' => $price,
                'total_price' => $price * $quantity
            ];
        }
        
        $order_data['cart_items'] = $cart_items;
        $_SESSION['pending_order'] = $order_data;
        
        error_log('Order data updated with Stripe session ID and cart items');
        
        // Create order in database
        $order_id = create_order($order_data);
        
        if ($order_id) {
            error_log('Order created in database with ID: ' . $order_id);
            $response_data = array(
                'session_id' => $checkout_session->id,
                'order_id' => $order_id
            );
            error_log('Sending success response: ' . json_encode($response_data));
            wp_send_json_success($response_data);
        } else {
            error_log('Failed to create order in database');
            wp_send_json_error('Failed to create order in database.');
        }
        
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('Stripe API Error: ' . $e->getMessage());
        error_log('Stripe Error Code: ' . $e->getStripeCode());
        error_log('Stripe Error Type: ' . $e->getError()->type);
        error_log('Stripe Error Stack Trace: ' . $e->getTraceAsString());
        
        // Provide more specific error messages for common issues
        if (strpos($e->getMessage(), 'certificate verify locations') !== false) {
            wp_send_json_error('SSL certificate verification issue. Please contact support to resolve this server configuration problem.');
        } else {
            wp_send_json_error('Stripe API Error: ' . $e->getMessage());
        }
        
    } catch (Exception $e) {
        error_log('General Error: ' . $e->getMessage());
        error_log('Error Stack Trace: ' . $e->getTraceAsString());
        wp_send_json_error('Unexpected error: ' . $e->getMessage());
    }
}

// Register AJAX actions
add_action('wp_ajax_delivery_address_submission', 'handle_delivery_address_submission');
add_action('wp_ajax_nopriv_delivery_address_submission', 'handle_delivery_address_submission');

add_action('wp_ajax_create_order', 'handle_order_creation');
add_action('wp_ajax_nopriv_create_order', 'handle_order_creation');

add_action('wp_ajax_get_shipping_cost', 'get_shipping_cost');
add_action('wp_ajax_nopriv_get_shipping_cost', 'get_shipping_cost');

add_action('wp_ajax_create_stripe_session', 'handle_create_stripe_session');
add_action('wp_ajax_nopriv_create_stripe_session', 'handle_create_stripe_session');

// Handle shop settings setup
add_action('wp_ajax_setup_shop_settings', 'handle_setup_shop_settings');
function handle_setup_shop_settings() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'setup_shop_settings_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Call the setup function
    $result = theme_setup_shop_settings_manually();
    
    if ($result) {
        wp_send_json_success('Shop Settings fields created successfully');
    } else {
        wp_send_json_error('Failed to create Shop Settings fields');
    }
}
