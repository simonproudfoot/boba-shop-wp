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
    
    // Ensure tables exist
    create_orders_table();
    create_order_items_table();
    
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
    
    // Handle order deletion
    if (isset($_POST['delete_order']) && wp_verify_nonce($_POST['delete_order_nonce'], 'delete_order')) {
        $order_id = intval($_POST['order_id']);
        
        // Get order details for confirmation
        $order_to_delete = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $order_id));
        
        if ($order_to_delete) {
            // Delete order items first (foreign key constraint)
            $items_table = $wpdb->prefix . 'shop_order_items';
            $items_deleted = $wpdb->delete(
                $items_table,
                array('order_id' => $order_to_delete->order_id),
                array('%s')
            );
            
            // Delete the order
            $order_deleted = $wpdb->delete(
                $table_name,
                array('id' => $order_id),
                array('%d')
            );
            
            if ($order_deleted !== false) {
                echo '<div class="notice notice-success"><p>Order "' . esc_html($order_to_delete->order_id) . '" and all associated items have been deleted successfully.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Failed to delete order. Please try again.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>Order not found.</p></div>';
        }
    }
    
    // Handle bulk delete
    if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'delete' && wp_verify_nonce($_POST['bulk_delete_nonce'], 'bulk_delete_orders')) {
        if (isset($_POST['order_ids']) && is_array($_POST['order_ids'])) {
            $deleted_count = 0;
            $failed_count = 0;
            
            foreach ($_POST['order_ids'] as $order_id) {
                $order_id = intval($order_id);
                
                // Get order details
                $order_to_delete = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $order_id));
                
                if ($order_to_delete) {
                    // Delete order items first
                    $items_table = $wpdb->prefix . 'shop_order_items';
                    $wpdb->delete(
                        $items_table,
                        array('order_id' => $order_to_delete->order_id),
                        array('%s')
                    );
                    
                    // Delete the order
                    $order_deleted = $wpdb->delete(
                        $table_name,
                        array('id' => $order_id),
                        array('%d')
                    );
                    
                    if ($order_deleted !== false) {
                        $deleted_count++;
                    } else {
                        $failed_count++;
                    }
                }
            }
            
            if ($deleted_count > 0) {
                echo '<div class="notice notice-success"><p>' . $deleted_count . ' order(s) deleted successfully.</p></div>';
            }
            if ($failed_count > 0) {
                echo '<div class="notice notice-error"><p>' . $failed_count . ' order(s) failed to delete.</p></div>';
            }
        }
    }
    
    // Handle test email template
    if (isset($_POST['test_email_template']) && wp_verify_nonce($_POST['test_email_template'], 'test_email_template')) {
        // Get the first order for testing
        $test_order = $wpdb->get_row("SELECT * FROM $table_name ORDER BY id DESC LIMIT 1");
        
        if ($test_order) {
            $test_items = get_order_items($test_order->order_id);
            
            // Send test email to admin
            $admin_email = get_option('admin_email');
            $test_sent = send_confirmation_email($test_order, $test_items);
            
            if ($test_sent) {
                echo '<div class="notice notice-success"><p>Test email template sent successfully to ' . $admin_email . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Failed to send test email template.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>No orders found to test email template.</p></div>';
        }
    }
    
    // Handle test email system
    if (isset($_POST['test_email_system']) && wp_verify_nonce($_POST['test_email_system'], 'test_email_system')) {
        if (function_exists('test_email_system')) {
            $results = test_email_system();
            echo '<div class="notice notice-info"><p><strong>Email System Test Results:</strong></p>';
            echo '<ul style="margin-left: 20px;">';
            echo '<li>Main email function: ' . ($results['main_function'] ? 'SUCCESS' : 'FAILED') . '</li>';
            echo '<li>Alternative email function: ' . ($results['alternative_function'] ? 'SUCCESS' : 'FAILED') . '</li>';
            echo '</ul>';
            echo '<p>Check your error logs for detailed information. Test email sent to: ' . get_option('admin_email') . '</p>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-error"><p>test_email_system function not found. Email templates may not be loaded properly.</p></div>';
        }
    }
    
    // Get orders
    $orders = $wpdb->get_results(
        "SELECT * FROM $table_name ORDER BY created_at DESC"
    );
    
    ?>
    <div class="wrap">
        <h1>Shop Orders</h1>
        
        <?php if (!empty($orders)): ?>
            <div class="bulk-actions" style="margin: 20px 0;">
                <form method="post" id="bulk-delete-form" style="display: inline-block;">
                    <?php wp_nonce_field('bulk_delete_orders', 'bulk_delete_nonce'); ?>
                    <select name="bulk_action" style="margin-right: 10px;">
                        <option value="">Bulk Actions</option>
                        <option value="delete">Delete Selected</option>
                    </select>
                    <button type="submit" class="button" id="do-bulk-delete" disabled>Apply</button>
                </form>
                
                <!-- Test Email Template -->
                <div style="display: inline-block; margin-left: 20px;">
                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field('test_email_template', 'test_email_nonce'); ?>
                        <button type="submit" name="test_email_template" class="button button-secondary">
                            Test Email Template
                        </button>
                    </form>
                </div>
                
                <!-- Test Email System -->
                <div style="display: inline-block; margin-left: 20px;">
                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field('test_email_system', 'test_email_system_nonce'); ?>
                        <button type="submit" name="test_email_system" class="button button-secondary">
                            Test Email System
                        </button>
                    </form>
                </div>
            </div>
            <?php
            // Calculate totals
            $total_orders = count($orders);
            $total_items = 0;
            $total_revenue = 0;
            
            foreach ($orders as $order) {
                $items = get_order_items($order->order_id);
                $total_items += count($items);
                $total_revenue += $order->order_total;
            }
            ?>
            <div class="order-summary" style="background: #fff; padding: 15px; margin: 20px 0; border: 1px solid #ddd; border-radius: 4px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div>
                        <strong>Total Orders:</strong> <?php echo $total_orders; ?>
                    </div>
                    <div>
                        <strong>Total Items:</strong> <?php echo $total_items; ?>
                    </div>
                    <div>
                        <strong>Total Revenue:</strong> £<?php echo number_format($total_revenue, 2); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (empty($orders)): ?>
            <p>No orders found.</p>
        <?php else: ?>
            <!-- Desktop Table View -->
            <div class="orders-table-desktop">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all-orders"></th>
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
                                    <input type="checkbox" name="order_ids[]" value="<?php echo esc_attr($order->id); ?>" class="order-checkbox">
                                </td>
                                <td>
                                    <strong><?php echo esc_html($order->order_id); ?></strong>
                                    <br>
                                    <small>Stripe: <?php echo esc_html($order->stripe_session_id); ?></small>
                                    <br>
                                    <small class="order-items-preview">
                                        <?php
                                        $items = get_order_items($order->order_id);
                                        if (!empty($items)) {
                                            $item_count = count($items);
                                            $first_item = $items[0];
                                            echo $item_count . ' item' . ($item_count > 1 ? 's' : '') . ': ' . esc_html($first_item->product_name);
                                            if ($item_count > 1) {
                                                echo ' +' . ($item_count - 1) . ' more';
                                            }
                                        } else {
                                            echo 'No items found';
                                        }
                                        ?>
                                    </small>
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
                                    <button type="button" class="button button-link-delete delete-order" data-order-id="<?php echo esc_attr($order->id); ?>" data-order-number="<?php echo esc_attr($order->order_id); ?>">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Order Details Row (Hidden by default) -->
                            <tr class="order-details-row" id="order-details-<?php echo esc_attr($order->id); ?>" style="display: none;">
                                <td colspan="10">
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
                                                <h5>Items Purchased</h5>
                                                <?php
                                                // Get order items
                                                $order_items = get_order_items($order->order_id);
                                                if (!empty($order_items)): ?>
                                                    <div class="order-items-list">
                                                        <?php foreach ($order_items as $item): ?>
                                                            <div class="order-item">
                                                                <strong><?php echo esc_html($item->product_name); ?></strong>
                                                                <br>
                                                                <small>
                                                                    SKU: <?php echo esc_html($item->product_sku ?: 'N/A'); ?> | 
                                                                    Qty: <?php echo esc_html($item->quantity); ?> | 
                                                                    Price: £<?php echo number_format($item->unit_price, 2); ?> | 
                                                                    Total: £<?php echo number_format($item->total_price, 2); ?>
                                                                </small>
                                                                <?php if (!empty($item->variant_id)): ?>
                                                                    <br><small class="variant-info">Variant: <?php echo esc_html($item->variant_id); ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <p class="no-items">No order items found in database.</p>
                                                    <?php if (WP_DEBUG): ?>
                                                    <div class="debug-info" style="background: #f0f0f0; padding: 10px; margin-top: 10px; border-radius: 4px; font-family: monospace; font-size: 12px;">
                                                        <strong>Debug Info:</strong><br>
                                                        Order ID: <?php echo esc_html($order->order_id); ?><br>
                                                        Database prefix: <?php echo esc_html($wpdb->prefix); ?><br>
                                                        Items table: <?php echo esc_html($wpdb->prefix . 'shop_order_items'); ?><br>
                                                        <?php
                                                        $items_table = $wpdb->prefix . 'shop_order_items';
                                                        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$items_table'") == $items_table;
                                                        echo 'Items table exists: ' . ($table_exists ? 'YES' : 'NO') . '<br>';
                                                        if ($table_exists) {
                                                            $item_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $items_table WHERE order_id = %s", $order->order_id));
                                                            echo 'Items found for this order: ' . $item_count . '<br>';
                                                        }
                                                        ?>
                                                    </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
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
            </div>
            
            <!-- Mobile Card View -->
            <div class="orders-mobile-view">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-card-header">
                            <div class="order-checkbox-wrapper">
                                <input type="checkbox" name="order_ids[]" value="<?php echo esc_attr($order->id); ?>" class="order-checkbox-mobile">
                            </div>
                            <div class="order-main-info">
                                <h3><?php echo esc_html($order->order_id); ?></h3>
                                <p class="order-date"><?php echo esc_html(date('M j, Y g:i A', strtotime($order->created_at))); ?></p>
                            </div>
                            <div class="order-status-badges">
                                <span class="order-status status-<?php echo esc_attr($order->order_status); ?>">
                                    <?php echo esc_html(ucfirst($order->order_status)); ?>
                                </span>
                                <span class="payment-status status-<?php echo esc_attr($order->payment_status); ?>">
                                    <?php echo esc_html(ucfirst($order->payment_status)); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="order-card-body">
                            <div class="order-info-grid">
                                <div class="info-item">
                                    <label>Customer:</label>
                                    <span><?php echo esc_html($order->customer_name); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Email:</label>
                                    <span><?php echo esc_html($order->customer_email); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Total:</label>
                                    <span>£<?php echo number_format($order->order_total, 2); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Shipping:</label>
                                    <span>£<?php echo number_format($order->shipping_cost, 2); ?></span>
                                </div>
                            </div>
                            
                            <div class="order-items-preview-mobile">
                                <?php
                                $items = get_order_items($order->order_id);
                                if (!empty($items)) {
                                    $item_count = count($items);
                                    $first_item = $items[0];
                                    echo '<strong>' . $item_count . ' item' . ($item_count > 1 ? 's' : '') . ':</strong> ' . esc_html($first_item->product_name);
                                    if ($item_count > 1) {
                                        echo ' +' . ($item_count - 1) . ' more';
                                    }
                                } else {
                                    echo '<em>No items found</em>';
                                }
                                ?>
                            </div>
                            
                            <div class="order-actions">
                                <button type="button" class="button view-order-details-mobile" data-order-id="<?php echo esc_attr($order->id); ?>">
                                    View Details
                                </button>
                                <button type="button" class="button button-link-delete delete-order-mobile" data-order-id="<?php echo esc_attr($order->id); ?>" data-order-number="<?php echo esc_attr($order->order_id); ?>">
                                    Delete
                                </button>
                            </div>
                        </div>
                        
                        <!-- Mobile Order Details (Hidden by default) -->
                        <div class="order-details-mobile" id="order-details-mobile-<?php echo esc_attr($order->id); ?>" style="display: none;">
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
                                        <h5>Items Purchased</h5>
                                        <?php
                                        // Get order items
                                        $order_items = get_order_items($order->order_id);
                                        if (!empty($order_items)): ?>
                                            <div class="order-items-list">
                                                <?php foreach ($order_items as $item): ?>
                                                    <div class="order-item">
                                                        <strong><?php echo esc_html($item->product_name); ?></strong>
                                                        <br>
                                                        <small>
                                                            SKU: <?php echo esc_html($item->product_sku ?: 'N/A'); ?> | 
                                                            Qty: <?php echo esc_html($item->quantity); ?> | 
                                                            Price: £<?php echo number_format($item->unit_price, 2); ?> | 
                                                            Total: £<?php echo number_format($item->total_price, 2); ?>
                                                        </small>
                                                        <?php if (!empty($item->variant_id)): ?>
                                                            <br><small class="variant-info">Variant: <?php echo esc_html($item->variant_id); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="no-items">No order items found in database.</p>
                                        <?php endif; ?>
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
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Delete Order Modal -->
    <div id="delete-order-modal" class="delete-modal" style="display: none;">
        <div class="delete-modal-content">
            <h3>Delete Order</h3>
            <p>Are you sure you want to delete order <strong id="delete-order-number"></strong>?</p>
            <p><strong>Warning:</strong> This action cannot be undone. All order items and data will be permanently removed.</p>
            
            <form method="post" id="delete-order-form">
                <?php wp_nonce_field('delete_order', 'delete_order_nonce'); ?>
                <input type="hidden" name="order_id" id="delete-order-id">
                
                <div class="delete-modal-actions">
                    <button type="button" class="button cancel-delete">Cancel</button>
                    <button type="submit" name="delete_order" class="button button-primary button-link-delete">Delete Order</button>
                </div>
            </form>
        </div>
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
        
        .order-items-list {
            margin-top: 10px;
        }
        
        .order-item {
            padding: 10px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 8px;
        }
        
        .order-item:last-child {
            margin-bottom: 0;
        }
        
        .order-item strong {
            color: #333;
            font-size: 14px;
        }
        
        .order-item small {
            color: #666;
            line-height: 1.4;
        }
        
        .variant-info {
            color: #0073aa;
            font-style: italic;
        }
        
        .no-items {
            color: #d63638;
            font-style: italic;
        }
        
        .order-items-preview {
            color: #0073aa;
            font-size: 11px;
            line-height: 1.3;
        }
        
        /* Delete Modal Styles */
        .delete-modal {
            position: fixed;
            z-index: 100000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .delete-modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .delete-modal-content h3 {
            margin-top: 0;
            color: #d63638;
            border-bottom: 2px solid #d63638;
            padding-bottom: 10px;
        }
        
        .delete-modal-content p {
            margin: 15px 0;
            line-height: 1.5;
        }
        
        .delete-modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        
        .button-link-delete {
            color: #d63638 !important;
            border-color: #d63638 !important;
        }
        
        .button-link-delete:hover {
            background-color: #d63638 !important;
            color: #fff !important;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .orders-table-desktop {
                display: none;
            }
            
            .orders-mobile-view {
                display: block;
            }
            
            .bulk-actions {
                margin: 15px 0;
            }
            
            .bulk-actions select,
            .bulk-actions button {
                width: 100%;
                margin: 5px 0;
            }
            
            .order-summary {
                padding: 15px;
                margin: 15px 0;
            }
            
            .order-summary > div {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
        
        @media (min-width: 769px) {
            .orders-table-desktop {
                display: block;
            }
            
            .orders-mobile-view {
                display: none;
            }
        }
        
        /* Mobile Card Styles */
        .order-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .order-card-header {
            background: #f9f9f9;
            padding: 15px;
            border-bottom: 1px solid #ddd;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .order-checkbox-wrapper {
            flex-shrink: 0;
        }
        
        .order-main-info {
            flex: 1;
            min-width: 200px;
        }
        
        .order-main-info h3 {
            margin: 0 0 5px 0;
            font-size: 18px;
            color: #333;
        }
        
        .order-date {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        
        .order-status-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .order-card-body {
            padding: 15px;
        }
        
        .order-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-item label {
            font-weight: 600;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        
        .info-item span {
            color: #333;
            font-size: 14px;
        }
        
        .order-items-preview-mobile {
            background: #f9f9f9;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .order-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .order-actions .button {
            flex: 1;
            min-width: 120px;
            text-align: center;
        }
        
        .order-details-mobile {
            border-top: 1px solid #ddd;
            background: #f9f9f9;
        }
        
        .order-details-mobile .order-details {
            margin: 0;
            border-radius: 0;
        }
        
        .order-details-mobile .order-details-grid {
            grid-template-columns: 1fr;
            gap: 15px;
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
            
            // Handle delete order buttons
            document.querySelectorAll('.delete-order').forEach(function(button) {
                button.addEventListener('click', function() {
                    var orderId = this.getAttribute('data-order-id');
                    var orderNumber = this.getAttribute('data-order-number');
                    
                    // Set modal content
                    document.getElementById('delete-order-id').value = orderId;
                    document.getElementById('delete-order-number').textContent = orderNumber;
                    
                    // Show modal
                    document.getElementById('delete-order-modal').style.display = 'block';
                });
            });
            
            // Handle cancel delete
            document.querySelector('.cancel-delete').addEventListener('click', function() {
                document.getElementById('delete-order-modal').style.display = 'none';
            });
            
            // Close modal when clicking outside
            document.getElementById('delete-order-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
            
            // Handle escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    document.getElementById('delete-order-modal').style.display = 'none';
                }
            });
            
            // Handle select all checkbox
            document.getElementById('select-all-orders').addEventListener('change', function() {
                var checkboxes = document.querySelectorAll('.order-checkbox');
                checkboxes.forEach(function(checkbox) {
                    checkbox.checked = this.checked;
                });
                updateBulkActionButton();
            });
            
            // Handle individual checkboxes
            document.querySelectorAll('.order-checkbox').forEach(function(checkbox) {
                checkbox.addEventListener('change', function() {
                    updateBulkActionButton();
                    updateSelectAllCheckbox();
                });
            });
            
            // Update bulk action button state
            function updateBulkActionButton() {
                var checkedBoxes = document.querySelectorAll('.order-checkbox:checked');
                var bulkButton = document.getElementById('do-bulk-delete');
                bulkButton.disabled = checkedBoxes.length === 0;
            }
            
            // Update select all checkbox state
            function updateSelectAllCheckbox() {
                var checkboxes = document.querySelectorAll('.order-checkbox');
                var checkedBoxes = document.querySelectorAll('.order-checkbox:checked');
                var selectAllCheckbox = document.getElementById('select-all-orders');
                
                if (checkedBoxes.length === 0) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                } else if (checkedBoxes.length === checkboxes.length) {
                    selectAllCheckbox.checked = true;
                    selectAllCheckbox.indeterminate = false;
                } else {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = true;
                }
            }
            
            // Handle bulk delete form submission
            document.getElementById('bulk-delete-form').addEventListener('submit', function(e) {
                var checkedBoxes = document.querySelectorAll('.order-checkbox:checked, .order-checkbox-mobile:checked');
                if (checkedBoxes.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one order to delete.');
                    return;
                }
                
                var action = document.querySelector('select[name="bulk_action"]').value;
                if (action === 'delete') {
                    if (!confirm('Are you sure you want to delete ' + checkedBoxes.length + ' selected order(s)? This action cannot be undone.')) {
                        e.preventDefault();
                        return;
                    }
                }
            });
            
            // Handle mobile order details
            document.querySelectorAll('.view-order-details-mobile').forEach(function(button) {
                button.addEventListener('click', function() {
                    var orderId = this.getAttribute('data-order-id');
                    var detailsDiv = document.getElementById('order-details-mobile-' + orderId);
                    
                    if (detailsDiv.style.display === 'none') {
                        detailsDiv.style.display = 'block';
                        this.textContent = 'Hide Details';
                    } else {
                        detailsDiv.style.display = 'none';
                        this.textContent = 'View Details';
                    }
                });
            });
            
            // Handle mobile delete orders
            document.querySelectorAll('.delete-order-mobile').forEach(function(button) {
                button.addEventListener('click', function() {
                    var orderId = this.getAttribute('data-order-id');
                    var orderNumber = this.getAttribute('data-order-number');
                    
                    // Set modal content
                    document.getElementById('delete-order-id').value = orderId;
                    document.getElementById('delete-order-number').textContent = orderNumber;
                    
                    // Show modal
                    document.getElementById('delete-order-modal').style.display = 'block';
                });
            });
            
            // Sync mobile and desktop checkboxes
            function syncCheckboxes() {
                var desktopCheckboxes = document.querySelectorAll('.order-checkbox');
                var mobileCheckboxes = document.querySelectorAll('.order-checkbox-mobile');
                
                desktopCheckboxes.forEach(function(checkbox, index) {
                    if (index < mobileCheckboxes.length) {
                        mobileCheckboxes[index].checked = checkbox.checked;
                    }
                });
            }
            
            // Update mobile checkboxes when desktop ones change
            document.querySelectorAll('.order-checkbox').forEach(function(checkbox) {
                checkbox.addEventListener('change', syncCheckboxes);
            });
            
            // Update desktop checkboxes when mobile ones change
            document.querySelectorAll('.order-checkbox-mobile').forEach(function(checkbox) {
                checkbox.addEventListener('change', function() {
                    var index = Array.from(document.querySelectorAll('.order-checkbox-mobile')).indexOf(this);
                    var desktopCheckbox = document.querySelectorAll('.order-checkbox')[index];
                    if (desktopCheckbox) {
                        desktopCheckbox.checked = this.checked;
                    }
                    updateBulkActionButton();
                    updateSelectAllCheckbox();
                });
            });
        });
    </script>
    <?php
}
