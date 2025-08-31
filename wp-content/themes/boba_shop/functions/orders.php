<?php
/**
 * Order Management Functions
 * Handles order creation, shipping calculations, and database operations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create orders table on theme activation
 */
function create_orders_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'shop_orders';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        order_id varchar(50) NOT NULL,
        stripe_session_id varchar(255) NOT NULL,
        stripe_customer_id varchar(255),
        customer_email varchar(255) NOT NULL,
        customer_name varchar(255) NOT NULL,
        delivery_address text NOT NULL,
        delivery_notes text,
        order_total decimal(10,2) NOT NULL,
        shipping_cost decimal(10,2) NOT NULL,
        subtotal decimal(10,2) NOT NULL,
        order_status varchar(50) DEFAULT 'pending',
        payment_status varchar(50) DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY order_id (order_id),
        KEY stripe_session_id (stripe_session_id),
        KEY order_status (order_status),
        KEY payment_status (payment_status)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create order items table on theme activation
 */
function create_order_items_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'shop_order_items';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        order_id varchar(50) NOT NULL,
        product_id int(11) NOT NULL,
        variant_id varchar(50),
        product_name varchar(255) NOT NULL,
        product_sku varchar(100),
        quantity int(11) NOT NULL,
        unit_price decimal(10,2) NOT NULL,
        total_price decimal(10,2) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY order_id (order_id),
        KEY product_id (product_id),
        KEY variant_id (variant_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Calculate shipping cost based on order total
 */
function calculate_shipping_cost($subtotal) {
    $free_shipping_threshold = 40.00; // £40
    $shipping_cost = 3.50; // £3.50
    
    if ($subtotal >= $free_shipping_threshold) {
        return 0.00; // Free shipping
    }
    
    return $shipping_cost;
}

/**
 * Create a new order in the database
 */
function create_order($order_data) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'shop_orders';
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'order_id' => $order_data['order_id'],
            'stripe_session_id' => $order_data['stripe_session_id'],
            'customer_email' => $order_data['customer_email'],
            'customer_name' => $order_data['customer_name'],
            'delivery_address' => $order_data['delivery_address'],
            'delivery_notes' => $order_data['delivery_notes'],
            'order_total' => $order_data['order_total'],
            'shipping_cost' => $order_data['shipping_cost'],
            'subtotal' => $order_data['subtotal'],
            'order_status' => 'pending',
            'payment_status' => 'pending'
        ),
        array(
            '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s'
        )
    );
    
    if ($result === false) {
        error_log('Failed to create order: ' . $wpdb->last_error);
        return false;
    }
    
    $order_id = $wpdb->insert_id;
    
    // Create order items if cart data is provided
    if (!empty($order_data['cart_items']) && is_array($order_data['cart_items'])) {
        $items_created = create_order_items($order_data['order_id'], $order_data['cart_items']);
        if (!$items_created) {
            error_log('Failed to create order items for order: ' . $order_data['order_id']);
        }
    }
    
    return $order_id;
}

/**
 * Create order items in the database
 */
function create_order_items($order_id, $cart_items) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'shop_order_items';
    
    foreach ($cart_items as $item) {
        $result = $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order_id,
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'] ?? '',
                'product_name' => $item['product_name'],
                'product_sku' => $item['product_sku'] ?? '',
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['total_price']
            ),
            array(
                '%s', '%d', '%s', '%s', '%s', '%d', '%f', '%f'
            )
        );
        
        if ($result === false) {
            error_log('Failed to create order item: ' . $wpdb->last_error);
            return false;
        }
    }
    
    return true;
}

/**
 * Update order with Stripe customer ID after payment
 */
function update_order_customer_id($stripe_session_id, $stripe_customer_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'shop_orders';
    
    $result = $wpdb->update(
        $table_name,
        array('stripe_customer_id' => $stripe_customer_id),
        array('stripe_session_id' => $stripe_session_id),
        array('%s'),
        array('%s')
    );
    
    return $result !== false;
}

/**
 * Update order status after payment confirmation
 */
function update_order_status($stripe_session_id, $order_status, $payment_status) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'shop_orders';
    
    $result = $wpdb->update(
        $table_name,
        array(
            'order_status' => $order_status,
            'payment_status' => $payment_status
        ),
        array('stripe_session_id' => $stripe_session_id),
        array('%s', '%s'),
        array('%s')
    );
    
    return $result !== false;
}

/**
 * Get order by Stripe session ID
 */
function get_order_by_session_id($stripe_session_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'shop_orders';
    
    $order = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE stripe_session_id = %s",
            $stripe_session_id
        )
    );
    
    return $order;
}

/**
 * Get order items by order ID
 */
function get_order_items($order_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'shop_order_items';
    
    $items = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE order_id = %s",
            $order_id
        )
    );
    
    return $items;
}

/**
 * Generate unique order ID
 */
function generate_order_id() {
    $prefix = 'ORD';
    $timestamp = time();
    $random = wp_generate_password(6, false);
    
    return $prefix . $timestamp . $random;
}

/**
 * Format delivery address for display
 */
function format_delivery_address($address_data) {
    $formatted = '';
    
    if (!empty($address_data['address_line1'])) {
        $formatted .= $address_data['address_line1'] . "\n";
    }
    if (!empty($address_data['address_line2'])) {
        $formatted .= $address_data['address_line2'] . "\n";
    }
    if (!empty($address_data['city'])) {
        $formatted .= $address_data['city'] . "\n";
    }
    if (!empty($address_data['state'])) {
        $formatted .= $address_data['state'] . "\n";
    }
    if (!empty($address_data['postal_code'])) {
        $formatted .= $address_data['postal_code'] . "\n";
    }
    if (!empty($address_data['country'])) {
        $formatted .= $address_data['country'];
    }
    
    return trim($formatted);
}

// Hook to create table on theme activation
add_action('after_switch_theme', 'create_orders_table');
add_action('after_switch_theme', 'create_order_items_table');

// Also create table if it doesn't exist (for existing themes)
add_action('init', function() {
    if (!get_option('shop_orders_table_created')) {
        create_orders_table();
        update_option('shop_orders_table_created', true);
    }
    if (!get_option('shop_order_items_table_created')) {
        create_order_items_table();
        update_option('shop_order_items_table_created', true);
    }
});
