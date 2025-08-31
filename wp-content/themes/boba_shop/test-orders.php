<?php
/**
 * Test script to check order system
 */

// Load WordPress
require_once '../wp-load.php';

// Include our functions
require_once 'functions/orders.php';

echo "<h1>Order System Test</h1>\n";

// Check if tables exist
global $wpdb;

echo "<h2>Checking Database Tables</h2>\n";

$orders_table = $wpdb->prefix . 'shop_orders';
$items_table = $wpdb->prefix . 'shop_order_items';

$orders_exists = $wpdb->get_var("SHOW TABLES LIKE '$orders_table'") == $orders_table;
$items_exists = $wpdb->get_var("SHOW TABLES LIKE '$items_table'") == $items_table;

echo "Orders table exists: " . ($orders_exists ? 'YES' : 'NO') . "<br>\n";
echo "Order items table exists: " . ($items_exists ? 'YES' : 'NO') . "<br>\n";

if (!$orders_exists || !$items_exists) {
    echo "<h3>Creating missing tables...</h3>\n";
    create_orders_table();
    create_order_items_table();
    echo "Tables created!<br>\n";
}

// Check existing orders
echo "<h2>Existing Orders</h2>\n";
$orders = $wpdb->get_results("SELECT * FROM $orders_table ORDER BY created_at DESC LIMIT 5");
if ($orders) {
    foreach ($orders as $order) {
        echo "Order ID: {$order->order_id} - Customer: {$order->customer_name} - Total: £{$order->order_total}<br>\n";
        
        // Check order items
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $items_table WHERE order_id = %s", $order->order_id));
        if ($items) {
            echo "Items:<br>\n";
            foreach ($items as $item) {
                echo "- {$item->product_name} x {$item->quantity} @ £{$item->unit_price}<br>\n";
            }
        } else {
            echo "No items found for this order!<br>\n";
        }
        echo "<br>\n";
    }
} else {
    echo "No orders found in database.<br>\n";
}

// Test order creation
echo "<h2>Testing Order Creation</h2>\n";

$test_order_data = array(
    'order_id' => 'TEST' . time(),
    'stripe_session_id' => 'test_session_' . time(),
    'customer_email' => 'test@example.com',
    'customer_name' => 'Test Customer',
    'delivery_address' => "123 Test Street\nTest City\nTest Postcode",
    'delivery_notes' => 'Test order',
    'order_total' => 25.00,
    'shipping_cost' => 3.50,
    'subtotal' => 21.50,
    'cart_items' => array(
        array(
            'product_id' => 1,
            'variant_id' => '',
            'product_name' => 'Test Product 1',
            'product_sku' => 'TEST001',
            'quantity' => 2,
            'unit_price' => 10.75,
            'total_price' => 21.50
        )
    )
);

echo "Creating test order...<br>\n";
$test_order_id = create_order($test_order_data);

if ($test_order_id) {
    echo "Test order created successfully with ID: $test_order_id<br>\n";
    
    // Check if items were created
    $test_items = get_order_items($test_order_data['order_id']);
    if ($test_items) {
        echo "Test order items created:<br>\n";
        foreach ($test_items as $item) {
            echo "- {$item->product_name} x {$item->quantity} @ £{$item->unit_price}<br>\n";
        }
    } else {
        echo "ERROR: Test order items were NOT created!<br>\n";
    }
} else {
    echo "ERROR: Failed to create test order!<br>\n";
}

echo "<h2>Test Complete</h2>\n";
?>
