<?php
/*
Template Name: Success
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include required functions
require_once get_template_directory() . '/functions/orders.php';
require_once get_template_directory() . '/functions/ajax-handlers.php';

// Start session if not already started
if (!session_id()) {
    session_start();
}

// Get Stripe secret key
$stripe_secret_key = get_field('stripe_secret_key', 'option');
if (empty($stripe_secret_key)) {
    $stripe_secret_key = get_option('options_stripe_secret_key');
}

// Include Stripe PHP library
$stripe_init_path = get_template_directory() . '/stripe-php/init.php';
if (file_exists($stripe_init_path)) {
    require_once $stripe_init_path;
}

// Initialize variables
$payment_status = 'unknown';
$order_details = null;
$error_message = '';

// Check if we have a Stripe session ID
if (isset($_GET['session_id'])) {
    $session_id = sanitize_text_field($_GET['session_id']);
    
    try {
        // Configure Stripe
        \Stripe\Stripe::setApiKey($stripe_secret_key);
        
        // Handle SSL certificate verification
        $ca_bundle_paths = [
            '/Users/simonproudfoot/Local Sites/boba-store/app/public/wp-includes/certificates/ca-bundle.crt',
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/usr/local/share/certs/ca-root-nss.crt',
            '/etc/ssl/cert.pem',
            '/etc/ssl/certs/ca-bundle.crt',
            '/usr/share/ssl/certs/ca-bundle.crt',
            '/usr/local/share/certs/ca-root-nss.crt',
            '/etc/certs/ca-bundle.crt'
        ];
        
        $ca_bundle_found = false;
        foreach ($ca_bundle_paths as $path) {
            if (file_exists($path) && is_readable($path)) {
                \Stripe\Stripe::setCABundlePath($path);
                $ca_bundle_found = true;
                break;
            }
        }
        
        // If no CA bundle found, disable SSL verification (for local/test environments)
        if (!$ca_bundle_found) {
            \Stripe\Stripe::setVerifySslCerts(false);
        }
        
        // Retrieve the checkout session
        $session = \Stripe\Checkout\Session::retrieve($session_id);
        
        if ($session && $session->payment_status === 'paid') {
            $payment_status = 'success';
            
            // Get order details from session metadata
            $order_id = $session->metadata->order_id ?? null;
            $customer_name = $session->metadata->customer_name ?? '';
            $customer_email = $session->metadata->customer_email ?? '';
            
            if ($order_id) {
                // Update order status to completed
                $order_updated = update_order_status($session_id, 'completed', 'paid');
                
                if ($order_updated) {
                    // Get order details for display
                    $order_details = get_order_by_session_id($session_id);
                    
                    // Send confirmation email for existing order
                    if ($order_details) {
                        error_log('About to send confirmation email for existing order: ' . $order_details->order_id);
                        $order_items = get_order_items($order_details->order_id);
                        error_log('Retrieved existing order items: ' . print_r($order_items, true));
                        
                        // Check if function exists
                        if (function_exists('send_confirmation_email')) {
                            error_log('send_confirmation_email function exists, calling it for existing order...');
                            $email_sent = send_confirmation_email($order_details, $order_items);
                            if ($email_sent) {
                                error_log('Confirmation email sent for existing order ' . $order_details->order_id);
                            } else {
                                error_log('Failed to send confirmation email for existing order ' . $order_details->order_id);
                                // Try alternative method
                                error_log('Trying alternative email method for existing order...');
                                if (function_exists('send_confirmation_email_direct')) {
                                    $email_sent = send_confirmation_email_direct($order_details, $order_items);
                                    if ($email_sent) {
                                        error_log('Alternative email method succeeded for existing order');
                                    } else {
                                        error_log('Alternative email method also failed for existing order');
                                    }
                                }
                            }
                        } else {
                            error_log('ERROR: send_confirmation_email function does not exist for existing order!');
                            // Try alternative method
                            if (function_exists('send_confirmation_email_direct')) {
                                error_log('Trying alternative email method for existing order...');
                                $email_sent = send_confirmation_email_direct($order_details, $order_items);
                                if ($email_sent) {
                                    error_log('Alternative email method succeeded for existing order');
                                    } else {
                                    error_log('Alternative email method also failed for existing order');
                                }
                            }
                        }
                    }
                    
                    // If order details not found, try to create it from session data
                    if (!$order_details) {
                        error_log('Order not found in database, creating from session data');
                        
                        // Get cart data from session
                        $cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
                        $delivery_data = isset($_SESSION['delivery_data']) ? $_SESSION['delivery_data'] : [];
                        
                        if (!empty($cart) && !empty($delivery_data)) {
                            // Calculate totals
                            $subtotal = 0;
                            foreach ($cart as $item) {
                                $product_id = isset($item['product_id']) ? $item['product_id'] : $item;
                                $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
                                $price = floatval(get_post_meta($product_id, 'price', true));
                                $subtotal += $price * $quantity;
                            }
                            
                            $shipping_cost = calculate_shipping_cost($subtotal);
                            $order_total = $subtotal + $shipping_cost;
                            
                            // Format delivery address
                            $formatted_address = format_delivery_address($delivery_data);
                            
                            // Create order data
                            $order_data = array(
                                'order_id' => $order_id,
                                'stripe_session_id' => $session_id,
                                'customer_email' => $customer_email,
                                'customer_name' => $customer_name,
                                'delivery_address' => $formatted_address,
                                'delivery_notes' => $delivery_data['delivery_notes'] ?? '',
                                'order_total' => $order_total,
                                'shipping_cost' => $shipping_cost,
                                'subtotal' => $subtotal
                            );
                            
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
                            
                            // Create order in database
                            $db_order_id = create_order($order_data);
                            
                            if ($db_order_id) {
                                $order_details = get_order_by_session_id($session_id);
                                error_log('Order created successfully in database with ID: ' . $db_order_id);
                                
                                // Send confirmation email
                                if ($order_details) {
                                    error_log('About to send confirmation email for order: ' . $order_details->order_id);
                                    $order_items = get_order_items($order_details->order_id);
                                    error_log('Retrieved order items: ' . print_r($order_items, true));
                                    
                                    // Check if function exists
                                    if (function_exists('send_confirmation_email')) {
                                        error_log('send_confirmation_email function exists, calling it...');
                                        $email_sent = send_confirmation_email($order_details, $order_items);
                                        if ($email_sent) {
                                            error_log('Confirmation email sent for order ' . $order_details->order_id);
                                        } else {
                                            error_log('Failed to send confirmation email for order ' . $order_details->order_id);
                                            // Try alternative method
                                            error_log('Trying alternative email method...');
                                            if (function_exists('send_confirmation_email_direct')) {
                                                $email_sent = send_confirmation_email_direct($order_details, $order_items);
                                                if ($email_sent) {
                                                    error_log('Alternative email method succeeded');
                                                } else {
                                                    error_log('Alternative email method also failed');
                                                }
                                            }
                                        }
                                    } else {
                                        error_log('ERROR: send_confirmation_email function does not exist!');
                                        // Try alternative method
                                        if (function_exists('send_confirmation_email_direct')) {
                                            error_log('Trying alternative email method...');
                                            $email_sent = send_confirmation_email_direct($order_details, $order_items);
                                            if ($email_sent) {
                                                error_log('Alternative email method succeeded');
                                            } else {
                                                error_log('Alternative email method also failed');
                                            }
                                        }
                                    }
                                }
                            } else {
                                error_log('Failed to create order in database');
                            }
                        }
                    }
                    
                    // Clear the cart
                    if (isset($_SESSION['cart'])) {
                        unset($_SESSION['cart']);
                    }
                    
                    // Clear pending order
                    if (isset($_SESSION['pending_order'])) {
                        unset($_SESSION['pending_order']);
                    }
                    
                    // Clear delivery data
                    if (isset($_SESSION['delivery_data'])) {
                        unset($_SESSION['delivery_data']);
                    }
                } else {
                    $error_message = 'Order could not be updated. Please contact support.';
                }
            } else {
                $error_message = 'Order ID not found in payment session.';
            }
        } else {
            $payment_status = 'failed';
            $error_message = 'Payment was not completed successfully.';
        }
        
    } catch (Exception $e) {
        $payment_status = 'error';
        $error_message = 'Error verifying payment: ' . $e->getMessage();
    }
} else {
    // No session ID provided
    $payment_status = 'no_session';
}

get_header();
?>

<main class="bg-black relative text-white bg-bottom-right pt-16 overflow-hidden min-h-screen">
    <?php if ($payment_status === 'success' && $order_details): ?>
        <!-- Success Content -->
        <div class="max-w-4xl mx-auto px-4 py-16 text-center">
            <div class="mb-8">
                <svg class="w-24 h-24 text-green-500 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h1 class="text-4xl font-bold mb-4">Payment Successful!</h1>
                <p class="text-xl text-gray-300 mb-8">Thank you for your order. Your payment has been processed successfully.</p>
            </div>
            
            <!-- Order Details -->
            <div class="bg-gray-900 rounded-lg p-6 mb-8 text-left">
                <h2 class="text-2xl font-bold mb-4 text-center">Order Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-gray-400">Order ID:</p>
                        <p class="font-semibold"><?php echo esc_html($order_details->order_id); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400">Customer:</p>
                        <p class="font-semibold"><?php echo esc_html($order_details->customer_name); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400">Email:</p>
                        <p class="font-semibold"><?php echo esc_html($order_details->customer_email); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400">Total Amount:</p>
                        <p class="font-semibold">£<?php echo number_format($order_details->order_total, 2); ?></p>
                    </div>
                </div>
                
                <?php if (!empty($order_details->delivery_address)): ?>
                <div class="mt-4">
                    <p class="text-gray-400">Delivery Address:</p>
                    <p class="font-semibold"><?php echo nl2br(esc_html($order_details->delivery_address)); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Order Items -->
            <?php 
            $order_items = get_order_items($order_details->order_id);
            
            // If no order items in database, try to get from session as fallback
            if (empty($order_items)) {
                $cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
                if (!empty($cart)) {
                    // Convert cart data to display format
                    $order_items = [];
                    foreach ($cart as $item) {
                        $product_id = isset($item['product_id']) ? $item['product_id'] : $item;
                        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
                        $price = floatval(get_post_meta($product_id, 'price', true));
                        
                        $order_items[] = (object) [
                            'product_name' => get_the_title($product_id),
                            'product_sku' => get_post_meta($product_id, 'sku', true),
                            'variant_id' => isset($item['variant_id']) ? $item['variant_id'] : '',
                            'quantity' => $quantity,
                            'unit_price' => $price,
                            'total_price' => $price * $quantity
                        ];
                    }
                }
            }
            
            if (!empty($order_items)): ?>
            <div class="bg-gray-900 rounded-lg p-6 mb-8">
                <h3 class="text-xl font-bold mb-4">Items Ordered</h3>
                <?php if (empty(get_order_items($order_details->order_id)) && !empty($_SESSION['cart'])): ?>
                <p class="text-yellow-400 text-sm mb-4">Note: Items shown from your session data</p>
                <?php endif; ?>
                <div class="space-y-3">
                    <?php foreach ($order_items as $item): ?>
                    <div class="flex justify-between items-center border-b border-gray-700 pb-3">
                        <div>
                            <p class="font-semibold"><?php echo esc_html($item->product_name); ?></p>
                            <?php if (!empty($item->product_sku)): ?>
                            <p class="text-sm text-gray-400">SKU: <?php echo esc_html($item->product_sku); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($item->variant_id)): ?>
                            <p class="text-sm text-gray-400">Variant: <?php echo esc_html($item->variant_id); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold">£<?php echo number_format($item->total_price, 2); ?></p>
                            <p class="text-sm text-gray-400">Qty: <?php echo $item->quantity; ?> × £<?php echo number_format($item->unit_price, 2); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="space-y-4">
                <p class="text-lg">You will receive a confirmation email shortly.</p>
                <a href="<?php echo get_post_type_archive_link('shop'); ?>" class="inline-block bg-white text-black px-8 py-3 rounded-lg font-semibold hover:bg-gray-200 transition-colors">
                    Continue Shopping
                </a>
            </div>
        </div>
        
    <?php elseif ($payment_status === 'failed' || $payment_status === 'error'): ?>
        <!-- Error Content -->
        <div class="max-w-4xl mx-auto px-4 py-16 text-center">
            <div class="mb-8">
                <svg class="w-24 h-24 text-red-500 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h1 class="text-4xl font-bold mb-4">Payment Issue</h1>
                <p class="text-xl text-gray-300 mb-8"><?php echo esc_html($error_message); ?></p>
            </div>
            
            <div class="space-y-4">
                <p class="text-lg">Please try again or contact support if the problem persists.</p>
                <a href="<?php echo get_post_type_archive_link('shop'); ?>" class="inline-block bg-white text-black px-8 py-3 rounded-lg font-semibold hover:bg-gray-200 transition-colors">
                    Return to Shop
                </a>
            </div>
        </div>
        
    <?php elseif ($payment_status === 'no_session'): ?>
        <!-- No Session Content -->
        <div class="max-w-4xl mx-auto px-4 py-16 text-center">
            <div class="mb-8">
                <svg class="w-24 h-24 text-yellow-500 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h1 class="text-4xl font-bold mb-4">No Payment Session Found</h1>
                <p class="text-xl text-gray-300 mb-8">It seems your payment session has expired or the link is invalid.</p>
            </div>
            
            <div class="space-y-4">
                <p class="text-lg">Please return to the shop to start a new order.</p>
                <a href="<?php echo get_post_type_archive_link('shop'); ?>" class="inline-block bg-white text-black px-8 py-3 rounded-lg font-semibold hover:bg-gray-200 transition-colors">
                    Return to Shop
                </a>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Default Success Image -->
        <img class="object-contain w-full h-auto max-w-6xl mx-auto z-0" src="<?php echo esc_url(get_theme_file_uri('assets/img/success.png')); ?>" alt="Success">
    <?php endif; ?>
</main>

<style>
    body {
        background-color: #000;
    }
</style>

<?php get_footer(); ?>