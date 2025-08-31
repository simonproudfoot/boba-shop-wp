<?php
/**
 * Admin Orders Management
 * Provides admin interface for viewing and managing orders
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add admin menu for orders
 */
function add_orders_admin_menu() {
    add_menu_page(
        'Orders',
        'Orders',
        'manage_options',
        'shop-orders',
        'display_orders_admin_page',
        'dashicons-cart',
        30
    );
}
add_action('admin_menu', 'add_orders_admin_menu');

/**
 * Display orders admin page
 */
function display_orders_admin_page() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'shop_orders';
    
    // Handle order status updates
    if (isset($_POST['update_order_status']) && wp_verify_nonce($_POST['order_nonce'], 'update_order_status')) {
        $order_id = intval($_POST['order_id']);
        $new_status = sanitize_text_field($_POST['new_status']);
        
        $result = $wpdb->update(
            $table_name,
            array('order_status' => $new_status),
            array('id' => $order_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            echo '<div class="notice notice-success"><p>Order status updated successfully.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Failed to update order status.</p></div>';
        }
    }
    
    // Get orders
    $orders = $wpdb->get_results(
        "SELECT * FROM $table_name ORDER BY created_at DESC"
    );
    
    ?>
    <div class="wrap">
        <h1>Shop Orders</h1>
        
        <?php if (empty($orders)): ?>
            <p>No orders found.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Total</th>
                        <th>Shipping</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($order->order_id); ?></strong>
                                <br>
                                <small>Stripe: <?php echo esc_html($order->stripe_session_id); ?></small>
                            </td>
                            <td><?php echo esc_html($order->customer_name); ?></td>
                            <td><?php echo esc_html($order->customer_email); ?></td>
                            <td>£<?php echo number_format($order->order_total, 2); ?></td>
                            <td>£<?php echo number_format($order->shipping_cost, 2); ?></td>
                            <td>
                                <span class="order-status status-<?php echo esc_attr($order->order_status); ?>">
                                    <?php echo esc_html(ucfirst($order->order_status)); ?>
                                </span>
                            </td>
                            <td>
                                <span class="payment-status status-<?php echo esc_attr($order->payment_status); ?>">
                                    <?php echo esc_html(ucfirst($order->payment_status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(date('M j, Y g:i A', strtotime($order->created_at))); ?></td>
                            <td>
                                <button type="button" class="button view-order-details" data-order-id="<?php echo esc_attr($order->id); ?>">
                                    View Details
                                </button>
                            </td>
                        </tr>
                        
                        <!-- Order Details Row (Hidden by default) -->
                        <tr class="order-details-row" id="order-details-<?php echo esc_attr($order->id); ?>" style="display: none;">
                            <td colspan="9">
                                <div class="order-details">
                                    <h4>Order Details</h4>
                                    <div class="order-details-grid">
                                        <div class="detail-section">
                                            <h5>Delivery Address</h5>
                                            <p><?php echo nl2br(esc_html($order->delivery_address)); ?></p>
                                        </div>
                                        
                                        <?php if (!empty($order->delivery_notes)): ?>
                                        <div class="detail-section">
                                            <h5>Delivery Notes</h5>
                                            <p><?php echo esc_html($order->delivery_notes); ?></p>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="detail-section">
                                            <h5>Order Summary</h5>
                                            <p><strong>Subtotal:</strong> £<?php echo number_format($order->subtotal, 2); ?></p>
                                            <p><strong>Shipping:</strong> £<?php echo number_format($order->shipping_cost, 2); ?></p>
                                            <p><strong>Total:</strong> £<?php echo number_format($order->order_total, 2); ?></p>
                                        </div>
                                        
                                        <div class="detail-section">
                                            <h5>Update Status</h5>
                                            <form method="post" style="display: inline;">
                                                <?php wp_nonce_field('update_order_status', 'order_nonce'); ?>
                                                <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>">
                                                <select name="new_status">
                                                    <option value="pending" <?php selected($order->order_status, 'pending'); ?>>Pending</option>
                                                    <option value="confirmed" <?php selected($order->order_status, 'confirmed'); ?>>Confirmed</option>
                                                    <option value="shipped" <?php selected($order->order_status, 'shipped'); ?>>Shipped</option>
                                                    <option value="delivered" <?php selected($order->order_status, 'delivered'); ?>>Delivered</option>
                                                    <option value="cancelled" <?php selected($order->order_status, 'cancelled'); ?>>Cancelled</option>
                                                </select>
                                                <button type="submit" name="update_order_status" class="button button-primary">Update</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <style>
        .order-status, .payment-status {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-pending { background-color: #ffeaa7; color: #d63031; }
        .status-confirmed { background-color: #74b9ff; color: #2d3436; }
        .status-shipped { background-color: #fd79a8; color: #2d3436; }
        .status-delivered { background-color: #55a3ff; color: #2d3436; }
        .status-cancelled { background-color: #ff7675; color: #2d3436; }
        
        .status-paid { background-color: #00b894; color: #2d3436; }
        .status-failed { background-color: #e17055; color: #2d3436; }
        
        .order-details {
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
            margin: 10px 0;
        }
        
        .order-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        
        .detail-section h5 {
            margin-top: 0;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        .detail-section p {
            margin: 5px 0;
        }
    </style>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle view order details buttons
            document.querySelectorAll('.view-order-details').forEach(function(button) {
                button.addEventListener('click', function() {
                    var orderId = this.getAttribute('data-order-id');
                    var detailsRow = document.getElementById('order-details-' + orderId);
                    
                    if (detailsRow.style.display === 'none') {
                        detailsRow.style.display = 'table-row';
                        this.textContent = 'Hide Details';
                    } else {
                        detailsRow.style.display = 'none';
                        this.textContent = 'View Details';
                    }
                });
            });
        });
    </script>
    <?php
}
