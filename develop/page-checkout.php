<?php
// page-checkout.php

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

// Get header first to ensure proper rendering of navigation
get_header();

// Check if we're on the success page
$is_success_page = isset($_GET['success']) && $_GET['success'] == 1;

// If success page, show different layout
if ($is_success_page) {
    // Handle the cart clearing and stock updates
    $cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
    if (!empty($cart)) {
        foreach ($cart as $key => $item) {
            $product_id = isset($item['product_id']) ? $item['product_id'] : $key;
            $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
            
            $current_stock = intval(get_post_meta($product_id, 'stock', true));
            $new_stock = max(0, $current_stock - $quantity); // Prevent negative stock
            update_post_meta($product_id, 'stock', $new_stock);
            if ($new_stock == 0) {
                update_post_meta($product_id, 'sold_out', 1); // Mark as sold out if stock reaches 0
            }
        }
        unset($_SESSION['cart']); // Clear cart after updating stock
    }

    // Show success page with black background
    echo '<main class="bg-black relative text-white bg-bottom-right pt-16 overflow-hidden" style="background-color: black; min-height: 100vh; padding-top: 100px;">';
    echo '<img class="object-contain w-full h-auto max-w-6xl mx-auto z-0" src="' . esc_url(get_theme_file_uri('assets/img/success.png')) . '" alt="Success">';
    echo '</main>';

    get_footer();

    // Add success page styles after footer
?>
   
<?php
    exit;
}

// Regular checkout page code follows
echo '<div class="max-w-4xl mx-auto px-4 md:px-8 py-6 md:py-8 mt-16 bg-white rounded-xl shadow-lg">';
echo '<h1 class="text-3xl font-bold text-gray-800 mb-8 pb-4 border-b-2 border-gray-200">Checkout</h1>';

// Get Stripe secret key from ACF fields (options page)
$stripe_secret_key = get_field('stripe_secret_key', 'option');
$stripe_publishable_key = get_field('stripe_publishable_key', 'option');

// Fallback: Try to get from WordPress options directly
if (empty($stripe_secret_key)) {
    $stripe_secret_key = get_option('options_stripe_secret_key');
}
if (empty($stripe_publishable_key)) {
    $stripe_publishable_key = get_option('options_stripe_publishable_key');
}

// Check if Stripe keys are set - error displayed in main container
if (empty($stripe_secret_key) || empty($stripe_publishable_key)) {
    echo '<div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6 rounded">Stripe configuration is missing. Please contact the site administrator to set up Stripe keys in Shop Settings.</div>';
    echo '<a href="' . get_post_type_archive_link('shop') . '" class="inline-block mt-4 px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition-colors">Back to Shop</a>';
    echo '</div>';
    get_footer();
    exit;
}

// Include Stripe PHP library from theme directory
$stripe_init_path = get_template_directory() . '/stripe-php/init.php';
if (file_exists($stripe_init_path)) {
    require_once $stripe_init_path;
} else {
    echo '<div class="bg-red-50 border-l-4 border-red-400 p-6 mb-6 rounded">Stripe PHP library init file not found. Please place the full stripe-php/ folder (including init.php) in the theme directory.</div>';
    echo '<a href="' . get_post_type_archive_link('shop') . '" class="inline-block mt-4 px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">Back to Shop</a>';
    echo '</div>';
    get_footer();
    exit;
}

// Configure Stripe with SSL fix
\Stripe\Stripe::setApiKey($stripe_secret_key);

// Fix SSL certificate verification issue
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

// Get cart items from session
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$line_items = [];
$total = 0;

// Check stock before creating Stripe session
$inventory_errors = [];
if (!empty($cart)) {
    foreach ($cart as $key => $item) {
        $product_id = isset($item['product_id']) ? $item['product_id'] : $key;
        $variant_id = isset($item['variant_id']) ? $item['variant_id'] : '';
        $color = isset($item['color']) ? $item['color'] : '';
        $color_name = isset($item['color_name']) ? $item['color_name'] : '';
        $size = isset($item['size']) ? $item['size'] : '';
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : (is_numeric($item) ? intval($item) : 1);

        // Try to get variant price/stock from ACF if available
        $variant_price = null;
        $variant_stock = null;
        $color_variations = get_field('color_variations', $product_id);
        if ($color_variations && $variant_id) {
            foreach ($color_variations as $variant) {
                $variant_color = isset($variant['color']) ? $variant['color'] : '';
                $variant_size = isset($variant['size']) ? $variant['size'] : '';
                $v_id = $variant_color . '-' . $variant_size;
                if ($v_id === $variant_id) {
                    $variant_price = isset($variant['price']) ? floatval($variant['price']) : null;
                    $variant_stock = isset($variant['stock']) ? intval($variant['stock']) : null;
                    break;
                }
            }
        }
        $price = $variant_price !== null ? $variant_price : floatval(get_post_meta($product_id, 'price', true));
        $stock = $variant_stock !== null ? $variant_stock : intval(get_post_meta($product_id, 'stock', true));

        if ($stock < $quantity) {
            $inventory_errors[] = 'Insufficient stock for <strong>' . esc_html(get_the_title($product_id)) . '</strong>. Only ' . $stock . ' available.';
        } else {
            $product_title = get_the_title($product_id);
            $variant_desc = '';
            if ($color_name) $variant_desc .= 'Color: ' . $color_name . ' ';
            if ($size) $variant_desc .= 'Size: ' . $size;
            $subtotal = $price * $quantity;
            $total += $subtotal;

            $line_items[] = [
                'price_data' => [
                    'currency' => 'gbp',
                    'product_data' => [
                        'name' => $product_title . ($variant_desc ? ' (' . trim($variant_desc) . ')' : ''),
                        'metadata' => [
                            'product_id' => $product_id,
                            'variant_id' => $variant_id,
                            'color' => $color,
                            'color_name' => $color_name,
                            'size' => $size
                        ]
                    ],
                    'unit_amount' => $price * 100, // Stripe expects pence
                ],
                'quantity' => $quantity,
            ];
        }
    }
}

// Display inventory errors if any
if (!empty($inventory_errors)) {
    echo '<div class="bg-red-50 border-l-4 border-red-400 p-6 mb-6 rounded">';
    echo '<h3 class="text-lg font-semibold text-red-800 mb-3">Stock Issues</h3>';
    echo '<ul class="list-disc list-inside mb-4 space-y-2">';
    foreach ($inventory_errors as $error) {
        echo '<li class="text-red-700">' . $error . '</li>';
    }
    echo '</ul>';
    echo '<p class="text-red-700 mb-4">Please adjust quantities or remove items to continue.</p>';

    // Add buttons for actions
    echo '<div class="flex gap-4 justify-center">';
    echo '<a href="' . get_post_type_archive_link('shop') . '" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-medium transition-colors">Continue Shopping</a>';
    echo '<button id="clear-cart" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded font-medium transition-colors">Clear Cart</button>';
    echo '</div>';

    // Add JavaScript for clearing the cart
    echo '<script>
        document.getElementById("clear-cart").addEventListener("click", function() {
            if (confirm("Are you sure you want to clear your cart?")) {
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "' . admin_url('admin-ajax.php') . '", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        window.location.href = "' . get_post_type_archive_link('shop') . '";
                    }
                };
                xhr.send("action=remove_from_cart&product_id=all");
            }
        });
    </script>';

    echo '</div>';
}

// Create Stripe Checkout session if there are items and no errors
$checkout_session = null;
if (!empty($line_items) && empty($inventory_errors)) {
    try {
        $checkout_session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => $line_items,
            'mode' => 'payment',
            'success_url' => home_url('/?checkout=1&success=1'),
            'cancel_url' => home_url('/?checkout=1'),
        ]);

        // Debugging - check if session ID is generated
        error_log('Stripe Session ID: ' . $checkout_session->id);
    } catch (Exception $e) {
        error_log('Stripe Error: ' . $e->getMessage());
        echo '<div class="error-message">Error creating Stripe checkout session: ' . esc_html($e->getMessage()) . '</div>';
        echo '<a href="' . get_post_type_archive_link('shop') . '" class="back-to-shop">Back to Shop</a>';
        echo '</div>';
        get_footer();

        // Add styles after footer
?>
        <style>
            .shop-checkout {
                max-width: 900px;
                margin: 0;
                padding: 40px;
                margin-top: 200px;
                background-color: #fff;
                border-radius: 12px;
                width: 90%;
                max-height: 90vh;
                overflow-y: auto;
                z-index: 1000;
            }

            .text-3xl {
                font-size: 1.875rem;
                line-height: 2.25rem;
            }

            .shop-checkout h1 {
                margin-top: 0;
                margin-bottom: 30px;
                color: #222;
                border-bottom: 2px solid #f0f0f0;
                padding-bottom: 15px;
            }

            .error-message {
                background-color: #fff8f8;
                border-left: 4px solid #f44336;
                padding: 20px;
                margin-bottom: 25px;
                border-radius: 4px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            }

            .back-to-shop {
                display: inline-block;
                margin-top: 20px;
                padding: 10px 18px;
                background-color: #6c757d;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                transition: background-color 0.2s;
            }

            .back-to-shop:hover {
                background-color: #5a6268;
                color: white;
                text-decoration: none;
            }
        </style>
<?php
        exit;
    }
}

// Get cart items from session
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$total = 0;

// Calculate total and check stock
if (!empty($cart)) {
    foreach ($cart as $key => $item) {
        $product_id = isset($item['product_id']) ? $item['product_id'] : $key;
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : (is_numeric($item) ? intval($item) : 1);
        
        // Get price (handle variants if needed)
        $price = floatval(get_post_meta($product_id, 'price', true));
        $total += $price * $quantity;
    }
}

// Calculate shipping cost
$shipping_cost = calculate_shipping_cost($total);

// Display cart contents
if (empty($cart)) {
    echo '<div class="text-center py-12">';
    echo '<p class="text-gray-600 text-lg mb-4">Your cart is empty.</p>';
    echo '<a href="' . get_post_type_archive_link('shop') . '" class="inline-block px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">Back to Shop</a>';
    echo '</div>';
} else {
?>
    <table class="w-full border-collapse mb-6 rounded-lg overflow-hidden shadow-md">
        <thead>
            <tr class="bg-gray-50">
                <th class="px-4 py-3 text-left font-semibold text-gray-700 border border-gray-200">Product</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700 border border-gray-200 hidden md:table-cell">Price</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700 border border-gray-200">Quantity</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700 border border-gray-200">Subtotal</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700 border border-gray-200">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cart as $key => $item):
                $product_id = isset($item['product_id']) ? $item['product_id'] : $key;
                $color_name = isset($item['color_name']) ? $item['color_name'] : '';
                $size = isset($item['size']) ? $item['size'] : '';
                $quantity = isset($item['quantity']) ? intval($item['quantity']) : (is_numeric($item) ? intval($item) : 1);
                // Get correct price for variant or product
                $variant_price = null;
                $color_variations = get_field('color_variations', $product_id);
                if ($color_variations && isset($item['variant_id'])) {
                    foreach ($color_variations as $variant) {
                        $variant_color = isset($variant['color']) ? $variant['color'] : '';
                        $variant_size = isset($variant['size']) ? $variant['size'] : '';
                        $v_id = $variant_color . '-' . $variant_size;
                        if ($v_id === $item['variant_id']) {
                            $variant_price = isset($variant['price']) ? floatval($variant['price']) : null;
                            break;
                        }
                    }
                }
                $price = $variant_price !== null ? $variant_price : floatval(get_post_meta($product_id, 'price', true));
                $subtotal = $price * $quantity;
            ?>
                <tr class="bg-white hover:bg-gray-50" data-product-id="<?php echo esc_attr($product_id); ?>" data-variant-id="<?php echo esc_attr(isset($item['variant_id']) ? $item['variant_id'] : ''); ?>">
                    <td class="px-4 py-3 border border-gray-200"><?php echo esc_html(get_the_title($product_id)); ?><?php if ($color_name || $size): ?><br><small class="text-gray-600"><?php echo esc_html($color_name); ?><?php if ($color_name && $size) echo ' / '; ?><?php echo esc_html($size); ?></small><?php endif; ?></td>
                    <td class="px-4 py-3 border border-gray-200 hidden md:table-cell">£<?php echo number_format($price, 2); ?></td>
                    <td class="px-4 py-3 border border-gray-200"><?php echo esc_html($quantity); ?></td>
                    <td class="px-4 py-3 border border-gray-200 font-medium">£<?php echo number_format($subtotal, 2); ?></td>
                    <td class="px-4 py-3 border border-gray-200">
                        <button class="remove-item bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm transition-colors" data-product-id="<?php echo esc_attr($product_id); ?>" data-variant-id="<?php echo esc_attr(isset($item['variant_id']) ? $item['variant_id'] : ''); ?>">Remove</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr class="bg-gray-50">
                <td colspan="2" class="px-4 py-3 border border-gray-200 font-semibold md:col-span-3">Subtotal</td>
                <td class="px-4 py-3 border border-gray-200 font-semibold">£<?php echo number_format($total, 2); ?></td>
                <td class="px-4 py-3 border border-gray-200"></td>
            </tr>
            <tr class="bg-gray-50">
                <td colspan="2" class="px-4 py-3 border border-gray-200 font-semibold md:col-span-3">Shipping</td>
                <td class="px-4 py-3 border border-gray-200">
                    <?php if ($shipping_cost > 0): ?>
                        <span class="font-semibold">£<?php echo number_format($shipping_cost, 2); ?></span>
                        <br><small class="text-gray-600 text-xs">Free shipping on orders over £40</small>
                    <?php else: ?>
                        <span class="text-green-600 font-semibold">FREE</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 border border-gray-200"></td>
            </tr>
            <tr class="bg-gray-100 border-t-2 border-gray-300">
                <td colspan="2" class="px-4 py-3 border border-gray-200 font-bold text-lg md:col-span-3">Total</td>
                <td class="px-4 py-3 border border-gray-200 font-bold text-lg">£<?php echo number_format($total + $shipping_cost, 2); ?></td>
                <td class="px-4 py-3 border border-gray-200"></td>
            </tr>
        </tbody>
    </table>

    <!-- Delivery Address Form -->
    <checkout inline-template 
        :stripe-publishable-key="'<?php echo esc_js($stripe_publishable_key); ?>'"
        :delivery-nonce="'<?php echo wp_create_nonce('delivery_address_nonce'); ?>'"
        :create-order-nonce="'<?php echo wp_create_nonce('create_order_nonce'); ?>'"
        :create-stripe-session-nonce="'<?php echo wp_create_nonce('create_stripe_session_nonce'); ?>'"
        :admin-ajax-url="'<?php echo admin_url('admin-ajax.php'); ?>'">
        
        <div class="mt-10 p-4 md:p-8 bg-gray-50 rounded-xl border border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 pb-3 border-b-2 border-gray-300">Delivery Information</h2>
            <form @submit="handleDeliveryAddressSubmission" class="space-y-6">
                <?php wp_nonce_field('delivery_address_nonce', 'delivery_nonce'); ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label for="customer_name" class="block text-sm font-medium text-gray-700">Full Name *</label>
                        <input type="text" id="customer_name" name="customer_name" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    </div>
                    <div class="space-y-2">
                        <label for="customer_email" class="block text-sm font-medium text-gray-700">Email Address *</label>
                        <input type="email" id="customer_email" name="customer_email" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    </div>
                </div>
                
                <div class="space-y-2">
                    <label for="address_line1" class="block text-sm font-medium text-gray-700">Address Line 1 *</label>
                    <input type="text" id="address_line1" name="address_line1" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                </div>
                
                <div class="space-y-2">
                    <label for="address_line2" class="block text-sm font-medium text-gray-700">Address Line 2</label>
                    <input type="text" id="address_line2" name="address_line2" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label for="city" class="block text-sm font-medium text-gray-700">City *</label>
                        <input type="text" id="city" name="city" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    </div>
                    <div class="space-y-2">
                        <label for="state" class="block text-sm font-medium text-gray-700">County/State</label>
                        <input type="text" id="state" name="state" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label for="postal_code" class="block text-sm font-medium text-gray-700">Postal Code *</label>
                        <input type="text" id="postal_code" name="postal_code" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    </div>
                    <div class="space-y-2">
                        <label for="country" class="block text-sm font-medium text-gray-700">Country</label>
                        <select id="country" name="country" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                            <option value="United Kingdom" selected>United Kingdom</option>
                            <option value="United States">United States</option>
                            <option value="Canada">Canada</option>
                            <option value="Australia">Australia</option>
                            <option value="Germany">Germany</option>
                            <option value="France">France</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="space-y-2">
                    <label for="delivery_notes" class="block text-sm font-medium text-gray-700">Delivery Notes</label>
                    <textarea id="delivery_notes" name="delivery_notes" placeholder="e.g., Leave behind the bin, Call on arrival, etc." rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"></textarea>
                </div>
                
                <div class="pt-4">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition-colors focus:ring-2 focus:ring-green-500 focus:ring-offset-2" :disabled="isSubmitting">
                        <span v-if="!isSubmitting">Save Address & Continue to Payment</span>
                        <span v-else>Saving...</span>
                    </button>
                </div>
            </form>
            
            <!-- Checkout Actions -->
            <div class="mt-8 text-center" id="checkout-actions">
                <div class="bg-gray-50 p-4 md:p-6 rounded-xl border border-gray-200 mb-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Order Summary</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center py-2 border-b border-gray-200">
                            <span class="text-gray-600">Subtotal:</span>
                            <span class="font-medium">£<?php echo number_format($total, 2); ?></span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-gray-200">
                            <span class="text-gray-600">Shipping:</span>
                            <span class="font-medium">
                                <?php if ($shipping_cost > 0): ?>
                                    £<?php echo number_format($shipping_cost, 2); ?>
                                <?php else: ?>
                                    <span class="text-green-600">FREE</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="flex justify-between items-center py-3 border-t-2 border-gray-300">
                            <span class="text-lg font-bold text-gray-800">Total:</span>
                            <span class="text-lg font-bold text-gray-800">£<?php echo number_format($total + $shipping_cost, 2); ?></span>
                        </div>
                    </div>
                </div>
                
                <button @click="handleCheckoutClick" class="bg-gray-800 hover:bg-black text-white px-8 py-4 rounded-lg font-semibold text-lg transition-all duration-200 transform hover:-translate-y-1 shadow-lg focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed" :disabled="isCreatingSession">
                    <span v-if="!isCreatingSession">Continue to Stripe Payment</span>
                    <span v-else>Redirecting to Stripe...</span>
                </button>
                <p class="text-gray-600 italic mt-4">Complete your purchase securely through Stripe.</p>
            </div>
        </div>
    </checkout>

<?php
}
echo '</div>'; // Close shop-checkout div

get_footer();
?>